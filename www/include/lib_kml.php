<?php

	#################################################################

	function kml_parse_fh($fh, $more=array()){

		$fsize = filesize($more['file']['path']);

		$data = fread($fh, $fsize);
		fclose($fh);

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string($data);

		if (! $xml){

			$errors = array();

			foreach (libxml_get_errors() as $error) {
				$errors[] = $error->message;
			}

			return array(
				'ok' => 0,
				'error' => 'failed to parse XML: ' . implode(";", $errors),
			);
		}

		# Please KML, die in a fire...

		$nl = null;

		if ($xml->NetworkLink){
			$nl = $xml->NetworkLink;
		}

		else if ($xml->Document->NetworkLink){
			$nl = $xml->Document->NetworkLink;
		}

		else { }

		#

		if ($nl){

			if (! $GLOBALS['cfg']['import_kml_resolve_network_links']){
				return array(
					'ok' => 0,
					'error' => 'Network linked KML files are not currently supported.'
				);
			}

			$url = $nl->Url;
			$link = (string)$url->href;

			$rsp = http_get($link);

			if (! $rsp['ok']){
				return $rsp;
			}

			$xml = new SimpleXMLElement($rsp['body']);
		}

		$ctx = ($xml->Document) ? $xml->Document : $xml->Folder;

		if (! $ctx){

			return array(
				'ok' => 0,
				'error' => 'Failed to locate any placemarks',
			);
		}

		if ($ctx->Folder){
			$ctx = $ctx->Folder;
		}

		$label = (string)$ctx->name;
		$label = import_scrub($label);

		$data = array();
		$errors = array();

		$record = 1;

		# I hate KML...

		$placemarks = array();

		if ($ctx->Folder){

			foreach ($ctx->Folder as $f){
				foreach ($f->Placemark as $p){
					$placemarks[] = $p;
				}
			}
		}

		else {

			$placemarks = $ctx->Placemark;
		}

		#

		foreach ($placemarks as $p){

			$record ++;

			if (($more['max_records']) && ($record > $more['max_records'])){
				break;
			}

			$tmp = array();

			# do everything but the geo bits first in case we run in to a big
			# bag of points like we might find in a KML file from Google's
			# mytracks (20110111/straup)

			$title = (string)$p->name;
			$desc = (string)$p->description;

			# foursquare-isms

			if ((preg_match("/^foursquare /", $label)) && ($a = $p->description->a)){
				$attrs = $a->attributes();
				$href = (string)$attrs['href'];

				if (preg_match("/venue\/(\d+)\/?$/", $href, $m)){
					$tmp['foursquare:venue'] = $m[1];
					$desc = (string)$a;
				}
			}

			# random other stuff

			if ($pub = $p->published){
				$pub = (string)$pub;
				$pub = import_scrub($pub);
				$tmp['created'] = $pub;
			}

			if ($vis = $p->visibility){

				if ($vis = (string)$vis){
					$tmp['perms'] = 'private';
				}
			}

			$title = import_scrub($title);
			$desc = import_scrub($desc);

			$tmp['title'] = $title;
			$tmp['description'] = $desc;

			# sigh...

			if ($coords = (string)$p->Point->coordinates){

				$coords = trim($coords);

				list($lon, $lat, $altitude) = explode(",", $coords, 3);
				list($lat, $lon) = import_ensure_valid_latlon($lat, $lon);

				if (! $lat){

					$errors[] = array(
						'record' => $record,
						'error' => 'invalid latitude',
						'column' => 'latitude',
					);

					continue;
				}

				if (! $lon){

					$errors[] = array(
						'record' => $record,
						'error' => 'invalid longitude',
						'column' => 'longitude',
					);

					continue;
				}

				$tmp['altitude'] = import_scrub($altitude);
				$tmp['latitude'] = $lat;
				$tmp['longitude'] = $lon;
			}

			else if (($coords = $p->MultiGeometry->LineString->coordinates) || ($coords = $p->LineString->coordinates)){

				$simplify = (($GLOBALS['cfg']['import_do_simplification']['kml']) && ($more['simplify'])) ? 1 : 0;

				# We're going to keep our own counter below
				$record -= 2;

				$coords = (string)$coords;
				$coords = trim($coords);

				$coords = preg_split("/[\s]+/", $coords);

				#

				foreach ($coords as $coord){

					$record ++;

					if (($more['max_records']) && ($record > $more['max_records']) && (! $simplify)){
						break;
					}

					list($lon, $lat, $altitude) = explode(",", $coord, 3);

					list($lat, $lon) = import_ensure_valid_latlon($lat, $lon);

					if (! $lat){

						$errors[] = array(
							'record' => $record,
							'error' => "invalid latitude",
							'column' => 'latitude',
						);

						continue;
					}

					if (! $lon){

						$errors[] = array(
							'record' => $record,
							'error' => "invalid longitude",
							'column' => 'longitude',
						);

						continue;
					}

					$data[] = array(
						'latitude' => $lat,
						'longitude' => $lon,
						'altitude' => import_scrub($altitude),
					);
				}

				#

				if ($simplify){

					$data = _kml_simplify($data);

					if (($more['max_records']) && (count($data) > $more['max_records'])){
						$data = array_slice($data, 0, $more['max_records']);
					}
				}

				continue;
			}

			else {

				# to be revisited maybe; this really just makes things
				# more complicated than not (20110415/straup)

				continue;

				$errors[] = array(
					'record' => $record,
					'error' => 'Unable to find any location information (that Dotspotting knows about).',
				);

			}

			$data[] = $tmp;
		}

		return array(
			'ok' => 1,
			'label' => $label,
			'data' => &$data,
			'errors' => &$errors,
			'simplified' => $simplify,
		);
	}

	#################################################################

	function kml_export_dots(&$dots, &$more){

		$ns_map = array(
			'' => 'http://earth.google.com/kml/2.0',
			'dotspotting' => 'x-urn:dotspotting#internal',
			'sheet' => 'x-urn:dotspotting#sheet',
		);

		$skip = array(
			'latitude',
			'longitude',
			'altitude',
			'created',
			'title',
			'description',
			'perms',
		);

		$doc = new DomDocument('1.0', 'UTF-8');

		$kml = $doc->createElement('kml');
		$kml = $doc->appendChild($kml);

		foreach ($ns_map as $prefix => $uri){

			$xmlns = ($prefix) ? "xmlns:{$prefix}" : "xmlns";
			$attr = $doc->createAttribute($xmlns);

			$uri = $doc->createTextNode($uri);
			$attr->appendChild($uri);

			$kml->appendChild($attr);
		}

		$document = $doc->createElement('Document');
		$document = $kml->appendChild($document);

		foreach ($dots as $dot){

			$placemark = $doc->createElement('Placemark');

			$properties = array();

			foreach ($dot as $key => $value){

				if (in_array($key, $skip)){
					continue;
				}

				$properties[] = implode("\t", array(
					htmlspecialchars($key),
					htmlspecialchars($value)
				));

				# maybe do OSM-style k= v= pairs? (20110114/straup)

				if (0){
				if (! preg_match("/^dotspotting:/", $key)){
					$key = "sheet:{$key}";
				}

				$el = $doc->createElement($key);
				$text = $doc->createTextNode($value);

				$el->appendChild($text);
				$placemark->appendChild($el);
				}
			}

			# title

			if (isset($dot['title'])){			
				$_name = $doc->createTextNode($dot['title']);

				$name = $doc->createElement("name");
				$name->appendChild($_name);
				$placemark->appendChild($name);
			}

			# description (see above inre: osm stag tags)

			$_description = $doc->createTextNode(implode("\n", $properties));
			$description = $doc->createElement("description");
			$description->appendChild($_description);
			$placemark->appendChild($description);

			# pubdate

			$_published = $doc->createTextNode($dot['created']);

			$published = $doc->createElement("published");
			$published->appendChild($_published);
			$placemark->appendChild($published);

			# perms

			$perms = $doc->createTextNode(($dot['perms'] == 'private') ? 0 : 1);

			$visibility = $doc->createElement("visibility");
			$visibility->appendChild($perms);

			# geo

			$lat = $dot['latitude'];
			$lon = $dot['longitude'];

			$_coords = array($lon, $lat);

			if (isset($dot['altitude'])){
				$_coords[] = $dot['altitude'];
			}

			$point = $doc->createElement('Point');
			$coords = $doc->createElement('coordinates');
			$lonlat = $doc->createTextNode(implode(",", $_coords));

			$coords->appendChild($lonlat);
			$point->appendChild($coords);
			$placemark->appendChild($point);

			$document->appendChild($placemark);
		}

		$doc->save($more['path']);
		return $more['path'];
	}

	#################################################################

	function _kml_simplify(&$coords){

		loadlib("geo_douglaspeucker");

		$simplified = array();

		foreach (geo_douglaspeucker_simplify($coords) as $gp){
			$simplified[] = $gp->extra;
		}

		return $simplified;
	}

	#################################################################

	# the end
