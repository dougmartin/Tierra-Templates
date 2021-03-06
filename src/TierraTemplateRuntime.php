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

	require_once dirname(__FILE__) . "/TierraTemplateStackFrame.php";
	
	class TierraTemplateRuntime {
	
		private $stackFrame;
		private $currentFrame;
		private $request;
		private $options;
		private $loadedFunctions;
		private $loadedIdentifiers;
		
		public function __construct($request, $options) {
			$this->request = $request;
			
			// add the built in externals
			if (!isset($options["virtualDirs"]["_"])) {
				// rebuild the array so our builtin is first when we loop on it
				$virtualDirs["_"] = array(
					"path" => dirname(__FILE__) . "/internals",
					"classPrefix" => "TierraTemplateInternals_",
					"functionPrefix" => "TierraTemplateInternals_",
				);
				if (isset($options["virtualDirs"])) {
					foreach ($options["virtualDirs"] as $virtualDir => $dirInfo)
						$virtualDirs[$virtualDir] = $dirInfo;
				}
				$options["virtualDirs"] = $virtualDirs;
			}			
			
			// validate the virtual dirs
			if (isset($options["virtualDirs"])) {
				foreach ($options["virtualDirs"] as $virtualDir => $dirInfo) {
					if (!isset($dirInfo["path"]))
						throw new TierraTemplateException("No path given in options for '{$virtualDir}' virtualDir");
				}
			}
			
			$this->options = $options;
			
			$this->stackFrame = array();
			$this->currentFrame = false;
			$this->loadedFunctions = array();
			$this->loadedIdentifiers = array();
		}
		
		public function attr($var, $name) {
			$value = "";
			if (is_array($var) && isset($var[$name]))
				$value = $var[$name];
			else if (is_object($var) && isset($var->$name))
				$value = $var->$name;
			return $value;
		}
		
		public function identifier($name) {
			// walk down the stack looking for the identifier
			for ($i=count($this->stackFrame)-1; $i>=0; $i--) {
				$currentFrame = $this->stackFrame[$i];
				if ($currentFrame->hasIdentifier($name))
					return $currentFrame->identifier($name);
			}
	
			// look in the request
			if ($this->request->haveVar($name))
				return $this->request->getVar($name);
				
			// finally look in the blocks
			return $this->request->getBlock($name, "");
		}
		
		public function specialIdentifier($name) {
			return ($this->currentFrame ? $this->currentFrame->specialIdentifier($name) : false);
		}
		
		public function externalIdentifier($name, $className, $virtualDir, $subDir, $debugInfo) {

			$signature = "{$virtualDir}/{$subDir}/{$className}";
			
			if (!isset($this->loadedIdentifiers[$signature])) {
				if ($virtualDir) {
					if (isset($this->options["virtualDirs"][$virtualDir])) {
						$dirInfo = $this->options["virtualDirs"][$virtualDir];
						$path = $this->findExternalPath($dirInfo["path"], $subDir, $className);
						if ($path) {
							include_once $path;
							$this->loadedIdentifiers[$signature] = get_class_vars($this->addPrefix($dirInfo, $className ? $className : "index", "classPrefix"));
						}
					}
				}
				else {
					if (isset($this->options["virtualDirs"])) {
						foreach ($this->options["virtualDirs"] as $virtualDir => $dirInfo) {
							$path = $this->findExternalPath($dirInfo["path"], $subDir, $className);
							if ($path) {
								include_once $path;
								$this->loadedIdentifiers[$signature] = get_class_vars($this->addPrefix($dirInfo, $className ? $className : "index", "classPrefix"));
								if (isset($this->loadedIdentifiers[$signature][$name]))
									break;
							}
						}
					}
				}
			}
			
			return isset($this->loadedIdentifiers[$signature][$name]) ? $this->loadedIdentifiers[$signature][$name] : false; 
		}
		
		public function startConditerator($expression) {
			$this->currentFrame = new TierraTemplateStackFrame($expression, $this->currentFrame);
			$this->stackFrame[] = $this->currentFrame;
			
			$result = false;
			if (is_array($expression) || is_object($expression))
				$result = count($expression) > 0;
			else if (is_string($expression))
				$result = strlen($expression) > 0;
			else if (is_int($expression))
				$result = $expression != 0;
			else 
				$result = $expression;
			return $result;			
		}
		
		public function endConditerator() {
			array_pop($this->stackFrame);
			$this->currentFrame = count($this->stackFrame) > 0 ? $this->stackFrame[count($this->stackFrame) - 1] : false;
		}
		
		public function call($functionName, $debugInfo, $params=array()) {
			if (function_exists($functionName))
				return call_user_func_array($functionName, $params);
			return $this->externalCall($functionName, "", "", "", $debugInfo, $params);
		}
		
		public function externalCall($functionName, $className, $virtualDir, $subDir, $debugInfo, $params=array()) {

			if ("{$virtualDir}/{$subDir}/{$className}" == "//request") {
				if (method_exists($this->request, $functionName))
					return call_user_func_array(array($this->request, $functionName), $params);
				throw new TierraTemplateException("Internal function not found for {$debugInfo}");
			}
			
			$signature = "{$virtualDir}/{$subDir}/{$className}/{$functionName}";
			if (!isset($this->loadedFunctions[$signature])) {
				if ($virtualDir) {
					if (isset($this->options["virtualDirs"][$virtualDir])) {
						$dirInfo = $this->options["virtualDirs"][$virtualDir];
						$path = $this->findExternalPath($dirInfo["path"], $subDir, $className, $functionName);
						if (!$path)
							throw new TierraTemplateException("Virtual directory '{$virtualDir}' found but function file was not found for {$debugInfo}");
						
						include_once $path;
						
						if (method_exists($prefixedClassName = $this->addPrefix($dirInfo, $className ? $className : "index", "classPrefix"), $functionName))
							$this->loadedFunctions[$signature] = array($prefixedClassName, $functionName);
						else if (function_exists($prefixedFunctionName = $this->addPrefix($dirInfo, $functionName, "functionPrefix")))
							$this->loadedFunctions[$signature] = $prefixedFunctionName;
					}
					else
						throw new TierraTemplateException("Virtual directory '{$virtualDir}' not found in template options for {$debugInfo}");
				}
				else {
					if (isset($this->options["virtualDirs"])) {
						foreach ($this->options["virtualDirs"] as $virtualDir => $dirInfo) {
							$path = $this->findExternalPath($dirInfo["path"], $subDir, $className, $functionName);
							if ($path) {
								include_once $path;
								if (method_exists($prefixedClassName = $this->addPrefix($dirInfo, $className ? $className : "index", "classPrefix"), $functionName)) {
									$this->loadedFunctions[$signature] = array($prefixedClassName, $functionName);
									$this->loadedIdentifiers["{$virtualDir}/{$subDir}/{$className}"] = $prefixedClassName;
									break;
								}
								else if (function_exists($prefixedFunctionName = $this->addPrefix($dirInfo, $functionName, "functionPrefix"))) {
									$this->loadedFunctions[$signature] = $prefixedFunctionName;
									break;
								}
							}
						}
					}
				}
			}

			if (isset($this->loadedFunctions[$signature]))
				return call_user_func_array($this->loadedFunctions[$signature], $params);
			
			throw new TierraTemplateException("External function not found for {$debugInfo}");
		}
		
		// on BSD systems prior to 5.3 realpath() does not look at the last component of the path when seeing if it exists so we need to add the file_exists calls
		private function findExternalPath($virtualPath, $subDir, $className, $functionName=false) {
			$path = false;
			if ($className) {
				$path = realpath("{$virtualPath}/{$subDir}/{$className}.php");
				$path = file_exists($path) ? $path : false;
			}
			if (!$path && $functionName) {
				$path = realpath("{$virtualPath}/{$subDir}/{$functionName}.php");
				$path = file_exists($path) ? $path : false;
			}
			if (!$path) {
				$path = realpath("{$virtualPath}/{$subDir}/index.php");
				$path = file_exists($path) ? $path : false;
			}
			return $path;
		}

		private function addPrefix($dirInfo, $text, $prefixSetting) {
			return isset($dirInfo[$prefixSetting]) ? "{$dirInfo[$prefixSetting]}{$text}" : $text; 
		}
		
		public function limit($value, $start, $length=false) {
			return is_array($value) ? ($length !== false ? array_slice($value, $start, $length) : array_slice($value, $start)) : $value;
		}
		
		public function loop() {
			return $this->currentFrame ? $this->currentFrame->loop() : false;
		}
		
		public function currentValue() {
			return $this->currentFrame ? $this->currentFrame->currentValue() : false;
		}
		
		public function cycle($values) {
			if (count($values) == 0)
				return false;
			return $values[$this->currentFrame->currentIndex() % count($values)];
		}
		
	}
