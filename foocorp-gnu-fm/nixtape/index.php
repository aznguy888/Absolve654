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

if (isset($_REQUEST['hs']) && isset($_REQUEST['p'])) {
	if (substr($_REQUEST['p'], 0, 3) == '1.2') {
		require_once('1.x/submissions/1.2/handshake.php');
	} else if (substr($_REQUEST['p'], 0, 3) == '1.1') {
		require_once('1.x/submissions/1.1/handshake.php');
	}
} else {
	//If we're not handshaking we display the nixtape start page
	require_once('templating.php');
	require_once('data/sanitize.php');
	require_once('data/Server.php');
	require_once('data/TagCloud.php');
	try {
		$aTagCloud = TagCloud::GenerateTagCloud('loved', 'artist');
		$smarty->assign('tagcloud', $aTagCloud);
	} catch(Exception $e) {
		// Installation doesn't have any loved tracks yet
	}

	$smarty->assign('headerfile', 'welcome-header.tpl');
	$smarty->assign('welcome', true);
	$smarty->display('welcome.tpl');
}
