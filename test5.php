<?php
$feed_url = 'http://avalonchurch.podomatic.com/rss2.xml';
$doc = new DOMDocument();
$doc->load('feed');
$cnt=0;

$items = array();
foreach ($doc->getElementsByTagName('item') as $node) {
    $image = $node->getElementsByTagNameNS('http://www.itunes.com/dtds/podcast-1.0.dtd', 'image')->item(0);
    $href = null;
    if ($image) {
        $href = $image->getAttribute('href');
    }

    $title = $node->getElementsByTagName('title')->item(0)->nodeValue;
    $summary = $node->getElementsByTagName('summary')->item(0)->nodeValue;

    $speaker = null;
    $re = '/\bSpeaker:\s*([^\n]+)/i';
     if (preg_match($re, $summary, $match)) {
    	$speaker = $match[1];
    }

    $re = '/\bDate:\s*([^\n]+)/i';
    if (preg_match($re, $summary, $match)) {
       $date = $match[1];
    } else {
        $date = $node->getElementsByTagName('pubDate')->item(0)->nodeValue;
    }
    $date = date('Y-m-d', strtotime($date));

    $track = '01';
    $re = '/\b(?:Part|Week):*\s*(\d+)/i';
    if (preg_match($re, $title, $match)) {
       $track = str_pad('0', 2, $match[1]);
    }

    $title = str_ireplace(': Week', ' | PART', $title);
    $title = str_ireplace('Week', ' | PART', $title);
    $title = str_replace(': Part', ' | PART', $title);
    $title = str_replace('Part', ' | PART', $title);

$itemRSS = array ( 
'title' => $title,
'track' => $track,
'series' => $title,
'speaker' => $speaker,
'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
'date' => $date,
'audio' => $node->getElementsByTagName('enclosure')->item(0)->getAttribute('url'),
'img' => $href,
'desc' => $summary,
'blurb' => $summary,
);
$summary = preg_replace('/(^|\n)Avalon Church\s*($|\n)/i', "$1", $summary);
$summary = preg_replace('/(^|\n)Date: [\d\/]\s*($|\n)/i', "$1", $summary);
$summary = preg_replace('/(^|\n)Pastor Don Dodge\s*($|\n)/i', "$1", $summary);
$items[$date] = trim($summary);
}

print(json_encode(array_reverse($items), JSON_PRETTY_PRINT));
