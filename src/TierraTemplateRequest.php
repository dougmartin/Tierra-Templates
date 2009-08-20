<?php

	class TierraTemplateRequest {
		
		private $__vars;
		private $__settings;
		
		public function __construct($settings=array()) {
			$this->__vars = array();
			$this->__settings = $settings;
			
			$this->addSetting("server", $_SERVER);
			$this->addSetting("get", $_GET);
			$this->addSetting("post", $_POST);
			$this->addSetting("files", $_FILES);
			$this->addSetting("request", $_REQUEST);
			$this->addSetting("session", isset($_SESSION) ? $_SESSION : array());
			$this->addSetting("env", $_ENV);
			$this->addSetting("cookie", $_COOKIE);
		}
		
		public function getParam($name, $default=false, $from="request") {
			$source = $this->getSetting($from);
			if ($source)
				return isset($source[$name]) ? $source[$name] : $default; 
			return $default;
		}
		
		public function setParam($name, $value, $to="request") {
			$this->__settings[$to][$name] = $value;
		}
		
		public function getParams($from="request") {
			$source = $this->getSetting($from);
			return $source ? array_slice($source, 0) : array();
		}
		
		public function setVar($name, $value) {
			$this->__vars[$name] = $value;
		}
		
		public function getVar($name, $default=false) {
			return isset($this->__vars[$name]) ? $this->__vars[$name] : $default;	
		}
		
		public function setVars($map) {
			foreach ($map as $name => $value)
				$this->setVar($name, $value);
		}
		
		public function getVars() {
			// return a copy of the vars
			return array_slice($this->__vars, 0);
		}
		
		public function __set($name, $value) {
			$this->setVar($name, $value);
		}
		
		public function __get($name) {
			return $this->getVar($name);
		}
		
		public function __isset($name) {
			return isset($this->__vars[$name]);
		}
		
		public function __unset($name) {
			unset($this->__vars[$name]);
		}
		
		public function getSetting($name, $default=false) {
			return isset($this->__settings[$name]) ? $this->__settings[$name] : $default;
		}
				
		public function setSetting($name, $value) {
			$this->__settings[$name] = $value;
		}
		
		public function addSetting($name, $value) {
			if (!isset($this->__settings[$name]))
				$this->__settings[$name] = $value;
		}
			
		public function getSettings() {
			// return a copy of the settings
			return array_slice($this->__settings, 0);
		}		
	}