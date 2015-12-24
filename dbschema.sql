CREATE TABLE `STT_Peers` (
  `info_hash` binary(20) NOT NULL,
  `peer_id` binary(20) NOT NULL,
  `ip` INT UNSIGNED NOT NULL,
  `port` smallint(5) unsigned NOT NULL,
  `uploaded` bigint(20) unsigned NOT NULL default '0',
  `downloaded` bigint(20) unsigned NOT NULL default '0',
  `left` bigint(20) unsigned NOT NULL default '0',
  `update_time` timestamp NOT NULL,
  PRIMARY KEY  (`info_hash`,`ip`,`port`)
) ENGINE=HEAP;

CREATE EVENT `STT_Clean` 
ON SCHEDULE EVERY 10 MINUTE 
DO DELETE FROM `STT_Peers` WHERE `update_time` < NOW() - INTERVAL 1 DAY;

/* As admin set event scheduler on */
set GLOBAL event_scheduler=ON;
