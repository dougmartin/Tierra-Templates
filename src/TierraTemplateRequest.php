<?php

	class TierraTemplateRequest {
		
		private $__vars;
		private $__settings;
		private $__blocks;
		private $__decorators;
		private $__decoratorStack;
		
		public function __construct($settings=array()) {
			$this->__vars = array();
			$this->__settings = $settings;
			$this->__blocks = array();
			$this->__decorators = array();
			$this->__decoratorStack = array();
			
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
			return $this->__settings[$to][$name] = $value;
		}
		
		public function getParams($from="request") {
			$source = $this->getSetting($from);
			return $source ? array_slice($source, 0) : array();
		}
		
		public function setVar($name, $value, $attrs=array()) {
			$parent = &$this->__vars;
			array_unshift($attrs, $name);
			while ($attr = array_shift($attrs)) {
				if (count($attrs) > 0) {
					if (is_array($parent)) {
						if (!isset($parent[$attr]))
							$parent[$attr] = array();
						$parent = &$parent[$attr];
					}
					else {
						if (!isset($parent->$attr))
							$parent->$attr = new stdClass;
						$parent = &$parent->$attr;
					}
				}
				else if (is_array($parent))
					$parent[$attr] = $value;
				else
					$parent->$attr = $value;
			}
			return $value;
		}
		
		public function getVar($name, $default=false) {
			return isset($this->__vars[$name]) ? $this->__vars[$name] : $default;	
		}
		
		public function haveVar($name) {
			return isset($this->__vars[$name]);	
		}
		
		public function setVars($map) {
			foreach ($map as $name => $value)
				$this->setVar($name, $value);
			return $map;
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
			return $this->__settings[$name] = $value;
		}
		
		public function addSetting($name, $value) {
			if (!isset($this->__settings[$name]))
				$this->__settings[$name] = $value;
		}
			
		public function getSettings() {
			// return a copy of the settings
			return array_slice($this->__settings, 0);
		}		
		
		function haveBlock($blockName) {
			return isset($this->__blocks[$blockName]);
		}
		
		public function getBlock($blockName, $default=false) {
			return isset($this->__blocks[$blockName]) ? $this->__blocks[$blockName] : $default;	
		}		
		
		function setBlock($blockName, $blockContents) {
			return $this->__blocks[$blockName] = $blockContents;
		}
		
		function appendBlock($blockName, $blockContents) {
			if ($this->haveBlock($blockName))
				$this->__blocks[$blockName] .= $blockContents;
			else
				$this->__blocks[$blockName] = $blockContents;
		}
		
		function prependBlock($blockName, $blockContents) {
			if ($this->haveBlock($blockName))
				$this->__blocks[$blockName] = $blockContents . $this->__blocks[$blockName];
			else
				$this->__blocks[$blockName] = $blockContents;
		}
		
		function echoBlock($blockName) {
			$haveBlock = $this->haveBlock($blockName);
			echo $haveBlock ? $this->__blocks[$blockName] : "";
			return $haveBlock;
		}
		
		function redirect($url) {
			header("Location: {$url}");
		}
		
		function reload() {
			header("Location: {$this->server["REQUEST_URI"]}");
		}
		
		function moved($url) {
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: {$url}");
			header("Connection: close");
		}
		
		// used by the code generator - do not call directly
		public function __decorator($action, $method, $blockName = "") {
			$this->__decorators[$blockName][$method] = $action;
		}
		
		// $blockName is empty on page blocks 
		public function __startDecorator($action, $method, $blockName = "") {
			$result = false;
			switch ($action) {
				case "add":
					if (!isset($this->__decorators[$blockName][$method])) {
						$this->__decorators[$blockName][$method] = "add";
						$result = true;
					}
					break;					
					
				case "append":
					if (!isset($this->__decorators[$blockName][$method]) || ($this->__decorators[$blockName][$method] != "remove")) {
						$this->__decorators[$blockName][$method] = "append";
						$result = true;
					}
					break;					
												
				case "set":
					$this->__decorators[$blockName][$method] = "set";
					$result = true;
					break;					
			}
			array_push($this->__decoratorStack, $result);
			return $result;
		}				
		
		public function __endDecorator() {
			return array_pop($this->__decoratorStack);
		}
	}