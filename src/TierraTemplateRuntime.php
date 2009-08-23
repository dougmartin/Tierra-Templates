<?php
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
			$this->options = $options;
			
			// validate the virtual dirs
			if (isset($options["virtualDirs"])) {
				foreach ($options["virtualDirs"] as $virtualDir => $dirInfo) {
					if (!isset($dirInfo["path"]))
						throw new TierraTemplateException("No path given in options for '{$virtualDir}' virtualDir");
				}
			}
			
			$this->stackFrame = array();
			$this->currentFrame = false;
			$this->loadedFunctions = array();
			$this->loadedIdentifiers = array();
		}
		
		public function attr($var, $name) {
			$value = false;
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
			return $this->request->getBlock($name, false);
		}
		
		public function specialIdentifier($name) {
			return ($this->currentFrame ? $this->currentFrame->specialIdentifier($name) : false);
		}
		
		public function externalIdentifier($name, $filename, $virtualDir, $subDir, $debugInfo) {
			$signature = $this->loadExternalIdentifier($name, $filename, $virtualDir, $subDir);
			return isset($this->loadedIdentifiers[$signature][$name]) ? $this->loadedIdentifiers[$signature][$name] : $false; 
		}
		
		public function startGenerator($expression) {
			$this->currentFrame = new TierraTemplateStackFrame($expression);
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
		
		public function endGenerator() {
			array_pop($this->stackFrame);
			$this->currentFrame = count($this->stackFrame) > 0 ? $this->stackFrame[count($this->stackFrame) - 1] : false;
		}
		
		public function call($functionName, $debugInfo, $params=array()) {
			if (function_exists($functionName))
				return call_user_func_array($functionName, $params);
			return $this->externalCall($functionName, "", "", "", $debugInfo, $params);
		}
		
		public function externalCall($functionName, $filename, $virtualDir, $subDir, $debugInfo, $params=array()) {
			
			if ("{$virtualDir}/{$subDir}/{$filename}" == "//request") {
				if (method_exists($this->request, $functionName))
					return call_user_func_array(array($this->request, $functionName), $params);
				throw new TierraTemplateException("Internal function not found for {$debugInfo}");
			}
			
			if (!$filename)
				$filename = "index";
			
			$signature = "{$virtualDir}/{$subDir}/{$filename}/{$functionName}";
			if (!isset($this->loadedFunctions[$signature])) {
				if ($virtualDir) {
					if (isset($this->options["virtualDirs"][$virtualDir])) {
						$dirInfo = $this->options["virtualDirs"][$virtualDir];
						$path = realpath("{$dirInfo["path"]}/{$subDir}/{$filename}.php");
						if (!$path)
							throw new TierraTemplateException("Virtual directory '{$virtualDir}' found but function file was not found for {$debugInfo}");
						
						include_once $path;
						
						if (method_exists($prefixedClassName = $this->addPrefix($dirInfo, $filename, "classPrefix"), $functionName))
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
							$path = realpath("{$dirInfo["path"]}/{$subDir}/{$filename}.php");
							if ($path) {
								include_once $path;
								if (method_exists($prefixedClassName= $this->addPrefix($dirInfo, $filename, "classPrefix"), $functionName)) {
									$this->loadedFunctions[$signature] = array($prefixedClassName, $functionName);
									$this->loadedIdentifiers["{$virtualDir}/{$subDir}/{$filename}"] = $prefixedClassName;
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

		private function loadExternalIdentifier($name, $filename, $virtualDir, $subDir) {
			if (!$filename)
				$filename = "index";

			$signature = "{$virtualDir}/{$subDir}/{$filename}";
			
			if (!isset($this->loadedIdentifiers[$signature])) {
				if ($virtualDir) {
					if (isset($this->options["virtualDirs"][$virtualDir])) {
						$dirInfo = $this->options["virtualDirs"][$virtualDir];
						$path = realpath("{$dirInfo["path"]}/{$subDir}/{$filename}.php");
						if ($path) {
							include_once $path;
							$this->loadedIdentifiers[$signature] = get_class_vars($this->addPrefix($dirInfo, $filename, "classPrefix"));
						}
					}
				}
				else {
					if (isset($this->options["virtualDirs"])) {
						foreach ($this->options["virtualDirs"] as $virtualDir => $dirInfo) {
							$path = realpath("{$dirInfo["path"]}/{$subDir}/{$filename}.php");
							if ($path) {
								include_once $path;
								$prefixedClassName = $this->addPrefix($dirInfo, $filename, "classPrefix");
								$this->loadedIdentifiers[$signature] = get_class_vars($prefixedClassName);
								if (isset($this->loadedIdentifiers[$signature][$name]))
									break;
							}
						}
					}
				}
			}
			
			return $signature;
		}
		
		private function addPrefix($dirInfo, $text, $prefixSetting) {
			return isset($dirInfo[$prefixSetting]) ? "{$dirInfo[$prefixSetting]}{$text}" : $text; 
		}
		
		public function assign($name, $value, $attrs=array()) {
			return $this->request->setVar($name, $value, $attrs);
		}
		
		public function limit($value, $start, $length=false) {
			return is_array($value) ? ($length !== false ? array_slice($value, $start, $length) : array_slice($value, $start)) : $value;
		}
		
		public function loop() {
			return $this->currentFrame->loop();
		}
		
	}
