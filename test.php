<?php

$feeds = simplexml_load_file('feed.rss');

$site = $feeds->channel->title;
$sitelink = $feeds->channel->link;

echo $site."\n";

foreach ($feeds->channel as $channel) {
   $ns_itunes = $channel->children('http://www.itunes.com/dtds/podcast-1.0.dtd');
foreach ($channel->item as $item) {
   print_r($item);
   $title = $item->title;
   $link = $item->link;
   $description = $item->description;
   $postDate = $item->pubDate;
   $pubDate = date('D, d M Y',strtotime($postDate));
   $category_text = $item->category->attributes()->text;

   echo "--------\n";
   echo "Title: $title\n";
   echo "Link: $link\n";
   echo "Description: $description\n";
   echo "Post Date: $postDate\n";
   echo "Pub Date: $pubDate\n";
   echo "Category Text: $category_text\n";
   echo "IMAGE: ".$ns_itunes->image->attributes()."\n";
   echo "---------\n";
}
}

