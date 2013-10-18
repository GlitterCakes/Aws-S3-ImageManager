<?php
/**
 * Bootstrap
 * 
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	/**
	 * Init Config
	 * 
	 * Stores config params for later use
	 * @return void 
	 */
	protected function _initConfig()
	{
		Zend_Registry::set('config', $this->getOptions());
	}
}

