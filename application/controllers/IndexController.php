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
    	$uploadDir = APPLICATION_PATH . '/../public/data/upload';
		if ( !file_exists($uploadDir) ) {
			if ( !mkdir($uploadDir, 0777, true) ) {
				throw new Exception("could not create upload directory at {$uploadDir}");
			}
		}

		if ( !is_writable($uploadDir) ) {
			throw new Exception("missing write permission to {$uploadDir}");
		}

    	$form = new Zend_Form();
    	$form->setAttrib('enctype', 'multipart/form-data')->setMethod('POST');
		$file = new Zend_Form_Element_File('file');
		$file->setLabel('Log-Upload:')
			->setDestination($uploadDir)
			->addValidator('Count', false, 1)
			->addValidator('Size', false, 1024*1024);

		$submit = new Zend_Form_Element_Submit('submit');
		$form->addElements(array($file, $submit));
		$this->view->form = $form;


		if ( $form->isValid($form->getValues()) && $form->file->receive()) {
			$uploadedFile = $file->getDestination() . '/' . $form->getValue('file');
			if ( $form->getValue('file') && file_exists($uploadedFile) ) {
				$cubes = array();

				switch ( $this->getRequest()->getParam('type') ) {
					// LogView temp history
					case 'logview':
						$cube = $this->getRequest()->getParam('cube');
						$serial = $this->getRequest()->getParam('serial');
						$title = $this->getRequest()->getParam('title');
						$this->getMax()->importLogView($uploadedFile, $cube, $serial, $title);
						$cubes[] = $cube;
						break;
					
					// OpenWeather.org upload
					case 'openweather':
						$cube = $this->getRequest()->getParam('cube');
						$serial = $this->getRequest()->getParam('serial');
						$title = $this->getRequest()->getParam('title');
						$this->getMax()->importOpenWeather($uploadedFile, $cube, $serial, $title);
						$cubes[] = $cube;
						break;
				
					// max buddy export is default
					default:
						$cubes = $this->getMax()->importFile($uploadedFile);
						break;
				}

				unlink ($uploadedFile);
				$this->_redirect('/index/dashboard/cubes/' . join(',',$cubes));
			}
		}
		else {
			$this->_forward('cube');
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
		$cubes = new Zend_Form_Element_Radio('cubes');
		$cubes->setLabel('Cube(s):');
		
		$cubeList = $this->getMax()->getCubes();
		
		// one cube? just forward to the dashboard!
		if ( sizeof($cubeList) == 1 ) {
			$cube = array_shift($cubeList);
			$this->_redirect('/index/dashboard/cubes/' . $cube['serial']);
			return;
		}

		$now = time();
		foreach ($cubeList as $cubeData) {
			$lastUpdate = $now - $cubeData['lastUpdate'];

			// only add cubes that have been updated in the last n hours
			if ( !$this->getRequest()->getParam('outdated') && $lastUpdate > (1*3600) ) {
				continue;
			}

			if ( $lastUpdate < 120 ) {
				$lastUpdate = "{$lastUpdate} Sekunden";
			}
			else if ( $lastUpdate < 3600 ) {
				$lastUpdate = round($lastUpdate/60) . " Minuten";
			}
			else {
				$lastUpdate = round($lastUpdate/3600) . " Stunden";
			}
			$title = " Serial: {$cubeData['serial']} | Geräte: {$cubeData['deviceCount']} | Letztes Update: vor {$lastUpdate}";
			$cubes->addMultiOption($cubeData['serial'], $title);
		}

		// auto select first cube in the list
		if ( sizeof($cubeList) ) {
			$firstEntry = array_shift($cubeList);
			$cubes->setValue($firstEntry['serial']);
		}

		$submit = new Zend_Form_Element_Submit('submit');
		$submit->setLabel('Cube laden');
		$form->addElements(array($cubes, $submit));
		$form->setAction('/index/dashboard')->setMethod('GET');
		EasyBib_Form_Decorator::setFormDecorator($form, EasyBib_Form_Decorator::BOOTSTRAP, 'submit');
		$this->view->form = $form;
	}
}
