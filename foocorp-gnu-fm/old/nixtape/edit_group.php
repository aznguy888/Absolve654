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
require_once('data/Group.php');
require_once('data/TagCloud.php');

if ($logged_in == false) {
	$smarty->assign('pageheading', 'Error!');
	$smarty->assign('details', 'Not logged in! You shouldn\'t be here!');
	$smarty->display('error.tpl');
	die();
}

if ($_REQUEST['group'] == 'new') {
	if ($_REQUEST['new']) {
		try {
			$result = Group::create(strtolower($_REQUEST['new']), $this_user);
		} catch (Exception $e) {
			$smarty->assign('pageheading', 'Error!');
			$smarty->assign('details', $e->getMessage());
			$smarty->display('error.tpl');
			die();
		}
		if ($result instanceof Group) {
			header('Location: ' . $base_url . '/edit_group.php?group=' . $_REQUEST['new']);
			exit();
		}
	} else {
		$smarty->assign('newform', true);
		try {
			$aTagCloud = TagCloud::GenerateTagCloud(TagCloud::scrobblesTable(), 'artist');
			$smarty->assign('tagcloud', $aTagCloud);
		} catch (Exception $e) {}
		$smarty->display('edit_group.tpl');
		exit();
	}
}

$group = new Group($_REQUEST['group']);

if ($group->owner->name != $this_user->name) {
	$smarty->assign('pageheading', 'Error!');
	$smarty->assign('details', 'You don\'t own this group!');
	$smarty->display('error.tpl');
	die();
}

$errors = array();

if ($_POST['submit']) {
	if (!empty($_POST['homepage'])) {
		# Need better URI validation, but this will do for now. I think
		# PEAR has a suitable module to help out here.
		if (!preg_match('/^[a-z0-9\+\.\-]+\:/i', $_POST['homepage'])) {
			$errors[] = 'Homepage must be a URI.';
		}
		if (preg_match('/\s/', $_POST['homepage'])) {
			$errors[] = 'Homepage must be a URI. Valid URIs cannot contain whitespace.';
		}
	}

	if (!empty($_POST['avatar_uri'])) {
		# Need better URI validation, but this will do for now. I think
		# PEAR has a suitable module to help out here.
		if (!preg_match('/^[a-z0-9\+\.\-]+\:/i', $_POST['avatar_uri'])) {
			$errors[] = 'Avatar must be a URI.';
		}
		if (preg_match('/\s/', $_POST['avatar_uri'])) {
			$errors[] = 'Avatar must be a URI. Valid URIs cannot contain whitespace.';
		}
	}

	if (!isset($errors[0])) {
		if ($_POST['owner'] != $group->owner->username) {
			try {
				$new_owner = new User($_POST['owner']);
			} catch (Exception $e) {
				$smarty->assign('pageheading', 'Error!');
				$smarty->assign('details', 'Cannot assign group ownership to someone who does not exist!');
				$smarty->display('error.tpl');
				die();
			}

			if (!$group->memberCheck($new_owner)) {
				$smarty->assign('pageheading', 'Error!');
				$smarty->assign('details', 'Cannot assign group ownership to someone who is not a member!');
				$smarty->display('error.tpl');
				die();
			} else {
				$group->owner = $new_owner;
			}
		}

		$group->fullname    = $_POST['fullname'];
		$group->homepage    = $_POST['homepage'];
		$group->bio         = $_POST['bio'];
		$group->avatar_uri  = $_POST['avatar_uri'];

		$group->save();

		header('Location: ' . $group->getURL());
		exit;
	}

	if (isset($errors[0])) {
		header('Content-Type: text/plain');
		//($errors);
		exit;
	}
}

if (isset($group->name)) {
	# Stuff which cannot be changed.
	$smarty->assign('group', $group->name);

	if ($_POST['submit']) {
		$smarty->assign('fullname',     $_POST['fullname']);
		$smarty->assign('bio',          $_POST['bio']);
		$smarty->assign('homepage',     $_POST['homepage']);
		$smarty->assign('avatar_uri',   $_POST['avatar_uri']);
	} else {
		$smarty->assign('fullname',     $group->fullname);
		$smarty->assign('bio',          $group->bio);
		$smarty->assign('homepage',     $group->homepage);
		$smarty->assign('avatar_uri',   $group->avatar_uri);
	}

	$smarty->assign('members', $group->getUsers());
	$smarty->assign('owner',   $group->owner);

	# And display the page.
	$smarty->assign('pageheading', $errors);
	$smarty->assign('newform', false);
	try {
		$aUserTagCloud = $group->tagCloudData();
		$smarty->assign('tagcloud', $aTagCloud);
	} catch (Exception $e) {}
	$smarty->display('edit_group.tpl');
} else {
	$smarty->assign('pageheading', 'Group not found');
	$smarty->assign('details', 'Shall I call in a missing peoples report? This shouldn\'t happen.');
	$smarty->display('error.tpl');
}
