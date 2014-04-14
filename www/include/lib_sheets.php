<?php

	#################################################################

	loadlib("sheets_lookup");

	loadlib("dots_search");
	loadlib("dots_search_extras");

	loadlib("export_cache");

	#################################################################

	function sheets_create_sheet(&$user, $more=array()){

		$sheet_id = dbtickets_create(32);

		if (! $sheet_id){

			return array(
				'ok' => 0,
				'error' => 'Ticket server failed',
			);
		}

		$now = time();

		$sheet = array(
			'user_id' => $user['id'],
			'created' => $now,
			'last_modified' => $now,
			'id' => $sheet_id,
			'simplified' => $more['simplified'],
		);

		$optional = array(
			'label',
			'mime_type',
		);

		foreach ($optional as $o){

			if (isset($more[$o])){
				$sheet[$o] = $more[$o];
			}
		}

		$insert = array();

		foreach ($sheet as $k => $v){
			$insert[$k] = AddSlashes($v);
		}

		$rsp = db_insert_users($user['cluster_id'], 'Sheets', $insert);

		if (! $rsp['ok']){
			return null;
		}

		#
		# Store in the lookup table
		#

		$lookup = array(
			'sheet_id' => AddSlashes($sheet_id),
			'user_id' => AddSlashes($user['id']),
			'created' => AddSlashes($now),
			'fingerprint' => AddSlashes($more['fingerprint']),
		);

		$lookup_rsp = sheets_lookup_create($lookup);

		if (! $lookup_rsp['ok']){
			# What ?
		}

		#
		# Okay!
		#

		sheets_load_details($sheet, $user['id']);

		$rsp['sheet'] = $sheet;
		return $rsp;
	}

	#################################################################

	#
	# Whatever else happens in this function, it should be
	# done in a way that allows it to be run out of band (as
	# in: some kind of offline task) should that ever become
	# necessary.
	#

	function sheets_delete_sheet(&$sheet){

		#
		# Figure out where the sheet is stored
		#

		loadlib("archive");
		$archive_path = archive_path_for_sheet($sheet);

		#

		export_cache_purge_sheet($sheet);

		#
		# Purge search
		#

		dots_search_remove_sheet($sheet);
		dots_search_extras_remove_sheet($sheet);

		#
		# Okay, go
		#

		$user = users_get_by_id($sheet['user_id']);

		$enc_id = AddSlashes($sheet['id']);
		$sql = "SELECT * FROM Dots WHERE sheet_id='{$enc_id}'";

		$more = array(
			'page' => 1,
			'per_page' => 1000,
		);

		$page_count = null;
		$total_count = null;
		$dots_deleted = 0;

		while((! isset($page_count)) || ($page_count >= $more['page'])){

			$rsp = db_fetch_paginated_users($user['cluster_id'], $sql, $more);		

			if (! $rsp['ok']){
				$rsp['dots_deleted'] = $dots_deleted;
				$rsp['dots_count'] = $total_count;
				return $rsp;
			}

			if (! isset($page_count)){
				$page_count = $rsp['pagination']['page_count'];
				$total_count = $rsp['pagination']['total_count'];
			}

			foreach ($rsp['rows'] as $dot){

				$dot_more = array(
					'skip_update_sheet' => 1,
					'skip_update_search' => 1,
				);

				$dot_rsp = dots_delete_dot($dot, $dot_more);

				if ($dot_rsp['ok']){
					$dots_deleted ++;
				}
			}

			$more['page'] ++;
		}

		$sql = "DELETE FROM Sheets WHERE id='{$enc_id}'";
		$rsp = db_write_users($user['cluster_id'], $sql);

		# Dot specific caches are purged above when we call
		# dots_delete_dot.

		$cache_keys = array(
			"sheet_{$sheet['id']}",
			"sheets_counts_for_user_{$sheet['user_id']}",
			"sheets_counts_for_user_{$sheet['user_id']}_public",
		);

		foreach ($cache_keys as $key){
			cache_unset($key);
		}

		#
		# Update the lookup table
		#

		$update = array(
			'deleted' => time(),
		);

		$lookup_rsp = sheets_lookup_update($sheet, $update);

		if (! $lookup_rsp['ok']){
			# what?
		}

		#
		# Remove the upload (if any)
		#

		if (file_exists($archive_path)){

			$ok = unlink($archive_path);
			# if not $ok then what?
		}

		# Remove any cache exports

		export_cache_purge_sheet($sheet);

		#
		# Happy happy!
		#

		$rsp['dots_deleted'] = $dots_deleted;
		$rsp['dots_count'] = $total_count;

		return $rsp;
	}

	#################################################################

	#
	# Whatever else happens in this function, it should be
	# done in a way that allows it to be run out of band (as
	# in: some kind of offline task) should that ever become
	# necessary.
	#

	function sheets_delete_sheets_for_user(&$user){

		$enc_id = AddSlashes($user['id']);
		$sql = "SELECT * FROM Sheets WHERE user_id='{$enc_id}'";
	
		$more = array(
			'page' => 1,
			'per_page' => 100,
		);

		$page_count = null;

		$sheets_count = 0;
		$sheets_deleted = 0;

		$dots_count = 0;
		$dots_deleted = 0;

		while((! isset($page_count)) || ($page_count >= $more['page'])){

			$rsp = db_fetch_paginated_users($user['cluster_id'], $sql, $more);		

			if (! $rsp['ok']){
				$rsp['sheets_deleted'] = $sheets_deleted;
				$rsp['sheets_count'] = $sheets_count;
				$rsp['dots_deleted'] = $dots_deleted;
				$rsp['dots_count'] = $dots_count;

				return $rsp;
			}

			if (! isset($page_count)){
				$page_count = $rsp['pagination']['page_count'];
				$sheets_count = $rsp['pagination']['total_count'];
			}

			foreach ($rsp['rows'] as $sheet){

				$sheet_rsp = sheets_delete_sheet($sheet);

				if ($sheet_rsp['ok']){

					$dots_count += $sheet_rsp['dots_count'];
					$dots_deleted += $sheet_rsp['dots_deleted'];

					$sheets_deleted ++;
				}
			}

			$more['page'] ++;
		}

		# TO DO: finish cleaning up any export cache directories left hanging
		# around. The delete_sheet function will purge any files but all the
		# zero-padded directories are still in place (20110302/straup)

		return array(
			'ok' => 1,
			'sheets_deleted' => $sheets_deleted,
			'sheets_count' => $sheets_count,
			'dots_deleted' => $dots_deleted,
			'dots_count' => $dots_count,
		);
	}

	#################################################################

	#
	# Note the pass-by-ref
	#

	function sheets_load_details(&$sheet, $viewer_id=0, $more=array()){

		if ($more['load_extent']){
			$sheet['extent'] = dots_get_extent_for_sheet($sheet, $viewer_id);
		}

		if ($more['load_dots']){
			$sheet['dots'] = dots_get_dots_for_sheet($sheet, $viewer_id);
		}

		if ($more['load_user']){
			$sheet['user'] = users_get_by_id($sheet['user_id']);
		}
	}

	#################################################################

	#
	# Grab the sheet from the shards
	#

	function sheets_get_sheet($sheet_id, $viewer_id=0, $more=array()){

		$cache_key = "sheet_{$sheet_id}";
		$cache = cache_get($cache_key);

		if ($cache['ok']){
			$sheet = $cache['data'];
		}

		else {

			# this is the sort of thing that would be called from lib_dots

			if ($sheet_user_id = $more['sheet_user_id']){
				$user = users_get_by_id($sheet_user_id);
			}

			else {

				$lookup = sheets_lookup_sheet($sheet_id);

				if (! $lookup){
					return;
				}

				if ($lookup['deleted']){

					return array(
						'id' => $sheet_id,
						'deleted' => $lookup['deleted'],
					);
				}

				$user = users_get_by_id($lookup['user_id']);
			}

			if (! $user['id']){
				return;
			}

			$enc_id = AddSlashes($sheet_id);
			$enc_user = AddSlashes($user['id']);

			$sql = "SELECT * FROM Sheets WHERE id='{$enc_id}'";

			$rsp = db_fetch_users($user['cluster_id'], $sql);
			$sheet = db_single($rsp);

			cache_set($cache_key, $sheet, 'cache locally');
		}

		if ($sheet){
			sheets_load_details($sheet, $viewer_id, $more);
		}

		return $sheet;
	}

	#################################################################

	function sheets_can_view_sheet(&$sheet, $viewer_id=0){

		if ($sheet['user_id'] == $viewer_id){
			return 1;
		}

		if ($sheet['count_dots_public'] >= 1){
			return 1;
		}

		return 0;
	}

	#################################################################

	function sheets_update_sheet(&$sheet, $update){

		$user = users_get_by_id($sheet['user_id']);

		$enc_id = AddSlashes($sheet['id']);
		$where = "id='{$enc_id}'";

		foreach ($update as $k => $v){
			$update[$k] = AddSlashes($v);
		}

		$update['last_modified'] = time();

		$rsp = db_update_users($user['cluster_id'], 'Sheets', $update, $where);

		if ($rsp['ok']){

			$cache_keys = array(
				"sheet_{$sheet['id']}",
			);

			foreach ($cache_keys as $key){
				cache_unset($key);
			}

			export_cache_purge_sheet($sheet);
		}

		return $rsp;
	}

	#################################################################

	function sheets_recently_created($viewer_id=0, $more=array()){

        $defaults = array(
            'per_page' => 50,
        );
        
        $args = array_merge($defaults, $more);

		$sql = "SELECT * FROM SheetsLookup WHERE deleted=0 AND count_dots_public > 0 ORDER BY created DESC";
		$rsp = db_fetch_paginated($sql, $args);

		if (! $rsp['ok']){
			return $rsp;
		}

		$sheets = array();

		foreach ($rsp['rows'] as $row){

			$more = array(
				'sheet_user_id' => $row['user_id'],
				'load_extent' => 1,
				'load_user' => 1,
			);

			if ($sheet = sheets_get_sheet($row['sheet_id'], $viewer_id, $more)){
				$sheets[] = $sheet;
			}
		}

		$ok = (count($sheets)) ? 1 : 0;

		return array(
			'ok' => $ok,
			'sheets' => &$sheets,
		);
	}

	#################################################################

	function sheets_sheets_for_user($user, $viewer_id=0, $more=array()){

		# CACHING: pagination makes caching cry

		$enc_id = AddSlashes($user['id']);

		$sql = "SELECT * FROM Sheets WHERE user_id='{$enc_id}'";

		if ($user['id'] != $viewer_id){

			$sql .= " AND count_dots_public > 0";
		}

		$order_by = 'created';
		$order_sort = 'DESC';

		# check $args for alternate sorting

		$order_by = AddSlashes($order_by);
		$order_sort = AddSlashes($order_sort);

		$sql .= " ORDER BY {$order_by} {$order_sort}";

		$rsp = db_fetch_paginated_users($user['cluster_id'], $sql, $more);
		$sheets = array();

		if ($rsp['ok']){

			foreach ($rsp['rows'] as $row){
				sheets_load_details($row, $viewer_id, array('load_extent' => 1));
				$sheets[] = $row;
			}
		}

		return $sheets;
	}

	#################################################################

	function sheets_update_dot_count_for_sheet(&$sheet){

		$counts = dots_count_dots_for_sheet($sheet);

		$sheet_update = array(
			'count_dots' => $counts['total'],
			'count_dots_public' => $counts['public'],
		);

		$lookup_update = array(
			'count_dots_public' => $counts['public'],
		);

		$sheet_rsp = sheets_update_sheet($sheet, $sheet_update);
		$lookup_rsp = sheets_lookup_update($sheet, $lookup_update);

		$ok = ($sheet_rsp['ok'] && $lookup_rsp['ok']) ? 1 : 0;

		return array(
			'ok' => $ok,
		);
	}

	#################################################################

	function sheets_counts_for_user(&$user, $viewer_id=0){

		$cache_key = "sheets_counts_for_user_{$user['id']}";

		if ($user['id'] != $viewer_id){
			$cache_key .= "_public";
		}

		$cache = cache_get($cache_key);

		if ($cache['ok']){
			return $cache['data'];
		}

		#

		$enc_id = AddSlashes($user['id']);

		if ($viewer_id == $user['id']){
			$sql = "SELECT COUNT(id) AS count_sheets, SUM(count_dots) AS count_dots FROM Sheets WHERE user_id='{$enc_id}'";
		}

		else {
			$sql = "SELECT COUNT(id) AS count_sheets, SUM(count_dots_public) AS count_dots FROM Sheets WHERE user_id='{$enc_id}' AND count_dots_public > 0";
		}

		#

		$row = db_single(db_fetch_users($user['cluster_id'], $sql));

		if ($row){
			cache_set($cache_key, $row, 'cache locally');
		}

		return $row;
	}

	#################################################################

	# the end