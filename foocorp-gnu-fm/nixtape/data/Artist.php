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
require_once($install_path . '/data/Album.php');
require_once($install_path . '/data/Track.php');
require_once($install_path . '/data/Server.php');
require_once($install_path . '/utils/linkeddata.php');
require_once($install_path . '/data/Tag.php');

/**
 * Represents artist data
 *
 * General artist attributes are accessible as public variables.
 * Lists of tracks and albums are only generated when requested.
 */
class Artist {

	public $name, $mbid, $streamable, $bio_content, $bio_published, $bio_summary, $image_small, $image_medium, $image_large, $flattr_uid;
	public $id;
	private $query, $album_query;

	/**
	 * Artist constructor
	 *
	 * @param string $name The name of the artist to load
	 * @param string $mbid The mbid of the artist (optional)
	 * @param boolean $recache Whether the artist cache should be cleared before loading the artist
	 */
	function __construct($name, $mbid = false, $recache = false) {
		global $adodb;

		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		$mbidquery = '';
		if ($mbid) {
			$mbidquery = 'mbid = ' . $adodb->qstr($mbid) . ' OR ';
		}
		$this->query = 'SELECT name, mbid, streamable, bio_published, bio_content, bio_summary, image_small, image_medium, image_large, homepage, hashtag, flattr_uid FROM Artist WHERE '
			. $mbidquery
			. 'lower(name) = lower(' . $adodb->qstr($name) . ')';
		if($recache) {
			$this->clearCache();
		}
		$row = $adodb->CacheGetRow(1200, $this->query);
		if (!$row) {
			throw new Exception('No such artist' . $name);
		} else {
			$this->name = $row['name'];
			$this->mbid = $row['mbid'];
			$this->streamable = $row['streamable'];
			$this->bio_published = $row['bio_published'];
			$this->bio_content = strip_tags($row['bio_content'], '<p><a><li><ul><ol><br><b><em><strong><i>');
			$this->bio_summary = strip_tags($row['bio_summary']. '<p><a><li><ul><ol><br><b><em><strong><i>');
			$this->image_small = $row['image_small'];
			$this->image_medium = $row['image_medium'];
			$this->image_large = $row['image_large'];
			$this->homepage = $row['homepage'];
			$this->hashtag = $row['hashtag'];
			$this->flattr_uid = $row['flattr_uid'];

			$this->id = identifierArtist(null, $this->name, null, null, null, null, $this->mbid, null);
			$this->album_query = 'SELECT name, image FROM Album WHERE artist_name = '. $adodb->qstr($this->name);
		}
	}

	/**
	 * Retrieves the artist's albums
	 *
	 * @return array Album objects
	 */
	function getAlbums() {
		global $adodb;
		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		$res = $adodb->CacheGetAll(600, $this->album_query);
		foreach ($res as &$row) {
			$albums[] = new Album($row['name'], $this->name);
		}

		return $albums;
	}

	/**
	 * Clear the album cache, should be called after creating a new album
	 */
	function clearAlbumCache() {
		global $adodb;
		$adodb->CacheFlush($this->album_query);
	}

	/**
	 * Retrieves the artist's tracks
	 *
	 * @return array Track objects
	 */
	function getTracks() {
		global $adodb;
		$adodb->SetFetchMode(ADODB_FETCH_ASSOC);
		$res = $adodb->CacheGetAll(600, 'SELECT name FROM Track WHERE artist_name = '
			. $adodb->qstr($this->name));
		foreach ($res as &$row) {
			$tracks[] = new Track($row['name'], $this->name);
		}

		return $tracks;
	}

	/**
	 * Get this artist's top tracks
	 *
	 * @param int $limit The number of tracks to return
	 * @param int $offset Skip this number of rows before returning tracks
	 * @param bool $streamable Only return streamable tracks
	 * @param int $begin Only use scrobbles with time higher than this timestamp
	 * @param int $end Only use scrobbles with time lower than this timestamp
	 * @param int $cache Caching period in seconds
	 * @return array An array of tracks ((artist, track, freq, listeners, artisturl, trackurl) ..) or empty array in case of failure
	 */
	function getTopTracks($limit = 20, $offset = 0, $streamable = False, $begin = null, $end = null, $cache = 600) {
		return Server::getTopTracks($limit, $offset, $streamable, $begin, $end, $this->name, null, $cache);
	}

	/**
	 * Get this artist's top listeners
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
		return Server::getTopListeners($limit, $offset, $streamable, $begin, $end, $this->name, null, $cache);
	}

	/**
	 * Gives the URL for this artist
	 *
	 * @param string $component Type of page
	 * @return string URL of this artist
	 */
	function getURL($component = '') {
		return Server::getArtistURL($this->name, $component);
	}

	/**
	 * Gives the URL to the management interface for this artist
	 *
	 * @return string URL for this artist's management interface
	 */
	function getManagementURL() {
		return Server::getArtistManagementURL($this->name);
	}

	/**
	 * Gives the URL for manages to add a new album to this artist
	 *
	 * @return string URL for adding albums to this artist
	 */
	function getAddAlbumURL() {
		return Server::getAddAlbumURL($this->name);
	}

	/**
	 * Add a list of tags to an artist
	 *
	 * @param string $tags A comma seperated list of tags
	 * @param int $userid The user adding these tags
	 */
	function addTags($tags, $userid) {
		global $adodb;

		$tags = explode(',', strtolower($tags));
		foreach ($tags as $tag) {
			$tag = trim($tag);
			if (strlen($tag) == 0) {
				continue;
			}
			try {
				$adodb->Execute('INSERT INTO Tags (tag, artist, userid) VALUES ('
					. $adodb->qstr($tag) . ', '
					. $adodb->qstr($this->name) . ', '
					. $userid . ')');
			} catch (Exception $ex) {}
		}
	}

	/**
	 * Get the top tags for an artist, ordered by tag count
	 * (including any tags for the artist's albums and tracks)
	 *
	 * @param int $limit The number of tags to return (default is 10)
	 * @param int $offset The position of the first tag to return (default is 0)
	 * @param int $cache Caching period of query in seconds (default is 600)
	 * @return array Tag details ((tag, freq) .. )
	 */
	function getTopTags($limit=10, $offset=0, $cache=600) {
		return Tag::_getTagData($cache, $limit, $offset, null, $this->name);
	}

	/**
	 * Get a specific user's tags for this artist.
	 *
	 * @param int $userid Get tags for this user
	 * @param int $limit The number of tags to return (default is 10)
	 * @param int $offset The position of the first tag to return (default is 0)
	 * @param int $cache Caching period of query in seconds (default is 600)
	 * @return array Tag details ((tag, freq) .. )
	 */
	function getTags($userid, $limit=10, $offset=0, $cache=600) {
		if(isset($userid)) {
			return Tag::_getTagData($cache, $limit, $offset, $userid, $this->name);
		}
	}

	/**
	 * Clear cached database query for this artist
	 */
	function clearCache() {
		global $adodb;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set an artist's biography summary
	 *
	 * @param string $bio_summary The new biography summary to enter into the database.
	 */
	function setBiographySummary($bio_summary) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET bio_summary = ' . $adodb->qstr($bio_summary) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->bio_summary = $bio_summary;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set an artist's full biography
	 *
	 * @param string $bio The new biography to enter into the database.
	 */
	function setBiography($bio) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET bio_content = ' . $adodb->qstr($bio) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->bio_content = $bio;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set an artist's homepage
	 *
	 * @param string $homepage The artist's homepage
	 */
	function setHomepage($homepage) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET homepage = ' . $adodb->qstr($homepage) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->homepage = $homepage;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set a URL to an image of this artist
	 *
	 * @param string $image_url A URL linking directly to an image file.
	 */
	function setImage($image_url) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET image_medium = ' . $adodb->qstr($image_url) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->image_medium = $image_url;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set an identi.ca hashtag, used to display dents from on the artist page
	 *
	 * @param string $hashtag An identi.ca hashtag.
	 */
	function setHashtag($hashtag) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET hashtag = ' . $adodb->qstr($hashtag) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->hashtag = $hashtag;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Set a flattr user id for to allow this artist to be tipped
	 *
	 * @param string $flattr_uid A flattr username to associate with this artist
	 */
	function setFlattr($flattr_uid) {
		global $adodb;
		$adodb->Execute('UPDATE Artist SET flattr_uid = ' . $adodb->qstr($flattr_uid) . ' WHERE name = ' . $adodb->qstr($this->name));
		$this->flattr_uid = $flattr_uid;
		$adodb->CacheFlush($this->query);
	}

	/**
	 * Get streamable status for this artist
	 *
	 * @return bool True if artist have any streamable tracks
	 */
	function isStreamable() {
		global $adodb;
		return $this->streamable;
	}

	/**
	 * Finds out which users manage this artist.
	 *
	 * @return array User objects who manage this artist.
	 */
	function getManagers() {
		global $adodb;
		$managers = array();
		$res = $adodb->Execute('SELECT userid FROM Manages WHERE lower(artist)=lower(' . $adodb->qstr($this->name) . ') AND authorised=1');
		foreach($res as $row) {
			$managers[] = User::new_from_uniqueid_number($row['userid']);
		}
		return $managers;
	}

	/**
	 * Returns the number of listeners this artist has in total.
	 *
	 * @return int The number of people who've listened to this artist.
	 */
	function getListenerCount() {
		global $adodb;
		$row = $adodb->CacheGetRow(600, 'SELECT COUNT(DISTINCT userid) AS listeners FROM Scrobbles WHERE'
			. ' lower(artist) = lower(' . $adodb->qstr($this->name) . ')');
		return $row['listeners'];
	}

	/**
	 * Retrieves a list of similar artist names
	 *
	 * @param int $limit Number of artists to return
	 * @return array Artists and their similarity measure (between 0 and 1), sorted from most to least similar
	 */
	function getSimilar($limit = 10) {
		global $adodb;

		$similarArtists = array();

		// Find this artist's tags
		$tmpTags = $adodb->CacheGetAll(86400, 'SELECT lower(tag) as ltag, count(tag) as num FROM Tags WHERE artist = ' . $adodb->qstr($this->name) . ' GROUP BY ltag ORDER BY num DESC');
		$tagCount = $adodb->CacheGetOne(86400, 'SELECT count(artist) FROM Tags WHERE artist = ' . $adodb->qstr($this->name));
		// Narrow down similar artists to ones that at least share the most common tag and get hold of their other tags
		$otherArtists = $adodb->CacheGetAll(86400, 'SELECT artist, lower(tag) as ltag, count(tag) as num FROM Tags INNER JOIN Artist ON Artist.name = Tags.artist WHERE Artist.streamable = 1 AND artist in '
			. '(SELECT distinct(artist) FROM Tags WHERE lower(tag) = ' . $adodb->qstr($tmpTags[0]['ltag']) . ') '
			. 'GROUP BY artist, ltag ORDER BY num DESC LIMIT 1000');


		$totalTags = array();
		// Normalise tag proportions
		foreach ($otherArtists as &$commonArtist) {
			if (!array_key_exists($commonArtist['artist'], $totalTags)) {
				$totalTags[$commonArtist['artist']] = $commonArtist['num'];
			}

			$totalTags[$commonArtist['artist']] += $commonArtist['num'];
		}
		foreach ($otherArtists as &$commonArtist) {
			$commonArtist['num'] /= $totalTags[$commonArtist['artist']];
		}
		$tags = array();
		foreach ($tmpTags as &$tag) {
			$tags[$tag['ltag']] = $tag['num'] / $tagCount;
		}

		$mostSimilar = 1;
		// Calculate similarity
		foreach ($otherArtists as &$commonArtist) {
			if (!array_key_exists($commonArtist['artist'], $similarArtists)) {
				$similarArtists[$commonArtist['artist']] = array('artist' => $commonArtist['artist'], 'similarity' => 0);
			}

			if (array_key_exists($commonArtist['ltag'], $tags)) {
				$sdiff = (1 - abs($tags[$commonArtist['ltag']] - $commonArtist['num'])) * $tags[$commonArtist['ltag']];
			} else {
				$sdiff = 0;
			}

			$similarArtists[$commonArtist['artist']]['similarity'] += $sdiff;

			if ($similarArtists[$commonArtist['artist']]['similarity'] > $mostSimilar) {
				$mostSimilar = $similarArtists[$commonArtist['artist']]['similarity'];
			}
		}

		// Normalise similarity metric
		foreach ($similarArtists as &$artist) {
			$artist['similarity'] /= $mostSimilar;
		}

		// Sort artists by similarity
		$tmp = array();
		foreach ($similarArtists as &$ar) {
			$tmp[] = &$ar['similarity'];
		}
		array_multisort($tmp, SORT_DESC, $similarArtists);

		$similarWithMeta = array();
		$sizes = array('xx-large', 'x-large', 'large', 'medium', 'small', 'x-small', 'xx-small');
		$i = 0;
		foreach ($similarArtists as $artist) {
			if ($artist['artist'] != $this->name) {
				$similarWithMeta[$i]['artist'] = $artist['artist'];
				$similarWithMeta[$i]['similarity'] = $artist['similarity'];
				$similarWithMeta[$i]['url'] = Server::getArtistURL($artist['artist']);
				$similarWithMeta[$i]['size'] = $sizes[(int) ($i/($limit/count($sizes)))];
				$i++;
				if ($i >= $limit) {
					break;
				}
			}
		}
		sort($similarWithMeta);
		return $similarWithMeta;
	}

}
