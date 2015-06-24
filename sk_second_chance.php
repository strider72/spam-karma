<?php
/**********************************************************************************************
 Spam Karma (c) 2015 - http://github.com/strider72/spam-karma

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

************************************************************************************************/

global $sk_log;
header('Expires: Mon, 26 Aug 1980 09:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo '<html><head></head><body>';

include_once(dirname(__FILE__) . '/sk_core_class.php');

$comment_ID = (int) @$_REQUEST['c_id'];
$author_email = @$_REQUEST['c_author'];

//DEBUG:
$sk_log->live_output = false;

$sk_log->log_msg(__('Second Chance. Comment ID:', 'spam-karma') . $comment_ID, 4, $comment_ID, '2nd_chance');
$sk_core = new SK_Core(0, true, false);

if ($sk_core->load_comment($comment_ID))
{
	//echo '<pre>'; 	print_r($sk_core->cur_comment);
	if ($sk_core->cur_comment->author_email != $author_email)
		die(__('Email not matching comment ID', 'spam-karma'));
		
	$sk_core->load_plugin_files();	
	$sk_core->second_chance();
}
else
{
	die(__('Invalid comment', 'spam-karma'));
}
?>
</body></html>
