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

if (isset($_GET['tag'])) {
	$station = 'librefm://globaltags/' . $_GET['tag'];
} else if (isset($_GET['station'])) {
	$station = $_GET['station'];
}

if (isset($_GET['only_loved']) && $_GET['only_loved']) {
	$station .= '/loved';
}

if (isset($station)) {
	if (isset($this_user)) {
		$smarty->assign('station', $station);
	} else {
		$radio_session = Server::getRadioSession($station);
		$smarty->assign('radio_session', $radio_session);
	}
}
$smarty->assign('pageheading', _('Go ahead, listen all you want'));
$smarty->display('listen.tpl');
