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
require_once('utils/EmailAddressValidator.php');

global $adodb;
$errors = '';

function sendEmail($text, $email) {
	$subject = $site_name . ' Password Reset';
	return mail($email, $subject, $text);
}

if (isset($_GET['code'])) {
	$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
	$sql = 'SELECT * FROM Recovery_Request WHERE code=' . $adodb->qstr($_GET['code'])
		. ' AND expires > ' . $adodb->qstr(time());
	$row = $adodb->GetRow($sql);
	if (!$row) {
		displayError("Error", "Invalid reset token.");
	}

	$password = '';
	$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';

	for ($i = 0; $i < 8; $i++) {
		$password .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
	}

	$email = $row['email'];

	$sql = 'UPDATE Users SET password=' . $adodb->qstr(md5($password)) . ' WHERE email='
		. $adodb->qstr($email);

	$adodb->Execute($sql);

	$content = "Hi!\n\nYour password has been set to " . $password . "\n\n - The " . $site_name . " Team";
	sendEmail($content, $email);
	$sql = 'DELETE FROM Recovery_Request WHERE code=' . $adodb->qstr($email);
	$adodb->Execute($sql);
	$smarty->assign('changed', true);
} else if (isset($_POST['user']) || isset($_POST['email'])) {
	if (isset($_POST['email']) && !empty($_POST['email'])) {
		$field = 'email';
		$value = $_POST['email'];
	} else {
		$field = 'username';
		$value = $_POST['user'];
	}

	$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
	$err = 0;

	try {
		$row = $adodb->GetRow('SELECT * FROM Users WHERE lower(' . $field . ') = lower(' . $adodb->qstr($value) .')');
	} catch (Exception $e) {
		$err = 1;
	}

	if ($err || !$row) {
		displayError("Error", "User not found.");
	}
	$username = $row['username'];
	$code = md5($username . $row['email'] . time());

	// If a recovery_request already exists, delete it from the database
	$sql = 'SELECT COUNT(*) as c FROM Recovery_Request WHERE username =' .
		$adodb->qstr($username);
	try {
		$res = $adodb->GetRow($sql);
		if ($res['c'] != 0) {
			$sql = 'DELETE FROM Recovery_Request WHERE username =' .
				$adodb->qstr($username);
			$adodb->Execute($sql);
		}
	} catch (Exception $e) {
		displayError("Error", "Error on: {$sql}");
	}

	$sql = 'INSERT INTO Recovery_Request (username, email, code, expires) VALUES('
		. $adodb->qstr($username) . ', '
		. $adodb->qstr($row['email']) . ', '
		. $adodb->qstr($code) . ', '
		. $adodb->qstr(time() + 86400) . ')';

	try {
		$res = $adodb->Execute($sql);
	} catch (Exception $e) {
		displayError("Error", "Error on: {$sql}");
	}

	$url = $base_url . '/reset.php?code=' . $code;
	// TODO: Read names from variable
	$content = "Hi!\n\nSomeone requested a password reset on your account.\n\n"
		. "Username: {$username}\n\n"
		. "To reset your password, please visit\n\n"
		. $url . "\n\nIf you do not wish to reset your password, simply "
		. "disregard this email.\n\n- The " . $site_name . " Team";

	$status = sendEmail($content, $row['email']);
	if (!$status) {
		displayError("Error",
			"Error while trying to send email to: {$row['email']}. Please try again later, or contact the site administrators.");
	}

	$smarty->assign('sent', true);
}

$smarty->display('reset.tpl');
