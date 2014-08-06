#!/usr/bin/env python

import xml.etree.cElementTree as ElementTree
import sys, gzip, time, httplib
import psycopg2 as ordbms

JAMENDO_MIRROR = "gigue.rrbone.net"

genremap = {
	0 : "Blues",
	1 : "Classic Rock",
	2 : "Country",
	3 : "Dance",
	4 : "Disco",
	5 : "Funk",
	6 : "Grunge",
	7 : "Hip-Hop",
	8 : "Jazz",
	9 : "Metal",
	10 : "New Age",
	11 : "Oldies",
	12 : "Other",
	13 : "Pop",
	14 : "R&B",
	15 : "Rap",
	16 : "Reggae",
	17 : "Rock",
	18 : "Techno",
	19 : "Industrial",
	20 : "Alternative",
	21 : "Ska",
	22 : "Death Metal",
	23 : "Pranks",
	24 : "Soundtrack",
	25 : "Euro-Techno",
	26 : "Ambient",
	27 : "Trip-Hop",
	28 : "Vocal",
	29 : "Jazz+Funk",
	30 : "Fusion",
	31 : "Trance",
	32 : "Classical",
	33 : "Instrumental",
	34 : "Acid",
	35 : "House",
	36 : "Game",
	37 : "Sound Clip",
	38 : "Gospel",
	39 : "Noise",
	40 : "Alternative Rock",
	41 : "Bass",
	42 : "Soul",
	43 : "Punk",
	44 : "Space",
	45 : "Meditative",
	46 : "Instrumental Pop",
	47 : "Instrumental Rock",
	48 : "Ethnic",
	49 : "Gothic",
	50 : "Darkwave",
	51 : "Techno-Industrial",
	52 : "Electronic",
	53 : "Pop-Folk",
	54 : "Eurodance",
	55 : "Dream",
	56 : "Southern Rock",
	57 : "Comedy",
	58 : "Cult",
	59 : "Gangsta",
	60 : "Top 40",
	61 : "Christian Rap",
	62 : "Pop/Funk",
	63 : "Jungle",
	64 : "Native American",
	65 : "Cabaret",
	66 : "New Wave",
	67 : "Psychadelic",
	68 : "Rave",
	69 : "Showtunes",
	70 : "Trailer",
	71 : "Lo-Fi",
	72 : "Tribal",
	73 : "Acid Punk",
	74 : "Acid Jazz",
	75 : "Polka",
	76 : "Retro",
	77 : "Musical",
	78 : "Rock & Roll",
	79 : "Hard Rock",
	80 : "Folk",
	81 : "Folk-Rock",
	82 : "National Folk",
	83 : "Swing",
	84 : "Fast Fusion",
	85 : "Bebop",
	86 : "Latin",
	87 : "Revival",
	88 : "Celtic",
	89 : "Bluegrass",
	90 : "Avantgarde",
	91 : "Gothic Rock",
	92 : "Progressive Rock",
	93 : "Psychedelic Rock",
	94 : "Symphonic Rock",
	95 : "Slow Rock",
	96 : "Big Band",
	97 : "Chorus",
	98 : "Easy Listening",
	99 : "Acoustic",
	100 : "Humour",
	101 : "Speech",
	102 : "Chanson",
	103 : "Opera",
	104 : "Chamber Music",
	105 : "Sonata",
	106 : "Symphony",
	107 : "Booty Bass",
	108 : "Primus",
	109 : "Porn Groove",
	110 : "Satire",
	111 : "Slow Jam",
	112 : "Club",
	113 : "Tango",
	114 : "Samba",
	115 : "Folklore",
	116 : "Ballad",
	117 : "Power Ballad",
	118 : "Rhythmic Soul",
	119 : "Freestyle",
	120 : "Duet",
	121 : "Punk Rock",
	122 : "Drum Solo",
	123 : "A capella",
	124 : "Euro-House",
	125 : "Dance Hall",
}

class JamendoImport:

	def __init__(self, username='librefm', database='librefm', updates=False):
		self.conn = ordbms.connect ("dbname='%s' user='%s'" % (database, username))
		self.perform_updates = updates
		self.cursor = self.conn.cursor ()


	def parse(self, dump):
		for event, elem in ElementTree.iterparse(dump):
			if elem.tag == "artist":
				artist = self.proc_artist(elem)

				if self.artist_exists(artist["name"]):
					if self.perform_updates:
						try:
							self.cursor.execute("UPDATE Artist SET image_small = %s, homepage = %s, mbid = %s WHERE name = %s", (artist["image"], artist["url"], artist["mbid"], artist["name"]))
							self.conn.commit()
						except Exception,  e:
							self.conn.rollback()
							print 'ua', e
				else:
					try:
						self.cursor.execute("INSERT INTO Artist (name, image_small, mbid, homepage)  VALUES (%s, %s, %s, %s)", (artist["name"], artist["image"], artist["mbid"], artist["url"]))
						self.conn.commit()
					except Exception,  e:
						self.conn.rollback()
						print 'ia', e

				any_streamable_tracks = 0
				for album in artist["albums"]:
					if self.album_exists(artist["name"], album["name"]):
						if self.perform_updates:
							try:
								self.cursor.execute("UPDATE Album SET albumurl = %s, image = %s, artwork_license = %s, mbid = %s, releasedate = %s, downloadurl = %s WHERE name = %s AND artist_name = %s", 
										(album["url"], album["image"], album["license_artwork"], album["mbid"], album["releasedate"], album["downloadurl"],
										album["name"], artist["name"]))
								self.conn.commit()
							except Exception,  e:
								self.conn.rollback()
								print 'ub', e
					else:
						try:
							self.cursor.execute("INSERT INTO Album (name, artist_name, albumurl, image, artwork_license, mbid, releasedate, downloadurl) VALUES (%s, %s, %s, %s, %s, %s, %s, %s)",
									(album["name"], artist["name"], album["url"], album["image"], album["license_artwork"], album["mbid"], album["releasedate"], album["downloadurl"]))
							self.conn.commit()
						except Exception,  e:
							self.conn.rollback()
							print 'ib', e

					for tag in album["tags"]:
						if not self.tag_exists(tag, artist["name"], album["name"]):
							try:
								self.cursor.execute("INSERT INTO Tags (tag, artist, album) VALUES (%s, %s, %s)",
										(tag, artist["name"], album["name"]))
								self.conn.commit()
							except Exception,  e:
								self.conn.rollback()
								print 'ig', e

					for track in album["tracks"]:

						if self.free_license(track["license"]) and self.is_in_mirror(track["id"]):
							streamable = 1
							any_streamable_tracks = 1
						else:
							streamable = 0

						try:
							duration = int(track["duration"])
						except:
							duration = None

						otherid = "jm:"

						try:
							otherid += str(int(track["id"]))
						except:
							otherid += "unknown"

						if self.track_exists(artist["name"], album["name"], track["name"]):
							try:
								self.cursor.execute("UPDATE Track SET downloadurl = %s, streamurl = %s, mbid = %s, license = %s, duration = %s, otherid = %s, streamable = %s WHERE name = %s AND artist_name = %s AND album_name = %s", (track["downloadurl"], track["streamurl"], track["mbid"], track["license"], duration, otherid, streamable, track["name"], artist["name"], album["name"]))
								self.conn.commit()
							except Exception,  e:
								self.conn.rollback()
								print 'ut', e
						else:
							try:
								self.cursor.execute("INSERT INTO Track (name, artist_name, album_name, mbid, downloadurl, streamurl, license, duration, otherid, streamable) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)", (track["name"], artist["name"], album["name"], track["mbid"], track["downloadurl"], track["streamurl"], track["license"], duration, otherid, streamable))
								self.conn.commit()
							except Exception,  e:
								self.conn.rollback()
								print 'it', e

						for tag in track["tags"]:
							if not self.tag_exists(tag, artist["name"], album["name"], track["name"]):
								try:
									self.cursor.execute("INSERT INTO Tags (tag, artist, album, track) VALUES (%s, %s, %s, %s)",
											(tag, artist["name"], album["name"], track["name"]))
									self.conn.commit()
								except Exception,  e:
									self.conn.rollback()
									print 'ig2', e


				if any_streamable_tracks:
					try:
						self.cursor.execute("UPDATE Artist SET streamable = 1 WHERE name = %s", (artist["name"],))
						self.conn.commit()
					except Exception,  e:
						self.conn.rollback()
						print 'ua', e






	def close(self):
		self.cursor.close()
		self.conn.commit()
		self.conn.close()

	def proc_artist(self, elem):
		artist = {}	
		artist["albums"] = []
		artist["image"] = None

		for artist_e in elem.getchildren():

			if artist_e.tag == "name":
				artist["name"] = artist_e.text
			
			if artist_e.tag == "id":
				artist["id"] = int(artist_e.text)
			
			if artist_e.tag == "image":
				artist["image"] = artist_e.text
			
			if artist_e.tag == "mbgid":
				if artist_e.text is None or len(artist_e.text) == 36:
					artist["mbid"] = artist_e.text
				else:
					print "Artist mbgid wrong length (%d): %s" % (len(artist_e.text),artist_e.text)

			if artist_e.tag == "url":
				artist["url"] = artist_e.text

			if artist_e.tag == "Albums":
				for album_e in artist_e.getchildren():
					artist["albums"].append(self.proc_album(album_e))

		return artist


	def proc_album(self, elem):
	
		album = {}
		album["tracks"] = []
		album["tags"] = []
		album["name"] = None
	
		for album_e in elem.getchildren():
		
			if album_e.tag == "name":
				album["name"] = album_e.text
		
			if album_e.tag == "id":
				album["id"] = int(album_e.text)
				album["url"] = "jamendo://album/%d" % album["id"]
				album["downloadurl"] = "jamendo://album/download/%d" % album["id"]
				album["image"] = "jamendo://album/art/%d" % album["id"]
		
			if album_e.tag == "id3genre":
				genre = genremap[int(album_e.text)]
				album["tags"].append(genre)
		
			if album_e.tag == "mbgid":
				if album_e.text is None or len(album_e.text) == 36:
					album["mbid"] = album_e.text
				else:
					print "Album mbgid wrong length (%d): %s" % (len(album_e.text),album_e.text)
			
			if album_e.tag == "license_artwork":
				album["license_artwork"] = album_e.text

			if album_e.tag == "releasedate":
				album["releasedate"] = time.mktime(time.strptime(album_e.text, "%Y-%m-%dT%H:%M:%S+01:00"))
		
			if album_e.tag == "Tracks":
				for track_e in album_e.getchildren():
					album["tracks"].append(self.proc_track(track_e))

		return album


	def proc_track(self, elem):
		track = {} 
		track["tags"] = []
		track["mbid"] = None
		track["downloadurl"] = None

		for track_e in elem.getchildren():
		
			if track_e.tag == "id":
				track["id"] = int(track_e.text)
				track["streamurl"] = "jamendo://track/stream/%d" % track["id"]

			if track_e.tag == "name":
				track["name"] = track_e.text

			if track_e.tag == "id3genre":
				genre = genremap[int(track_e.text)]
				track["tags"].append(genre)

			if track_e.tag == "license":
				track["license"] = track_e.text

			if track_e.tag == "duration":
				track["duration"] = track_e.text

			if track_e.tag == "mbgid":
				if track_e.text is None or len(track_e.text) == 36:
					track["mbid"] = track_e.text
				else:
					print "Track mbgid wrong length (%d): %s" % (len(track_e.text),track_e.text)

			if track_e.tag == "Tags":
				for tag_e in track_e.getchildren():
					track["tags"].append(self.proc_tag(tag_e))

		return track


	def proc_tag(self, elem):
		for track_e in elem.getchildren():
			if track_e.tag == "idstr":
				return track_e.text


	def artist_exists(self, artist):
		try:
			self.cursor.execute("SELECT name FROM Artist WHERE name = %s ", (artist,))
			return self.cursor.rowcount != 0
		except:
			return False

	def album_exists(self, artist, album):
		try:
			self.cursor.execute("SELECT name FROM Album WHERE artist_name = %s AND name = %s", (artist, album))
			return self.cursor.rowcount != 0
		except:
			return False

	def track_exists(self, artist, album, track):
		try:
			self.cursor.execute("SELECT name FROM Track WHERE artist_name = %s AND album_name = %s AND name = %s", (artist, album, track))
			return self.cursor.rowcount != 0
		except:
			return False


	def tag_exists(self, tag, artist, album, track=None):
		try:
			if track:
				self.cursor.execute("SELECT tag FROM Tags WHERE tag = %s AND artist = %s AND album = %s AND track = %s", (tag, artist, album, track))
			else:
				self.cursor.execute("SELECT tag FROM Tags WHERE tag = %s AND artist = %s AND album = %s AND track = ''", (tag, artist, album))
			return self.cursor.rowcount != 0
		except:
			return False

	def free_license(self, license):
		return ("http://creativecommons.org/licenses/by-sa" in license or "http://creativecommons.org/licenses/by/" in license or "http://artlibre.org/licence.php/lal.html" in license)

	def is_in_mirror(self, id):
		try:
			trackfile = "/" + str(id) + ".ogg2"
			connection = httplib.HTTPConnection(JAMENDO_MIRROR)
			connection.request("HEAD", trackfile)
			response = connection.getresponse()
			connection.close()
		except:
			return False
		else:
			return response.status == 200 and response.getheader('Content-Type') == 'audio/ogg'

if __name__ == "__main__":

	if len(sys.argv) != 2 and len(sys.argv) != 4:
		print "Usage: import-jamendo.py <database dump>"
		print "or"
		print "import-jamendo.py <database dump> <username> <database>"
		sys.exit(1)

	if sys.argv[1][-2:] == "gz":
		dump = gzip.open(sys.argv[1], "r")
	else:
		dump = open(sys.argv[1], "r")

	if len(sys.argv) == 2:
		importer = JamendoImport()
	else:
		importer = JamendoImport(sys.argv[2], sys.argv[3])
	importer.parse(dump)
	importer.close()
