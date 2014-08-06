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
require_once('data/sanitize.php');
require_once('data/Server.php');
require_once('data/TagCloud.php');
require_once('artist-menu.php');

$station = 'librefm://artist/' . $artist->name;
if (isset($this_user)) {
	$smarty->assign('station', $station);
} else {
	$radio_session = Server::getRadioSession($station);
	$smarty->assign('radio_session', $radio_session);
}

$smarty->assign('name', $artist->name);
$smarty->assign('id', $artist->id);
$smarty->assign('bio_summary', $artist->bio_summary);
$smarty->assign('bio_content', $artist->bio_content);
$smarty->assign('homepage', $artist->homepage);
$smarty->assign('streamable', $artist->isStreamable());
$smarty->assign('image', $artist->image_medium ? $artist->image_medium : $artist->image_small);
$smarty->assign('hashtag', $artist->hashtag);
$smarty->assign('flattr_uid', $artist->flattr_uid);
$smarty->assign('url', $artist->getURL());
$smarty->assign('similarArtists', $artist->getSimilar());

$aArtistAlbums = $artist->getAlbums();
if ($aArtistAlbums) {
	$smarty->assign('albums', $aArtistAlbums);
}

try {
	$tagCloud = TagCloud::generateTagCloud('tags', 'tag', 10, 'artist', $artist->name);
	$smarty->assign('tagcloud', $tagCloud);
} catch (Exception $ex) {
	$tagCloud = array();
}

$submenu = artist_menu($artist, 'Overview');
$smarty->assign('submenu', $submenu);

$smarty->display('artist.tpl');
