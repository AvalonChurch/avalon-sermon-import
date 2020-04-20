<?php
require_once('assets/getid3/getid3.php');
require_once('assets/getid3/write.php');

function get_bible_info($text, $passages = array(), $books = array())
{
	require('books.php');
	$possible_refs = array();
	foreach($BIBLE_BOOKS as $abr=>$name) {
		$possible_refs[] = strtolower($abr);
		$lower_name = strtolower($name);
		if (! in_array($lower_name, $possible_refs)) {
			$possible_refs[] = $lower_name;
		}
	}
	$pattern = '/(' . implode('|', $possible_refs) . ')\.* (\d+[\d:-]*)/i';
	preg_match_all($pattern, $text, $matches);
	if ($matches) {
		echo "TEXT: $text\n";
		print_r($matches);
		foreach ($matches[1] as $idx => $book) {
			if (isset($BIBLE_BOOKS[strtolower($book)]))
				$book = $BIBLE_BOOKS[strtolower($book)];
			$pass1 = $matches[0][$idx];
			$pass2 = $book . " " . $matches[2][$idx];
			print_r(array($pass1, $pass2));
			if (
				!preg_grep("/" . preg_quote($pass1) . "/i", $passages) &&
				!preg_grep("/" . preg_quote($pass2) . "/i", $passages)
			) {
				echo "ADDING to passagse: $pass2\n";
				$passages[] = $pass2;
			}
			if (!preg_grep("/" . preg_quote($book) . "/i", $books)) {
				echo "ADDING to books: $book\n";
				$books[] = $book;
			}
		}
	}
	print_r(array('passages', $passages));
	return array($passages, $books);
}

function update_sermons()
{
	global $wpdb;

	$posts = query_posts(array('post_type' => 'wpfc_sermon', 'posts_per_page' => -1, 'orderby' => 'post_date', 'order' => 'DESC'));
	foreach ($posts as $post) {
		print "\n\n============\nPROCESSING: {$post->post_title} ({$post->ID})\n";

		$meta = get_post_meta($post->ID);
		$sermon_notes = null;
		if (isset($meta['sermon_notes_id']) && $meta['sermon_notes_id'][0]) {
			$sermon_notes = get_post($meta['sermon_notes_id'][0]);
			print_r($sermon_notes);
		}
		$sermon_bulletin = null;
		if (isset($meta['sermon_bulletin_id']) && $meta['sermon_bulletin_id'][0]) {
			$sermon_bulletin = get_post($meta['sermon_bulletin_id'][0]);
			print_r($sermon_bulletin);
		}
		$image_ID = $meta['_thumbnail_id'][0];
		$meta_sermon_audio = $meta['sermon_audio'][0];
		$sql_prep = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s AND post_type = 'attachment' AND post_status = 'inherit'", $meta_sermon_audio);
		$audio_ID = $wpdb->get_var($sql_prep);
		if (!$audio_ID) {
			echo "$sql_prep\n";
			echo "NO AUDIO FOR {$post->post_title} ({$post->ID})!!!\n";
			continue;
		}
		$series = wp_get_post_terms($post->ID, 'wpfc_sermon_series');
		$preachers = wp_get_post_terms($post->ID, 'wpfc_preacher');
		$bible_books = wp_get_post_terms($post->ID, 'wpfc_bible_book');
		$image = get_post($image_ID);
		$image_meta = wp_get_attachment_metadata($image->ID);
		$audio = get_post($audio_ID);
		$audio_meta = wp_get_attachment_metadata($audio->ID);

		$time = $meta['sermon_date'][0];
		$date = array(
			'display_date' => date('F j, Y', $time),
			'long_date'    => date('l, F j, Y', $time),
			'datetime'	   => date('Y-m-d H:i:s', $time),
			'datetimegm'	   => gmdate('Y-m-d H:i:s', $time),
			'file_date'    => date('Y-m-d', $time),
			'unix_date'    => date('U', $time),
			'meridiem'     => date('a', $time),
			'year_month'   => date('Y/m', $time),
			'year'         => date('Y', $time)
		);

		$title = $post->post_title;
		$excerpt = $post->post_excerpt;
		$desc = trim($meta['sermon_description'][0]);
		$video = trim($meta['sermon_video_link'][0]);
		$track = (isset($audio_meta['track_number'])?$audio_meta['track_number']:'01');
		if (! trim($track))
			$track = '01';

		$preacher_names = implode(', ', array_map(function($obj) { return $obj->name; }, $preachers));
		$series_names = implode(', ', array_map(function($obj) { return $obj->name; }, $series));
		$preacher_slug = $preachers[0]->slug;

		preg_match('/(?:PART|WEEK) (\d+)/i', $title, $matches);
		if ($matches) {
			$track = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
		}

		$desc_lines = array();
		if ($excerpt && strpos($desc, $excerpt) === false) {
			$desc_excerpt = $excerpt;
			if (strpos($excerpt, '<p') === false)
				$desc_excerpt = "<p>$excerpt</p>\n<br/>\n";
			$desc_lines[] = $desc_excerpt; 
		}
		$desc_lines[] = "<b>Series:</b> {$series_names}";
		$desc_lines[] = "<b>Speaker:</b> {$preacher_names}";

		if (strpos($desc, '<div class="notes">') !== false) {
			$notes = explode('<div class="notes">', $desc)[1];
			$notes = str_replace('<b>Notes:</b>', '', $notes);
			$notes = preg_replace('/^([\r\n\s]*<br\s*\/>[\r\n\s]*)+/i', '', $notes);
			$notes = str_replace('</div>', '', $notes);
			$notes = trim($notes);
		} else {
			$notes = $desc;
		}

		if (! $notes) {
			$notes = trim($excerpt);
		}

		$bible_passage_items = array();
		$bible_book_items = array();
		if ($meta['bible_passage'] && trim($meta['bible_passage'][0])) {
			$bible_passage_items = explode(', ', $meta['bible_passage'][0]);
		}
		if ($bible_books) {
			foreach ($bible_books as $book) {
				$bible_book_items[] = $book->name;
			}
		}
		if ($notes) {
			$bible_info = get_bible_info($notes, $bible_passage_items, $bible_book_items);
			$bible_passage_items = $bible_info[0];
			$bible_book_items = $bible_info[1];
		}
		if ($excerpt) {
			$bible_info = get_bible_info($excerpt, $bible_passage_items, $bible_book_items);
			$bible_passage_items = $bible_info[0];
			$bible_book_items = $bible_info[1];
		}
		if ($title) {
			$bible_info = get_bible_info($title, $bible_passage_items, $bible_book_items);
			$bible_passage_items = $bible_info[0];
			$bible_book_items = $bible_info[1];
		}
		$bible_passage = implode(', ', $bible_passage_items);

		if ($bible_passage) {
			$desc_lines[] = '<b>Scripture:</b> ' . $bible_passage;
		}
		if ($video) {
			$desc_lines[] = '<b>Video:</b> <a href="' . $video . '">' . $video . '</a>';
		}
		$permalink = get_permalink($post);
		print_r(array("PERMALINK", $permalink));
		$desc_lines[] = '<b>Sermon page:</b> <a href="' . $permalink . '">' . $permalink . '</a>';
		if ($sermon_notes) {
			$desc_lines[] = '<b>Sermon Notes:</b> <a href="' . $sermon_notes->guid . '">' . basename($sermon_notes->guid) . '</a>';
		}
		if ($sermon_bulletin) {
			$desc_lines[] = '<b>Discussion Questions:</b> <a href="' . $sermon_bulletin->guid . '">' . basename($sermon_bulletin->guid) . '</a>';
		}

		if ($notes) {
			// $notes = preg_replace('/Week (\d+)/i', 'PART $1', $notes);
			foreach ($date as $format) {
				$notes = preg_replace('/(^|\n)(Sermon |Message )*(Date[: ]*)*' . preg_quote($format, '/') . '\s*(\n|$)/i', '$1', $notes);
			}
			$notes = preg_replace('/(^|\n)(Sermon |Message )*(Title[: ]*)*' . preg_quote($title, '/') . '\s*(\n|$)/i', '$1', $notes);
			$notes = preg_replace('/(^|\n)(Sermon |Message )*(Title[: ]*)*' . preg_quote($series_names, '/') . '\s*(\n|$)/i', '$1', $notes);
			$notes = preg_replace('/(^|\n)(Speaker|Preacher|Pastor)*[: ]*' . preg_quote($preacher_names, '/') . '\s*(\n|$)/i', '$1', $notes);
			$notes = preg_replace('/(^|\n)(Sermon )*Date:\s*[\d\/-]*\s*(\n|$)/i', '$1', $notes);
			if ($bible_passage)
				$notes = preg_replace('/(^|\n)(Scripture[: ]*)*' . preg_quote($bible_passage) . '\s*(\n|$)/i', '$1', $notes);
		}

		$meta_sermon_description = implode(", <br/>\n", $desc_lines);
		if ($notes) {
			$meta_sermon_description .= ", <br/>";
		}
		$meta_sermon_description .= "\n" . '<div class="notes">';
		if ($notes) {
			$meta_sermon_description .= '<b>Notes:</b> <br/>' . "\n" . $notes;
		}
		$meta_sermon_description .= "\n</div>";

		if (!trim($excerpt)) {
			if ($notes) {
				$excerpt = $notes;
			} else {
				$excerpt = ' ';
			}
		}

		$old_audio_guid = $audio->guid;
		$old_audio = str_ireplace(wp_upload_dir()['baseurl'] . '/', '', $old_audio_guid);
		$new_audio = 'sermons/' . $date['year_month'] . '/' . $date['file_date'] . '_' . $post->post_name . '_' . $preacher_slug . '_ac.mp3';
		$audio_name = pathinfo(basename($new_audio), PATHINFO_FILENAME);
		$old_audio_file_path = wp_upload_dir()['basedir'] . '/' . $old_audio;
		$audio_file_path = wp_upload_dir()['basedir'] . '/' . $new_audio;
		$audio_guid = wp_upload_dir()['baseurl'] . '/' . $new_audio;
		$meta_sermon_audio = $audio_guid;

		$tag_data = array(
			'title' => array($title),
			'artist' => array($preacher_names),
			'album' => array($series_names),
			'year' => array($date['year']),
			'genre' => array('Podcast'),
			'comment' => array($meta_sermon_description),
			'track' => array($track),
			'popularimeter' => array('email' => 'podcast@avalonchurch.org', 'rating' => 128, 'data' => 0),
			'unique_file_identifier' => array('ownerid' => 'podcast@avalonchurch.org', 'data' => md5(time())),
		);
		if ($image->guid) {
			$image_file_path = wp_upload_dir()['basedir'] . str_ireplace(wp_upload_dir()['baseurl'], '', $image->guid);
			$tag_data['attached_picture'] = array(
				array(
					'encodingid'     => 0, // ISO-8859-1; 3=UTF8 but only allowed in ID3v2.4
					'picturetypeid'  => 3, // see https://github.com/JamesHeinrich/getID3/blob/master/getid3/module.tag.id3v2.php#L3130-L3150
					'mime'           => 'image/jpeg',
					'description'    => 'cover art',
					'data'           => file_get_contents($image_file_path),
				),
			);
		}

		if ($old_audio_file_path != $audio_file_path) {
			$dir = dirname($audio_file_path);
			if (!file_exists($dir)) {
				mkdir($dir, 0777, true);
			}
			$ret = rename($old_audio_file_path, $audio_file_path);
			if (!$ret) {
				print("Unable to move file from $old_audio_file_path to $audio_file_path\n");
				print_r(array('dir' => $dir, '$old_audio_file_path' => $old_audio_file_path, 'audio_file_path' => $audio_file_path, 'ret' => $ret));
				die;
			}
		}
		$tagwriter = new getid3_writetags;
		$tagwriter->filename = $audio_file_path;
		$tagwriter->tagformats = array('id3v1', 'id3v2.3');
		$tagwriter->overwrite_tags = true;  // if true will erase existing tag data and write only passed data; if false will merge passed data with existing tag data (experimental)
		$tagwriter->remove_other_tags = false;
		$tagwriter->tag_encoding = 'UTF-8';
		$tagwriter->tag_data = $tag_data;
		// UNCOMENT IN REAL
		if ($tagwriter->WriteTags()) {
			echo "Successfully wrote tags.\n";
			if (!empty($tagwriter->warnings)) {
				echo "There were some warnings:\n" . implode("\n", $tagwriter->warnings);
			}
		} else {
			die("Failed to write tags! Errors:\n" . implode("\n", $tagwriter->errors));
		}
		$audio_filesize = filesize($audio_file_path);
		// REMOVE IN REAL
		// $audio_filesize = filesize($old_audio_file_path);

		$post_content_items = array();
		if ($bible_passage) {
			$post_content_items[] = 'Bible Text: ' . $bible_passage;
		}
		$post_content_items[] = 'Speaker: ' . $preacher_names;
		$post_content_items[] = 'Series: ' . $series_names;
		$post_content_items[] = 'Date: ' . $date['file_date'];
		if ($notes)
			$post_content_items[] = trim(strip_tags($notes));
		$post_content = implode(' | ', $post_content_items);

		$post_modified = date('Y-m-d H:i:s');
		$post_modified_gmt = gmdate('Y-m-d H:i:s');

		$ret = array();

		$post->post_modified = $post_modified;
		$post->post_modified_gmt = $post_modified_gmt;
		$post->post_excerpt = $excerpt;
		$post->post_content = $post_content;
		print_r($post);
		$ret[] = wp_update_post($post);

		print_r(array(
			'sermon_description'=>$meta_sermon_description, 
			'bible_passage'=>$bible_passage, 
			'sermon_audio'=>$meta_sermon_audio
		));
		$ret[] = update_post_meta($post->ID, 'sermon_description', $meta_sermon_description, $meta['sermon_description'][0]);
		$ret[] = update_post_meta($post->ID, 'bible_passage', $bible_passage, $meta['bible_passage'][0]);
		$ret[] = update_post_meta($post->ID, 'sermon_audio', $meta_sermon_audio, $meta['sermon_audio'][0]);
		$ret[] = update_post_meta($post->ID, '_wpfc_sermon_size', $audio_filesize, (isset($meta['_wpfc_sermon_size'])?(is_array($meta['_wpfc_sermon-size'])?$meta['_wpfc_sermon_size'][0]:$meta['_wpfc_sermon_size']):''));

		print_r($bible_book_items);
		$ret[] = wp_set_post_terms($post->ID, $bible_book_items, 'wpfc_bible_book', true);

		$audio->guid = $audio_guid;
		$audio->post_date = $date['datetime'];
		$audio->post_date_gm = $date['datetimegm'];
		$audio->post_modified = $post_modified;
		$audio->post_modified_gmt = $post_modified_gmt;
		$audio->post_parent = $post->ID;
		$audio->post_content = $post_content;
		$audio->post_title = $title;
		$audio->post_excerpt = $excerpt;
		print_r($audio);
		$ret[] = wp_update_post($audio);
		$ret[] = $wpdb->update($wpdb->posts, ['guid' => $audio->guid], ['ID' => $audio->ID]);

		print_r(array(
		    'title'=>$title,
		    'artist'=>$preacher_names,
		    'album'=>$series_names,
		    'genre'=>'Podcast',
		    'filesize'=>$audio_filesize,
		    'track_number'=>$track,
		));
		$ret[] = update_post_meta($audio->ID, '_wp_attached_file', $new_audio, (isset($audio_meta['_wp_attached_file'])?$audio_meta['_wp_attached_file']:''));
		$ret[] = update_post_meta($audio->ID, 'title', $title, (isset($audio_meta['title'])?$audio_meta['title']:''));
		$ret[] = update_post_meta($audio->ID, 'artist', $preacher_names, (isset($audio_meta['artist'])?$audio_meta['artist']:''));
		$ret[] = update_post_meta($audio->ID, 'album', $series_names, (isset($audio_meta['album'])?$audio_meta['album']:''));
		$ret[] = update_post_meta($audio->ID, 'genre', 'Podcast', (isset($audio_meta['genre'])?$audio_meta['genre']:''));
		$ret[] = update_post_meta($audio->ID, 'filesize', $audio_filesize, (isset($audio_meta['filesize'])?$audio_meta['filesize']:''));
		$ret[] = update_post_meta($audio->ID, 'track_number', $track, (isset($audio_meta['track_number'])?$audio_meta['track_number']:''));

		$audio_attachment_data = wp_generate_attachment_metadata($audio->ID, $audio_file_path);
		print_r($audio_attachment_data);
		$ret[] = wp_update_attachment_metadata($audio->ID, $audio_attachment_data);
		print_r($ret);

		print_r(array($old_audio_file_path, $old_audio, $new_audio));
	}
}

update_sermons();
