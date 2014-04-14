<?php

	loadlib("flickr");

	# A comma-delimited list of extra information to fetch for each returned record.
	# Currently supported fields are: description, license, date_upload, date_taken,
	# owner_name, icon_server, original_format, last_update, geo, tags, machine_tags,
	# o_dims, views, media, path_alias, url_sq, url_t, url_s, url_m, url_z, url_l,
	# url_o (http://www.flickr.com/services/api/flickr.photos.search.htm)

	$GLOBALS['import_flickr_spr_extras'] = 'geo,description,date_taken,owner_name,tags';

	#################################################################

	function import_flickr_url_type($url, $more=array()){

		# photosets

		if (preg_match("!/sets/(\d+)/?$!", $url, $m)){
			return array("set", $m[1]);
		}

		# groups

		if (preg_match("!/groups/([^/]+)(?:/pool)?/?$!", $url, $m)){
			return array("group", $m[1]);
		}

		# individual users

		if (preg_match("!/photos/([^/]+)/?!", $url, $m)){
			return array("user", $m[1]);
		}

		# for example:
		# http://api.flickr.com/services/feeds/geo/?id=35034348999@N01&amp;lang=en-us

		if (preg_match("!/services/feeds/geo!", $url)){
			return array("feed", $url);
		}

		return null;
	}

	#################################################################
	

	function import_flickr_url($url, $more=array()){

		list($type, $uid) = import_flickr_url_type($url);

		# photosets

		if ($type == "set"){
			$rows = import_flickr_photoset($uid, $more);
    		
			return array('ok' => 1, 'data' => $rows);
		}

		# groups

		if ($type == "group"){

			$group_id = $uid;

			if (! preg_match("!\@N\d+$!", $group_id)){
				$group_id = flickr_lookup_group_id_by_url($url);
			}

			if (! $group_id){
				return array('ok' => 0, 'error' => 'Invalid group ID');
			}

			$rows = import_flickr_group_pool($group_id, $more);
			return array('ok' => 1, 'data' => $rows);
		}

		# individual users

		if ($type == "user"){

			$user_id = $uid;

			if (! preg_match("!\@N\d+$!", $user_id)){
				$user_id = flickr_lookup_user_id_by_url($url);
			}

			if (! $user_id){
				return array('ok' => 0, 'error' => 'Invalid user ID');
			}

			$rows = import_flickr_user($user_id, $more);
			return array('ok' => 1, 'data' => $rows);
		}

		# for example:
		# http://api.flickr.com/services/feeds/geo/?id=35034348999@N01&amp;lang=en-us

		if ($type == "feed"){

			$more = array(
				'assume_mime_type' => 'application/rss+xml'
			);

			$upload = import_fetch_uri($url, $more);

			if ($upload['ok']){
				$upload = import_process_file($upload);
			}

			return $upload;
		}

		# yahoo says no

		return array('ok' => 0, 'error' => 'Failed to parse URL');
	}

	#################################################################

	function import_flickr_user($user_id, $more=array()){

		$method = 'flickr.photos.search';

		$args = array(
			'user_id' => $user_id,
			'has_geo' => 1,
			'extras' => $GLOBALS['import_flickr_spr_extras'],
		);

		# Note the order of precedence

		if (is_array($more['filter'])){
			$args = array_merge($more['filter'], $args);
		}

		return import_flickr_spr_paginate($method, $args, $more);
	}

	#################################################################

	function import_flickr_photoset($set_id, $more=array()){

		$method = 'flickr.photosets.getPhotos';

		$args = array(
			'photoset_id' => $set_id,
			'extras' => $GLOBALS['import_flickr_spr_extras'],
		);

		# Note the order of precedence

		if (is_array($more['filter'])){
			$args = array_merge($more['filter'], $args);
		}

		# I don't know why we did this... (20110427/straup)
		$more['root'] = 'photoset';

		return import_flickr_spr_paginate($method, $args, $more);
	}

	#################################################################

	function import_flickr_group_pool($group_id, $more=array()){

		$method = 'flickr.photos.search';

		$args = array(
			'group_id' => $group_id,
			'has_geo' => 1,
			'extras' => $GLOBALS['import_flickr_spr_extras'],
		);

		# Note the order of precedence

		if (is_array($more['filter'])){
			$args = array_merge($more['filter'], $args);
		}

		return import_flickr_spr_paginate($method, $args, $more);
	}

	#################################################################

	function import_flickr_spr_paginate($method, $args, $more=array()){

		$defaults = array(
			'root' => 'photos',
			'max_photos' => $GLOBALS['cfg']['import_max_records'],
			'ensure_geo' => 0,
		);

		$more = array_merge($defaults, $more);
		$root = $more['root'];

		$photos = array();
		$count_photos = 0;

		$to_remove = array(
			'secret',
			'server',
			'farm',
			'isprimary',
			'place_id',
			'isfriend',
			'isfamily',
			'ispublic',
			'owner',	# we're already grabbing ownername so don't bother
					# with NSIDs until someone asks (20110429/straup)
			'geo_is_family',
			'geo_is_friend',
			'geo_is_contact',
			'geo_is_public',
			'datetakengranularity',
		);

		$page = 1;
		$pages = null;

		while ((! isset($pages)) || ($page <= $pages)){

			# If any sort of geo filter is passed to the API
			# Flickr will silently set this number to 250

			$args['per_page'] = 500;
			$args['page'] = $page;

			$_rsp = flickr_api_call($method, $args);

			if (! $_rsp['ok']){
				break;
			}

			$rsp = $_rsp['rsp'];

			if (! isset($pages)){
				$pages = $rsp[$root]['pages'];
			}

			foreach ($rsp[$root]['photo'] as $ph){

				# why didn't we just add a "has_geo" attribute
				# to the API responses... (20110425/straup)

				if ($ph['accuracy'] == 0){
					continue;
				}

				foreach ($to_remove as $key){
					if (isset($ph[$key])){
						unset($ph[$key]);
					}
				}

				$ph['flickr:id'] = $ph['id'];
				unset($ph['id']);

				$ph['description'] = $ph['description']['_content'];
				$photos[] = $ph;
				$count_photos += 1;

				if ((isset($more['max_photos'])) && ($count_photos >= $more['max_photos'])){
					break;
				}
			}

			if ((isset($more['max_photos'])) && ($count_photos >= $more['max_photos'])){
				break;
			}

			$page += 1;
		}

		return $photos;
	}

	#################################################################

	# the end
