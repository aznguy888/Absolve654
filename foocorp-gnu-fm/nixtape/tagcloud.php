<?php

require_once('database.php');
require_once('templating.php');
require_once('data/sanitize.php');
require_once('data/Server.php');
require_once('data/TagCloud.php');

$n = $_GET['n'];

$n = (int)$n;

if ($n < 1) {
	$n = 1000000;
}

try {
	$aTagCloud = TagCloud::GenerateTagCloud('Free_Scrobbles', 'artist', $n);
	$smarty->assign('tagcloud', $aTagCloud);
} catch (Exception $e) {
	displayError("Error", $errors);
}

$smarty->display('tagcloud.tpl');
