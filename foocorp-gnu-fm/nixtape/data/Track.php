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
require_once($install_path . '/data/Artist.php');
require_once($install_path . '/data/Album.php');
require_once($install_path . '/data/Tag.php');
require_once($install_path . '/data/Server.php');
require_once($install_path . '/utils/resolve-external.php');
require_once($install_path . '/utils/licenses.php');
require_once($install_path . '/utils/linkeddata.php');

/**
 * Represents track data
 *
 * All track attributes are accessible as public variables.
 */
class Track {

	public $name, $artist_name, $album_name, $mbid, $duration, $streamable, $license, $downloadurl, $streamurl;
	public $id;

	private $_playcount = false, $_listenercount = false;

	/**
	 * Track constructor
	 *
	 * @param string $name The name of the track to load
	 * @param string $artist The name of the artist who recorded this track
	 *
	 * @todo Should we call Track::create() instead of throwing "No such track" exception?
	 */
	function __construct($name, $artist) {
		global $adodb;
		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		$this->query = 'SELECT name, artist_name, album_name, duration, streamable, license, downloadurl, streamurl, mbid FROM Track WHERE '
			. 'lower(name) = lower(' . $adodb->qstr($name) . ') AND '
			. 'lower(artist_name) = lower(' . $adodb->qstr($artist) . ')'
			. 'ORDER BY streamable DESC';
		$res = $adodb->CacheGetRow(600, $this->query);
		if (!$res) {
			throw new Exception('No such track: ' . $name);
		} else {
			$row = $res;
			$this->name = $row['name'];
			$this->mbid = $row['mbid'];
			$this->artist_name = $row['artist_name'];
			$this->album_name = $row['album_name'];
			$this->duration = $row['duration'];
			$this->streamable = $row['streamable'];
			$this->license = simplify_license($row['license']);
			$this->licenseurl = $row['license'];
			$this->downloadurl = resolve_external_url($row['downloadurl']);
			$this->streamurl = resolve_external_url($row['streamurl']);

			$this->id = identifierTrack(null, $this->artist_name, $this->name, $this->album_name, null, $this->mbid, null, null);
		}

	}

	/**
	 * Add a new track to the database.
	 *
	 * @param string $name Track name
	 * @param string $artist_name Artist name
	 * @param string $album_name Album name
	 * @param string $streamurl The URL pointing to a streamable file
	 * @param string $downloadurl The URL pointing to a downloadable file
	 * @param string $license A license URL
	 * @return Track A newly created track object
	 */
	public static function create($name, $artist_name, $album_name, $streamurl, $downloadurl, $license) {
		global $adodb;

		$streamable = (is_free_license($license) && !empty($streamurl)) ? 1 : 0;

		$adodb->Execute('INSERT INTO Track (name, artist_name, album_name, streamurl, downloadurl, license, streamable) VALUES ('
			. $adodb->qstr($name) . ', '
			. $adodb->qstr($artist_name) . ', '
			. $adodb->qstr($album_name) . ', '
			. $adodb->qstr($streamurl) . ', '
			. $adodb->qstr($downloadurl) . ', '
			. $adodb->qstr($license) . ', '
			. $streamable . ')');

		$album = new Album($album_name, $artist_name);
		$album->clearTrackCache();

		$artist = new Artist($artist_name);
		if (!$artist->isStreamable() && $streamable == 1) {
			// This artist has just had a streamable track added, so are now streamable
			$adodb->Execute('UPDATE Artist SET streamable = 1 WHERE name = ' . $adodb->qstr($artist->name));
		}

		return new Track($name, $artist_name);
	}

	/**
	 * Clear cached database query for this track
	 */
	function clearCache() {
		global $adodb;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Sets the playcount
	 *
	 * @param int $playcount The number of plays this track has received
	 */
	function setPlayCount($playcount) {
		$this->_playcount = $playcount;
	}

	/**
	 * Sets the number of listeners
	 *
	 * @param int $listeners The number of people who've listened to this track
	 */
	function setListenerCount($listeners) {
		$this->_listenercount = $listeners;
	}

	/**
	 * Sets the streaming URL
	 *
	 * @param string $streamurl The URL pointing to a streamable file
	 */
	function setStreamURL($streamurl) {
		global $adodb;

		$adodb->Execute('UPDATE Track SET streamurl=' . $adodb->qstr($streamurl) .
			' WHERE artist_name=' . $adodb->qstr($this->artist_name) . ' AND ' .
			' name=' . $adodb->qstr($this->name));
		$this->clearCache();
	}

	/**
	 * Sets the download URL
	 *
	 * @param string $downloadurl The URL pointing to a downloadable file
	 */
	function setDownloadURL($downloadurl) {
		global $adodb;

		$adodb->Execute('UPDATE Track SET downloadurl=' . $adodb->qstr($downloadurl) .
			' WHERE artist_name=' . $adodb->qstr($this->artist_name) . ' AND ' .
			' name=' . $adodb->qstr($this->name));
		$this->clearCache();
	}

	/**
	 * Sets the license
	 *
	 * @param string $license A license URL
	 */
	function setLicense($license) {
		global $adodb;

		$streamable = 0;
		if (is_free_license($license)) {
			$streamable = 1;
		}

		$adodb->Execute('UPDATE Track SET license=' . $adodb->qstr($license) . ', streamable=' . $streamable .
			' WHERE artist_name=' . $adodb->qstr($this->artist_name) . ' AND ' .
			' name=' . $adodb->qstr($this->name));

		if ($streamable) {
			$adodb->Execute('UPDATE Artist SET streamable=1 WHERE name=' . $adodb->qstr($this->artist_name));
			try {
				$artist = new Artist($this->artist_name);
				$artist->clearCache();
			} catch (Exception $e) {
				// No such artist.
			}
		}
		$this->clearCache();
	}


	/**
	 * Gets the play count for this track
	 *
	 * @return int Number of times this track has been played
	 */
	function getPlayCount() {
		if ($this->_playcount) {
			// If we've been given a cached value from another SQL call use that
			return $this->_playcount;
		}

		$this->_getPlayCountAndListenerCount();
		return $this->_playcount;
	}

	/**
	 * Gets the listener count for this track
	 *
	 * @return int Number of listeners this track has
	 */
	function getListenerCount() {
		if ($this->_listeners) {
			return $this->_listenercount;
		}

		$this->_getPlayCountAndListenerCount();
		return $this->_listenercount;
	}


	/**
	 * Updates the play count and listener count for this track
	 */
	private function _getPlayCountAndListenerCount() {
		global $adodb;

		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		$row = $adodb->CacheGetRow(300, 'SELECT COUNT(track) AS freq, COUNT(DISTINCT userid) AS listeners FROM Scrobbles WHERE'
			. ' lower(artist) = lower(' . $adodb->qstr($this->artist_name) . ')'
			. ' AND lower(track) = lower(' . $adodb->qstr($this->name) . ')'
			. ' GROUP BY track ORDER BY freq DESC');

		if (!isset($row)) {
			$this->setPlaycount(0);
			$this->setListenerCount(0);
		} else {
			$this->setPlaycount($row['freq']);
			$this->setListenerCount($row['listeners']);
		}
	}

	/**
	 * Gets the artist for this track.
	 *
	 * @return Artist Artist object for this track
	 */
	function getArtist() {
		try {
			return new Artist($this->artist_name);
		} catch (Exception $e) {
			throw $e;
		}
	}

	/**
	 * Gets the URL for this track
	 *
	 * @param string $component Type of page, 'tags', ''
	 * @return string URL for this track
	 */
	function getURL($component = '') {
		return Server::getTrackURL($this->artist_name, $this->album_name, $this->name, $component);
	}

	/**
	 * Gets the edit URL for this track
	 *
	 * @return string Edit URL for this track
	 */
	function getEditURL() {
		return Server::getTrackEditURL($this->artist_name, $this->album_name, $this->name);
	}

	/**
	 * Gets the top tags for this track, ordered by tag count
	 *
	 * @param int $limit The number of tags to return (default is 10)
	 * @param int $offset The position of the first tag to return (default is 0)
	 * @param int $cache Caching period of query in seconds (default is 600)
	 * @return array Tag details ((tag, freq) .. )
	 */
	function getTopTags($limit=10, $offset=0, $cache=600) {
		return Tag::_getTagData($cache, $limit, $offset, null, $this->artist_name, null, $this->name);
	}

	/**
	 * Get this track's top listeners
	 *
	 * @param int $limit Amount of results to return
	 * @param int $offset Skip this many items before returning results
	 * @param int $streamable Only return results for streamable tracks
	 * @param int $begin Only use scrobbles with time higher than this timestamp
	 * @param int $end Only use scrobbles with time lower than this timestamp
	 * @param int $cache Caching period in seconds
	 * @return array ((userid, freq, username, userurl) ..)
	 */
	function getTopListeners($limit = 20, $offset = 0, $streamable = False, $begin = null, $end = null, $cache = 600) {
		return Server::getTopListeners($limit, $offset, $streamable, $begin, $end, $this->artist_name, $this->name, $cache);
	}

	/**
	 * Get a specific user's tags for this track.
	 *
	 * @param int $userid Get tags for this user
	 * @param int $limit The number of tags to return (default is 10)
	 * @param int $offset The position of the first tag to return (default is 0)
	 * @param int $cache Caching period of query in seconds (default is 600)
	 * @return array Tag details ((tag, freq) .. )
	 */
	function getTags($userid, $limit=10, $offset=0, $cache=600) {
		if(isset($userid)) {
			return Tag::_getTagData($cache, $limit, $offset, $userid, $this->artist_name, null, $this->name);
		}
	}

	/**
	 * Add a list of tags to a track
	 *
	 * @param string $tags A comma-separated list of tags.
	 * @param int $userid The user adding these tags.
	 * @return bool True if any tag was added, False if no tags were added.
	 */
	function addTags($tags, $userid) {
		global $adodb;

		$tags = explode(',', strtolower($tags));
		$query = 'INSERT INTO Tags (tag, artist, album, track, userid) VALUES(?,?,?,?,?)';
		foreach($tags as $tag) {
			$tag = trim($tag);
			if(strlen($tag) == 0) {
				continue;
			}
			$params = array($tag, $this->artist_name, $this->album_name, $this->name, (int) $userid);
			try {
				$adodb->Execute($query, $params);
				if ($adodb->Affected_Rows()) {
					$res = $res + 1;
				}
			} catch (Exception $e) {
				reportError($e->GetMessage(), $e->GetTraceAsString());
			}
		}
		return (bool) $res;
	}

	/**
	 * Love a track
	 *
	 * @param int $userid The user loving this track.
	 * @return bool True on success, False on fail.
	 */
	function love($userid) {
		global $adodb;

		$query = 'INSERT INTO Loved_Tracks (userid, track, artist, time) VALUES(?,?,?,?)';
		$params = array((int) $userid, $this->name, $this->artist_name, time());
		try {
			$adodb->Execute($query, $params);
			$res = $adodb->Affected_Rows();
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}
		return (bool) $res;
	}

	/**
	 * Unlove a track
	 *
	 * @param int $userid The user unloving this track.
	 * @return bool True on success, False on fail.
	 */
	function unlove($userid) {
		global $adodb;

		$query = 'DELETE FROM Loved_Tracks WHERE userid=? AND track=? AND artist=?';
		$params = array((int) $userid, $this->name, $this->artist_name);

		try {
			$adodb->Execute($query, $params);
			$res = $adodb->Affected_Rows();
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}
		return (bool) $res;
	}

	/**
	 * Check if track has been loved by user
	 *
	 * @param int $userid The user we are looking for
	 * @return bool True if track has been loved by user
	 */
	function isLoved($userid) {
		global $adodb;

		$query = 'SELECT * FROM Loved_Tracks WHERE userid=? AND track=? AND artist=?';
		$params = array((int) $userid, $this->name, $this->artist_name);
		try {
			$res = $adodb->GetRow($query, $params);
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}

		if($res) {
			return True;
		}
		return False;
	}


	/**
	 * Ban a track
	 *
	 * @param int $userid The user banning this track.
	 * @return bool True on success, False on fail.
	 *
	 */
	function ban($userid) {
		global $adodb;

		$query = 'INSERT INTO Banned_Tracks (userid, track, artist, time) VALUES(?,?,?,?)';
		$params = array((int) $userid, $this->name, $this->artist_name, time());
		try {
			$adodb->Execute($query, $params);
			$res = $adodb->Affected_Rows();
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}
		return (bool) $res;
	}

	/**
	 * Unban a track
	 *
	 * @param int $userid The user unbanning this track.
	 * @return bool True on success, False on fail.
	 */
	function unban($userid) {
		global $adodb;

		$query = 'DELETE FROM Banned_Tracks WHERE userid=? AND track=? AND artist=?';
		$params = array((int) $userid, $this->name, $this->artist_name);

		try {
			$adodb->Execute($query, $params);
			$res = $adodb->Affected_Rows();
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}
		return (bool) $res;
	}


	/**
	 * Check if track has been banned by user
	 *
	 * @param int $userid The user we are looking for
	 * @return bool True if track has been banned by user
	 */
	function isBanned($userid) {
		global $adodb;

		$query = 'SELECT * FROM Banned_Tracks WHERE userid=? AND track=? AND artist=?';
		$params = array((int) $userid, $this->name, $this->artist_name);
		try {
			$res = $adodb->GetRow($query, $params);
		} catch (Exception $e) {
			reportError($e->GetMessage(), $e->GetTraceAsString());
			return False;
		}

		if($res) {
			return True;
		}
		return False;
	}


	/*	
	 * Remove a tag from a track
	 *
	 * @param string $tag The tag to be removed
	 * @param int $userid The user removing the tag
	 * @return bool True on success, False on fail.
	 */
	function removeTag($tag, $userid) {
		global $adodb;

		$tag = trim($tag);
		if(strlen($tag) == 0) {
			return;
		}
		$query = 'DELETE FROM Tags WHERE tag=? AND artist=? AND track=? AND userid = ?';
		$params = array($tag, $this->artist_name, $this->name, (int) $userid);
		try {
			$adodb->Execute($query, $params);
			$res = $adodb->Affected_Rows();
		} catch (Exception $e) {
			reportError($e->getMessage(), $e->getTraceAsString());
			return False;
		}
		return (bool) $res;
	}

}
