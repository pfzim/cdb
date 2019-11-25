CREATE DATABASE `cdb` /*!40100 DEFAULT CHARACTER SET utf8 */;

DROP TABLE IF EXISTS `cdb`.`c_computers`;
CREATE TABLE  `cdb`.`c_computers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `dn` varchar(2048) NOT NULL DEFAULT '',
  `ao_ptnupdtime` datetime DEFAULT NULL,
  `ao_script_ptn` int(10) unsigned NOT NULL DEFAULT '0',
  `ao_as_pstime` datetime DEFAULT NULL,
  `ee_lastsync` datetime DEFAULT NULL,
  `ee_encryptionstatus` int(10) unsigned NOT NULL DEFAULT '0',
  `laps_exp` datetime NOT NULL,
  `ee_operid` varchar(36) NOT NULL DEFAULT '',
  `ee_opernum` varchar(10) NOT NULL DEFAULT '',
  `ao_operid` varchar(36) NOT NULL DEFAULT '',
  `ao_opernum` varchar(10) NOT NULL DEFAULT '',
  `rn_operid` varchar(36) NOT NULL DEFAULT '',
  `rn_opernum` varchar(10) NOT NULL DEFAULT '',
  `laps_operid` varchar(36) NOT NULL,
  `laps_opernum` varchar(10) NOT NULL,
  `flags` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Index_2` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cdb`.`c_tasks`;
CREATE TABLE  `cdb`.`c_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `operid` varchar(36) NOT NULL,
  `opernum` varchar(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
