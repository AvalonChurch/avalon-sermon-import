<?php
$feed_url = 'http://avalonchurch.podomatic.com/rss2.xml';
$feed = simplexml_load_file($feed_url, null, LIBXML_NOCDATA);
$channel = $feed->channel;
echo "TITLE: {$channel->title}\n";
echo "DESC: {$channel->description}\n";
foreach ($channel->item as $item) {
    echo "================\n";
    echo "LINK: {$item->link}\n";
    echo "TITLE: {$item->title}\n";
    echo "PUBDATE: {$item->pubDate}\n";
    echo "DESC: {$item->description}\n";
    echo "IMAGE: ".$ns_itunes->image->attributes()."\n";
    echo "Categories: ".implode(', ', (array) $item->category)."\n";
    echo "================\n";
}
