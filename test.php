<?php

header('Content-Type: text/plain; charset=UTF-8');

define('RCMAIL_CHARSET', 'UTF-8');
define('DEFAULT_MAIL_CHARSET', 'ISO-8859-1');
define('RCMAIL_PREFER_HTML', false);

require_once('rc/rcube_charset.php');
require_once('rc/rcube_imap_generic.php');
require_once('rc/rcube_imap.php');
require_once('rc/rcube_message.php');

$imap = new rcube_imap();
$imap->connect('<HOST>', '<USER>', '<PASS>');

foreach($imap->list_headers() as $header)
{
	// parsed header
	//print_r($header);
	
	// parsed systemical msg
	//$msg = $imap->get_message($header->uid);
	
	// conains header information, too
	//var_dump($msg->subject);
	//print_r($msg->structure);
	
	// parsed & analyzed logical message
	$logical = new rcube_message($imap, $header->uid);
	
	echo "= ".$header->subject;
	echo "\n=== from ===\n";
	print_r($logical->sender);
	
	echo "\n=== to ===\n";
	print_r($logical->receiver);
	
	echo "\n=== text ===\n";
	echo $logical->first_text_part();
	
	echo "\n=== html ===\n";
	echo $logical->first_html_part();
	
	echo "\n=== attachments ===\n";
	print_r($logical->attachments);
	
	foreach ($logical->attachments as $idx => $attachment) {
		echo "\n=== attachment[$idx] ===\n";

		$data = $logical->get_part_content($attachment->mime_id);
		printf("read %u bytes of %s-data\n", strlen($data), $attachment->mimetype);
	}
	
	echo "\n\n\n\n==================================================================================================================================\n\n\n\n\n";
}

$imap->close();

?>