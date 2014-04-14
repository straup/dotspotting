<?php

	#################################################################

	loadlib("geo_utils");
	loadlib("formats");

	#################################################################

	function import_import_file(&$user, &$file, $more=array()){

		if (! import_is_valid_mimetype($file, $more)){

			return array(
				'error' => 'invalid_mimetype',
				'ok' => 0,
			);
		}

		# Parse the file

		$process_rsp = import_process_file($file);

		if (! $process_rsp['ok']){

			return $process_rsp;
		}

		#
		# store the data
		#

		$fingerprint = md5_file($file['path']);

		$label = ($more['label']) ? $more['label'] : $process_rsp['label'];

		$import_more = array(
			'return_dots' => $more['return_dots'],
			'dots_index_on' => $more['dots_index_on'],
			'label' => $label,
			'mark_all_private' => $more['mark_all_private'],
			'mime_type' => $file['type'],
			'fingerprint' => $fingerprint,
			'simplified' => (($process_rsp['simplified']) ? 1 : 0),
		);

		$import_rsp = import_process_data($user, $process_rsp['data'], $import_more);

		if (! $import_rsp['ok']){
			return $import_rsp;
		}

		#
		# Hello new thing
		#

		$cache_key = "sheets_lookup_fingerprint_{$fingerprint}";
		cache_unset($cache_key);

		#
		# store the actual file?
		#

		if ($GLOBALS['cfg']['enable_feature_import_archive']){

			loadlib("archive");
			$archive_rsp = archive_store_file($file, $import_rsp['sheet']);

			# throw an error if archiving fails?
		}

		#
		# happy happy
		#

		return $import_rsp;
	}

	#################################################################

	function import_fetch_uri($uri, $more=array()){

		# QUESTION: do a HEAD here to check the content-type and file-size ?

		$max_filesize = ini_get("upload_max_filesize");

		# http://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes

		if (preg_match("/^(\d+)(K|M|G)$/", $max_filesize, $m)){

			$unit = $m[1];
			$measure = $m[2];

			if ($measure == 'G'){
				$max_bytes = $unit * 1024 * 1024 * 1024; 
			}

			else if ($measure == 'M'){
				$max_bytes = $unit * 1024 * 1024;
			}

			else {
				$max_bytes = $unit * 1024;
			}
		}

		else {
			$max_bytes = $max_filesize;
		}

		# This is mostly just to try...
		#
		# "If no Accept header field is present, then it is assumed that the client accepts all media types.
		# If an Accept header field is present, and if the server cannot send a response which is acceptable
		# according to the combined Accept field value, then the server SHOULD send a 406 (not acceptable)
		# response." (http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html)

		$map = formats_valid_import_map();
		$accept = array_keys($map);

		if (! isset($map['text/plain'])){
			$accept[] = 'text/plain';
		}

		$headers = array(
			'Range' => "bytes=0-{$max_bytes}",
			'Accept' => implode(", ", $accept),
		);

		#
		# Try to do some basic anity checking before we suck in the entire file
		#

		if ($GLOBALS['cfg']['import_by_url_do_head']){

			$head_rsp = http_head($uri, $headers);

			if (! $head_rsp['ok']){
				return $head_rsp;
			}

			if ($head_rsp['info']['download_content_length'] > $max_bytes){

				return array(
					'ok' => 0,
					'error' => 'Remote file is too large',
				);
			}
		}

		#
		# Go!
		#

		$http_rsp = http_get($uri, $headers);

		if (! $http_rsp['ok']){
			return $http_rsp;
		}

		#
		# Am I partial content?
		#

		if ($http_rsp['headers']['content-length'] > $max_bytes){
			# throw an error ?
		}

		#
		# Write the file to disk
		#

		$fname = tempnam("/tmp", getmypid());
		$fh = fopen($fname, "w");

		if (! $fh){

			return array(
				'ok' => 0,
				'error' => 'failed to open tmp filehandle',
			);
		}

		if (! trim($http_rsp['body'])){

			return array(
				'ok' => 0,
				'error' => 'remote service failed to return any data!',
			);
		}

		fwrite($fh, $http_rsp['body']);
		fclose($fh);

		if (! filesize($fname)){

			return array(
				'ok' => 0,
				'error' => 'failed to write any data!',
			);
		}

		#
		# Ima Viking!
		#

		$type = $http_rsp['headers']['content-type'];

		if ($more['assume_mime_type']){
			$type = $more['assume_mime_type'];
		}

		$type_map = formats_valid_import_map();

		if (! isset($type_map[$type])){

			if (preg_match("/\.([a-z0-9]+)$/", basename($uri), $m)){

				$ext = $m[1];
				$ext_map = formats_valid_import_map('key by extension');

				if (isset($ext_map[$ext])){
					$type = $ext_map[$ext];
				}
			}

			else { }
		}

		return array(
			'ok' => 1,
			'type' => $type,
			'path' => $fname,
		);
	}

	#################################################################

	function import_import_uri(&$user, $uri, $more=array()){        
		$upload = import_fetch_uri($uri, $more);

		if (! $upload['ok']){
			return $upload;
		}

		return import_import_file($user, $upload, $more);
	}

	#################################################################

	function import_is_valid_mimetype(&$file, $more){

		#
		# TODO: read bits of the file?
		#

		$type = ($more['assume_mime_type']) ? $more['assume_mime_type'] : $file['type'];

		if (! $type){
			return 0;
		}

		$map = formats_valid_import_map();

		if ($GLOBALS['cfg']['enable_feature_ogre']){
			$ogre_map = formats_valid_ogre_import_map();
			$map = array_merge($map, $ogre_map);
		}

		if (isset($map[$type])){

			$file['type'] = $type;
			$file['extension'] = $map[$type];
		}

		else {
			# check by extension...

			$map = array_flip($map);

			$parts = pathinfo($file['name']);
			$ext = $parts['extension'];

			if (! isset($map[$ext])){
				return 0;
			}

			# Note the pass-by-ref above
			$file['type'] = $map[$ext];
			$file['extension'] = $ext;
		}

		return 1;
	}

	#################################################################

	# It is assumed that you've checked $file['type'] by now

	function import_process_file(&$file){

		# Is this something that we are going to try parsing with
		# ogre (assuming it's been enabled). If it has then it will
		# return GeoJSON and we'll just carry on pretending that's
		# what the file is.

		$use_ogre = 0;

		if ($GLOBALS['cfg']['enable_feature_ogre']){

			$ogre_map = formats_valid_ogre_import_map();

			if (isset($ogre_map[$file['type']])){
				$use_ogre = 1;
			}
		}

		if ($use_ogre){

			$new_path = "{$file['path']}.{$file['extension']}";
			rename($file['path'], $new_path);

			$file['path'] = $new_path;

			loadlib("geo_ogre");
			$rsp = geo_ogre_convert_file($file['path']);

			if (! $rsp['ok']){
				$rsp['details'] = $rsp['error'];
				$rsp['error'] = 'ogre_fail';
				return $rsp;
			}

			$fh = fopen($file['path'], 'w');
			fwrite($fh, json_encode($rsp['data']));
			fclose($fh);

			$map = formats_valid_import_map("key by extension");

			$old_path = $file['path'];
			$new_path = str_replace(".{$file['extension']}", ".json", $file['path']);

			rename($old_path, $new_path);

			$file = array(
				'path' => $new_path,
				'name' => basename($new_path),
				'size' => filesize($new_path),
				'extension' => 'json',
				'type' => $map['json'],
			);
		}

		#
		# Basic setup stuff
		#

		$rsp = array(
			'ok' => 0,
		);

		$more = array();

		if ($max = $GLOBALS['cfg']['import_max_records']){
			$more['max_records'] = $max;
		}

		#
		# CAN HAZ FILE?
		#

		$fh = fopen($file['path'], 'r');

		if (! $fh){

			return array(
				'ok' => 0,
				'error' => 'failed to open file'
			);
		}

		#
		# Store the $file hash we're passing around. It may be the case
		# that some import related libraries do not have functions for
		# working with filehandles (DOMDocument for example...wtf?)
		#

		$more['file'] = $file;

		#
		# Okay, now figure what we need to load and call. We
		# do this by asking the import map for an extension
		# corresponding to the file's mime-type (note: at some
		# point we may need to make this a bit more fine-grained
		# but today we don't) and then load lib_EXTENSION and
		# call that library's 'parse_fh' function.
		#

		$map = formats_valid_import_map();

		$type = $map[$file['type']];
		$func = "{$type}_parse_fh";

		#
		# HEY LOOK! THIS PART IS IMPORTANT!! It is left to the
		# format specific libraries to sanitize both field names
		# and values (using lib_sanitize). This is *not* a
		# question of validating the data (checking lat/lon
		# ranges etc.) but just making sure that the user isn't
		# passing in pure crap. Take a look at the parse_fh function
		# in lib_csv for an example of how/what to do.
		#

		loadlib($type);

		$rsp = call_user_func_array($func, array($fh, $more));

		# sudo put me in a function? (20110610/straup)
		# made it a function (20110707 | seanc)

		if ($rsp['ok']){
            $rsp = import_preprocess_address_fields($rsp);
            
            /*
			loadlib("dots_address");

			$needs_geocoding = 0;

			# note the pass-by-ref on $row

			foreach ($rsp['data'] as &$row){

				if (($row['latitude']) && ($row['longitude'])){
					$row['_has_latlon'] = 1;
					continue;
				}

				$row['_has_latlon'] = 0;
				$row['_address'] = dots_address_parse_for_geocoding($row);

				$needs_geocoding += 1;
			}
            
			$rsp['needs_geocoding'] = $needs_geocoding;
			*/
		}

		# TO DO: check $GLOBALS['cfg'] to see whether we should
		# store a permanent copy of $file['tmp_name'] somewhere
		# on disk. It would be nice to store it with the sheet
		# ID the data has been associated which we don't have
		# yet so maybe this isn't the best place to do the storing...
		# (2010107/straup)

		return $rsp;
	}

	#################################################################

	function import_process_data(&$user, &$data, $more=array()){

		#
		# First do some sanity-checking on the data before
		# we bother to create a sheet.
		#

		$rsp = import_ensure_valid_data($data);

		if (! $rsp['ok']){
			return $rsp;
		}

		#
		# CAN I HAS MAH SHEET?
		#

		$sheet_rsp = sheets_create_sheet($user, $more);

		if (! $sheet_rsp['ok']){
			return $sheet_rsp;
		}

		$sheet = $sheet_rsp['sheet'];

		#
		# OMG!!! IT'S FULL OF DOTS!!!!
		#

		$more['skip_validation'] = 1;	# see above

		$dots_rsp = dots_import_dots($user, $sheet_rsp['sheet'], $data, $more);

		# No soup for sheet! Or is it the other way around...

		if (! $dots_rsp['ok']){
			sheets_delete_sheet($sheet);
		}

		else {

			$dots_rsp['sheet'] = $sheet;

			$count_rsp = sheets_update_dot_count_for_sheet($sheet);
			$dots_rsp['update_sheet_count'] = $count_rsp['ok'];

			if ($more['return_dots']){
				$dots_rsp['dots'] = dots_get_dots_for_sheet($sheet, $sheet['user_id']);
			}
		}

		return $dots_rsp;
	}

	#################################################################

	function import_scrub($input, $sanitize_as='str'){

		$input = html_entity_decode($input, ENT_QUOTES, 'UTF-8');

		$input = sanitize($input, $sanitize_as);
		$input = filter_strict($input);

		$input = trim($input);
		return $input;
	}

	#################################################################

	function import_ensure_valid_data(&$data){

		$errors = array();
		$record = 1;

		foreach ($data as $row){

			$rsp = dots_ensure_valid_data($row);

			if (! $rsp['ok']){

				$errors[] = array(
					'error' => $rsp['error'],
					'record' => $record,
				);
			}

			$record++;
		}

		$ok = (count($errors)) ? 0 : 1;

		return array(
			'ok' => $ok,
			'errors' => $errors,
		);
	}

	#################################################################

	function import_ensure_valid_latlon($lat, $lon){
		$lat = ($lat && geo_utils_is_valid_latitude($lat)) ? $lat : null;
		$lon = ($lon && geo_utils_is_valid_longitude($lon)) ? $lon : null;
		return array($lat, $lon);
	}

	#################################################################
	
	
	# does stuff that front-end needs to create dataTable (seanc | 07072011)

	function import_preprocess_address_fields($rsp){

		if (!isset($rsp['data']) && empty($rsp['data'])){
			return $rsp;
		}

		loadlib("dots_address");

		$needs_geocoding = 0;

		# note the pass-by-ref on $row

		foreach ($rsp['data'] as &$row){

			if (($row['latitude']) && ($row['longitude'])){
				$row['_has_latlon'] = 1;
				continue;
			}

			$row['_has_latlon'] = 0;
			$row['_address'] = dots_address_parse_for_geocoding($row);

			$needs_geocoding += 1;
		}

		$rsp['needs_geocoding'] = $needs_geocoding;
		return $rsp;
	}

	#################################################################

	# the end
