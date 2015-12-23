<?
/*
 * Simple Torrent Tracker PHP - by Apollo (2016)
 * Base on OpenTracker WhitSoft project (2009)
 * By BitTorrent Tracker Protocol (https://wiki.theory.org)
 * Need: Linux / PHP 5.5+ / Apache / MYSQL
 * Version 1.00
 */

// Block browsers get info from tracker
if(preg_match('/Windows|Mozilla|Opera/i',$_SERVER['HTTP_USER_AGENT'])) exit;

header('Content-Type: text/plain');
require_once 'config.inc.php';
require_once 'func.inc.php';

$expire_time = ceil($expire_factor*$min_announce_interval);
$action = parse_url($_SERVER['REQUEST_URI']);

// Announce MOD
if ($action["path"]!= '/scrape')
{
	// Check validate announce request
	$failure_code = 0;
	if (!isset($_GET))						$failure_code = 100;
	elseif (!isset($_GET['info_hash']))		$failure_code = 101;
	elseif (!isset($_GET['peer_id']))		$failure_code = 102;
	elseif (!isset($_GET['port']) || !is_numeric($_GET['port']))	$failure_code = 103;
	elseif (strlen($_GET['info_hash'])!=20)	$failure_code = 150;
	elseif (strlen($_GET['peer_id'])!=20)	$failure_code = 151;
	elseif (!isset($_GET['uploaded']) || !is_numeric($_GET['uploaded']) || !isset($_GET['downloaded']) || !is_numeric($_GET['downloaded']) || !isset($_GET['left']) || !is_numeric($_GET['left']) || (!empty($_GET['event']) && ($_GET['event'] != 'started') && ($_GET['event'] != 'completed') && ($_GET['event'] != 'stopped')))
		$failure_code = 900;
	if ($failure_code)	sendError($failure_code);
		
	$ip = getUserIP();
	if ($ip === false) exit("Unable to resolve ip");

	// Get announce event
	if (isset($_GET['event'])){
		if ($_GET['event'] == 'stopped') $expire_time=0;
		$left = ($_GET['event'] == 'completed') ? 0 : $_GET['left'];
	}
	
	// Connect to DB
	$DB = mysqli_connect($db_server, $db_user, $db_pass,$db_db) or exit("DB Error: Can't connect - ".mysqli_connect_error());

	// Remove stopped peer
	if ($_GET['event'] == 'stopped'){
		mysqli_query($DB,"DELETE FROM `$db_table` WHERE `info_hash` ='".$_GET["info_hash"]."' AND `peer_id`='".$_GET["peer_id"]."';") or exit("DB Error: Can't run DELETE - ".mysqli_connect_error());
		exit;
	}

	// Update peer in DB
	$columns = '`info_hash`, `ip`, `port`, `peer_id`, `uploaded`, `downloaded`, `left`, `update_time`';
	$values = "'".$_GET["info_hash"]."', INET_ATON('$ip'), '".$_GET['port']."', '".$_GET["peer_id"]."', '".$_GET['uploaded']."', '".$_GET['downloaded']."', '$left', NOW()";
	mysqli_query($DB,"REPLACE INTO `$db_table` ($columns) VALUES ($values);") or exit('database error');

	// Get peers info from DB
	$max_peers = (isset($_GET["numwant"]) && is_numeric($_GET["numwant"]) && ($_GET["numwant"]>0) && ($_GET["numwant"]<$max_ann_peers)?$_GET["numwant"]:$max_ann_peers);
	$query = mysqli_query($DB,"SELECT INET_NTOA(`ip`) as `ip`, `port`, `peer_id` FROM `$db_table` WHERE `info_hash` = '".$_GET["info_hash"]."' AND `update_time` > NOW() - INTERVAL $expire_time MINUTE ORDER BY RAND() LIMIT $max_peers;") or exit("DB Error: Can't run QUERY - ".mysqli_connect_error());

	// Peers output method
	$peers = Array();							
	if (!empty($_REQUEST['compact'])) 			// Compact output
	{
		$peers = '';
		while ($array = mysqli_fetch_array($query,MYSQL_ASSOC))
			$peers .= pack('Nn', ip2long($array['ip']), intval($array['port']));
	}
	else if (!empty($_REQUEST['no_peer_id']))	// Omit peer id output
		while ($array = mysqli_fetch_array($query,MYSQL_ASSOC))
			$peers[] = array('ip' => $array['ip'], 'port' => intval($array['port']));
	else 										// Default output
		while ($array = mysqli_fetch_array($query,MYSQL_ASSOC)) 
			$peers[] = array('ip' => $array['ip'], 'port' => intval($array['port']), 'peer id' => $array['peer_id']);

	// Get seed&leech info from DB
	$query = mysqli_query($DB,"SELECT `info_hash`, 
			SUM(CASE WHEN `left` =0 AND `update_time` > NOW() - INTERVAL $expire_time MINUTE THEN 1 ELSE 0 END) AS complete, 
			SUM(CASE WHEN `left` !=0 AND `update_time` > NOW() - INTERVAL $expire_time MINUTE THEN 1 ELSE 0 END) AS incomplete,
			SUM(CASE WHEN `left` =0 THEN 1 ELSE 0 END) AS downloaded
			FROM `$db_table` WHERE `info_hash`='".$_GET["info_hash"]."' GROUP BY `info_hash`;") or exit("DB Error: Can't run QUERY - ".mysqli_connect_error());
	$result = mysqli_fetch_array($query,MYSQL_ASSOC);

	// Send results to client
	$output = array('interval' => intval($min_announce_interval*60), 'peers' => $peers,'complete' => intval($result["complete"]), 'incomplete' => intval($result["incomplete"]), 'downloaded' => intval($result["downloaded"]));
}
else // Scrape MOD
{
	// Check validate info_hash
	if (isset($_GET['info_hash']) && strlen($_GET['info_hash'])!=20) sendError(150);
	
	// Connect to DB
	$DB = mysqli_connect($db_server, $db_user, $db_pass,$db_db) or exit("DB Error: Can't connect - ".mysqli_connect_error());

	// Get info hash/s from DB
	$results = Array();
	$query = mysqli_query($DB,"SELECT HEX(`info_hash`) as info_hash, 
			SUM(CASE WHEN `left` =0 AND `update_time` > NOW() - INTERVAL $expire_time MINUTE THEN 1 ELSE 0 END) AS complete, 
			SUM(CASE WHEN `left` !=0 AND `update_time` > NOW() - INTERVAL $expire_time MINUTE THEN 1 ELSE 0 END) AS incomplete,
			SUM(CASE WHEN `left` =0 THEN 1 ELSE 0 END) AS downloaded
			FROM `$db_table` ".(!empty($_GET['info_hash'])?"WHERE HEX(`info_hash`)='".$_GET["info_hash"]."'":"")." GROUP BY `info_hash`;") 
			or exit("DB Error: Can't run QUERY - ".mysqli_connect_error());
	while ($row = mysqli_fetch_array($query,MYSQL_ASSOC)) $results[] = $row;

	if(!isset($results[0]["info_hash"])) exit;	// No results

	$files = array();
	foreach ($results as $result)
		$files[$result["info_hash"]] = array('complete' => intval($result["complete"]), 'incomplete' => intval($result["incomplete"]), 'downloaded' => intval($result["downloaded"]));

	// Send results to client
	$output = array('files' => $files, 'flags' => array('min_request_interval' => intval($min_scrape_interval*60)));
}
echo bencode($output);	
mysqli_close($DB);
exit;
?>
