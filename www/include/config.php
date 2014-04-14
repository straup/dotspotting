<?php

	$GLOBALS['cfg'] = array();

	$GLOBALS['cfg']['dotspotting_version'] = '0.0.0';	# see also: http://semver.org/

	# Things you might want to do quickly

	$GLOBALS['cfg']['disable_site'] = 0;
	$GLOBALS['cfg']['show_show_header_message'] = 0;

	# Feature flags
	# See also: http://code.flickr.com/blog/2009/12/02/flipping-out/

	$GLOBALS['cfg']['enable_feature_import'] = 1;
	$GLOBALS['cfg']['enable_feature_import_by_url'] = 0;

	$GLOBALS['cfg']['enable_feature_import_archive'] = 0;

	$GLOBALS['cfg']['enable_feature_dots_indexing'] = 1;
	$GLOBALS['cfg']['dots_indexing_index_all'] = 1; 	# This flag has precedence over dots_indexing_max_cols

	$GLOBALS['cfg']['dots_indexing_max_cols'] = 4;
	$GLOBALS['cfg']['dots_indexing_max_cols_list'] = range(1, $GLOBALS['cfg']['dots_indexing_max_cols']);

	# Don't turn this on until there is a working offline tasks system
	# $GLOBALS['cfg']['enable_feature_enplacify'] = 0;

	$GLOBALS['cfg']['enable_feature_api'] = 1;

	$GLOBALS['cfg']['enable_feature_signup'] = 1;
	$GLOBALS['cfg']['enable_feature_signin'] = 1;
	$GLOBALS['cfg']['enable_feature_account_delete'] = 1;
	$GLOBALS['cfg']['enable_feature_password_retrieval'] = 1;

	$GLOBALS['cfg']['password_retrieval_from_email'] = "do-not-reply@{$_SERVER['SERVER_NAME']}";
	$GLOBALS['cfg']['password_retrieval_from_name'] = 'Dotspotting Password Helper Robot';

	# wscompose for stitching together map tiles into a
	# static image

	$GLOBALS['cfg']['enable_feature_wscompose'] = 0;	# Use the ModestMaps wscompose server for rendering maps
								# This is off by default because it requires running a
								# separate service.

	$GLOBALS['cfg']['wscompose_host'] = 'http://127.0.0.1';
	$GLOBALS['cfg']['wscompose_port'] = 9999;

	$GLOBALS['cfg']['wscompose_enable_multigets'] = 0;
	$GLOBALS['cfg']['wscompose_max_dots_for_multigets'] = 25;	# This is to prevent Dotspoting from accidentally DOS-ing itself.

	$GLOBALS['cfg']['enable_feature_geocoding'] = 1;

	$GLOBALS['cfg']['enable_feature_search'] = 0;
	$GLOBALS['cfg']['enable_feature_search_export'] = 1;
	$GLOBALS['cfg']['enable_feature_search_facets'] = 1;

	$GLOBALS['cfg']['enable_feature_cors'] = 1;	# http://www.w3.org/TR/cors/

	$GLOBALS['cfg']['enable_feature_http_prefetch'] = 0;

	$GLOBALS['cfg']['enable_feature_magicwords'] = array(

		'flickr' => array(
			'id' => 1,
		),

		'foursquare' => array(
			'venue' => 1,
		),

		'geonames' => array(
			'id' => 0,
		),

		'oam' => array(
			'mapid' => 1,
		),

		'vimeo' => array(
			# 'id' => 1,
		),

		'walkingpapers' => array(
			'scanid' => 1,
		),

		'yahoo' => array(
			'woeid' => 1,
		),

		'youtube' => array(
			# 'id' => 1,
		),
	);

	# God auth

	$GLOBALS['cfg']['auth_enable_poormans_god_auth'] = 0;

	# $GLOBALS['cfg']['auth_poormans_god_auth'] = array(
	# 	xxx => array(
	# 		'roles' => array( 'staff' ),
	# 	),
	# );

	# Crypto stuff

	$GLOBALS['cfg']['crypto_cookie_secret'] = 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['crypto_password_secret'] = 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['crypto_crumb_secret'] = 'READ-FROM-SECRETS';

	# Database stuff

	$GLOBALS['cfg']['db_main'] = array(
		'host'	=> 'localhost',
		'name'	=> 'dotspotting',
		'user'	=> 'dotspotting',
		'pass'	=> 'READ-FROM-SECRETS',
		'auto_connect' => 1,
	);

	$GLOBALS['cfg']['db_enable_poormans_slaves'] = 1;
	$GLOBALS['cfg']['db_enable_poormans_ticketing'] = 1;
	$GLOBALS['cfg']['db_enable_poormans_federation'] = 1;

	#
	# API stuff
	#

	# This is defined in config-api.php and gets pulled in Dotspotting's init.php
	# assuming that 'enable_feature_api' is true.

	#
	# Templates
	#

	$GLOBALS['cfg']['smarty_template_dir'] = DOTSPOTTING_WWW_DIR . '/templates';
	$GLOBALS['cfg']['smarty_compile_dir'] = DOTSPOTTING_WWW_DIR . '/templates_c';
	$GLOBALS['cfg']['smarty_compile'] = 1;

	#
	# App specific stuff
	#

	# Just blow away whatever Flamework says for abs_root_url. The user has the chance to reset these in
	# config/dotspotting.php and we want to ensure that if they don't the code in include/init.php for
	# wrangling hostnames and directory roots has a clean start. (20101127/straup)

	$GLOBALS['cfg']['abs_root_url'] = '';
	$GLOBALS['cfg']['safe_abs_root_url'] = '';

	$GLOBALS['cfg']['auth_cookie_domain'] = parse_url($GLOBALS['cfg']['abs_root_url'], 1);
	$GLOBALS['cfg']['auth_cookie_name'] = 'a';

	$GLOBALS['cfg']['maptiles_template_url'] = 'http://tile.stamen.com/toner/{Z}/{X}/{Y}.png';
	$GLOBALS['cfg']['maptiles_template_hosts'] = array();

	$GLOBALS['cfg']['maptiles_license'] = 'Map data <a href="http://creativecommons.org/licenses/by-sa/3.0/">CCBYSA</a> 2010 <a href="http://openstreetmap.org/">OpenStreetMap.org</a> contributors';

	$GLOBALS['cfg']['pagination_per_page'] = 100;
	$GLOBALS['cfg']['pagination_spill'] = 5;
	$GLOBALS['cfg']['pagination_assign_smarty_variable'] = 1;
	$GLOBALS['cfg']['pagination_style'] = "pretty";

	$GLOBALS['cfg']['import_max_records'] = 1000;
	$GLOBALS['cfg']['import_by_url_do_head'] = 1;

	$GLOBALS['cfg']['import_archive_root'] = '';

	$GLOBALS['cfg']['import_fields_mightbe_latitude'] = array(
		'lat',
	);

	$GLOBALS['cfg']['import_fields_mightbe_longitude'] = array(
		'lon',
		'long',
		'lng',
	);

	# this is off by default since we don't necessarily know where
	# to write the cache files.

	$GLOBALS['cfg']['enable_feature_export_cache'] = 0;
	$GLOBALS['cfg']['export_cache_root'] = '';

	# the list of things that can not be cached

	$GLOBALS['cfg']['export_cache_exclude_formats'] = array(
	);

	# things that users can tweaks exporting a sheet

	$GLOBALS['cfg']['export_valid_extras'] = array(

		# in case someone decides to be cute and start doing an
		# auto-incrementing attack on user-supplied parameters...
		# (20110302/straup)

		'png' => array(
			'height' => array(156, 480, 768),
			'width' => array(234, 640, 1024),
			'dot_size' => null,
		),

		'ppt' => array(
			'dot_size' => null,
		),

		'pdf' => array(
			'dot_size' => null,
		),
	);

	# a list of format which might be simplified

	$GLOBALS['cfg']['import_do_simplification'] = array(
		'kml' => 0, # when coordinates are stored in LineStrings
		'gpx' => 0, # basically always
	);

	# If these two are arrays they will be checked by the upload_by_url.php
	# code. They are expected to be lists of hostnames

	$GLOBALS['cfg']['import_by_url_blacklist'] = '';
	$GLOBALS['cfg']['import_by_url_whitelist'] = '';

	$GLOBALS['cfg']['import_kml_resolve_network_links'] = 1;

	#
	# Email
	#

	$GLOBALS['cfg']['email_from_name']	= 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['email_from_email']	= 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['auto_email_args']	= 'READ-FROM-SECRETS';

	#
	# Geo
	#

	$GLOBALS['cfg']['geo_geocoding_service'] = 'yahoo';
	$GLOBALS['cfg']['geo_geocoding_yahoo_apikey'] = '';

	# See also: lib_dots_derive.php

	$GLOBALS['cfg']['dots_derived_from'] = array(
		0 => 'user',
		1 => 'dotspotting',
		2 => 'geocoded (yahoo)',
		3 => 'geohash',
	);

	#
	# Enplacification
	#

	# This requires that 'enable_feature_enplacify' be enabled (see above)

	$GLOBALS['cfg']['enplacify'] = array(

		'chowhound' => array(
			'uris' => array(
				"/chow\.com\/restaurants\/([^\/]+)/",
			),
		),

		'dopplr' => array(
			'uris' => array(
				"/dplr\.it\/(eat|stay|explore)\/([^\/]+)/",
				"/dopplr\:(eat|stay|explore)=(.+)$/",
			),
		),

		'flickr' => array(
			'uris' => array(
				"/flickr\.com\/photos\/(?:[^\/]+)\/(\d+)/",
				# flickr short Uris
			),
			'machinetags' => array(
				'dopplr' => array('eat', 'explore', 'stay'),
				'foodspotting' => array('place'),
				'foursquare' => array('venue'),
				'osm' => array('node', 'way'),
				'yelp' => array('biz'),
			),
		),

		'foodspotting' => array(
			'uris' => array(
				"/foodspotting\.com\/places\/(\d+)/",
				"/foodspotting\:place=(.+)$/",
			),
		),

		'foursquare' => array(
			'uris' => array(
				"/foursquare\.com\/venue\/(\d+)/",
				"/foursquare\:venue=(\d+)$/",
			),
		),

		'openstreetmap' => array(
			'uris' => array(
				"/openstreetmap.org\/browse\/(node)\/(\d+)/",
				"/osm\:(node)=(\d+)$/",
			),
		),

		'yelp' => array(
			'uris' => array(
				"/yelp\.com\/biz\/([^\/]+)/",
				"/yelp\:biz=([^\/]+)/",
			),
		),
	);

	# Third-party API keys

	$GLOBALS['cfg']['flickr_apikey'] = 'READ-FROM-SECRETS';
	$GLOBALS['cfg']['google_analytics_id'] = '';
	$GLOBALS['cfg']['mixpanel_id'] = '';

	# Things you can probably not worry about

	$GLOBALS['cfg']['user'] = null;
	$GLOBALS['cfg']['smarty_compile'] = 1;
	$GLOBALS['cfg']['http_timeout'] = 3;
	$GLOBALS['cfg']['check_notices'] = 1;
	$GLOBALS['cfg']['db_profiling'] = 0;	

	# the end