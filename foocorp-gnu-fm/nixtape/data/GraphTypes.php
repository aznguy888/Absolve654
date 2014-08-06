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

require_once($install_path . '/data/Graph.php');

/**
 * Each subclass within this file extends the parent Graph object and
 * correlate to individual graph data on the statistics pages.
 *
 * GraphTopArtists represents the Top Artists displayed on the user
 * statistics page. */
class GraphTopArtists extends Graph {

	public $artists, $artists_data;
	public $number_of_artists;
	public $begin;

	/**
	 * @param string $user The current user to build the information on.
	 * @param int $num The number of artists to be included in the search.
	 * @param int $begin Only count scrobbles with a timestamp higher than this.
	 **/
	function __construct($user, $num = 20, $begin = null) {
		parent::__construct($user, 'bar_horiz');
		$this->number_of_artists = $num;
		$this->begin = $begin;
		$this->buildGraphData();
	}

	/**
	 * Parses the data internally into a format expected by the plotting
	 * JS libraries.
	 *
	 * Arrays are reversed to the expectation of order in the current (jqPlot)
	 * plotting utility.
	 **/
	private function buildGraphData() {
		$tmp = $this->user->getTopArtists($this->number_of_artists, 0, False, $this->begin);

		if (!empty($tmp)) {
			foreach ($tmp as $root => $node) {
				$tmp = '<a href="'.$node['artisturl'].'">';
				$tmp .= htmlentities($node['artist'], ENT_QUOTES, 'UTF-8').'</a>';
				$artists[] = $tmp;
				$artists_data[] = $node['freq'];
			}

			$this->setMaxX($artists_data[0]);
			$artists = array_reverse($artists);
			$artists_data = array_reverse($artists_data);
			$this->artists = $this->buildJsSingleArray($artists);
			$this->data[0][0] = $artists_data;
			$this->artists_data = $this->buildJsDataArray(true);
		}
	}
}

/**
 * Represents the Top Tracks data on the user statistic page.
 **/
class GraphTopTracks extends Graph {

	public $tracks, $tracks_data;
	public $number_of_tracks;
	public $begin;

	/**
	 * @param string $user The current user to build the information on.
	 * @param int $num The number of tracks to be included in the search, 20 by default.
	 * @param int $begin Only count scrobbles with a timestamp higher than this.
	 **/
	function __construct($user, $num = 20, $begin = null) {
		parent::__construct($user, 'bar_horiz');
		$this->number_of_tracks = $num;
		$this->begin = $begin;
		$this->buildGraphData();
	}

	/**
	 * Parses the data internally into a format expected by the plotting
	 * JS libraries.
	 *
	 * Arrays are reversed to the expectation of order in the current (jqPlot)
	 * plotting utility.
	 **/
	private function buildGraphData() {
		$this->data_buffer = $this->user->getTopTracks($this->number_of_tracks, 0, False, $this->begin);
		$tracks = array();
		$listings = array();

		foreach($this->data_buffer as $key => $entry) {
			$tmp_line = '<a href="'.$entry['artisturl'].'">';
			$tmp_line .= htmlentities($entry['artist'], ENT_QUOTES, 'UTF-8').'</a>';
			$tmp_line .= ' - <a href="'.$entry['trackurl'].'">';
			$tmp_line .= htmlentities($entry['track'], ENT_QUOTES, 'UTF-8').'</a>';
			$listings[] = $entry['freq'];
			$tracks[] = $tmp_line;
		}

		$this->setMaxX($listings[0]);
		$tracks = array_reverse($tracks);
		$listings = array_reverse($listings);
		$this->tracks = $this->buildJsSingleArray($tracks);
		$this->data[0][0] = $listings;
		$this->tracks_data = $this->buildJsDataArray(true);
	}
}

/**
 * Represents the Plays By Days line graph data on the user statistic page.
 **/
class GraphPlaysByDays extends Graph {

	public $plays_by_days;
	public $number_of_days;

	/**
	 * @param $user - the current user to build the information on.
	 * @param $num - the number of days worth of tracks to be included in
	 * the search, 20 by default.
	 **/
	function __construct($user, $num = 20) {
		parent::__construct($user, 'line');
		$this->number_of_days = $num;
		$this->buildGraphData();
	}

	/**
	 * Parses the data internally into a format expected by the plotting
	 * JS libraries.
	 *
	 * Currently does not delegate the construction of the JS array to parent
	 * object, however it should do this. Tokenisation required in parent.
	 *
	 * @todo: tokenise build JS array functions and refactor accordingly.
	 **/
	private function buildGraphData() {
		$this->data_buffer = Statistic::generatePlayByDays('Scrobbles',
							$this->number_of_days, $this->user->uniqueid, 300);

		$date_line = '[';
		$prev_date == null;

		if (!empty($this->data_buffer)) {
			foreach ($this->data_buffer as $key => $entry) {
				$curr_date = DateTime::createFromFormat("Y-m-d", $entry['date']);
				if($prev_date == null) {
					$prev_date = DateTime::createFromFormat("Y-m-d", $entry['date']);
				}
				while($prev_date > $curr_date) {
					$date_line .= '[\'' . $prev_date->format("Y-m-d") . '\', 0],';
					$prev_date->sub(new DateInterval("P1D"));
				}
				$prev_date->sub(new DateInterval("P1D"));
				$date_line .= '[\'' . $entry['date'] . '\', ' . $entry['count'] . '],';
			}

			$this->plays_by_days = rtrim($date_line, ',');
			$this->plays_by_days .= ']';
		} else {
			$this->plays_by_days = '[]';
		}
	}
}


class GraphTrackPerformance extends Graph {}
