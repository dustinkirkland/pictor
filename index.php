<?php

/*
 * Pictor 1.1
 * Copyright (C) 2000-2008 Dustin Kirkland
 * Author: Dustin Kirkland <dustin.kirkland@gmail.com>
 * Pictor is released under the GNU Public License V3
 *
 *  Pictor is a  free web-based tool for sharing, viewing, and 
 *  organizing pictures through a web browser over the internet.  The intention 
 *  is provide a very light weight framework, a single php file, for
 *  accomplishing this goal.
 *
 */

/* Configurable options */
include_once("settings.php");

/* variables that may come in through an http GET request */
$album     = sanity_check($_REQUEST["album"]);
$picture   = sanity_check($_REQUEST["picture"]);
$width     = sanity_check($_REQUEST["width"]);
$rotate    = sanity_check($_REQUEST["rotate"]);
$base      = sanity_check($_REQUEST["base"]);
$search    = sanity_check($_REQUEST["search"]);
$thumbs    = sanity_check($_REQUEST["thumbs"]);
$slideshow = sanity_check($_REQUEST["slideshow"]);
$write	   = sanity_check($_REQUEST["write"]);
$file	   = sanity_check_array($_REQUEST["file"]);
$desc	   = sanity_check_array($_REQUEST["desc"]);
$random	   = sanity_check($_REQUEST["random"]);
$edit	   = sanity_check($_REQUEST["edit"]);

$EDIT = 0;
if (isset($edit) && ($edit == $EDIT_PW)) {
	$EDIT = 1;
}

/* check for malicious .. in $base input */
$PICTURE_ROOT = "pictures";
$BASEDIR = $PICTURE_ROOT . "/" . $base;
assert_path_ok($BASEDIR);
$THUMB_ROOT = $PICTURE_ROOT;

/****************************************************************************/
/* Check input for malicious intentions */
function sanity_check($string) {
  $decoded = urldecode($string);
  if (preg_match("/\.\./", $decoded)) {
    exit;
  }
  if (preg_match("/[;<>]/", $decoded)) {
    exit;
  }
  return $decoded;
}
/****************************************************************************/

/****************************************************************************/
/* Call the sanity_check() function on everything in an array */
function sanity_check_array($array) {
  for ($i=0; $i<sizeof($array); $i++) {
    $array[$i] = sanity_check($array[$i]);
  }
  return $array;
}
/****************************************************************************/


/****************************************************************************/
/* Check for .. in path get arguments */
function assert_path_ok($dir) {
	if (preg_match("/\.\./", $dir)) {
		exit;
	}
}
/****************************************************************************/


/****************************************************************************/
/* Allow for nesting albums, determing if this dir has non-hidden subdirs */
function has_subdir($dir) {
	assert_path_ok($dir);
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
			        if (is_dir("$dir/$file") && !preg_match("/^[_\.]/", $file)) {
					return 1;
				}
			}
			closedir($dh);
			return 0;
		}
		return 0;
	}	
	return 0;
}
/****************************************************************************/


/****************************************************************************/
/* Read and parse the description */
function get_description($path) {
	$path .= "/";
	$dirs = preg_split("/\//", $path);
	$current = array_shift($dirs);
	while (sizeof($dirs) > 0) {
		if ( file_exists("$current/description.txt") ) {
			$file = file("$current/description.txt");
			for ($i=0; $i<sizeof($file); $i++) {
				$line = preg_split("/\t+/", $file[$i], 2);
				$description[$line[0]] = rtrim($line[1]);
			}
		}
		$current = $current . "/" . array_shift($dirs);
	}
	return $description;
}
/****************************************************************************/


/****************************************************************************/
/* Given a filename, return true if has an image file extension */
function is_image($file) {
	if (	!preg_match("/^[\._]/", $file) && 
		( preg_match("/\.jpg$/i", $file) ||
		preg_match("/\.jpeg$/i", $file) ) 
	) {
		return 1;
	}
	return 0;
}
/****************************************************************************/


/****************************************************************************/
/* Given a filename, return true if has a video file extension */
function is_video($file) {
	if (	!preg_match("/^[\._]/", $file) && 
		(preg_match("/\.mpg$/i", $file) ||
		preg_match("/\.mpeg$/i", $file) ||
		preg_match("/\.mov$/i", $file) ||
		preg_match("/\.avi$/i", $file))
	) {
		return 1;
	}
	return 0;
}
/****************************************************************************/


/****************************************************************************/
/* Given an album, get a listing of the jpg's in that album */
function get_pictures_from_album($album) {
	global $BASEDIR;
	$pictures = array();
	assert_path_ok("$BASEDIR/$album");
        if ($dir = opendir("$BASEDIR/$album")) {
		while (($file = readdir($dir)) !== false) {
			if (is_image($file) || is_video($file)) {
				array_push($pictures, $file);
			}
		}
		sort($pictures);
	}
	return $pictures;
}
/****************************************************************************/


/****************************************************************************/
/* Remove temporary images older than 15 minutes */
function clean_tmp($dirname) {
	$dir = opendir($dirname);	
	while (($file = readdir($dir)) !== false) {
	// delete temp images that have not been accessed in the last 15 minutes
		if ( 
			is_file("tmp/$file") && 
			is_image($file) &&
			(date("U") - date("U", fileatime("tmp/$file")) > 15*60) 
		   ) {
			unlink("tmp/$file");	
		}
	}
	closedir($dir);
}
/****************************************************************************/


/****************************************************************************/
/* Make the shell call to create a resized tmp image, depends on convert */
function do_resize_picture($path_to_picture, $width, $height, $rotate) {
	$path_parts = preg_split("/\//", $path_to_picture);
	$file = array_pop($path_parts);
	$tempfilename = "tmp/$height" . "_" . $rotate . "_" . $file;
	if ( ! file_exists($tempfilename) && is_image($path_to_picture) ) {
		$size = $width . "x" . $height;
		$input = escapeshellarg($path_to_picture);
		`convert -size $size -resize x$height -rotate $rotate $input "$tempfilename"`;
	}
	return $tempfilename;
}
/****************************************************************************/


/****************************************************************************/
/* Find the index of a given value in an array */
function locate_index($needle, $haystack) {
	for ($i=0; $i<sizeof($haystack); $i++) {
		if ($needle == $haystack[$i]) {
			return $i;
		}
	}
	return -1;
}
/****************************************************************************/


/****************************************************************************/
/* Print html header information */
function print_header() {
	global $TITLE;
	print("
<html>
<head>
<script>
function surf(form) {
	var myindex = form.dest.selectedIndex;
	window.open(form.dest.options[myindex].value, \"_self\");
}
</script>
<style>
td {
	text-decoration: none; 
	font-size: 10px
}
a {
	text-decoration: none; 
	font-size: 10px
}
a:hover {
	text-decoration: underline; 
}
body {
	font-size: 10px; 
	font-family: verdana,arial,helvetica,sans-serif; 
	font-weight: 400; 
	color: #000000;
}
</style>
<title>$TITLE</title>
</head>
<body topmargin=0 leftmargin=0 rightmargin=0 bottommargin=0 bgcolor=#DDDDDD>
<table width=100% height=100%><tr><td align=center valign=center>
		<table width=600 border=1 cellspacing=0>
		  <tr>
		    <td bgcolor=#FFFFFF align=center>
<a href=/><big><b>$TITLE</b><big></a>
		    </td>
		  </tr>
		</table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print html footer information */
function print_footer() {
	global $PHOTOGRAPHER, $PHOTOGRAPHER_EMAIL;
	print("
		<table width=600 border=1 cellspacing=0>
		  <tr>
		    <td bgcolor=#FFFFFF align=center>
All images are &copy;1997-2008 <a href=mailto:$PHOTOGRAPHER_EMAIL>$PHOTOGRAPHER</a><br>
Copyright &copy; 2000-2008 <a href=mailto:dustin.kirkland@gmail.com>Dustin Kirkland</a>, the <a href=https://launchpad.net/pictor>Pictor Web Application</a> is free software under the <a href=gpl.txt>GPLv3</a>
		  </tr>
		</table>
</td></tr></table>
</body>
</html>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print search form */
function print_search_form() {
	global $base;
	print("
<table align=center>
  <tr>
    <td align=center>
      <form method=post>
        <input type=text name=search>
        <input type=submit name=find value=find>
	<input type=hidden base='$base'>
      </form>
    </td>
  </tr>
</table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Perform search and display results */
function do_search($search) {
	global $BASEDIR;
	global $PICTURE_ROOT;

	print("<table width=400 align=center bgcolor=#FFFFFF><tr><td>\n");
	print("<center><b>Search Results</b><br>\n");
	$terms = array();
	$terms = preg_split("/\s+/", $search);
	for ($i=0; $i<sizeof($terms); $i++) {
		print($terms[$i]);
		if ($i+1<sizeof($terms)) {
			print(" AND ");
		}
	}
	print("</center><br>\n");
	// Find matches in paths

	print("<hr><b>Matching Albums</b><br>\n");
	$BASEDIR = escapeshellarg($BASEDIR);
	$results = `find $BASEDIR -type d`;
	$paths = array();
	$paths = preg_split("/\n/", $results);
	for ($i=0; $i<sizeof($paths); $i++) {
		for ($j=0; $j<sizeof($terms); $j++) {
			$path_match = 1;
			if (!preg_match("/$terms[$j]/i", $paths[$i])) {
				$path_match = 0;
				break;
			}
		}
		if ($path_match && !preg_match("/\.thumbnails/", $paths[$i])) {
			$path = preg_replace("/^$PICTURE_ROOT\//", "", $paths[$i]);
			print("<a href=?album=" . urlencode($path) . ">$path</a><br>\n");
		}
	}

	// Find matches in descriptions
	print("<br><hr><b>Matching Descriptions</b><br>\n");
	$results = `find $BASEDIR -type f -name description.txt`;
	$descriptions = array();
	$descriptions = preg_split("/\n/", $results);
	for ($i=0; $i<sizeof($descriptions); $i++) {
		$escaped_descriptions[$i] = escapeshellarg($descriptions[$i]);
	}
	for ($i=0; $i<sizeof($terms); $i++) {
		$terms[$i] = escapeshellarg($terms[$i]);
	}
	$x = 0;
	print("<table cellspacing=6 border=0>\n");
	for ($i=0; $i<sizeof($escaped_descriptions); $i++) {
		$cmd = "grep -i $terms[0] $escaped_descriptions[$i]";
		for ($j=1; $j<sizeof($terms); $j++) {
			$cmd = $cmd . "  | grep -i $terms[$j]";
		}
		$results = `$cmd`;
		$path = preg_replace("/description\.txt$/", "", $descriptions[$i]);
		$path = preg_replace("/^$PICTURE_ROOT\//", "", $path);
		$matches = array();
		$matches = preg_split("/\n/", $results);
		for ($j=0; $j<sizeof($matches); $j++) {
			list ($file, $desc) = preg_split("/\t/", $matches[$j], 2);
			if ($path && $file && $desc && file_exists("$PICTURE_ROOT/$path/$file")) {
				if ($x % 3 == 0) { print("<tr>"); }
				print("<td align=center>");
				print_thumbnail($path, $file, "$path<br>$desc");
				print("<td>");
				if ($x % 3 == 2) { print("</tr>"); }
				$x++;
			}
		}
	}
	print("</table></td></tr></table>");
	print_search_form();
}
/****************************************************************************/


/****************************************************************************/
/* Print single thumbnail */
function print_thumbnail($path, $file, $desc) {
	global $THUMB_ROOT;
	global $PICTURE_ROOT;
	$href = "?album=" . urlencode($path) . "&picture=" . urlencode($file);
	print("<table border=1 height=100 width=100 bgcolor=white><tr><td align=center valign=center><a href='$href'>");
	if (is_image($file)) {
		print("<img border=0 src='$THUMB_ROOT/$path/.thumbnails/$file'>");
	} elseif (is_video($file)) {
		print("<big>video clip</big><br>" . round(filesize("$PICTURE_ROOT/$path/$file")/1024) . " KB");
	}
	print("</td><tr>");
	if ($desc) {
		print("<tr><td align=center>$desc</a></td></tr>");
	}
	print("</table>\n");

}
/****************************************************************************/


/****************************************************************************/
/* List albums */
function do_list_albums($base) {
	global $BASEDIR;
	global $COLUMNS;

	if ($dir = opendir("$BASEDIR")) {
		$i = 0;
		while (($file = readdir($dir)) !== false) {
			$files[$i++] = $file;
		}
		closedir($dir);
		sort($files);
		if ($base) {
			$header = preg_replace("/^\//", "", $base);
			$header = preg_replace("/\//", " | ", $header);
		} else {
			$header = "All Albums";
		}
		print("
<table border=2 cellspacing=0 cellpadding=10 align=center>
  <tr>
    <td bgcolor=#EEEEEE>
      <table border=2 cellspacing=4 cellpadding=2 align=center width=90%>
        <tr>
          <th colspan=$COLUMNS bgcolor=#DDDDDD>$header</th>
        </tr>
		");
		$count = 0;
		for ($i=0; $i<sizeof($files); $i++) {
			$file = $files[$i];
			if (
					is_dir("$BASEDIR/$file") && 
					(!preg_match("/^[_\.]/", $file))
			   ) {
				if (has_subdir("$BASEDIR/$file")) { 
					$href = "?base=" . urlencode("$base/$file");
				} else {
					$href = "?album=" . urlencode("$base/$file") . "&thumbs=1";
				}
				if ( ($count % $COLUMNS) == 0 ) {
					print("
        <tr>
					");
				}
				print("
          <td bgcolor=#FFFFFF align=center><a href=$href>$file</a></td>
				");
				if ( (($count+1) % $COLUMNS) == 0 ) {
					print("
        </tr>
					");
				}
				$count++;
			}
		}
		print("
      </table>
    </td>
  </tr>
</table>
		");
	}
	print_search_form();
	// if only one option, go straight to it
	if ($count == 1) 
		print("<meta http-equiv='refresh' content='0;url=$href'>");
}
/****************************************************************************/


/****************************************************************************/
/* Print album thumbails */
function print_thumbnails($album) {
	global $BASEDIR, $EDIT;
	global $THUMB_COLUMNS;
	print_upper_banner($album, "", 600);
	print("<br>\n");
	$pictures = get_pictures_from_album($album);
	$description = get_description("$BASEDIR/$album");
	$tab = 1;
	print("<table width=100%><tr><td><center>\n");
	if ($EDIT == 1) {
		print("<table><form method=post><input type=hidden name=write value=1>\n");
		$i = sizeof($pictures);
		print("<tr><td><b>Title</b><td><input type=hidden name=file[$i] value=description><input type=text size=30 name=desc[$i] value='$description[description]' tabindex=$tab></td></tr>\n"); $tab++;
		$i++;
		print("<tr><td><b>City</b><td><input type=hidden name=file[$i] value=city><input type=text size=30 name=desc[$i] value='$description[city]' tabindex=$tab></td></tr>\n"); $tab++;
		$i++;
		print("<tr><td><b>State</b><td><input type=hidden name=file[$i] value=state><input type=text size=30 name=desc[$i] value='$description[state]' tabindex=$tab></td></tr>\n"); $tab++;
		$i++;
		print("<tr><td><b>Country</b><td><input type=hidden name=file[$i] value=country><input type=text size=30 name=desc[$i] value='$description[country]' tabindex=$tab></td></tr>\n"); $tab++;
		print("</table>");
	} else {
		$edit = "";
	}
	print("<table cellspacing=6>\n");
	for ($i=0; $i<sizeof($pictures); $i++) {
		$desc = $description[$pictures[$i]];
		if ($i % $THUMB_COLUMNS == 0) { print("<tr>"); }
		if ($EDIT == 1) {
			$edit = "<br><input type=hidden name=file[$i] value='$pictures[$i]'><input type=text size=20 value='$desc' name=desc[$i] tabindex=$tab>"; $tab++;
		}
		print("<td align=center>");
		print_thumbnail($album, $pictures[$i], $EDIT ? 0 : $desc);
		print("$edit</td>");
		if ($i % $THUMB_COLUMNS == ($THUMB_COLUMNS-1)) { print("</tr>"); }
	}
	print("</table>");
	if ($EDIT == 1) {
		print("<input type=submit value=Submit tabindex=$tab></form>");
	}
	print("</center></td></tr></table>");
}
/****************************************************************************/


/****************************************************************************/
/* Write description file */
/* Descriptions are typically written to description.txt
   Descriptions written via the web interface are written to
   description.txt which must be chowned to apache:apache
*/
function do_write_descriptions($album, $file, $desc) {
	print("<table bgcolor=white border=1><tr><td align=left><pre>\n");
	print("FILENAME: $album/description.txt<br><br>\n");
	if (!$fh = fopen("pictures/$BASEDIR/$album/description.txt", "w")) {
		print("Cannot open [$BASEDIR/$album/description.txt]\n");
		exit;
	}
	for ($i=0; $i<sizeof($file); $i++) {
		if ($desc[$i]) {
			print("$file[$i]\t\t$desc[$i]\n");
			fwrite($fh, "$file[$i]\t\t$desc[$i]\n");
		}
	}
	fclose($fh);
	print("</pre></td></tr></table>");
	print("<br><a href=index.html>index</a> | <a href=?thumbs=1&album=". urlencode($album) . ">thumbs</a> | <a href=?album=" . urlencode($album) . ">flipbook</a><br><br>");
}
/****************************************************************************/


/****************************************************************************/
/* Print rotate form */
function build_rotate_form($album, $picture, $width, $rotate) {
	$rotatearray = array("0", "90", "-90");
	$form = "<form name=rotateform><select name=dest onchange=javascript:surf(this.form)>\n";
	for ($i=0; $i<sizeof($rotatearray); $i++) {
		$thisrotate = $rotatearray[$i];
		if ($rotate == $thisrotate) {
			$form .= "<option selected value>$rotate</option>\n";
		} else {
			$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($picture) . "&width=" . urlencode($width) . "&rotate=" . urlencode($thisrotate) . "'>$thisrotate</option>\n";
		}
	}
	$form .= "</select></form>";
	return $form;
}
/****************************************************************************/


/****************************************************************************/
/* Print resize form */
function build_resize_form($path_to_picture, $album, $picture, $width, $rotate) {
	list($thiswidth, $thisheight, $thistype, $thisattr) = getimagesize($path_to_picture);
	$sizearray = array("160", "400", "640", "800", "1024", "1280", $thiswidth);
	$form = "<form name=resizeform><select name=dest onchange=javascript:surf(this.form)>\n"; 
	for ($i=0; $i<sizeof($sizearray); $i++) {
		$thiswidth = $sizearray[$i];
		$height = $thiswidth * 3 / 4;
		if ($width == $thiswidth) {
			$form .= "<option selected value>$width" . "x" ."$height</option>\n";
		} else {
			$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($picture) . "&width=" . urlencode($thiswidth) . "'>$thiswidth" . "x" . "$height</option>\n";
		}
	}
	$form .= "</select></form>";
	return $form;
}
/****************************************************************************/


/****************************************************************************/
/* Print picture form */
function build_picture_form($album, $picture, $width, $slideshow, $pictures, $description) {
	global $BASEDIR;
	$form = "<form name=picform><select name=dest size=1 onchange=javascript:surf(this.form)>\n";
	if (is_dir("$BASEDIR/$album")) {
		$i = 0;
		for ($i=0; $i<sizeof($pictures); $i++) {
			$file = $pictures[$i];
			if ($description[$file]) {
				$clean = substr($description[$file], 0, round($width/13));
			} else {
				$clean = preg_replace("/\.jpg$/i", "", $file);
				$clean = preg_replace("/[\-\_]/", " ", $clean);
				$clean = preg_replace("/&/", " and ", $clean);
			}
			if ($file == $picture) {
				$currentindex = $i;
				$form .= "<option selected value>$clean</option>\n";
			} else {
				$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($file) . "'>$clean</option>\n";
			}
			$temp = "<a href='?album=" . urlencode($album) . "&picture=" . urlencode($file) . "&width=$width'><b>&lt; Back</b></a>";
		}
	}
	$form .= "</select></form>\n";
	return $form;
}
/****************************************************************************/


/****************************************************************************/
/* Determine the link to the next picture in the flipbook */
function get_next_link($album, $width, $pictures, $currentindex, $slideshow) {
	if ($currentindex == sizeof($pictures) - 1) {
		return "&nbsp;";
	} else {
		$next = $pictures[$currentindex+1];
		$url = "?album=" . urlencode($album) . "&picture=" . urlencode($next) . "&width=$width";
	}
	if ($slideshow > 0) {
		print("<meta http-equiv='refresh' content='$slideshow;url=$url&slideshow=$slideshow'>\n");
	}
	$next = "<table border=1 cellspacing=0 bgcolor=white><tr><td><a href='$url'><b>Next &gt;</b></a></td></tr></table>";
	return $next;
}

function get_back_link($album, $width, $pictures, $currentindex) {
	if ($currentindex != 0) {
		$back = $pictures[$currentindex-1];
		$url = "?album=" . urlencode($album) . "&picture=" . urlencode($back) . "&width=$width";
		$back = "<table border=1 cellspacing=0 bgcolor=white><tr><td><a href='$url'><b>&lt; Back</b></a></td></tr></table>";
		return $back;
	} else {
		return "&nbsp;";
	}
}
/****************************************************************************/


/****************************************************************************/
/* Get exif data */
function get_exif_hash($path_to_picture) {
	$shell_arg = escapeshellarg($path_to_picture);
	$exif = shell_exec("jhead $shell_arg");
	$lines = preg_split("/\n/", $exif);
	$exif_hash = array();
	for ($i=0; $i<sizeof($lines); $i++) {
		list($key, $value) = preg_split("/\s*:\s*/", $lines[$i], 2);
		$exif_hash["$key"] = "$value"; 
	}
	return $exif_hash;
}
/****************************************************************************/


/****************************************************************************/
/* Print data cell */
function print_data_cell($key, $value) {
	if ($value) {
		print("<tr><td bgcolor=#EEEEEE>$key</td><td bgcolor=white>$value</td></tr>");
	}
}
/****************************************************************************/


/****************************************************************************/
/* Print exif data */
function print_exif_data($path_to_picture, $description) {
	$exif = array();
	$exif = get_exif_hash($path_to_picture);
	$keys = array();
	$values = array();
	array_push($keys, "File name"); array_push($values, basename($path_to_picture));
	array_push($keys, "File size"); array_push($values, round(filesize($path_to_picture)/1024)." KB");
	array_push($keys, "Resolution"); array_push($values, $exif["Resolution"]);
	array_push($keys, "Date/Time"); array_push($values, $exif["Date/Time"]);
	array_push($keys, "Camera model"); array_push($values, $exif["Camera model"]);
	$city = $description[city];
	$state = $description[state];
	$country = $description[country];
	$loc = "<a href=http://maps.google.com/maps?hl=en&q=" . urlencode(strtolower($city).",".strtolower($state)) . ">$city, $state, $country</a>";
	$date = $exif["Date/Time"];
	array_push($keys, "Location"); array_push($values, $loc);
//	array_push($keys, "Airport"); array_push($values, get_airport($city, $state, $country));
//	array_push($keys, "Weather"); array_push($values, get_weather($date, $city, $state, $country));
	
	print("<table border=0 cellspacing=5><tr valign=top><td>");
	print("<table border=1 cellspacing=0>");
	for ($i=0; $i<sizeof($keys); $i++) {
		print_data_cell($keys[$i], $values[$i]);
	}
	print("</table></td><td>");
	$keys = array("Flash used", "Focal length", "Exposure time", "Aperture", "ISO equiv.", "Whitebalance", "Metering Mode", "Exposure");
	print("<table border=1 cellspacing=0>");
	for ($i=0; $i<sizeof($keys); $i++) {
		print_data_cell($keys[$i], $exif[$keys[$i]]);
	}
	print("</table></td></tr></table>");
}	
/****************************************************************************/

function get_airport($city, $state="", $country="") {
	include_once("codes.php");
	if (!$country && !$state) { $country = "USA"; $state = "TX"; }
	$city = strtoupper($city);
	$state = strtoupper($state);
	$country = strtoupper($country);
	return $code["$country"]["$state"]["$city"];
}

function get_weather($date, $city, $state="", $country="") {
	$airport = "K" . get_airport($city, $state, $country);
	list($date, $time) = preg_split("/\s+/", $date);
	$date = preg_replace("/:/", "-", $date);
	if (!preg_match("/[A-Za-z]/", $date) && $airport) {
		switch (sizeof(preg_split("/[\-:]/", $date))) {
			case 3:
				$history = "DailyHistory.html";
				break;
			case 2:
				$history = "01/MonthlyHistory.html";
				break;
		}
		if ($history) {
			$weatherhref = "http://www.wunderground.com/history/airport/" . $airport . "/" . preg_replace("/\-/", "/", $date) . "/$history";
			return "<a href=$weatherhref>$date</a>";
		}
	}
}


/****************************************************************************/
/* Print picture details */
function print_picture_details($path_to_picture, $currentindex, $total, $width, $description) {
	print_exif_data($path_to_picture, $description);
}
/****************************************************************************/


/****************************************************************************/
/* Print upper banner */
function print_upper_banner($album, $description, $width) {
	$descr = preg_replace("/^\//", "", $album);
	$path_parts = preg_split("/\/+/", $descr);
	$descr = "";
	for ($i=0; $i<sizeof($path_parts)-1; $i++) {
		$subalbum .= "/" . $path_parts[$i];
		$descr .= "<a href=?base=" . urlencode($subalbum) . ">$path_parts[$i]</a> - ";
	}
	$subalbum .= "/" . $path_parts[$i];
	$descr .= "<a href=?album=" . urlencode($subalbum) . ">$path_parts[$i]</a>";
	if ($description) {
		$descr .= " - $description";
	}
	print("
		<table border=1 cellspacing=0 align=center width=$width bgcolor=#FFFFFF>
		  <tr align=center>
		    <td><b>$descr</b></td>
		  </tr>
		</table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print upper toolbar */
function print_upper_toolbar($album, $description, $back, $next, $width) {
	print_upper_banner($album, $description, $width);
	print("
		<table border=0 align=center width=100% bgcolor=#DDDDDD>
		  <tr align=center>
		    <td width=33%>$back</td>
		    <td width=34%><table border=1 bgcolor=white width=100% cellspacing=0><tr><td align=center>
		      <a href=" . $_SERVER[PHP_SELF] . ">index</a> | 
		      <a href='?album=" . urlencode($album) . "&thumbs=1'>thumbs</a> | 
		      <a href='?album=" . urlencode($album) . "&width=$width&slideshow=4'>slideshow</a>
		      </td></tr></table>
		    </td>
		    <td width=33%>$next</td>
		  </tr>
		</table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print picture img */
function print_picture($path_to_picture, $temp, $height, $alt) {
	if (!is_file($temp)) {
		$temp = $path_to_picture;
	}
	print("<a href='$path_to_picture'>");
	if (is_image($path_to_picture)) { 
		print("<img border=0 src='$temp' height=$height alt='$alt'>");
	} elseif (is_video($path_to_picture)) {
		print("<embed src='$path_to_picture' name='Video clip' loop='false' cache='true' width=400 height=300 controller='true' autoplay='true'></embed>");
	}
	print("</a>");
}
/****************************************************************************/

/****************************************************************************/
/* Print picture img */
function print_picture_in_table($path_to_picture, $temp, $height, $alt) {
	print("
		<table border=0 bgcolor=#DDDDDD width=100%><tr><td><table border=10 cellspacing=0 cellpadding=0 align=center>
		  <tr>
		    <td>
	");
	print_picture($path_to_picture, $temp, $height, $alt);
	print("
		    </td>
		  </tr>
		</table></td></tr></table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print lower toolbar */
function print_lower_toolbar($resizeform, $picform, $rotateform, $width) {
	print("
		<table border=0 align=center width=$width bgcolor=#DDDDDD>
		  <tr align=center>
		    <td>$resizeform</td>
		    <td>$picform</td>
		    <td>$rotateform</td>
		  </tr>
		</table>
	");
}

/****************************************************************************/


/****************************************************************************/
/* Display the flipbook page with the picture and all navigation tools */
function do_flipbook_page($album, $picture, $width, $rotate, $slideshow) {
	global $_SERVER, $BASEDIR;
	if (!$width) { 
		if ( $_SERVER["HTTP_UA_PIXELS"] ) {
			list ($width, $trash) = preg_split("/x/", $_SERVER["HTTP_UA_PIXELS"], 2);
		} else {
			$width = "640"; 
		}
	}
	$height = 3/4 * $width;
	if (!$rotate) {
		$rotate = "0";
	}
	$pictures = array();
	$pictures = get_pictures_from_album($album);
	if (!$picture) {
		$picture = $pictures[0];
	}

	$descriptions = get_description("$BASEDIR/$album");
	$picform = build_picture_form($album, $picture, $width, $slideshow, $pictures, $descriptions);
	$path_to_picture = $BASEDIR . "/" . $album . "/" . $picture;
	$path_to_picture = preg_replace("/\/+/", "/", "$path_to_picture");
	$rotateform = build_rotate_form($album, $picture, $width, $rotate);
	$resizeform = build_resize_form($path_to_picture, $album, $picture, $width, $rotate);

	clean_tmp("tmp/");
	$tempfilename = do_resize_picture($path_to_picture, $width, $height, $rotate);
	$currentindex = locate_index($picture, $pictures);
	$total = sizeof($pictures);
	$next = get_next_link($album, $width, $pictures, $currentindex, $slideshow);
	$back = get_back_link($album, $width, $pictures, $currentindex);

	print_upper_toolbar($album, $descriptions[$picture], $back, $next, $width);
	print_picture_in_table($path_to_picture, $tempfilename, $height, $alt);
	print_lower_toolbar($resizeform, $picform, $rotateform, $width);
	print_picture_details($path_to_picture, $currentindex, $total, $width, $descriptions);
}
/****************************************************************************/

/****************************************************************************/
/* Display a simple slideshow page, without navigation tools */
function do_slideshow_page($album, $picture, $width, $slideshow) {
	global $_SERVER, $BASEDIR;
	$rotate = "0";
	$height = "768";
	if ($width) {
		$height = 3/4*$width;
	}
	$height *= 0.99;
	$pictures = array();
	$pictures = get_pictures_from_album($album);
	if (!$picture) { $picture = $pictures[0]; }
	$path_to_picture = $BASEDIR . "/" . $album . "/" . $picture;
	$path_to_picture = preg_replace("/\/+/", "/", "$path_to_picture");
	clean_tmp("tmp/");
	$tempfilename = do_resize_picture($path_to_picture, $width, $height, $rotate);
	print_picture($path_to_picture, $tempfilename, $height, $alt);
	$currentindex = locate_index($picture, $pictures);
	$next = $pictures[$currentindex+1];
	$url = "?album=" . urlencode($album) . "&picture=" . urlencode($next) . "&width=$width";
	print("<meta http-equiv='refresh' content='$slideshow;url=$url&slideshow=$slideshow'>\n");
}
/****************************************************************************/

/****************************************************************************/
/* Randomly suggest a URL to an appropriate picture from the collection	*/
function do_random() {
	global $RAND_ALBUMS;
	$pictures = array();
	for ($i=0; $i<sizeof($RAND_ALBUMS); $i++) {
		$dh = opendir($RAND_ALBUMS[$i]);
		while (($file = readdir($dh)) !== false) {
			if (is_image($file)) {
				array_push($pictures, "$RAND_ALBUMS[$i]/$file");
			}
		}
		closedir($dh);
	}
	print("http://" . $_SERVER["HTTP_HOST"] . 
		"/" . $pictures[array_rand($pictures)]. "\n");
}
/****************************************************************************/



/****************************************************************************/
/****************************************************************************/
/* Main */

if ($random) {
	do_random();
	exit;
} elseif ($slideshow) {
	do_slideshow_page($album, $picture, $width, $slideshow);
	exit;
}

print_header();
if ($write) {
	do_write_descriptions($album, $file, $desc);
} elseif ($search) {
	do_search($search);
} elseif (!$album) {
	do_list_albums($base);
} elseif ($thumbs) {
	print_thumbnails($album);
} else {
	do_flipbook_page($album, $picture, $width, $rotate, $slideshow);
}
print_footer();
/****************************************************************************/
/****************************************************************************/
?>
