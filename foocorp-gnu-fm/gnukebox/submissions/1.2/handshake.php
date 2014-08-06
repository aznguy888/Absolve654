<?php
/* GNUkebox -- a free software server for recording your listening habits

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

// Implements the submissions handshake protocol as detailed at: http://www.last.fm/api/submissions

require_once('auth-utils.php');
require_once('config.php');
require_once('temp-utils.php');

$supported_protocols = array('1.2', '1.2.1');

if (!isset($_REQUEST['p']) || !isset($_REQUEST['u']) || !isset($_REQUEST['t']) || !isset($_REQUEST['a']) || !isset($_REQUEST['c'])) {
	die("BADAUTH\n");
}

$protocol = $_REQUEST['p'];
$username = $_REQUEST['u'];
$timestamp = $_REQUEST['t'];
$auth_token = $_REQUEST['a'];
$client = $_REQUEST['c'];

if ($client == 'import') {
	die("FAILED Import scripts are broken\n"); // this should be removed or changed to check the version once import.php is fixed
}

if (!in_array($protocol, $supported_protocols)) {
	die("FAILED Unsupported protocol version\n");
}

if (abs($timestamp - time()) > 300) {
	die("BADTIME\n"); // let's try a 5-minute tolerance
}

if (isset($_REQUEST['api_key']) && isset($_REQUEST['sk'])) {
	$authed = check_web_auth($username, $auth_token, $timestamp, $_REQUEST['api_key'], $_REQUEST['sk']);
} else {
	$authed = check_standard_auth($username, $auth_token, $timestamp);
}

if (!$authed) {
	die("BADAUTH\n");
}

$uniqueid = username_to_uniqueid($username);
$session_id = md5($auth_token . time());
$sql = 'INSERT INTO Scrobble_Sessions(userid, sessionid, client, expires) VALUES ('
	. $uniqueid . ','
	. $adodb->qstr($session_id) . ','
	. $adodb->qstr($client) . ','
	. (time() + 86400) . ')';

try {
	$res = $adodb->Execute($sql);
} catch (Exception $e) {
	$msg = $e->getMessage();
	reportError($msg, $sql);
	die('FAILED ' . $msg . "\n");
}

echo "OK\n";
echo $session_id . "\n";
echo $submissions_server . "/nowplaying/1.2/\n";
echo $submissions_server . "/submissions/1.2/\n";
