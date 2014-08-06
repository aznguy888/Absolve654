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
require_once('data/Album.php');
require_once('album-menu.php');

$smarty->assign('name', $album->name);
$smarty->assign('id', $album->id);
$aAlbumTracks = $album->getTracks();
if ($aAlbumTracks) {
	$smarty->assign('tracks', $aAlbumTracks);
}

$smarty->assign('extra_head_links', array(
		array(
			'rel'   => 'meta',
			'type'  => 'application/rdf+xml',
			'title' => 'Album Metadata',
			'href'  => $base_url . '/rdf.php?fmt=xml&page=' . rawurlencode(htmlentities(str_replace($base_url, '', $album->getURL())))
			)
	));

$smarty->display('album.tpl');
