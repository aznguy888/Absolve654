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
require_once('data/User.php');
require_once($install_path . '/temp-utils.php'); // this is extremely dodgy and shameful

$logged_in = false;

session_start();
if (isset($_COOKIE['session_id'])) {
	$err = 0;
	$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
	try {
		$row = $adodb->GetRow('SELECT userid FROM Scrobble_Sessions WHERE '
				. 'sessionid = ' . $adodb->qstr($_COOKIE['session_id'])
				. ' AND expires > ' . (int)(time()));
	} catch (Exception $e) {
		$err = 1;
	}
	if ($err || !$row) {
		// Session is invalid
		setcookie('session_id', '', time() - 3600);
		session_unset();
		session_destroy();
	} else {
		$logged_in = true;
		$username = uniqueid_to_username($row['userid']);
		try {
			$this_user = new User($username);
		} catch (Exception $e) {
			$err = 1;
		}
	}

header("Cache-Control:no-cache");
header("Pragma: no-cache");
header("Expires = -1");
        
} else {
$seconds_to_cache = 3600;
$ts = gmdate("D, d M Y H:i:s", time() + $seconds_to_cache) . " GMT";
header("Expires: $ts");
header("Pragma: no-cache");
header("Cache-Control: max-age=$seconds_to_cache");
setcookie('lang', '', time() - 3600);
}
