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
}
