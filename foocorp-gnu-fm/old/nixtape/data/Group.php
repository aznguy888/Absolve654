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

require_once($install_path . '/database.php');
require_once($install_path . '/data/sanitize.php');
require_once($install_path . '/utils/human-time.php');
require_once($install_path . '/data/Server.php');
require_once($install_path . '/data/TagCloud.php');
require_once($install_path . '/data/User.php');

/**
 * Represents Group data
 *
 * General attributes are accessible as public variables.
 *
 * @deprecated This class hasnt been used in a long time and needs to be reviewed before use. 20120308 kabniel
 */
class Group {

	public $id, $gid, $name, $owner, $fullname, $bio, $homepage, $count, $grouptype, $avatar_uri, $users;

	/**
	 * User constructor
	 *
	 * @param string $name The name of the user to load
	 */
	function __construct($name, $data = null) {

		global $base_url;
		$base = preg_replace('#/$#', '', $base_url);

		if (is_array($data)) {
			$row = $data;
		} else {
			global $adodb;
			$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
			try {
				$res = $adodb->GetRow('SELECT * FROM Groups WHERE lower(groupname) = lower(' . $adodb->qstr($name) . ')');
			} catch (Exception $e) {
				header('Content-Type: text/plain');
				exit;
			}

			if ($res) {
				$row = $res;
			}
		}

		if (is_array($row)) {
			$this->gid          = $row['id'];
			$this->name         = $row['groupname'];
			$this->fullname     = $row['fullname'];
			$this->homepage     = $row['homepage'];
			$this->bio          = $row['bio'];
			$this->avatar_uri   = $row['avatar_uri'];
			$this->owner        = User::new_from_uniqueid_number($row['owner']);
			$this->count        = -1;
			$this->users        = array();
			if (!preg_match('/\:/', $this->id)) {
				$this->id = $base . '/group/' . rawurlencode($this->name) . '#group';
			}
		}
	}

	/**
	 * Selects a random nixtape group.
	 *
	 * @return object a Group object on success, or false if there are no groups existing.
	 * @author tobyink
	 */
	static function random() {
		global $adodb;

		if (strtolower(substr($connect_string, 0, 5)) == 'mysql') {
			$random = 'RAND';
		} else if (strtolower(substr($connect_string, 0, 5)) == 'mssql') {
			$random = 'NEWID';  // I don't think we try to support MSSQL, but here's how it's done theoretically anyway
		} else {
			$random = 'RANDOM';  // postgresql, sqlite, possibly others
		}

		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		try {
			$res = $adodb->GetRow("SELECT * FROM Groups ORDER BY {$random}() LIMIT 1");
		} catch (Exception $e) {
			return $res;
		}
		if ($res) {
			$row = $res;
			return new Group($row['groupname'], $row);
		} else {
			// No groups found.
			return false;
		}
	}

	/**
	 * Create a new nixtape group.
	 *
	 * @param string $name the name of the group (used to generate its URL).
	 * @param object $owner a User object representing the person who owns this group.
	 * @return object a Group object on success, throw an Exception object otherwise.
	 * @author tobyink
	 */
	static function create($name, $owner) {
		global $adodb;

		if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_\.-]*[A-Za-z0-9]$/', $name)) {
			throw (new Exception('Group names should only contain letters, numbers, hyphens, underscores and full stops (a.k.a. dots/periods), must be at least two characters long, and can\'t start or end with punctuation.'));
		}

		if (in_array(strtolower($name), array('new', 'search'))) {
			throw (new Exception("Not allowed to create a group called '{$name}' (reserved word)!"));
		}

		// Check to make sure no existing group with same name (case-insensitive).
		$q = sprintf('SELECT groupname FROM Groups WHERE LOWER(groupname)=LOWER(%s)'
				, $adodb->qstr($name));
		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		try {
			$res = $adodb->GetRow($q);
		} catch (Exception $e) {
			return $res;
		}
		if ($res) {
			$row = $res;
			$existing = $row['groupname'];
			throw (new Exception(
						($existing == $name) ?
						"There is already a group called '{$existing}'." :
						"The name '{$name}' it too similar to existing group '{$existing}'"
						));
		}

		// Create new group
		$q = sprintf('INSERT INTO Groups (groupname, owner, created, modified) VALUES (%s, %s, %d, %d)'
				, $adodb->qstr($name)
				, (int)($owner->uniqueid)
				, time()
				, time());
		try {
			$res = $adodb->Execute($q);
		} catch (Exception $e) {
			return $res;
		}

		// Get ID number for group
		$q = sprintf('SELECT id FROM Groups WHERE lower(groupname) = lower(%s)', $adodb->quote($name, 'text'));
		try {
			$res = $adodb->GetOne($q);
		} catch (Exception $e) {
			return $res;
		}
		if (!$res) {
			throw (new Exception('Something has gone horribly, horribly wrong!'));
		}
		$grp = $res;

		// Group owner must be a member of the group
		$q = sprintf('INSERT INTO Group_Members (grp, member, joined) VALUES (%s, %s, %d)'
				, (int)($grp)
				, (int)($owner->uniqueid)
				, time());
		try {
			$res = $adodb->Execute($q);
		} catch (Exception $e) {
			return null;
		}

		// Return the newly created group. Callers should check the return value.
		return new Group($name);
	}

	static function groupList($user = false) {
		global $adodb;

		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		try {

			if ($user) {
				$res = $adodb->GetAll('SELECT gc.* FROM '
						. 'Group_Members m '
						. 'INNER JOIN (SELECT g.id, g.groupname, g.owner, g.fullname, g.bio, g.homepage, g.created, g.modified, g.avatar_uri, g.grouptype, COUNT(*) AS member_count '
						. 'FROM Groups g '
						. 'LEFT JOIN Group_Members gm ON gm.grp=g.id '
						. 'GROUP BY g.id, g.groupname, g.owner, g.fullname, g.bio, g.homepage, g.created, g.modified, g.avatar_uri, g.grouptype) gc '
						. 'ON m.grp=gc.id '
						. 'WHERE m.member=' . (int)($user->uniqueid));
			} else {
				$res = $adodb->GetAll('SELECT g.groupname, g.owner, g.fullname, g.bio, g.homepage, g.created, g.modified, g.avatar_uri, g.grouptype, COUNT(*) AS member_count '
						. 'FROM Groups g '
						. 'LEFT JOIN Group_Members gm ON gm.grp=g.id '
						. 'GROUP BY g.groupname, g.owner, g.fullname, g.bio, g.homepage, g.created, g.modified, g.avatar_uri, g.grouptype');
			}

		} catch (Exception $e) {
			header('Content-Type: text/plain');
			exit;
		}

		$list = array();
		foreach ($res as &$row) {
			$g = new Group($row['group_name'], $row);
			$g->count = $row['member_count'];
			$list[] = $g;
		}

		return $list;
	}

	function save() {
		global $adodb;

		$q = sprintf('UPDATE Groups SET '
				. 'owner=%s, '
				. 'fullname=%s, '
				. 'homepage=%s, '
				. 'bio=%s, '
				. 'avatar_uri=%s, '
				. 'modified=%d '
				. 'WHERE groupname=%s'
				, (int)($this->owner->uniqueid)
				, $adodb->qstr($this->fullname)
				, $adodb->qstr($this->homepage)
				, $adodb->qstr($this->bio)
				, $adodb->qstr($this->avatar_uri)
				, time()
				, $adodb->qstr($this->name));

		try {
			$res = $adodb->Execute($q);
		} catch (Exception $e) {
			header('Content-Type: text/plain');
			exit;
		}

		return 1;
	}

	/**
	 * Retrieve a user's avatar via the gravatar service
	 *
	 * @param int $size The desired size of the avatar (between 1 and 512 pixels)
	 * @return A URL to the user's avatar image
	 */
	function getAvatar($size = 64) {
		global $base_uri;
		if (!empty($this->avatar_uri)) {
			return $this->avatar_uri;
		}
		return $base_url . '/themes/librefm/images/default-avatar-stream.png';
	}

	function getURL() {
		return Server::getGroupURL($this->name);
	}

	function getURLAction ($action) {
		$url = $this->getURL();
		if (strstr($url, '?')) {
			return $url . '&action=' . rawurlencode($action);
		} else {
			return $url . '?action=' . rawurlencode($action);
		}
	}

	function getUsers () {
		global $adodb;
		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);

		if (!isset($this->users[0])) {
			$res = $adodb->GetAll('SELECT u.* '
					. 'FROM Users u '
					. 'INNER JOIN Group_Members gm ON u.uniqueid=gm.member '
					. 'WHERE gm.grp=' . (int)($this->gid)
					. ' ORDER BY gm.joined');
			if ($res) {
				foreach ($res as &$row) {
					try {
						$this->users[$row['username']] = new User($row['username'], $row);
					} catch (Exception $e) {}
				}
			}

			$this->count = count($this->users);
		}

		return $this->users;
	}

	function memberCheck ($user) {
		$users = $this->getUsers();
		if ($users[$user->name]->name == $user->name) {
			return true;
		}
		return false;
	}

	function memberJoin ($user) {
		if ($this->memberCheck($user)) {
			return false;
		}

		global $adodb;
		try {
			$res = $adodb->Execute(sprintf('INSERT INTO Group_Members (grp, member, joined) VALUES (%s, %s, %d)',
						(int)($this->gid),
						(int)($user->uniqueid),
						time()));
		} catch (Exception $e) {
			return false;
		}

		$this->users[$user->name] = $user;
		return true;
	}

	function memberLeave($user) {
		if (!$this->memberCheck($user)) {
			return false;
		}

		// Group owner cannot leave, so we need a way to reassign ownership.
		if ($this->owner->name == $user->name) {
			return false;
		}

		global $adodb;
		try {
			$res = $adodb->Execute(sprintf('DELETE FROM Group_Members WHERE grp=%s AND member=%s',
						(int)($this->gid),
						(int)($user->uniqueid)));
		} catch (Exception $e) {
			return false;
		}

		$this->users[$user->name] = null;
		// The array key still exists though. That's annoying. PHP needs an equivalent of Perl's 'delete'.
		// This shouldn't actually cause us any problems, but people should be aware of the oddness.
		return true;
	}

	function tagCloudData () {
		try {
			return TagCloud::generateTagCloud(
					TagCloud::scrobblesTable('group') . ' s LEFT JOIN Users u ON s.userid=u.uniqueid LEFT JOIN Group_Members gm ON u.uniqueid=gm.member LEFT JOIN Groups g ON gm.grp=g.id',
					'artist',
					40,
					$this->name,
					'groupname');
		} catch (Exception $e) {
			throw $e;
		}
	}

}
