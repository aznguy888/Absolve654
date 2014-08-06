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
require_once($install_path . '/data/Album.php');
require_once('xml.php');

/**
 * Class with functions that returns XML-formatted data for albums.
 *
 * These functions are mainly used by web service methods.
 *
 * @package API
 */
class AlbumXML {

	/**
	 * Get an album's Top Tags
	 *
	 * @param string $artist Name of the album's artist
	 * @param string $name Name of the album
	 * @param int $limit How many tags to return
	 * @param int $cache Caching period of request in seconds
	 * @return string XML-formatted data
	 */
	public static function getTopTags($artist, $name, $limit, $cache) {
		
		try {
			$album = new Album($name, $artist);
			$res = $album->getTopTags($limit, 0, $cache);
		} catch (Exception $e) {
			return(XML::error('failed', '7', 'Invalid resource specified'));
		}

		if(!$res) {
			return(XML::error('failed', '6', 'No tags for this album'));
		}

		$xml = new SimpleXMLElement('<lfm status="ok"></lfm>');
		$root = $xml->addChild('toptags', null);
		$root->addAttribute('artist', $album->artist_name);
		$root->addAttribute('album', $album->name);

		foreach ($res as &$row) {
			$tag_node = $root->addChild('tag', null);
			$tag_node->addChild('name', repamp($row['tag']));
			$tag_node->addChild('count', $row['freq']);
			$tag_node->addChild('url', Server::getTagURL($row['tag']));
		}

		return $xml;
	}

	public static function getTags($artist, $name, $userid, $limit, $cache) {
		
		try {
			$album = new Album($name, $artist);
			$res = $album->getTags($userid, $limit, 0, $cache);
		} catch (Exception $e) {
			return(XML::error('failed', '7', 'Invalid resource specified'));
		}

		if(!$res) {
			return(XML::error('failed', '6', 'No tags for this album'));
		}

		$xml = new SimpleXMLElement('<lfm status="ok"></lfm>');
		$root = $xml->addChild('tags', null);
		$root->addAttribute('artist', $artist);
		$root->addAttribute('album', $name);

		foreach ($res as &$row) {
			$tag_node = $root->addChild('tag', null);
			$tag_node->addChild('name', repamp($row['tag']));
			$tag_node->addChild('url', Server::getTagURL($row['tag']));
		}

		return $xml;
	}


}
