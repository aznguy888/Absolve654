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
require_once('user-menu.php');
require_once('templating.php');
require_once('data/User.php');
require_once('data/RemoteUser.php');
require_once('data/TagCloud.php');
require_once('data/Server.php');

if (!isset($_GET['user']) && $logged_in == false) {
	displayError("Error", "User not set. You shouldn't be here.");
}

try {
	if(strstr($_GET['user'], '@')) {
		$user = new RemoteUser($_GET['user']);
		$remote = true;
	} else {
		$user = new User($_GET['user']);
		$remote = false;
	}
} catch (Exception $e) {
	$user = null;
}

if (isset($user->name)) {
	if (isset($_GET['type'])) {
		$type = $_GET['type'];
	} elseif($remote) {
		$type = 'recommended';
	} else {
		$type = 'loved';
	}
	$smarty->assign('me', $user);
	$smarty->assign('pagetitle', $user->name . '\'s Radio ' . ucfirst($type) . ' Station');

	$station = 'librefm://user/' . $user->name . '/' . $type;
	if (isset($this_user)) {
		$smarty->assign('station', $station);
	} else {
		$radio_session = Server::getRadioSession($station);
		$smarty->assign('radio_session', $radio_session);
	}

	$submenu = user_menu($user, 'Radio Stations');
	$smarty->assign('submenu', $submenu);
	$smarty->assign('type', $type);
	$smarty->assign('remote', $remote);
	$smarty->display('user-station.tpl');
} else {
	displayError("User not found", "User not found, shall I call in a missing persons report?");
}
