<?php

/*
 *  pictor: a web application for sharing, viewing, and organizing pictures
 *  Copyright (C) 1997-2013 Dustin Kirkland <dustin.kirkland@gmail.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, version 3 of the License.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.

 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/* Configurable options */
include_once("/etc/pictor/settings.php");

/* variables that may come in through an http GET request */
$album       = sanity_check("album");
$picture     = sanity_check("picture");
$FILTER      = sanity_check("filter");
$width       = sanity_check_number("width");
$rotate      = sanity_check_number("rotate");
$base        = sanity_check("base");
$thumbs      = sanity_check_number("thumbs");
$slideshow   = sanity_check_number("slideshow");
$write       = sanity_check("write");
$file        = sanity_check_array("file");
$desc        = sanity_check_array("desc");
$random      = sanity_check_number("random");
$screensaver = sanity_check_number("screensaver");

if (!isset($DEFAULT_WIDTH)) {
	$DEFAULT_WIDTH = 800;
}

/* check for malicious .. in $base input */
$PICTURE_ROOT = "pictures";
$BASEDIR = $PICTURE_ROOT . "/" . $base;
assert_path_ok($BASEDIR);
$THUMB_ROOT = $PICTURE_ROOT;

/****************************************************************************/
/* Check input for malicious intentions */
function sanity_check($string) {
	global $_REQUEST;
	$decoded = "";
	if (isset($_REQUEST[$string])) {
		$decoded = urldecode($_REQUEST[$string]);
	}
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
/* Ensure input is a number, and non-malicious */
function sanity_check_number($input) {
	$input = sanity_check($input);
	if (empty($input)) {
		return "";
	}
	if (is_numeric($input)) {
		return intval($input);
	}
	exit;
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
/* Allow for nesting albums, determing if this dir has non-hidden images */
function has_images($dir) {
	assert_path_ok($dir);
	if (is_dir($dir)) {
		if ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				if (is_image_filename("$dir/$file")) {
					closedir($dh);
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
function is_image_filename($file) {
	if (
		!preg_match("/^[\._]/", $file) &&
		( preg_match("/\.jpg$/i", $file) ||
		  preg_match("/\.png$/i", $file) ||
		  preg_match("/\.jpeg$/i", $file)
		)
	) {
		return 1;
	}
	return 0;
}
/****************************************************************************/

/****************************************************************************/
/* Given a filename, return true if file is an image according to exif data */
function is_image($file) {
	if (is_image_filename($file) && exif_imagetype($file)) {
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
	global $BASEDIR, $FILTER;
	$pictures = array();
	assert_path_ok("$BASEDIR/$album");
        if ($dir = opendir("$BASEDIR/$album")) {
		while (($file = readdir($dir)) !== false) {
			if (is_image_filename("$BASEDIR/$album/$file") || is_video("$BASEDIR/$album/$file")) {
				if (preg_match("/$FILTER/", $file)) {
					array_push($pictures, $file);
				}
			}
		}
		sort($pictures);
	}
	return $pictures;
}
/****************************************************************************/

/****************************************************************************/
/* Remove temporary directories older than $max_age seconds */
/* Ideally, we'd support a cap size of the cache and enforce that */
function clean_tmp($dirname) {
	$max_age = 60*60;
	$dir = opendir($dirname);
	while (($i = readdir($dir)) != false) {
		if ($i == "." || $i == "..") {
			continue;
		}
		// delete temp images that have not been accessed in the last $max_age seconds
		$subdirname = "$dirname/$i";
		$subdir = opendir($subdirname);
		while (($j = readdir($subdir)) !== false) {
			if ($j == "." || $j == ".." || (date("U") - date("U", fileatime("$subdirname")) < $max_age)) {
				continue;
			}
			$filename = "$subdirname/$j";
			if ( is_file("$filename") ) {
				@unlink("$filename");
			}
		}
		@rmdir("$subdirname");
	}
	closedir($dir);
}
/****************************************************************************/

/****************************************************************************/
/* Resize an image, returns temp file name, depends on php5-imagick */
function do_resize_picture($path_to_picture, $width, $height, $rotate) {
	$path_parts = preg_split("/\//", $path_to_picture);
	$file = array_pop($path_parts);
        $tempfilename = get_cache_filename($height . "_" . $rotate . "_" . $file, "resize");
	if ( ! file_exists($tempfilename) && is_image($path_to_picture) ) {
		$size = $width . "x" . $height;
		$input = escapeshellarg($path_to_picture);
		try {
			$img = new Imagick($path_to_picture);
			$img->scaleImage($width, $height, true);
			if ($rotate != 0) {
				$img->rotateImage(new ImagickPixel(), $rotate);
			}
			$img->writeImage($tempfilename);
			$img->destroy();
			rotate_if_necessary($path_to_picture, $tempfilename);
		} catch (Exception $e) {
			$tempfilename = "";
		}

	}
	clean_tmp("tmp/resize");
	return $tempfilename;
}
/****************************************************************************/


/****************************************************************************/
/* Transcode a video, returns temp file name, depends on libav-tools */
function do_transcode_video($path) {
	$path_parts = preg_split("/\//", $path);
	$file = array_pop($path_parts);
        $tempfilename = get_cache_filename($file, "transcode");
	if (is_video($path)) {
		if (! file_exists($tempfilename)) {
			$input = escapeshellarg($path);
			try {
				print("<meta http-equiv='refresh' content='20'>");
				shell_exec("HOME=/var/cache/pictor/ run-one avconv -i " . escapeshellarg("$path") . " -vcodec libx264 -strict experimental " . escapeshellarg("$tempfilename"));
			} catch (Exception $e) {
				$tempfilename = "";
			}
		}
		//clean_tmp("tmp/transcode");
		return $tempfilename;
	}
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
function print_header($body=1) {
	global $TITLE;
	print("
<html>
<head>
<script>
function gotourl(url) {
	window.open(url, \"_self\");
}
function goto(form) {
	var myindex = form.dest.selectedIndex;
	window.open(form.dest.options[myindex].value, \"_self\");
}
</script>
<style>
td {
	text-decoration: none;
	font-size: 8px
	border: 1px black;
	-moz-border-radius: 10px;
	border-radius: 10px;
}
a {
	text-decoration: none;
	font-size: 10px
}
a:hover {
	text-decoration: underline;
}
body {
	font-size: 8px;
	font-family: verdana,arial,helvetica,sans-serif;
	font-weight: 400;
	color: black;
}
</style>
<title>$TITLE</title>
<link rel='shortcut icon' href='/pictor/favicon.ico' type='image/x-icon'>
</head>
	");
	if ($body == 1) {
		print("
<body topmargin=0 leftmargin=0 rightmargin=0 bottommargin=0 bgcolor=#101010>
<table width=100% height=100%><tr><td align=center valign=center>
		");
	}
}
/****************************************************************************/


/****************************************************************************/
/* Print html footer information */
function print_footer() {
	global $LICENSE;
	print("
		<table border=0 cellspacing=0 width=400 align=center>
		  <tr>
		    <td bgcolor=#FFFFFF align=center><small>
$LICENSE<br>
<small><a href='https://launchpad.net/pictor'>Pictor</a> is <a href='agpl-3.0.txt'>AGPLv3</a> free software, Copyright &copy; 1997-2013 <a href='http://blog.dustinkirkland.com'>Dustin Kirkland</a>.</small>
		    </small></td>
		  </tr>
		</table>
</td></tr></table>
</body>
</html>
	");
}
/****************************************************************************/


function rotate_if_necessary($input, $output) {
	if ($exif = @exif_read_data($input)) {
		if ($exif["Orientation"] == 6 || $exif["Orientation"] == 8) {
			$img = new Imagick($output);
			switch($exif["Orientation"]) {
				case 6: $rotate = 90; break;
				case 8: $rotate = -90; break;
				default: $rotate = 0; break;
			}
			$img->rotateImage(new ImagickPixel(), $rotate);
			$img->writeImage($output);
			$img->destroy();
		}
	}
}

function get_cache_filename($filename, $dir) {
	$md5 = md5($filename);
	$cache_filename = "tmp/$dir/" . substr($md5, 0, 2);
	@mkdir($cache_filename);
	if ("$dir" == "transcode") {
		return "$cache_filename/$md5.mp4";
	} else {
		return "$cache_filename/$md5.jpg";
	}
}

/****************************************************************************/
/* Print single thumbnail */
function print_thumbnail($path, $file, $desc) {
	global $THUMB_ROOT;
	global $PICTURE_ROOT;
	$filename = "$PICTURE_ROOT/$path/$file";
	$thumbnail_name = get_cache_filename($filename, "thumbnails");
	$href = "?album=" . urlencode($path) . "&picture=" . urlencode($file);
	print("<a title='$desc' href='$href'>");
	if (is_image_filename($filename)) {
		if (! file_exists($thumbnail_name)) {
			// No thumbnail in cache.
			if ($img = @exif_thumbnail($filename)) {
				// Try to extract thumbnail from the image.
				$fh = fopen($thumbnail_name, "w");
				fwrite($fh, $img);
				fclose($fh);
			} else {
				try {
					// Otherwise, try Imagick.
					$img = new Imagick($filename);
					$img->scaleImage(150, 150, true);
					$img->writeImage($thumbnail_name);
					$img->destroy();
				} catch (Exception $e) {
					print("");
				}
			}
			rotate_if_necessary($filename, $thumbnail_name);
		}
		print("<img height=130 align=center border=0 src='$thumbnail_name'></a>&nbsp;");
	} elseif (is_video($file)) {
		print("<img width=32 src=silk/film.png>");
	}
}
/****************************************************************************/


/****************************************************************************/
/* List albums */
function do_list_albums($base) {
	global $BASEDIR;
	global $ALBUM_COLUMNS;
	if ($dir = @opendir($BASEDIR . "/" . $base)) {
		$i = 0;
		while (($file = readdir($dir)) !== false) {
			$files[$i++] = $file;
		}
		closedir($dir);
		sort($files);
		if ($base) {
			$header = preg_replace("/^\//", "", $base);
			$header = preg_replace("/\//", " - ", $header);
		} else {
			$header = "All Albums";
			print_upper_banner("", "", 600);
		}
		print("
<table border=0 cellspacing=0 cellpadding=10 align=center>
  <tr>
    <td bgcolor=#EEEEEE>
      <table border=0 cellspacing=4 cellpadding=2 align=center>
        <tr>
          <td colspan=$ALBUM_COLUMNS bgcolor=#DDDDDD align='center'><b>$header</b></td>
        </tr>
		");
		$count = 0;
		for ($i=0; $i<sizeof($files); $i++) {
			$file = $files[$i];
			if ( is_dir("$BASEDIR/$base/$file") && (!preg_match("/^[_\.]/", $file))) {
				$href = "?album=" . urlencode("$base/$file") . "&thumbs=1";
				if ( ($count % $ALBUM_COLUMNS) == 0 ) {
					print("
        <tr>
					");
				}
				print("
          <td bgcolor=white onMouseOver=this.bgColor='lightblue' onMouseOut=this.bgColor='white' onClick=javascript:goto('$href') align=center><a href='$href'>" . htmlspecialchars($file) . "</a></td>
				");
				if ( (($count+1) % $ALBUM_COLUMNS) == 0 ) {
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
	} else {
		print("<table cellpadding=20><tr><td bgcolor=#EEEEEE><br><b>ERROR</b><br>No pictures found.<br><br>Create a symlink to your pictures folder at<pre>" . dirname($_SERVER["SCRIPT_FILENAME"]) . "/pictures</pre></td></tr></table>");
                exit;
	}
	// if only one option, go straight to it
	if ($count == 1)
		print("<meta http-equiv='refresh' content='0;url=$href'>");
}
/****************************************************************************/


/****************************************************************************/
/* Print album thumbails */
function print_thumbnails($album) {
	global $BASEDIR;
	print("<br>\n");
	$pictures = get_pictures_from_album($album);
	$description = get_description("$BASEDIR/$album");
	$tab = 1;
	do_list_albums($album);
	print("<table align=center><tr><td bgcolor=#888888><center>\n");
	for ($i=0; $i<sizeof($pictures); $i++) {
		if (isset($description[$pictures[$i]])) {
			$desc = $description[$pictures[$i]];
		} else {
			$desc = "";
		}
		print_thumbnail($album, $pictures[$i], $desc);
	}
	print("</center></td></tr></table>");
}
/****************************************************************************/


/****************************************************************************/
/* Write description file */
/* Descriptions are typically written to description.txt
   Descriptions written via the web interface are written to
   description.txt which must be chowned to www-data:www-data
*/
function do_write_descriptions($album, $file, $desc) {
	print("<table bgcolor=white border=0><tr><td align=left><pre>\n");
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
	print("<br><a href='?'>index</a> | <a href='?thumbs=1&album=". urlencode($album) . "'>thumbs</a> | <a href='?album=" . urlencode($album) . "'>flipbook</a><br><br>");
}
/****************************************************************************/


/****************************************************************************/
/* Print rotate form */
function build_rotate_form($album, $picture, $width, $rotate) {
	$rotatearray = array("0", "90", "-90");
	$form = "<form name=rotateform><select name=dest onchange=javascript:goto(this.form)>\n";
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
function build_resize_form($path_to_picture, $album, $picture, $width, $extra) {
	list($thiswidth, $thisheight, $thistype, $thisattr) = getimagesize($path_to_picture);
	$sizearray = array("160", "400", "640", "800", "1024", "1920", $thiswidth);
	$form = "<form name=resizeform><select name=dest onchange=javascript:goto(this.form)>\n";
	for ($i=0; $i<sizeof($sizearray); $i++) {
		$thiswidth = $sizearray[$i];
		$height = $thiswidth * 3 / 4;
		if ($width == $thiswidth) {
			$form .= "<option selected value>$width" . "x" ."$height</option>\n";
		} else {
			$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($picture) . "&width=" . urlencode($thiswidth) . "&$extra'>$thiswidth" . "x" . "$height</option>\n";
		}
	}
	$form .= "</select></form>";
	return $form;
}
/****************************************************************************/


/****************************************************************************/
/* Print delay form */
function build_delay_form($path_to_picture, $album, $picture, $width, $delay) {
//	$form = "<form name=delayform><select name=delay onchange=javascript:goto(this.form)>\n";
	$form = "<form name=delayform><select name=dest onchange=javascript:goto(this.form)\n";
	for ($i=0; $i<10; $i++) {
		if ($i == $delay) {
			$form .= "<option selected value='?album=" . urlencode($album) . "&picture=" . urlencode($picture) . "&width=" . urlencode($width) . "&slideshow=$i'>$i</option>\n";
		} else {
			$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($picture) . "&width=" . urlencode($width) . "&slideshow=$i'>$i</option>\n";
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
	$form = "<form name=picform><select name=dest size=1 onchange=javascript:goto(this.form)>\n";
	if (is_dir("$BASEDIR/$album")) {
		$i = 0;
		for ($i=0; $i<sizeof($pictures); $i++) {
			$file = $pictures[$i];
			if (isset($description[$file])) {
				$clean = substr($description[$file], 0, round($width/13));
			} else {
				$clean = preg_replace("/\.jpg$/i", "", $file);
				$clean = preg_replace("/[\-\_]/", " ", $clean);
				$clean = preg_replace("/&/", " and ", $clean);
				$clean = htmlspecialchars($clean);
			}
			if ($file == $picture) {
				$currentindex = $i;
				$form .= "<option selected value>$clean</option>\n";
			} else {
				$form .= "<option value='?album=" . urlencode($album) . "&picture=" . urlencode($file) . "'>$clean</option>\n";
			}
		}
	}
	$form .= "</select></form>\n";
	return $form;
}
/****************************************************************************/


/****************************************************************************/
/* Determine the link to the next picture in the flipbook */
function get_next_link($album, $width, $pictures, $currentindex, $slideshow) {
	global $NEXT;
	if ($currentindex == sizeof($pictures) - 1) {
		return "&nbsp;";
	} else {
		$next = $pictures[$currentindex+1];
		$NEXT = "?album=" . urlencode($album) . "&picture=" . urlencode($next) . "&width=$width";
	}
	if ($slideshow > 0) {
		print("<meta http-equiv='refresh' content='$slideshow;url=$NEXT&slideshow=$slideshow'>\n");
	}
	$next = "<table border=0><tr><td bgcolor=white><a href='$NEXT'><b>Next <img src=silk/resultset_next.png></b></a></td></tr></table>";
	return $next;
}

function get_back_link($album, $width, $pictures, $currentindex) {
	global $BACK;
	if ($currentindex != 0) {
		$back = $pictures[$currentindex-1];
		$BACK = "?album=" . urlencode($album) . "&picture=" . urlencode($back) . "&width=$width";
		$back = "<table border=0><tr><td bgcolor=white><a href='$BACK'><b><img src=silk/resultset_previous.png> Back</b></a></td></tr></table>";
		return $back;
	} else {
		return "&nbsp;";
	}
}
/****************************************************************************/


/****************************************************************************/
/* Print data cell */
function print_data_cell($key, $value) {
	if ($value) {
		print("<tr><td bgcolor=#DDDDDD><small><small>" . htmlspecialchars($key) . "</small></small></td><td bgcolor=white><small><small>" . htmlspecialchars($value) . "</small></small></td></tr>");
	}
}
/****************************************************************************/


/****************************************************************************/
/* Print exif data */
function print_exif_data($path_to_picture, $description) {
	$keys = array();
	$values = array();
	array_push($keys, "File name"); array_push($values, basename($path_to_picture));
	array_push($keys, "File size"); array_push($values, round(filesize($path_to_picture)/1024)." KB");
	if ($exif = @exif_read_data($path_to_picture, 0, false)) {
		array_push($keys, "Resolution"); array_push($values, $exif["ExifImageWidth"] . "x" . $exif["ExifImageLength"]);
		array_push($keys, "Date/Time"); array_push($values, $exif["DateTimeOriginal"]);
		array_push($keys, "Camera model"); array_push($values, $exif["Model"]);
		//foreach ($exif as $key => $section) {
		//    echo "$key -- $section <br>";
		//}
	}

	print("<center>");
	print("<table border=0 cellspacing=5><tr valign=top><td>");
	print("<table border=0 cellspacing=2>");
	for ($i=0; $i<sizeof($keys); $i++) {
		print_data_cell($keys[$i], $values[$i]);
	}
	print("</table></td><td>");
	$keys = array("Flash", "FocalLength", "ExposureTime", "ApertureValue", "ISOSpeedRatings", "WhiteBalance", "MeteringMode");
	print("<table border=0 cellspacing=1>");
	for ($i=0; $i<sizeof($keys); $i++) {
		if (isset($keys[$i]) && isset($exif[$keys[$i]])) {
			print_data_cell($keys[$i], $exif[$keys[$i]]);
		}
	}
	print("</table></td></tr></table></center>");
}
/****************************************************************************/


/****************************************************************************/
/* Print picture details */
function print_picture_details($path_to_picture, $currentindex, $total, $width, $description) {
	print_exif_data($path_to_picture, $description);
}
/****************************************************************************/


/****************************************************************************/
/* Print upper banner */
function print_upper_banner($album, $description, $width) {
	global $TITLE;
	$descr = preg_replace("/^\//", "", $album);
	$path_parts = preg_split("/\/+/", $descr);
	$descr = "";
	$subalbum = "";
	for ($i=0; $i<sizeof($path_parts)-1; $i++) {
		$subalbum .= "/" . $path_parts[$i];
		$descr .= "<a href='?thumbs=1&album=" . urlencode($subalbum) . "'>$path_parts[$i]</a> - ";
	}
	$subalbum .= "/" . $path_parts[$i];
	$descr .= "<a href='?album=" . urlencode($subalbum) . "'>$path_parts[$i]</a>";
	if ($description) {
		$descr .= " - $description";
	}
	print("<table border=0 cellpadding=0 cellspacing=0 align=center width=$width><tr><td bgcolor=white>
		  <table border=0 cellpadding=0 cellspacing=0 align=center width=100%><tr><td>
                   <table border=0 cellpadding=0 cellspacing=0 align=center width=100%>
		    <tr>
		     <td colspan=3>
		      <p align=center><b>$descr</b></p>
		     </td>
		    </tr>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print upper toolbar */
function print_upper_toolbar($album, $description, $back, $next, $width) {
	print_upper_banner($album, $description, $width);
	print("
		  <tr align=center>
		    <td width=20% align=left>$back</td>
		    <td width=60%>
		      <a href='" . $_SERVER["PHP_SELF"] . "'>index</a> |
		      <a href='?album=" . urlencode($album) . "&thumbs=1'>thumbs</a> |
		      <a href='?album=" . urlencode($album) . "&width=$width&slideshow=4'>slideshow</a> |
		      <a href='?album=" . urlencode($album) . "&width=$width&screensaver=1'>screensaver</a>
		    </td>
		    <td width=20% align=right bgcolor=white>$next</td>
		  </tr>
		 </table>
		</td></tr></table>
	      </td></tr></table>
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
		print("
<script>
if (window.innerHeight > window.innerWidth) {
	document.write('<img border=0 src=" . $temp . " width=' + (window.innerWidth-20) + ' alt=\"" . $alt . "\">');
} else {
	document.write('<img border=0 src=" . $temp . " height=' + (window.innerHeight-20) + '>');
}
</script>
");
	} elseif (is_video($path_to_picture)) {
		$tempvideoname = do_transcode_video($path_to_picture);
		print("<video width=400 height=300 controls><source src='$tempvideoname' type='video/mp4'></video>");
	}
	print("</a>");
}
/****************************************************************************/

/****************************************************************************/
/* Print picture img */
function print_picture_in_table($path_to_picture, $temp, $height, $alt) {
	global $BACK, $NEXT;
	print("
		<table border=0 width=100%><tr><td><table border=0 width=100% cellspacing=0 cellpadding=6 align=center>
		  <tr>
		    <td width=33% onClick=javascript:gotourl('$BACK')>&nbsp;</td>
		    <td bgcolor=black><center>
	");
	print_picture($path_to_picture, $temp, $height, $alt);
	print("
		    </center></td>
		    <td width=33% onClick=javascript:gotourl('$NEXT')>&nbsp;</td>
		  </tr>
		</table></td></tr></table>
	");
}
/****************************************************************************/


/****************************************************************************/
/* Print lower toolbar */
function print_lower_toolbar($resizeform, $picform, $rotateform, $width) {
	print("
		<table><tr><td bgcolor=white><table border=0 align=center width=$width>
		  <tr align=center>
		    <td width=33%>$resizeform</td>
		    <td width=34%>$picform</td>
		    <td width=33%>$rotateform</td>
		  </tr>
		</table>
	");
}

/****************************************************************************/


/****************************************************************************/
/* Display the flipbook page with the picture and all navigation tools */
function do_flipbook_page($album, $picture, $width, $rotate, $slideshow) {
	global $_SERVER, $BASEDIR, $DEFAULT_WIDTH;
	if (!$width) {
		if ( isset($_SERVER["HTTP_UA_PIXELS"]) ) {
			list ($width, $trash) = preg_split("/x/", $_SERVER["HTTP_UA_PIXELS"], 2);
		} else {
			$width = $DEFAULT_WIDTH;
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

	$tempfilename = do_resize_picture($path_to_picture, $width, $height, $rotate);
	$currentindex = locate_index($picture, $pictures);
	$total = sizeof($pictures);
	$next = get_next_link($album, $width, $pictures, $currentindex, $slideshow);
	$back = get_back_link($album, $width, $pictures, $currentindex);
	$alt = "";
	if (! isset($descriptions[$picture])) {
		$descriptions[$picture] = "";
	}

	print_picture_in_table($path_to_picture, $tempfilename, $height, $alt);
	print_upper_toolbar($album, $descriptions[$picture], $back, $next, $width);
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
	$tempfilename = do_resize_picture($path_to_picture, $width, $height, $rotate);
	print_header(0);
	print("<body bgcolor=black topmargin=0 leftmargin=0><center><table height=100% cellpadding=0 cellspacing=0><tr><td>");
	print_picture($path_to_picture, $tempfilename, $height, $alt);
	$currentindex = locate_index($picture, $pictures);
	$next = $pictures[$currentindex+1];
	$url = "?album=" . urlencode($album) . "&picture=" . urlencode($next) . "&width=$width";
	$resizeform = build_resize_form($path_to_picture, $album, $picture, $width, "slideshow=$slideshow");
	$delayform = build_delay_form($path_to_picture, $album, $picture, $width, $slideshow);
	print("</td></tr><tr><td align=center><table><tr><td align=center>$delayform</td><td align=center>$resizeform</td></tr></table></td></tr></table></center><meta http-equiv='refresh' content='$slideshow;url=$url&slideshow=$slideshow'></body></html>\n");
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
			if (is_image("$RAND_ALBUMS[$i]/$file")) {
				array_push($pictures, "$RAND_ALBUMS[$i]/$file");
			}
		}
		closedir($dh);
	}
	print("http://" . $_SERVER["HTTP_HOST"] .  "/" . $pictures[array_rand($pictures)]. "\n");
	print("<script>alert(screen.height)</script>");
}
/****************************************************************************/

function screensaver($album) {
	global $BASEDIR, $_SERVER;
	$pictures = get_pictures_from_album($album);
	$url = preg_replace("/screensaver=./", "screensaver=0", $_SERVER["REQUEST_URI"]);
	shuffle($pictures);
	print("
                <script type='text/javascript' src='https://ajax.googleapis.com/ajax/libs/jquery/1.5.0/jquery.min.js'></script>
                <script type='text/javascript' src='kenburns.js'></script>
                <script type='text/javascript'>
                        \$(function(){
                                \$('#kenburns').kenburns({
                                        images:[");
	for ($i=0; $i<sizeof($pictures); $i++) {
		$path_to_picture = $BASEDIR . "/" . $album . "/" . $pictures[$i];
		$path_to_picture = preg_replace("/\/+/", "/", "$path_to_picture");
		print("'$path_to_picture',");
	}
	print("],
                                        frames_per_second: 20,
                                        display_time: 10000,
                                        fade_time: 1000,
                                        zoom: 3,
                                        background_color:'#ffffff',
                                        post_render_callback:function(\$canvas, context) {
                                                context.save();
                                                context.fillStyle = '#000';
                                                context.font = 'bold 20px sans-serif';
                                                var width = \$canvas.width();
                                                var height = \$canvas.height();
                                                var text = 'Pictor';
                                                var metric = context.measureText(text);
                                                context.fillStyle = '#fff';
                                                context.shadowOffsetX = 3;
                                                context.shadowOffsetY = 3;
                                                context.shadowBlur = 4;
                                                context.shadowColor = 'rgba(0, 0, 0, 0.8)';
                                                context.fillText(text, width - metric.width - 8, height - 8);
                                                context.restore();
                                        }
                                });
                        });
                </script>
                <a href='$url'>
                        <canvas id='kenburns'>
                                <p>Your browser doesn't support canvas!</p>
                        </canvas>
                </a>
                <script type='text/javascript'>
			var c = document.getElementById('kenburns');
			c.width = document.width - 10;
			c.height = document.height - 10;
                </script>
");
}



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
if ($screensaver) {
	screensaver($album);
	exit;
} elseif ($write) {
	do_write_descriptions($album, $file, $desc);
} elseif (!$album) {
	do_list_albums($base);
} elseif ($thumbs) {
	print_thumbnails($album);
} else {
	do_flipbook_page($album, $picture, $width, $rotate, $slideshow);
}
print_footer();
/****************************************************************************/
?>
