<?php
	require_once dirname(__FILE__) . "/TierraTemplateStackFrame.php";
	
	class TierraTemplateRuntime {
	
		private $stackFrame;
		private $currentFrame;
		private $request;
		private $options;
		private $loadedFunctions;
		
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
		
		public function externalIdentifier($name) {
			// TODO:: implement lookup of static vars
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
						
						$this->loadedFunctions[$signature] = $this->addFunctionPrefix($dirInfo, $functionName);
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
								if (function_exists($this->addFunctionPrefix($dirInfo, $functionName))) {
									$this->loadedFunctions[$signature] = $this->addFunctionPrefix($dirInfo, $functionName);
									break;
								}
							}
						}
					}
				}
			}

			if (isset($this->loadedFunctions[$signature])) {
				if (function_exists($this->loadedFunctions[$signature]))
					return call_user_func_array($this->loadedFunctions[$signature], $params);
			}
			
			throw new TierraTemplateException("External function not found for {$debugInfo}");
		}
		
		private function addFunctionPrefix($dirInfo, $functionName) {
			return isset($dirInfo["functionPrefix"]) ? "{$dirInfo["functionPrefix"]}{$functionName}" : $functionName; 
		}
		
		public function assign($name, $value) {
			$this->request->setVar($name, $value);
			return $value;
		}
		
		public function limit($value, $start, $length=false) {
			return is_array($value) ? ($length !== false ? array_slice($value, $start, $length) : array_slice($value, $start)) : $value;
		}
		
		public function loop() {
			return $this->currentFrame->loop();
		}
		
	}
