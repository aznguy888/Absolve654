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
require_once('user-menu.php');
require_once('data/User.php');
require_once('data/TagCloud.php');

if ($logged_in == false) {
	displayError("Error", "Not logged in. You shouldn't be here.");
}

if (isset($_POST['remote_gnufm_url'])) {
	// redirect to a foreign GNU FM service
	$remote_url = $_POST['remote_gnufm_url'];
	$callback_url = $base_url . '/user-connections.php?webservice_url=' . $remote_url . '/2.0/';
	$url = $remote_url . '/api/auth/?api_key=' . $gnufm_key . '&cb=' . rawurlencode($callback_url);
	header('Location: ' . $url);
}

if (isset($_GET['token']) && isset($_GET['webservice_url'])) {
	// Handle authentication callback from a foreign service
	$token = $_GET['token'];
	$webservice_url = $_GET['webservice_url'];
	$sig = md5('api_key' . $lastfm_key . 'methodauth.getSession' . 'token' . $token . $lastfm_secret);
	$xmlresponse = simplexml_load_file($webservice_url . '?method=auth.getSession&token=' . $token . '&api_key=' . $lastfm_key . '&api_sig=' . $sig);
	if ($xmlresponse) {
		foreach ($xmlresponse->children() as $child => $value) {
			if ($child == 'session') {
				foreach ($value->children() as $child2 => $value2) {
					if ($child2 == 'name') {
						$remote_username = $value2;
					} else if ($child2 == 'key') {
						$remote_key = $value2;
					}
				}
			}
		}
	} elseif (!$xmlresponse || (!isset($remote_username) || !isset($remote_key))) {
		displayError("Error", "Sorry, we weren't able to authenticate your account.");
	}

	// Delete any old connection to this service
	$adodb->Execute('DELETE FROM Service_Connections WHERE '
		. 'userid = ' . $this_user->uniqueid . ' AND '
		. 'webservice_url = ' . $adodb->qstr($webservice_url));

	// Create our new connection
	$adodb->Execute('INSERT INTO Service_Connections VALUES('
		. $this_user->uniqueid . ', '
		. $adodb->qstr($webservice_url) . ', '
		. $adodb->qstr($remote_key) . ', '
		. $adodb->qstr($remote_username) . ')');

	// Flush cache so this change takes effect immediately
	$adodb->CacheFlush('SELECT * FROM Service_Connections WHERE userid = ' . $this_user->uniqueid . ' AND forward = 1');

	$smarty->assign('connection_added', true);
}

if (isset($_GET['forward']) && isset($_GET['service'])) {
	// Update the user's forwarding preferences
	$adodb->Execute('UPDATE Service_Connections SET forward = ' . (int) ($_GET['forward'])
		. ' WHERE userid = ' . $this_user->uniqueid
		. ' AND webservice_url = ' . $adodb->qstr($_GET['service']));

	// Flush cache so this change takes effect immediately
	$adodb->CacheFlush('SELECT * FROM Service_Connections WHERE userid = ' . $this_user->uniqueid . ' AND forward = 1');
}

if (isset($lastfm_key)) {
	$smarty->assign('lastfm_key', $lastfm_key);
}
if (isset($gnufm_key)) {
	$smarty->assign('gnufm_key', $gnufm_key);
}

$smarty->assign('connections', $this_user->getConnections());

$submenu = user_menu($this_user, 'Edit');
$smarty->assign('submenu', $submenu);

$smarty->assign('me', $this_user);
$smarty->display('user-connections.tpl');
