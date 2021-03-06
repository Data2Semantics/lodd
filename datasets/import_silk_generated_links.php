<?php

/**
* Create MySQL Dump from Diseasome files
*
* @author	Anja Jentzsch <mail@anjajentzsch.de>
*/

$database_linking = "lodd_linking";

require_once("../scripts/lodd_utils.php");

mysql_connect ($host, $user, $password) or die ("Database connection could not be established.");
if (!mysql_select_db ($database_linking)) {
	$sql_query = "CREATE DATABASE $database_linking";
	if (!mysql_query ($sql_query)) {
		die(mysql_error() . " - query: ".$sql_query);
	}
	mysql_select_db ($database_linking) or die("Database selection could not be established.");
}

mysql_query ("drop table ".$database_sider_table_dbpedia.";");

if ($dir=opendir($silk_path)) {
	while($file=readdir($dir)) {
		if (!is_dir($file) && (strpos($file,"accepted_links") !== false)) {
			$file_parts = split ("_", $file);
			if (sizeof(array_intersect($file_parts, $lodd_datasets))> 0) {
				$files[] = $file;
			}
		}
	}
	closedir($dir);
} else {
	if (!$silk_path) {
		die ("Please define Silk path in your config/config.php.");
	} else {
		die ("Can't find defined Silk path: $silk_path");
	}
}

foreach ($files as $file) {
	$file_handle = fopen($silk_path.$file, "r");
	if (!$file_handle) {
		die ("File not found ".$file);
	}
	$file_parts = split ("_", $file);
	$i = 0;
	$table_name = "";
	while ($file_parts[$i] != "accepted") {
		$table_name .= $file_parts[$i] . "_";
		$i = $i + 1;
	}
	$table_name = trim($table_name, "_");
	if (substr_count($table_name, "_") == 2) {
		$line = trim(fgets($file_handle));
		if (strlen($line) == 0) {
			die ("[DIE] empty file ".$file);
		}
		if ((preg_match("/<([^>]*)> owl:sameAs <([^>]*)>/", $line, $match)) || (preg_match("/<([^>]*)> <http[^>]*sameAs> <([^>]*)>/", $line, $match))) {
			$uri1 = $match[1];
			$uri2 = $match[2];
		}
		if ((strpos($uri, $file_parts[1])) || (strpos($uri2, $file_parts[0]))) {
			$tmp = $file_parts[0];
			$file_parts[0] = $file_parts[1];
			$file_parts[1] = $tmp;		
			$i = 0;
			$table_name = "";
			while ($file_parts[$i] != "accepted") {
				$table_name .= $file_parts[$i] . "_";
				$i = $i + 1;
			}
			$table_name = trim($table_name, "_");
		}
		
		$uri1_parts = split ("/", $uri1);
		$uri2_parts = split ("/", $uri2);

		$ns1 = rtrim($uri1, $uri1_parts[sizeof($uri1_parts)-1]);
		$ns2 = rtrim($uri2, $uri2_parts[sizeof($uri2_parts)-1]);
		$sql_query = "CREATE TABLE IF NOT EXISTS ".$table_name." ( 
			".$file_parts[0]." varchar(500) NOT NULL COMMENT '".$ns1."',
			".$file_parts[1]." varchar(500) NOT NULL COMMENT '".$ns2."',
			UNIQUE KEY ".$table_name." (".$file_parts[0].", ".$file_parts[1].")
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;";
		if (!mysql_query ($sql_query)) {
			die(mysql_error() . " - query: ".$sql_query);
		}

		$sql_query = "INSERT INTO ".$table_name." (".$file_parts[0].", ".$file_parts[1].") VALUES ('".$uri1_parts[sizeof($uri1_parts)-1]."', '".$uri2_parts[sizeof($uri2_parts)-1]."');";
		if (!mysql_query ($sql_query)) {
			if (strpos(mysql_error(), "Duplicate entry") === false) {
				die("[DIE] ". mysql_error() . " - query: ".$sql_query);
			}
		}
		
		while (!feof($file_handle)) {
			$line = trim(fgets($file_handle));
			if ((preg_match("/<([^>]*)> owl:sameAs <([^>]*)>/", $line, $match)) || (preg_match("/<([^>]*)> <http[^>]*sameAs> <([^>]*)>/", $line, $match))) {
				$uri1 = $match[1];
				$uri2 = $match[2];
				$uri1_parts = split ("/", $uri1);
				$uri2_parts = split ("/", $uri2);
				$sql_query = "INSERT INTO ".$table_name." (".$file_parts[0].", ".$file_parts[1].") VALUES ('".$uri1_parts[sizeof($uri1_parts)-1]."', '".$uri2_parts[sizeof($uri2_parts)-1]."');";
				if (!mysql_query ($sql_query)) {
					if (strpos(mysql_error(), "Duplicate entry") === false) {
						die("[DIE] ". mysql_error() . " - query: ".$sql_query);
					}
				}
			}
		}
	} else {
		echo "didn't import " . $file . "\n";
	}
	fclose($file_handle);
}
?>