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
}
