<?php

	loadlib("maps");

	loadpear("PHPPowerPoint");
	loadpear("PHPPowerPoint/IOFactory");

	#################################################################

	function ppt_export_dots(&$dots, &$more){

		$maps = array();

		$w = 960;
		$h = 720;

		$ppt = new PHPPowerPoint();
		$ppt->getProperties()->setTitle($more['title']);
		$ppt->getProperties()->setCreator("Dotspotting");

		# set title here
		# $slide = $ppt->getActiveSlide();

		$ppt->removeSlideByIndex(0);

		# draw the maps

		$dot_per_slide = 1;

		$img_more = array(
			'width' => $w,
			'height' => $h,
			'dot_size' => 20,
		);

		if ((! $dot_per_slide) || (count($dots) == 0)){

			$maps[] = maps_png_for_dots($dots, $img_more);
		}

		else {

			$img_more['dot_size'] = 25;
			$img_more['width'] = $img_more['height'];
			$img_more['img_prefix'] = 'ppt';

			# remember: 1 dot per slide

			$_dots = array();

			foreach ($dots as $dot){
				$_dots[] = array($dot);
			}

			# See this? It's to prevent Dotspoting from accidentally
			# DOS-ing itself.

			$enable_multigets = $GLOBALS['cfg']['wscompose_enable_multigets'];

			if (($dot_per_slide) && (count($dots) > $GLOBALS['cfg']['wscompose_max_dots_for_multigets'])){
				$GLOBALS['cfg']['wscompose_enable_multigets'] = 0;
			}

			$maps = maps_png_for_dots_multi($_dots, $img_more);

			$GLOBALS['cfg']['wscompose_enable_multigets'] = $enable_multigets;
		}

		# now draw all the maps...

		$count_maps = count($maps);

		for ($i = 0; $i < $count_maps; $i++){

			$map = $maps[$i];
			$slide = $ppt->createSlide();

			$shape = $slide->createDrawingShape();
			$shape->setName('map');
			$shape->setDescription('');
			$shape->setPath($map);

			$shape->setWidth($w);
			$shape->setHeight($h);

			$shape->setOffsetX(0);
			$shape->setOffsetY(0);

			if ($dot_per_slide){

				$dot = $dots[$i];

				if (! $dot['id']){
					continue;
				}

				$_dot = dots_get_dot($dot['id'], $more['viewer_id']);

				if (! $_dot['id']){
					continue;
				}

				$shape = $slide->createDrawingShape();
				$shape->setName('map');
				$shape->setDescription('');
				$shape->setPath($map);

				$text = $slide->createRichTextShape();
				$text->setHeight($h);
				$text->setWidth($w - $h);
				$text->setOffsetX($h + 20);
				$text->setOffsetY(0 + 20);

				$align = $text->getAlignment();
				$align->setHorizontal( PHPPowerPoint_Style_Alignment::HORIZONTAL_LEFT );

				$cols = array_merge($_dot['index_on'], array(
					'latitude',
					'longitude',
					'created',
					'id',
				));

				foreach ($cols as $col){

					$value = trim($dot[$col]);

					if (! $value){
						continue;
					}

					$body = $text->createTextRun("{$col}:\n");
					$body->getFont()->setSize(18);
					$body->getFont()->setBold(false);	# default bold font is not what do say "pretty"

					$body = $text->createTextRun("{$dot[$col]}\n\n");
					$body->getFont()->setSize(14);
					$body->getFont()->setBold(false);
				}
			}
		}

		#

		$writer = PHPPowerPoint_IOFactory::createWriter($ppt, 'PowerPoint2007');
		$writer->save($more['path']);

		$writer = null;

		foreach ($maps as $path){

			if (($path) && (! file_exists($path)) && (! unlink($path))){
				error_log("[EXPORT] (ppt) failed to unlink {$path}");
			}
		}

		return $more['path'];
	}

	#################################################################

	# the end
