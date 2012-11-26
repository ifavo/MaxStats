SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `device`
-- ----------------------------
CREATE TABLE `device` (
  `serial` varchar(128) NOT NULL DEFAULT '',
  `type` varchar(64) DEFAULT NULL,
  `title` varchar(128) DEFAULT NULL,
  `lastUpdate` datetime DEFAULT NULL,
  `roomAssignment` varchar(128) DEFAULT NULL,
  `cube` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`serial`),
  KEY `room` (`roomAssignment`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ----------------------------
--  Table structure for `device_history`
-- ----------------------------
CREATE TABLE `device_history` (
  `pk` varchar(128) NOT NULL DEFAULT '',
  `serial` varchar(64) DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  `data` blob,
  PRIMARY KEY (`pk`),
  KEY `serial` (`serial`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;