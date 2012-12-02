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
class favo_Max_Model_Device_History extends Zend_Db_Table_Abstract
{
	protected $_name = 'device_history';
	protected $_primary = 'pk';
	protected $_serial;
	protected $_time;
	protected $_data;

	
	public function setupTable () {
		$db = Zend_Db_Table_Abstract::getDefaultAdapter();
		$query = <<<EOS
CREATE TABLE IF NOT EXISTS `{$this->_name}` (
  `pk` varchar(128) NOT NULL DEFAULT '',
  `serial` varchar(64) DEFAULT NULL,
  `time` int(11) DEFAULT NULL,
  `data` blob,
  PRIMARY KEY (`pk`),
  KEY `serial` (`serial`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
EOS;
		$db->query($query);
	}
}
