<?php

/**
 * IndexController
 *
 * index, the very beginning
 *
 * @Category   Controller
 * @package    MobileLink
 * @subpackage Site
 * @Extends    Zend_Controller_Action
 * @Copyright  Copyright (c) 2011 Mario Micklisch
 */
class IndexController extends Zend_Controller_Action {
	private function getMax() {
		static $max;
		if ( !$max ) {
			$max = new favo_Max_Data();
		}
		return $max;
	}

    public function init() {
	    $this->_helper->contextSwitch()
	    									->setActionContext('history', 'json')
	    									->setActionContext('devices', 'json')
	    									->setActionContext('config', 'json')
	    									->setAutoJsonSerialization(true)
	    									->initContext();
    }

    public function indexAction() {

    	$form = new Zend_Form();
    	$form->setAttrib('enctype', 'multipart/form-data')->setMethod('POST');
		$file = new Zend_Form_Element_File('file');
		$file->setLabel('Max-Buddy-Export:')
			->setDestination( APPLICATION_PATH . '/../data/upload')
			->addValidator('Count', false, 1)
			->addValidator('Size', false, 1024*1024);

		$submit = new Zend_Form_Element_Submit('submit');
		$form->addElements(array($file, $submit));
		$this->view->form = $form;


/*
    	$form = new Zend_Form();
    	$form->setAttrib('enctype', 'multipart/form-data')->setMethod('POST');
		$file = new Zend_Form_Element_File('file');
		$file->setLabel('LogView-Export:')
			->setDestination( APPLICATION_PATH . '/../data/upload')
			->addValidator('Count', false, 1)
			->addValidator('Size', false, 1024*1024);

		$submit = new Zend_Form_Element_Submit('submit');
		$type = new Zend_Form_Element_Hidden('type');
		$type->setValue('logview');

		$cube = new Zend_Form_Element_Hidden('cube');
		$cube->setValue('JEQ0193016');

		$serial = new Zend_Form_Element_Hidden('serial');
		$serial->setValue('ash2200');


		$form->addElements(array($file, $submit, $type, $cube, $serial));
		$this->view->form2 = $form;
		
*/
		if ( $form->isValid($form->getValues()) && $form->file->receive()) {
			$uploadedFile = $file->getDestination() . '/' . $form->getValue('file');
			if ( $form->getValue('file') && file_exists($uploadedFile) ) {
			
				switch ( $this->getRequest()->getParam('type') ) {
					// LogView temp history
					case 'logview':
						$cube = $this->getRequest()->getParam('cube');
						$serial = $this->getRequest()->getParam('serial');
						$title = $this->getRequest()->getParam('title');
						$this->getMax()->importLogView($uploadedFile, $cube, $serial, $title);
						$this->_redirect('/index/stats/cubes/' . $cube);
						break;
					
					// OpenWeather.org upload
					case 'openweather':
						$cube = $this->getRequest()->getParam('cube');
						$serial = $this->getRequest()->getParam('serial');
						$title = $this->getRequest()->getParam('title');
						$this->getMax()->importOpenWeather($uploadedFile, $cube, $serial, $title);
						$this->_redirect('/index/stats/cubes/' . $cube);
						break;
				
					// max buddy export is default
					default:
						$cubes = $this->getMax()->importFile($uploadedFile);
						unlink ($uploadedFile);
						$this->_redirect('/index/stats/cubes/' . join(',',$cubes));
						break;
				}
			}
		}
    }
    
    public function historyAction () {
	    $this->_helper->contextSwitch()->initContext('json');
		$serial = $this->getRequest()->getParam('serial');
		$from = $this->getRequest()->getParam('from', (time()-86400+60));
		$to = $this->getRequest()->getParam('to', null);

		if ( is_array($serial) ) {
			$result = array();
			foreach ( $serial as $singleSerial ) {
				$result[$singleSerial] = array ();
				$result[$singleSerial]['history'] = $this->getMax()->getHistory($singleSerial, $from, $to);
				$result[$singleSerial]['device'] = $this->getMax()->getDevice($singleSerial);
			}
			$this->view->result = $result;
		}
		else {
			$this->view->history = $this->getMax()->getHistory($serial, $from, $to);
			$this->view->device = $this->getMax()->getDevice($serial);
		}
    }
    
    public function devicesAction() {
	    $this->_helper->contextSwitch()->initContext('json');
		
		$data = $this->getMax()->getDevices('Room');
		$this->view->devices = $data;
    }

	public function statsAction () {
		if ( !$this->getRequest()->getParam('cubes') ) {
			$this->_forward('cube');
		}
		else {
			$cubes = explode(',', $this->getRequest()->getParam('cubes'));
			$this->view->rooms = $this->getMax()->getDevices('Room', $cubes);
			$this->view->valves = $this->getMax()->getDevices('HeatingThermostat', $cubes);
			$this->view->windows = $this->getMax()->getDevices('ShutterContact', $cubes);
			$this->view->weather = $this->getMax()->getDevices('logview', $cubes);
		
			if ( !count($this->view->rooms) ) {
				throw new Exception("No Rooms found");
			}
	
			if ( !count($this->view->valves) ) {
				throw new Exception("No Devices found");
			}
		}
	}

	public function dashboardAction () {
		if ( !$this->getRequest()->getParam('cubes') ) {
			$this->_forward('cube');
		}
		else {
			$cubes = explode(',', $this->getRequest()->getParam('cubes'));
			$this->view->rooms = $this->getMax()->getDevices('Room', $cubes);
			$this->view->valves = $this->getMax()->getDevices('HeatingThermostat', $cubes);
			$this->view->windows = $this->getMax()->getDevices('ShutterContact', $cubes);
			$this->view->weather = $this->getMax()->getDevices('logview', $cubes);
		
			if ( !count($this->view->rooms) ) {
				throw new Exception("No Rooms found");
			}
	
			if ( !count($this->view->valves) ) {
				throw new Exception("No Devices found");
			}
		}
	}
	
	public function configAction () {
	    $this->_helper->contextSwitch()->initContext('json');
	    
	    switch ( $this->getRequest()->getParam('cmd') ) {
	    	case "assignRoom":
	    		$this->getMax()->assignRoom($this->getRequest()->getParam('serial'), $this->getRequest()->getParam('room'));
	    		break;
	    }
	}
	
	public function cubeAction () {
    	$form = new Zend_Form();
		$cubes = new Zend_Form_Element_Text('cubes');
		$cubes->setLabel('Cube(s):')->setValue('JEQ0193016');
		$submit = new Zend_Form_Element_Submit('submit');
		$form->addElements(array($cubes, $submit));
		$form->setAction('/index/dashboard');
		EasyBib_Form_Decorator::setFormDecorator($form, EasyBib_Form_Decorator::BOOTSTRAP, 'submit');
		$this->view->form = $form;
	}
}
