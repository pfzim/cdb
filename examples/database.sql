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

DROP TABLE IF EXISTS `cdb`.`c_properties_date`;
CREATE TABLE  `cdb`.`c_properties_date` (
  `pid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oid` int(10) unsigned NOT NULL,
  `value` datetime NOT NULL,
  PRIMARY KEY (`pid`,`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cdb`.`c_properties_int`;
CREATE TABLE  `cdb`.`c_properties_int` (
  `pid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oid` int(10) unsigned NOT NULL,
  `value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pid`,`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cdb`.`c_properties_str`;
CREATE TABLE  `cdb`.`c_properties_str` (
  `pid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `oid` int(10) unsigned NOT NULL,
  `value` varchar(4096) NOT NULL,
  PRIMARY KEY (`pid`,`oid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
