<?php

/**
 * Device
 *
 * @Category   Model
 * @package    Max
 * @subpackage Device
 * @Extends    Zend_Db_Table_Abstract
 * @Copyright  Copyright (c) 2011 Mario Micklisch
 */
class favo_Max_Model_Device extends Zend_Db_Table_Abstract
{
	protected $_name = 'device';
	protected $_primary = 'serial';
	protected $_serial;
	protected $_title;
	protected $_type;
	protected $_lastUpdate;
	protected $_roomAssignment;
	protected $_cube;
	
	public function setupTable () {
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = <<<EOS
CREATE TABLE IF NOT EXISTS `{$this->_name}` (
  `serial` varchar(128) NOT NULL DEFAULT '',
  `type` varchar(64) DEFAULT NULL,
  `title` varchar(128) DEFAULT NULL,
  `lastUpdate` datetime DEFAULT NULL,
  `roomAssignment` varchar(128) DEFAULT NULL,
  `cube` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`serial`),
  KEY `room` (`roomAssignment`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOS;
		$db->query($query);
	}
}
