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
require_once('data/sanitize.php');
require_once('data/Server.php');
require_once('data/User.php');

if (strtolower(substr($connect_string, 0, 5)) == 'mysql') {
	$random = 'RAND';
} else if (strtolower(substr($connect_string, 0, 5)) == 'mssql') {
	$random = 'NEWID';  // I don't think we try to support MSSQL, but here's how it's done theoretically anyway
} else {
	$random = 'RANDOM';  // postgresql, sqlite, possibly others
}

if ($_REQUEST['country']) {
	$q = sprintf('SELECT u.* FROM Users u INNER JOIN Places p ON u.location_uri=p.location_uri AND p.country=%s ORDER BY %s() LIMIT 100',
		$adodb->qstr(strtoupper($_REQUEST['country'])),
		$random);

	$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
	$res = $adodb->GetAll($q);

	foreach ($res as &$row) {
		try {
			$userlist[] = new User($row['username'], $row);
		} catch (Exception $e) {}
	}

	$smarty->assign('country', strtoupper($_REQUEST['country']));
	$row = $adodb->GetRow(sprintf('SELECT * FROM Countries WHERE country=%s LIMIT 1',
		$adodb->qstr(strtoupper($_REQUEST['country']))));
	if ($row) {
		$smarty->assign('country_info', $row);
	}

	$smarty->assign('userlist', $userlist);

	$smarty->assign('extra_head_links', array(
			array(
				'rel'   => 'meta',
				'type'  => 'application/rdf+xml',
				'title' => 'FOAF',
				'href'  => $base_url . '/rdf.php?fmt=xml&page=' . rawurlencode(str_replace($base_url, '', $_SERVER['REQUEST_URI']))
				)
		));

	$smarty->display('location-country.tpl');
} else {
	displayError("Location not found", "Location not found, shall I call in a missing locations report?");
}
