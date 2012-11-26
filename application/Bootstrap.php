<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap {
		/**
		 *	init the configuration as a global available object
		 *	@return Zend_Config
		 */
		protected function _initConfig() {
			$config = new Zend_Config($this->getOptions(), true);
			Zend_Registry::set('config', $config);
			return $config;
		}

		/**
		 *	init some view helpers
		 *	@return void
		 */
		protected function _initViewHelpers () {
			$view = new Zend_View();
			$view->addHelperPath('EasyBib/View/Helper', 'EasyBib_View_Helper');
			
			// jquery helper
			$view->addHelperPath('ZendX/JQuery/View/Helper/', 'ZendX_JQuery_View_Helper');

			$viewRenderer = new Zend_Controller_Action_Helper_ViewRenderer();
			$viewRenderer->setView($view);
			Zend_Controller_Action_HelperBroker::addPath(APPLICATION_PATH . '/controllers/helpers');
			Zend_Controller_Action_HelperBroker::addHelper($viewRenderer);
		}
}
