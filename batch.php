<?php
global $wpdb;

$userId = 1;

require_once('assets/getid3/getid3.php');
require_once('assets/getid3/write.php');

$my_dir = dirname(__FILE__);
if (! file_exists($my_dir."/podcasts.json")) {
	die("NOT FOUND: ".$my_dir."/podcasts.json");
}
$podcasts_data = json_decode(file_get_contents($my_dir."/podcasts.json"), true);

foreach($podcasts_data["podcasts"] as $podcast){
	// if($podcast['slug'] != 'encounters-with-jesus') continue;
	print("PROCESSING: ".$podcast['title']."\n");

	$title = $podcast['title'];
	$series = $podcast['series'];
	$speaker = $podcast['speaker'];
	$video = $podcast['video'];
	$comment = $podcast['blurb'];
	$desc = $podcast['desc'];
	$track = '01';
	if (isset($podcast['track'])) {
		$track = $podcast['track'];
	}

	$time = strtotime($podcast['date']);
	$date = array(
		'display_date' => date('F j, Y', $time),
		'file_date'    => date('Y-m-d', $time),
		'unix_date'    => date('U', $time),
		'meridiem'     => date('a', $time),
		'year'         => date('Y', $time)
	);

	$audio_base = explode('/uploads', $podcast['audio'])[1];
	$audio_file_path = wp_upload_dir()['basedir']  . $audio_base;
	$audio_link = wp_upload_dir()['baseurl'] . $audio_base;

	if (! file_exists($audio_file_path)) {
		print('MP3 does not exist: '.$audio."\n");
		//UNCOMMENT IN PROD:
		die;

		//REMOVE IN PROD:
		// $dir = dirname($audio_file_path);
		// if (!file_exists($dir)) {
		// 	mkdir($dir, 0777, true);
		// }    
		// copy('no_recording.mp3', $audio_file_path);
	}

	$sermon_notes_base = "/2020/03/" . $date['file_date'] . ".pdf";
	$sermon_notes_file_path = wp_upload_dir()['basedir'] . $sermon_notes_base;
	$sermon_notes_link = null;
	if (! file_exists($sermon_notes_file_path)) {
		print('PDF does not exist: '.$sermon_notes_file_path."\n");
	} else {
		$sermon_notes_link = wp_upload_dir()['baseurl'] . $sermon_notes_base;
		print('PDF: '.$sermon_notes_link."\n");
	}

	$image_file_path = null;
	$image_link = null;
	if (isset($podcasts_data['images'][$podcast['slug']])) {
		$image_base = $upload_dir.explode('/uploads', $podcasts_data['images'][$podcast['slug']])[1];
		$image_file_path = wp_upload_dir()['basedir'] . $image_base;
		if (! file_exists($image_file_path)) {
			print('IMAGE does not exist: '.$image_file_path."\n");
			die;
		} else {
			$image_link = wp_upload_dir()['baseurl'] . $image_base;
		}
	}

	$tagwriter = new getid3_writetags;
	$tagwriter->filename = $audio_file_path;
	$tagwriter->tagformats = array('id3v1', 'id3v2.3');
	$tagwriter->overwrite_tags = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
	$tagwriter->remove_other_tags = true; // if true removes other tag formats (e.g. ID3v1, ID3v2, APE, Lyrics3, etc) that may be present in the file and only write the specified tag format(s). If false leaves any unspecified tag formats as-is.
	$tagwriter->tag_encoding = 'UTF-8';
	$tag_data = array(
		'title' => array($title),
		'artist' => array($speaker),
		'album' => array($series),
		'year' => array($year),
		'genre' => array('Sermons'),
		'comment' => array($comment),
		'track' => array($track),
		'popularimeter' => array('email' => 'podcast@avalonchurch.org', 'rating' => 128, 'data' => 0),
		'unique_file_identifier' => array('ownerid' => 'podcast@avalonchurch.org', 'data' => md5(time())),
	);
	$tagwriter->tag_data = $tag_data;
	if ($tagwriter->WriteTags()) {
		echo "Successfully wrote tags.\n";
		if (!empty($tagwriter->warnings)) {
			echo "There were some warnings:\n" . implode("\n", $tagwriter->warnings);
		}
	} else {
		die("Failed to write tags! Errors:\n" . implode("\n", $tagwriter->errors));
	}

	$audio_info = get_ID3( $audio_file_path );

	// check if we have a title
	if ( $title ) {

		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'wpfc_sermon' AND post_status = 'publish'", $title ) );

		// If there are no posts with the title of the sermon then make the sermon
		if ( ! $post_id ) {

			$tax_input_array = array (
				'wpfc_preacher'      => $speaker,
				'wpfc_sermon_series' => $series,
				'wpfc_service_type'  => 'Sunday Service',
				'wpfc_sermon_duration' => $audio_info['length'],
				'wpfc_sermon_size' => $audio_info['size'],
			);

			// create basic post with info from ID3 details
			$my_post = array(
				'post_title'  => $title,
				'post_name'   => $title,
				'post_date'   => $date['file_date'],
				'post_status' => 'publish',
				'post_type'   => 'wpfc_sermon',
				'tax_input'   => $tax_input_array,
				'content'     => $desc,
            );
            
            print_r(array(
                'audio_base'=>$audio_base,
                'audio_file_path'=>$audio_file_path,
                'audio_link'=>$audio_link,
                'image_base'=>$image_base,
                'image_file_path'=>$image_file_path,
                'image_link'=>$image_link,
                'sermon_notes_base'=>$sermon_notes_base,
                'sermon_notes_file_path'=>$sermon_notes_file_path,
                'sermon_notes_link'=>$sermon_notes_link,
                'my_post'=>$my_post,
            ));
			$post_id = wp_insert_post( $my_post );
			
			$sql_prep = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = 'attachment' AND post_status = 'inherit'", $title );
			$audio_attach_id = $wpdb->get_var($sql_prep);
			print_r(array('sql'=>$sql_prep, 'audio_attach_id'=>$audio_attach_id));
			if ( ! $audio_attach_id ) {
				print( 'No previous AUDIO attachment found.'."\n");
				// UNCOMMENT in prod:
				die;

				// REMOVE/COMMENT-OUT in prod
				// $wp_filetype = wp_check_filetype( basename( $audio_file_path ), null );
				// $audio_attachment = array(
				// 	'post_mime_type' => $wp_filetype['type'],
				// 	'post_title'     => $title,
				// 	'post_content'   => $title.' by '.$speaker.' from '.$series.'. Released: '.$date['year'],
				// 	'post_status'    => 'inherit',
				// 	'guid'           => $audio_link,
				// 	'post_parent'    => $post_id,
				// );
				// $audio_attach_id = wp_insert_attachment( $audio_attachment, $audio_file_path, $post_id );                
                // $attachment_data = wp_generate_attachment_metadata($audio_attach_id, $audio_file_path);
                // wp_update_attachment_metadata($audio_attach_id, $attachment_data);
			}
			$audio_attachment = get_post($audio_attach_id);
			print_r(array($audio_attach_id, $audio_attachment));

			add_post_meta( $post_id, 'sermon_date', $date['unix_date'], $unique = false );
			add_post_meta( $post_id, 'bible_passage', $audio['composer'], $unique = false );
			add_post_meta( $post_id, 'sermon_audio', $audio_link, $unique = false );
			add_post_meta( $post_id, '_wpfc_sermon_duration', $audio['length'], $unique = false );
			add_post_meta( $post_id, '_wpfc_sermon_size', $audio['size'], $unique = false );

            if ($video)
                add_post_meta( $post_id, 'sermon_video_link', $video, $unique = false );
            if ($sermon_notes_link)
                add_post_meta( $post_id, 'sermon_notes', $sermon_notes_link, $unique = false );
            if ($desc)
    			add_post_meta( $post_id, 'sermon_description', $desc, $unique = false );

			if ($image_link) {
				$image_attach_id = null;
				$image_attachment = null;

				$sql_prep = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_type = 'attachment' AND post_status = 'inherit'", $image_link );
				$image_attach_id = $wpdb->get_var( $sql_prep );
				print_r(array('sql'=>$sql_prep, 'image_attach_id'=>$image_attach_id));

				if ( ! $image_attach_id ) {
					print( 'No previous image attachment found.'."\n");
					// UNCOMMENT in prod:
					die;

                    // REMOVE/COMMENT-OUT in prod
					// $wp_filetype = wp_check_filetype( basename( $image_file_path ), null );
	 				// $image_attachment = array(
					// 	'post_mime_type' => $wp_filetype['type'],
					//     'post_title'     => basename($image_file_path),
					// 	'post_status'    => 'inherit',
					//     'guid'           => $image_link,
					//     'post_parent'    => $post_id,
					// );
				    // $image_attach_id = wp_insert_attachment( $image_attachment, $image_file_path, $post_id );
                    // $attachment_data = wp_generate_attachment_metadata($image_attach_id, $image_file_path);
                    // wp_update_attachment_metadata($image_attach_id, $attachment_data);
                }
				$image_attachment = get_post($image_attach_id);
				print_r(array($image_attach_id, $image_attachment));

				$success = set_post_thumbnail($post_id, $image_attach_id);
				if (!$success) {
					echo "IMAGE THUMB FAILED\n";
				} else {
					echo "IMAGE THUMB SUCCESS\n";
				}
			}
			$link = get_permalink( $post_id );
			print('Sermon created: ' . $title . ' (' . $link . ")\n" );
		} else {
			print( 'Sermon already exists: ' . $title . "\n");
		}
	}
}

function get_ID3( $file_path ) {
	// Initialize getID3 engine
	$get_ID3 = new getID3;
	$ThisFileInfo = $get_ID3->analyze( $file_path );

	$imageWidth = "";
	$imageHeight = "";
	/**
	 * Optional: copies data from all subarrays of [tags] into [comments] so
	 * metadata is all available in one location for all tag formats
	 * meta information is always available under [tags] even if this is not called
	 */
	getid3_lib::CopyTagsToComments( $ThisFileInfo );

	$tags = array( 'title' => sanitize_text_field( $ThisFileInfo['filename'] ), 'genre' => '', 'artist' => '', 'album' => '', 'year' => '' );

	if ( !isset( $ThisFileInfo['tags']['id3v2'] ) ) {
		die( $ThisFileInfo['filename'] . ' does not seem to have ID3 version 2 tags.'."\n");
	}

	foreach ( $tags as $key => $tag ) {
		if ( isset( $ThisFileInfo['tags']['id3v2'] ) && array_key_exists( $key, $ThisFileInfo['tags']['id3v2'] ) ) {
			$value = sanitize_text_field( $ThisFileInfo['tags']['id3v2'][$key][0] );
			$tags[$key] = $value;
		}
	}

	if ( isset( $ThisFileInfo['comments_html']['comment'] ) ) {
		$value = sanitize_text_field( $ThisFileInfo['comments_html']['comment'][0] );
		$tags['comment'] = $value;
	}

	// see en.wikipedia.org/wiki/ID3
	// subtitle tit3 in id3v2 and tsst in id3v4
	if ( ! empty( $ThisFileInfo['id3v2']['TIT3'][0]['data'] ) ) {
		$tags['subtitle'] = sanitize_text_field( $ThisFileInfo['id3v2']['TIT3'][0]['data'] );
	}
	else if ( ! empty( $ThisFileInfo['id3v2']['TSST'][0]['data'] ) ) {
			$tags['subtitle'] = sanitize_text_field( $ThisFileInfo['id3v2']['TSST'][0]['data'] );
		}
	else {
		$tags['subtitle'] = '';
	}

	$tags['composer'] = empty( $ThisFileInfo['id3v2']['TCOM'][0]['data'] ) ? '' : sanitize_text_field( $ThisFileInfo['id3v2']['TCOM'][0]['data'] );
	$tags['bitrate'] = sanitize_text_field( $ThisFileInfo['bitrate'] );
	$tags['length'] = sanitize_text_field( $ThisFileInfo['playtime_string'] );
	$tags['size'] = sanitize_text_field( $ThisFileInfo['filesize'] );

	if ( isset( $ThisFileInfo['comments']['picture'][0] ) ) {
		$pictureData = $ThisFileInfo['comments']['picture'][0];
		$imageinfo = array();
		//$imagechunkcheck = getid3_lib::GetDataImageSize($pictureData['data'], $imageinfo);
		$imageWidth = "150"; //$imagechunkcheck[0];
		$imageHeight = "150"; //$imagechunkcheck[1];
		$tags['image'] = '<img src="data:'.$pictureData['image_mime'].';base64,'.base64_encode( $pictureData['data'] ).'" width="'.$imageWidth.'" height="'.$imageHeight.'" class="img-polaroid">';
	}

	return $tags;
}
