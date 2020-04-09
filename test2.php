<?php
require_once('simplepie-1.5/autoloader.php');

// We'll process this feed with all of the default options.
$pie = new SimplePie();

$feed = simplexml_load_file('feed.rss');
$channel = $feed->channel;
$channel_itunes = $channel->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
$summary = $channel_itunes->summary;
$subtitle = $channel_itunes->subtitle;
$category = $channel_itunes->category;
$owner = $channel_itunes->owner->name;

//$pie is a SimplePie object
$pie->init();
$pie->set_feed_url('./feed.rss');
$iTunesCategories=$pie->get_channel_tags(SIMPLEPIE_NAMESPACE_ITUNES,'category');
if ($iTunesCategories) {
  foreach ($iTunesCategories as $iTunesCategory) {
    $category=$iTunesCategory['attribs']['']['text'];
    $subcat=$iTunesCategory['child']["http://www.itunes.com/dtds/podcast-1.0.dtd"]['category'][0]['attribs']['']['text'];
    if ($subcat) {
      $category.=":$subcat";
    }
    //do something with $category
  }
}

