<?php
	/*
	 * Tierra Templates - %VERSION%
	 * 
	 * http://tierratemplates.com/
	 *
	 * Copyright (c) 2009 Tierra Innovation (http://tierra-innovation.com)
	 * 
 	 * This project is available for use in all personal or commercial projects under both MIT and GPL2 licenses. 
 	 * This means that you can choose the license that best suits your project, and use it accordingly.
	 * 
	 * MIT License: http://www.tierra-innovation.com/license/MIT-LICENSE.txt
	 * GPL2 License: http://www.tierra-innovation.com/license/GPL-LICENSE.txt
	 * 
	 */

	class TierraTemplateRequest {
		
		private $__vars;
		private $__settings;
		private $__blocks;
		private $__decorators;
		private $__decoratorStack;
		private $__guids;
		public  $__escapeSettingStack;
		
		public $__scratchPad;
		
		public function __construct($settings=array()) {
			$this->__vars = array();
			$this->__settings = $settings;
			$this->__blocks = array();
			$this->__decorators = array();
			$this->__decoratorStack = array();
			
			$this->addSetting("server", $_SERVER);
			$this->addSetting("env", $_ENV);
			
			$this->addSetting("get", $_GET);
			$this->addSetting("post", $_POST);
			$this->addSetting("files", $_FILES);
			$this->addSetting("request", $_REQUEST);
			$this->addSetting("session", isset($_SESSION) ? $_SESSION : array());
			$this->addSetting("cookie", $_COOKIE);
			
			// demand filled in getGuid()
			$this->__guids = array();

			// used by decorators as a namespace for temp variables
			$this->__scratchPad = new stdClass;
			
			// set the base escape setting
			$this->__escapeSettingStack = array();
			$this->pushEscapeSetting($this->getSetting("autoEscapeOutput", true), false);
		}
		
		public function rawOutput($text) {
			echo $text;
		}
		
		public function output($text) {
			if (!is_string($text))
				$text = json_encode($text);
			$escapeSetting = $this->getEscapeSetting();
			echo $escapeSetting["escape"] ? htmlspecialchars($text) : $text;
			if ($escapeSetting["autoPop"])
				$this->popEscapeSetting();
		}
		
		public function getEscapeSetting() {
			return $this->__escapeSettingStack[count($this->__escapeSettingStack) - 1];
		}
		
		public function pushEscapeSetting($escape, $autoPop) {
			$this->__escapeSettingStack[] = array("escape" => $escape, "autoPop" => $autoPop);
		}
		
		public function popEscapeSetting() {
			// always keep the base escape setting
			if (count($this->__escapeSettingStack) > 0)
				array_pop($this->__escapeSettingStack);
		}
		
		public function escape($text="") {
			$this->pushEscapeSetting(true, true);
			return $text;
		}

		public function noEscape($text="") {
			$this->pushEscapeSetting(false, true);
			return $text;
		}		
		
		public function getGuid($vary="url") {
			if (isset($this->__guids[$vary]))
				return $this->__guids[$vary];
				
			$requestSignature = array();
			if ($vary != "") {
				foreach (explode(",", $vary != "all" ? $vary : "url, get, post, files, request, session, cookie") as $setting) {
					$setting = trim($setting);
					if (!isset($this->__guids[$setting])) {
						if ($setting == "url")
							$this->__guids[$setting] = sha1($this->getParam("REQUEST_URI", "", "server"));
						else if (($bracketPos = strpos($setting, "[")) !== false) {
							$param = substr($setting, $bracketPos + 1, -1);
							if (($param[0] == "'") || ($param[0] == '"'))
								$param = substr($param, 1, -1);
							$from = substr($setting, 0, $bracketPos);
							$this->__guids[$setting] = sha1(var_export($this->getParam($param, false, $from), true));
						}
						else
							$this->__guids[$setting] = sha1(var_export($this->getSetting($setting), true));
					}
					$requestSignature[] = $this->__guids[$setting];
				}
			}
			
			$this->__guids[$vary] = sha1(implode("", $requestSignature));
			
			return $this->__guids[$vary];
		}
		
		public function uri($startIndex=0, $length=false) {
			$addSlash = ($startIndex == 0);
			
			$parts = explode("/", $this->getParam("REQUEST_URI", "/", "server"));
			array_shift($parts);
			$numParts = count($parts);
			if ($startIndex < 0)
				$startIndex = max($numParts + $startIndex, 0);
			else
				$startIndex = min($startIndex, $numParts - 1);
				
			if ($length === false)
				$length = $numParts;

			return ($addSlash ? "/" : "") . implode("/", array_slice($parts, $startIndex, $length));
		}
		
		public function getParam($name, $default=false, $from="request", $trim=true) {
			$source = $this->getSetting($from);
			$value = $default;
			if ($source)
				$value = isset($source[$name]) ? $source[$name] : (isset($source->$name) ? $source->$name : $default); 
			return $trim && is_string($value) ? trim($value) : $value;
		}
		
		public function setParam($name, $value, $to="request") {
			if (is_object($this->__settings[$to]))
				return $this->__settings[$to]->$name = $value;
			else
				return $this->__settings[$to][$name] = $value;
		}
		
		public function getParams($names=false, $from="request", $trim=true) {
			$source = $this->getSetting($from);
			if ($names === false)
				return $source ? array_slice($source, 0) : array();
				
			$values = array();
			foreach ($names as $name => $value) {
				$key = is_int($name) ? $value : $name;
				$default = is_int($name) ? $value : false;
				$value = isset($source[$key]) ? $source[$key] : (isset($source->$key) ? $source->$key : $default);
				$values[] = $trim ? trim($value) : $value;
			}
			return $values;
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
		
		function redirectToReferrer() {
			if (!isset($_SERVER["HTTP_REFERER"]))
				return;
			$parts = parse_url($_SERVER["HTTP_REFERER"]);
			if ($parts["path"] != $_SERVER["REQUEST_URI"])
				header("Location: " . $_SERVER["HTTP_REFERER"]);
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