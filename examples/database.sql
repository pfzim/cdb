-- MariaDB dump 10.19  Distrib 10.4.24-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: cdb
-- ------------------------------------------------------
-- Server version	10.4.24-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `cdb`
--

/*!40000 DROP DATABASE IF EXISTS `cdb`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `cdb` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `cdb`;

--
-- Table structure for table `c_ac_log`
--

DROP TABLE IF EXISTS `c_ac_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_ac_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL,
  `app_path` varchar(2048) NOT NULL,
  `hash` varchar(256) NOT NULL DEFAULT '',
  `cmdln` varchar(4096) NOT NULL,
  `last` datetime NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `i_app_path` (`app_path`(1024)) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_access`
--

DROP TABLE IF EXISTS `c_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_access` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sid` varchar(256) NOT NULL DEFAULT '',
  `dn` varchar(1024) NOT NULL DEFAULT '',
  `oid` int(10) unsigned NOT NULL DEFAULT 0,
  `allow_bits` binary(32) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_computers`
--

DROP TABLE IF EXISTS `c_computers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_computers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `dn` varchar(2048) NOT NULL DEFAULT '',
  `ao_ptnupdtime` datetime DEFAULT NULL,
  `ao_script_ptn` int(10) unsigned NOT NULL DEFAULT 0,
  `ao_as_pstime` datetime DEFAULT NULL,
  `ee_lastsync` datetime DEFAULT NULL,
  `ee_encryptionstatus` int(10) unsigned NOT NULL DEFAULT 0,
  `laps_exp` datetime DEFAULT NULL,
  `sccm_lastsync` datetime DEFAULT NULL,
  `delay_checks` date DEFAULT '0000-00-00',
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `i_name` (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_config`
--

DROP TABLE IF EXISTS `c_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_config` (
  `uid` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `value` varchar(8192) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`uid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_devices`
--

DROP TABLE IF EXISTS `c_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_devices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `type` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `i_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_files`
--

DROP TABLE IF EXISTS `c_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `path` varchar(2048) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `i_filename` (`filename`),
  FULLTEXT KEY `i_path` (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_files_inventory`
--

DROP TABLE IF EXISTS `c_files_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_files_inventory` (
  `pid` int(10) unsigned NOT NULL,
  `fid` int(10) unsigned NOT NULL,
  `scan_date` datetime NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`pid`,`fid`) USING BTREE,
  KEY `i_fid` (`fid`),
  KEY `i_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_logs`
--

DROP TABLE IF EXISTS `c_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `operation` varchar(1024) NOT NULL,
  `params` varchar(4096) NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_mac`
--

DROP TABLE IF EXISTS `c_mac`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_mac` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `inv_no` varchar(16) NOT NULL DEFAULT '0',
  `type_no` int(11) NOT NULL DEFAULT -1,
  `model_no` int(11) NOT NULL DEFAULT -1,
  `status` int(10) unsigned NOT NULL DEFAULT 0,
  `branch_no` int(11) NOT NULL DEFAULT -1,
  `loc_no` int(10) unsigned NOT NULL DEFAULT 0,
  `mac` varchar(60) NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `port` varchar(45) NOT NULL DEFAULT '',
  `vlan` smallint(5) unsigned DEFAULT NULL,
  `first` datetime DEFAULT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment` varchar(512) NOT NULL DEFAULT '',
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `i_name` (`name`),
  KEY `i_mac` (`mac`),
  KEY `i_status` (`status`),
  KEY `i_loc_no` (`loc_no`),
  KEY `i_port` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_mac_sn`
--

DROP TABLE IF EXISTS `c_mac_sn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_mac_sn` (
  `mac` varchar(12) NOT NULL,
  `sn` varchar(45) NOT NULL,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`mac`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_names`
--

DROP TABLE IF EXISTS `c_names`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_names` (
  `type` int(10) unsigned NOT NULL,
  `pid` int(10) unsigned NOT NULL DEFAULT 0,
  `id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`type`,`pid`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_net_errors`
--

DROP TABLE IF EXISTS `c_net_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_net_errors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL,
  `port` varchar(255) NOT NULL,
  `date` datetime NOT NULL,
  `scf` int(10) unsigned NOT NULL COMMENT 'SingleCollisionFrames',
  `cse` int(10) unsigned NOT NULL COMMENT 'CarrierSenseErrors',
  `ine` int(10) unsigned NOT NULL COMMENT 'InErrors',
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `i_pid` (`pid`),
  KEY `i_port` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_persons`
--

DROP TABLE IF EXISTS `c_persons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_persons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(45) NOT NULL,
  `dn` varchar(2048) NOT NULL,
  `fname` varchar(255) NOT NULL,
  `mname` varchar(255) NOT NULL,
  `lname` varchar(255) NOT NULL,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_properties_date`
--

DROP TABLE IF EXISTS `c_properties_date`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_properties_date` (
  `tid` int(10) unsigned NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `oid` int(10) unsigned NOT NULL,
  `value` datetime NOT NULL,
  PRIMARY KEY (`tid`,`pid`,`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_properties_int`
--

DROP TABLE IF EXISTS `c_properties_int`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_properties_int` (
  `tid` int(10) unsigned NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `oid` int(10) unsigned NOT NULL,
  `value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`tid`,`pid`,`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_properties_str`
--

DROP TABLE IF EXISTS `c_properties_str`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_properties_str` (
  `tid` int(10) unsigned NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `oid` int(10) unsigned NOT NULL,
  `value` varchar(4096) NOT NULL,
  PRIMARY KEY (`tid`,`pid`,`oid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_tasks`
--

DROP TABLE IF EXISTS `c_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(10) unsigned NOT NULL DEFAULT 1,
  `pid` int(10) unsigned NOT NULL,
  `type` int(10) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  `date` datetime NOT NULL,
  `operid` varchar(36) NOT NULL,
  `opernum` varchar(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_users`
--

DROP TABLE IF EXISTS `c_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(255) NOT NULL,
  `passwd` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `mail` varchar(1024) CHARACTER SET latin1 NOT NULL,
  `sid` varchar(16) DEFAULT NULL,
  `reset_token` varchar(16) DEFAULT NULL,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_vm_history`
--

DROP TABLE IF EXISTS `c_vm_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_vm_history` (
  `pid` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `name` varchar(255) NOT NULL,
  `cpu` int(10) unsigned NOT NULL,
  `ram_size` int(10) unsigned NOT NULL,
  `hdd_size` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_vuln_scans`
--

DROP TABLE IF EXISTS `c_vuln_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_vuln_scans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL,
  `plugin_id` int(10) unsigned NOT NULL,
  `scan_date` datetime NOT NULL,
  `folder_id` int(10) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `i_plugin_id` (`plugin_id`),
  KEY `i_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_vulnerabilities`
--

DROP TABLE IF EXISTS `c_vulnerabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_vulnerabilities` (
  `plugin_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(512) NOT NULL,
  `severity` int(10) unsigned NOT NULL,
  `flags` int(10) unsigned NOT NULL,
  PRIMARY KEY (`plugin_id`),
  KEY `i_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_vv_history`
--

DROP TABLE IF EXISTS `c_vv_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_vv_history` (
  `pid` int(10) unsigned NOT NULL,
  `date` datetime NOT NULL,
  `name` varchar(255) NOT NULL,
  `usr_rawrsvd_mb` int(10) unsigned NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `c_zabbix_hosts`
--

DROP TABLE IF EXISTS `c_zabbix_hosts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `c_zabbix_hosts` (
  `name` varchar(255) NOT NULL,
  `pid` int(10) unsigned NOT NULL,
  `host_id` int(10) unsigned NOT NULL DEFAULT 0,
  `flags` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`name`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-07-05 15:17:24
