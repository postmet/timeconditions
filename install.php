<?php /* $Id: install.php $ */

require_once dirname(__FILE__)."/functions.inc.php";

global $db;
global $amp_conf;

if (! function_exists("out")) {
	function out($text) {
		echo $text."<br />";
	}
}

if (! function_exists("outn")) {
	function outn($text) {
		echo $text;
	}
}

$autoincrement = (($amp_conf["AMPDBENGINE"] == "sqlite") || ($amp_conf["AMPDBENGINE"] == "sqlite3")) ? "AUTOINCREMENT":"AUTO_INCREMENT";

$sql = "
CREATE TABLE IF NOT EXISTS timeconditions (
	timeconditions_id INTEGER NOT NULL PRIMARY KEY $autoincrement,
	displayname VARCHAR( 50 ) ,
	time INT ( 11 ) ,
	truegoto VARCHAR( 50 ) ,
	falsegoto VARCHAR( 50 ),
	deptname VARCHAR( 50 )
)";
$check = $db->query($sql);
if(DB::IsError($check)) {
		die_freepbx("Can not create `timeconditions` table: " .  $check->getMessage() .  "\n");
}

$sql = "
CREATE TABLE IF NOT EXISTS `timegroups_groups` (
  `id` int(11) NOT NULL PRIMARY KEY $autoincrement,
  `description` varchar(50) NOT NULL default '',
  UNIQUE KEY `display` (`description`)
) $autoincrement = 1 
";
$check = $db->query($sql);
if(DB::IsError($check)) {
	die_freepbx("Can not create `timeconditions` table: " .  $check->getMessage() .  "\n");
}

$sql = "
CREATE TABLE IF NOT EXISTS `timegroups_details` (
  `id` int(11) NOT NULL PRIMARY KEY $autoincrement,
  `timegroupid` int(11) NOT NULL default '0',
  `time` varchar(100) NOT NULL default ''
) $autoincrement = 1 
";
$check = $db->query($sql);
if(DB::IsError($check)) {
	die_freepbx("Can not create `timeconditions` table: " .  $check->getMessage() .  "\n");
}

// Merge old findmefollow destinations to extension
//
$results = array();
$sql = "SELECT timeconditions_id, truegoto, falsegoto FROM timeconditions";
$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if (!DB::IsError($results)) { // error - table must not be there
	foreach ($results as $result) {
		$old_false_dest    = $result['falsegoto'];
		$old_true_dest     = $result['truegoto'];
		$timeconditions_id = $result['timeconditions_id'];

		$new_false_dest = merge_ext_followme(trim($old_false_dest));
		$new_true_dest  = merge_ext_followme(trim($old_true_dest));
		if (($new_true_dest != $old_true_dest) || ($new_false_dest != $old_false_dest)) {
			$sql = "UPDATE timeconditions SET truegoto = '$new_true_dest', falsegoto = '$new_false_dest' WHERE timeconditions_id = $timeconditions_id  AND truegoto = '$old_true_dest' AND falsegoto ='$old_false_dest'";
			$results = $db->query($sql);
			if(DB::IsError($results)) {
				die_freepbx($results->getMessage());
			}
		}
	}
}

/* Upgrade to 2.5
 * Migrate time condtions to new time condtions groups
 */
timeconditions_updatedb();

/* Alter the time field to int now that it refernces the id field in groups
 */
outn(_("converting timeconditions time field to int.."));
$sql = "ALTER TABLE `timeconditions` CHANGE `time` `time` INT (11)";
$results = $db->query($sql);
if(DB::IsError($results)) {
	out(_("ERROR: failed to convert field ").$results->getMessage());
} else {
	out(_("OK"));
}

// bring db up to date on install/upgrade
//
function timeconditions_updatedb() {
	$modinfo = module_getinfo('timeconditions');
	if (is_array($modinfo)) {
		$ver = $modinfo['timeconditions']['dbversion'];

		// If previous version was older than 2.5 then migrate the timeconditions to groups
		//
		if (version_compare_freepbx($ver,'2.5','lt')) { 
			outn(_("Checking for old timeconditions to upgrade.."));
			$upgradelist = timeconditions_list_forupgrade();
			if (isset($upgradelist)) { 
				// we have old conditions to upgrade
				//
				out(_("starting migration"));
				foreach($upgradelist as $upgrade) {
					$times[] = $upgrade['time'];
					$newid = timeconditions_timegroups_add_group_timestrings('migrated-'.$upgrade['displayname'],$times);
					timeconditions_set_timegroupid($upgrade['timeconditions_id'],$newid);
					$newtimes = timeconditions_timegroups_get_times($newid);
					out(sprintf(_("Upgraded %s and created group %s"), $upgrade['displayname'], 'migrated-'.$upgrade['displayname']));
					if (!is_array($newtimes)) {
						out(sprintf(_("%sWARNING:%s Not time defined for this condtion, please review"),"<font color='red'>","</font>"));
					}
					unset($times);
				}
			} else {
				out(_("no upgrade needed"));
			}
		}
	}
}

function timeconditions_list_forupgrade() {
	$results = sql("SELECT * FROM timeconditions","getAll",DB_FETCHMODE_ASSOC);
	if(is_array($results)){
		foreach($results as $result){
			$list[] = $result;
		}
	}
	if (isset($list)) {
		return $list;
	} else { 
		return null;
	}
}

function timeconditions_set_timegroupid($id, $timegroup) {
	sql("UPDATE timeconditions SET time = $timegroup WHERE timeconditions_id = $id;");
}
?>
