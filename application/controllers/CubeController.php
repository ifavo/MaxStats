<?php

/**
 * CubeController
 *
 * receive cube input
 *
 * @Category   Controller
 * @package    Max
 * @subpackage Cube
 * @Extends    Zend_Controller_Action
 * @Copyright  Copyright (c) 2011 Mario Micklisch
 */
class CubeController extends Zend_Controller_Action {
	private function getMax() {
		static $max;
		if ( !$max ) {
			$max = new favo_Max_Data();
		}
		return $max;
	}

    public function init() {
	    $this->_helper->contextSwitch()
	    									->setActionContext('export', 'json')
	    									->setAutoJsonSerialization(true)
	    									->initContext();
    }

	/**
	 * accept POST from Max!Buddy and import status
	 */
    public function exportAction() {
	    $this->_helper->contextSwitch()->initContext('json');
	    $data = $this->getRequest()->getPost();
		$this->view->cubes = $cubes = $this->getMax()->importStatus($data);
//		$this->_redirect('/index/dashboard/cubes/' . join(',',$cubes));
    }
}
