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

$errors = array();

if ($_POST['submit']) {
	if (!empty($_POST['id'])) {
		# Need better URI validation, but this will do for now. I think
		# PEAR has a suitable module to help out here.
		if (!preg_match('/^[a-z0-9\+\.\-]+\:/i', $_POST['id'])) {
			$errors[] = 'WebID must be a URI.';
		}
		if (preg_match('/\s/', $_POST['id'])) {
			$errors[] = 'WebID must be a URI. Valid URIs cannot contain whitespace.';
		}
	}

	if (!empty($_POST['delete_account'])) {
		header('Location: ' . $base_url . '/delete-profile.php');
		die();
	}

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

	if (!empty($_POST['laconica_profile'])) {
		# Need better URI validation, but this will do for now. I think
		# PEAR has a suitable module to help out here.
		if (!preg_match('/^[a-z0-9\+\.\-]+\:/i', $_POST['laconica_profile'])) {
			$errors[] = 'Laconica profile must be a URI.';
		}
		if (preg_match('/\s/', $_POST['laconica_profile'])) {
			$errors[] = 'Laconica profile must be a URI. Valid URIs cannot contain whitespace.';
		}
	}

	if (!empty($_POST['journal_rss'])) {
		# Need better URI validation, but this will do for now. I think
		# PEAR has a suitable module to help out here.
		if (!preg_match('/^[a-z0-9\+\.\-]+\:/i', $_POST['journal_rss'])) {
			$errors[] = 'Journal RSS must be a URI.';
		}
		if (preg_match('/\s/', $_POST['journal_rss'])) {
			$errors[] = 'Journal RSS must be a URI. Valid URIs cannot contain whitespace.';
		}
	}

	if (!empty($_POST['password_1'])) {
		if ($_POST['password_1'] != $_POST['password_2']) {
			$errors[] = 'Passwords do not match.';
		}
	}

	if (!empty($_POST['location_uri'])) {
		# Currently only allow geonames URIs, but there's no reason we can't accept
		# others at some point in the future. (e.g. dbpedia)
		if (!preg_match('/^http:\/\/sws.geonames.org\/[0-9]+\/$/', $_POST['location_uri'])) {
			$errors[] = 'This should be a geonames.org semantic web service URI.';
		}
	}

	if (!isset($errors[0])) {
		# Currently we don't allow them to change e-mail as we probably should
		# have some kind of confirmation login to do so.
		$this_user->id               = $_POST['id'];
		$this_user->fullname         = $_POST['fullname'];
		$this_user->homepage         = $_POST['homepage'];
		$this_user->bio              = $_POST['bio'];
		$this_user->location         = $_POST['location'];
		$this_user->location_uri     = $_POST['location_uri'];
		$this_user->avatar_uri       = $_POST['avatar_uri'];
		$this_user->laconica_profile = $_POST['laconica_profile'];
		$this_user->journal_rss      = $_POST['journal_rss'];
		$this_user->anticommercial   = $_POST['anticommercial'] == 'on' ? 1 : 0;
		$this_user->receive_emails   = $_POST['receive_emails'] == 'on' ? 1 : 0;

		if (!empty($_POST['password_1'])) {
			$this_user->password = md5($_POST['password_1']);
		}

		$this_user->save();

		header('Location: ' . $this_user->getURL());
		exit;
	}
}

if (isset($this_user->name)) {
	if (isset($errors[0])) {
		$smarty->assign('errors', $errors);
	}
	# Stuff which cannot be changed.
	$smarty->assign('acctid', $this_user->acctid);
	$smarty->assign('avatar', $this_user->getAvatar());
	$smarty->assign('user',   $this_user->name);

	# Stuff which cannot be changed *here*
	$smarty->assign('userlevel', $this_user->userlevel);

	# Stuff which cannot be changed *yet*
	$smarty->assign('email', $this_user->email);

	if ($_POST['submit']) {
		$smarty->assign('id',               $_POST['id']);
		$smarty->assign('fullname',         $_POST['fullname']);
		$smarty->assign('bio',              $_POST['bio']);
		$smarty->assign('homepage',         $_POST['homepage']);
		$smarty->assign('location',         $_POST['location']);
		$smarty->assign('location_uri',     $_POST['location_uri']);
		$smarty->assign('avatar_uri',       $_POST['avatar_uri']);
		$smarty->assign('laconica_profile', $_POST['laconica_profile']);
		$smarty->assign('journal_rss',      $_POST['journal_rss']);
		$smarty->assign('anticommercial',   $_POST['anticommercial'] == 'on' ? 1 : 0);
		$smarty->assign('receive_emails',   $_POST['receive_emails'] == 'on' ? 1 : 0);
	} else {
		$smarty->assign('id',               $this_user->webid_uri);
		$smarty->assign('fullname',         $this_user->fullname);
		$smarty->assign('bio',              $this_user->bio);
		$smarty->assign('homepage',         $this_user->homepage);
		$smarty->assign('location',         $this_user->location);
		$smarty->assign('location_uri',     $this_user->location_uri);
		$smarty->assign('avatar_uri',       $this_user->avatar_uri);
		$smarty->assign('laconica_profile', $this_user->laconica_profile);
		$smarty->assign('journal_rss',      $this_user->journal_rss);
		$smarty->assign('anticommercial',   $this_user->anticommercial);
		$smarty->assign('receive_emails',   $this_user->receive_emails);
	}

	# And display the page.
	$submenu = user_menu($this_user, 'Edit');
	$smarty->assign('submenu', $submenu);
	$smarty->assign('me', $this_user);

	$smarty->assign('errors', $errors);
	$smarty->display('user-edit.tpl');
} else {
	displayError("User not found", "User not found, shall I call in a missing persons report?");
}
