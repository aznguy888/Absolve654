<?php

/* GNU FM -- a free network service for sharing your music listening habits

   Copyright (C) 2009 Free Software Foundation, Inc

   This program is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

require_once('database.php');
require_once('templating.php');
require_once('data/User.php');
require_once('data/TagCloud.php');
require_once('data/Server.php');
require_once('utils/arc/ARC2.php');
require_once('utils/human-time.php');

if (!isset($_GET['user']) && $logged_in == false) {
	$smarty->assign('pageheading', 'Error!');
	$smarty->assign('details', 'User not set! You shouldn\'t be here!');
	$smarty->display('error.tpl');
	die();
}

try {
	$user = new User($_GET['user']);
} catch (Exception $e) {
	$smarty->assign('pageheading', $error);
	$smarty->assign('details', 'Shall I call in a missing persons report?');
	$smarty->display('error.tpl');
	die();
}

if (!$user->journal_rss) {
	$smarty->assign('pageheading', 'Error!');
	$smarty->assign('details', 'You need an RSS feed set up for your account.');
	$smarty->display('error.tpl');
	die();
}

# We have to implement HTTP caching here!
$parser = ARC2::getRDFParser();
$parser->parse($user->journal_rss);

$index = $parser->getSimpleIndex();
krsort($index); // Newest last.
$items = array();
foreach ($index as $subject => $data) {
	if (in_array('http://purl.org/rss/1.0/item', $data['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'])) {
		$ts = strtotime($data['http://purl.org/dc/elements/1.1/date'][0]);
		$items[] = array(
			'subject_uri' => $subject,
			'title'       => $data['http://purl.org/rss/1.0/title'][0],
			'link'        => $data['http://purl.org/rss/1.0/link'][0],
			'date_iso'    => $data['http://purl.org/dc/elements/1.1/date'][0],
			'date_unix'   => $ts,
			'date_human'  => human_timestamp($ts)
			);
	}
}

try {
	$aUserTagCloud = TagCloud::GenerateTagCloud(TagCloud::scrobblesTable('user'), 'artist', 40, $user->uniqueid);
	$smarty->assign('user_tagcloud', $aUserTagCloud);
} catch (Exception $e) {}
$smarty->assign('isme', ($this_user->name == $user->name));
$smarty->assign('me', $user);
$smarty->assign('geo', Server::getLocationDetails($user->location_uri));
$smarty->assign('profile', true);
$smarty->assign('items', $items);
$smarty->assign('extra_head_links', array(
			array(
				'rel'   => 'alternate',
				'type'  => 'application/rss+xml',
				'title' => 'RSS 1.0 Feed (Journal)',
				'href'  => $user->journal_rss
				),
			array(
				'rel'   => 'meta',
				'type'  => 'application/rdf+xml',
				'title' => 'FOAF',
				'href'  => $base_url . '/rdf.php?fmt=xml&page=' . rawurlencode(str_replace($base_url, '', $user->getURL()))
				)
			));
$smarty->display('user-journal.tpl');
