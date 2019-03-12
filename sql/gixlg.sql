DROP DATABASE IF EXISTS `gixlg`;
CREATE DATABASE `gixlg`;

GRANT ALL ON gixlg.* TO 'gixlg'@'localhost' IDENTIFIED BY 'gixlg';
FLUSH PRIVILEGES;

USE `gixlg`;

DROP TABLE IF EXISTS `members`;
CREATE TABLE IF NOT EXISTS `members` (
  `neighbor` varchar(39) NOT NULL,
  `asn` int(10) unsigned NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `status` tinyint(3) unsigned NOT NULL,
+  `time` timestamp NOT NULL DEFAULT '1970-01-01 09:00:01' ON UPDATE CURRENT_TIMESTAMP,  
+  `lastup` timestamp NOT NULL DEFAULT '1970-01-01 09:00:01',
+  `lastdown` timestamp NOT NULL DEFAULT '1970-01-01 09:00:01',
  `prefixes` mediumint(8) unsigned NOT NULL,
  `updown` mediumint(8) unsigned NOT NULL,
+  UNIQUE KEY `neighbor_type` (`neighbor`,`type`),
+  KEY `neighbor` (`neighbor`),
  KEY `type` (`type`),
  KEY `status` (`status`),
  KEY `asn` (`asn`),
  KEY `lg_summary` (`neighbor`,`type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- insert IPv4 peer with IP 198.51.100.[1-3], ASN 12345
INSERT INTO `members` VALUES('198.51.100.1', 12345, 4, 0, '', '', '', 0, 0);
INSERT INTO `members` VALUES('198.51.100.2', 12345, 4, 0, '', '', '', 0, 0);
INSERT INTO `members` VALUES('198.51.100.3', 12345, 4, 0, '', '', '', 0, 0);

-- insert IPv6 peers with IP 2001:db8::[1-3], ASN 12345
INSERT INTO `members` VALUES('2001:db8::1', 12345, 6, 0, '', '', '', 0, 0);
INSERT INTO `members` VALUES('2001:db8::2', 12345, 6, 0, '', '', '', 0, 0);
INSERT INTO `members` VALUES('2001:db8::3', 12345, 6, 0, '', '', '', 0, 0);

DROP TABLE IF EXISTS `prefixes`;
CREATE TABLE IF NOT EXISTS `prefixes` (
  `neighbor` varchar(39) NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `prefix` varchar(43) NOT NULL,
  `length` tinyint(3) unsigned NOT NULL,
  `ip_start` decimal(39,0) NOT NULL,
  `ip_end` decimal(39,0) NOT NULL,
  `ip_poly` polygon NOT NULL,
  `aspath` varchar(500) NOT NULL,
  `nexthop` varchar(39) NOT NULL,
  `community` text NOT NULL,
  `extended_community` text NOT NULL,
  `origin` varchar(10) NOT NULL,
  `originas` int(10) unsigned NOT NULL,
+  `time` timestamp NOT NULL DEFAULT '1970-01-01 09:00:01' ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `neighbor_prefix` (`neighbor`,`prefix`),
  KEY `prefix` (`prefix`),
  KEY `neighbor` (`neighbor`),
  KEY `aspath` (`aspath`),
  SPATIAL KEY `ip_poly` (`ip_poly`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `iptoasn`;
CREATE TABLE IF NOT EXISTS `iptoasn` (
  `prefix` varchar(43) NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `length` tinyint(3) unsigned NOT NULL,
  `ip_poly` polygon NOT NULL,
  `originas` int(10) unsigned NOT NULL,
  UNIQUE KEY `prefix` (`prefix`),
  KEY `type` (`type`),
  SPATIAL KEY `ip_poly` (`ip_poly`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `nexthops`;
CREATE TABLE IF NOT EXISTS `nexthops` (
  `ip4_start` varchar(15) NOT NULL,
  `ip4_end` varchar(15) NOT NULL,
  `ip6_start` decimal(39,0) NOT NULL,
  `ip6_end` decimal(39,0) NOT NULL,
  `ip4_net` varchar(18) NOT NULL,
  `ip6_net` varchar(43) NOT NULL,
  `node` text NOT NULL,
  `type` text NOT NULL,
  `location` text NOT NULL,
+  `asn` int(10) unsigned NOT NULL,
  KEY `ip4_range` (`ip4_start`,`ip4_end`),
  KEY `ip6_range` (`ip6_start`,`ip6_end`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `nexthops` VALUES('3275939840', '3275940863', '42540649787432207563077505771657756672', '42540649787432207581524249845367308287', '195.66.224.0/22',  '2001:7f8:4::/64',    'LINX Juniper', 'peering LAN', 'London', 1103);
INSERT INTO `nexthops` VALUES('3275942912', '3275943935', '42540649787432207581524249845367308288', '42540649787432207599970993919076859903', '195.66.236.0/22',  '2001:7f8:4:1::/64',  'LINX Extreme', 'peering LAN', 'London', 1103);
INSERT INTO `nexthops` VALUES('3276115968', '3276116736', '42540649787428580785618661884133638144', '42540649787428580804065405957843189759', '195.69.144.0/22',  '2001:7f8:1::/64',    'AMS-IX', 'peering LAN', 'Amsterdam', 1200);
INSERT INTO `nexthops` VALUES('1347534848', '1347535871', '42540649787427371859799047254958931968', '42540649787427371878245791328668483583', '80.81.192.0/22',   '2001:7f8::/64',      'DE-CIX', 'peering LAN', 'Frankfurt', 0);
INSERT INTO `nexthops` VALUES('87642112',   '87643135',   '42540649787455177153650183725977174016', '42540649787455177172096927799686725631', '5.57.80.0/22',     '2001:7f8:17::/64',   'LONAP', 'peering LAN', 'London', 8330);
INSERT INTO `nexthops` VALUES('3275944960', '3275945215', '42540649787432207599970993919076859904', '42540649787432207618417737992786411519', '195.66.244.0/24',  '2001:7f8:4:2::/64',  'IXMANCHESTER', 'peering LAN', 'Manchester', 0);
INSERT INTO `nexthops` VALUES('1541007104', '1541007359', '42540649787551891219219354059953668096', '42540649787551891237666098133663219711', '91.217.231.0/24',  '2001:7f8:67::/64',   'IXLEEDS', 'peering LAN', 'Leeds', 0);
INSERT INTO `nexthops` VALUES('1542066688', '1542066943', '42540649787551891385240050723339632640', '42540649787551891403686794797049184255', '91.234.18.0/24',   '2001:7f8:67:9::/64', 'IXLEEDS', '9k peering LAN', 'Leeds', 51526);
INSERT INTO `nexthops` VALUES('3283540480', '3283540991', '42540649787507160963893612780489539584', '42540649787507160982340356854199091199', '195.182.218.0/23', '2001:7f8:42::/64',   'PLIX', 'peering LAN', 'Warsaw', 0);
INSERT INTO `nexthops` VALUES('3284118016', '3284118271', '42540649787537384109383978509857193984', '42540649787537384127830722583566745599', '195.191.170.0/24', '2001:7f8:5b::/64',   'EPIX', 'peering LAN', 'Katowice', 0);
INSERT INTO `nexthops` VALUES('624028672',  '624029183',  '42540649787528921628646676105634250752', '42540649787528921647093420179343802367', '37.49.236.0/23',   '2001:7f8:54::/64',   'FRANCE-IX', 'peering LAN', 'Paris', 0);

DROP TABLE IF EXISTS `nodes`;
CREATE TABLE IF NOT EXISTS `nodes` (
  `ip4` varchar(15) NOT NULL,
  `ip6` varchar(39) NOT NULL,
  `vendor` text NOT NULL,
  `model` text NOT NULL,
  `type` text NOT NULL,
  `location` text NOT NULL,
  `postcode` text NOT NULL,
  KEY `ip4` (`ip4`),
  KEY `ip6` (`ip6`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- Information about peers which appear with results from cmd: show ip bgp ...
INSERT INTO `nodes` VALUES('198.51.100.1', '2001:db8::1', 'NODE 1', '', 'full transit', 'Location 1', '');
INSERT INTO `nodes` VALUES('198.51.100.2', '2001:db8::2', 'NODE 2', '', 'peering IXLEEDS', 'Location 1', '');
INSERT INTO `nodes` VALUES('198.51.100.3', '2001:db8::3', 'NODE 3', '', 'peering LINX', 'Location 2', '');
