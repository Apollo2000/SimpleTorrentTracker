<?
// Parameters for database
$db_server = 'localhost';
$db_user = '';
$db_pass = '';
$db_db = '';
$db_table = 'STT_Peers';

// Peers should wait at least this many minutes between announcements (Max 1440).
$min_announce_interval = 30; // minutes. 

// Consider a peer dead if it has not announced in 1.2*min_announce_interval (must be greater than 1; recommend 1.2)
$expire_factor = 1.2;

// Peers should wait at least this many minutes between scrape requests (Max 1440).
$min_scrape_interval = 30;

// Max numbers of peers return announcement
$max_ann_peers = 30;
?>
