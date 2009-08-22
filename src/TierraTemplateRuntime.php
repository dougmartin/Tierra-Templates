<?php
	require_once dirname(__FILE__) . "/TierraTemplateStackFrame.php";
	
	class TierraTemplateRuntime {
	
		private $stackFrame;
		private $currentFrame;
		private $request;
		private $options;
		
		public function __construct($request, $options) {
			$this->request = $request;
			$this->options = $options;
			
			$this->stackFrame = array();
			$this->currentFrame = false;
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
		
		public function call($functionName, $params=array()) {
			if (function_exists($functionName)) {
				array_unshift($params, $functionName);
				return call_user_func_array(array("CMS", "Call"), $params);
			}
			throw new TierraTemplateException("Function not found: {$functionName}");
		}
		
		public function externalCall($functionName, $class, $virtualDir, $subDir, $debugInfo, $params=array()) {
			
			if (!class_exists($class)) {
				if ($virtualDir) {
					if (isset($this->options["virtualDirs"][$virtualDir])) {
						$filename = realpath("{$this->options["virtualDirs"][$virtualDir]}/{$subDir}/{$class}.php");
						if ($filename)
							include_once $filename;
						else
							throw new TierraTemplateException("Virtual directory '{$virtualDir}' found but class file '{$class}.php' was not found for {$debugInfo}");
					}
					else
						throw new TierraTemplateException("Virtual directory '{$virtualDir}' not found in template options for {$debugInfo}");
				}
				else {
					// search the virtual dirs for the class
					if (isset($this->options["virtualDirs"])) {
						foreach ($this->options["virtualDirs"] as $virtualDir => $path) {
							if (file_exists("{$path}/{$class}.php")) {
								include_once "{$path}/{$class}.php";
								break;
							}
						}
					}
				}
			}
			
			if (class_exists($class)) {
				if (method_exists($class, $functionName))
					return call_user_func_array(array($class, $functionName), $params);
				else
					throw new TierraTemplateException("Class found '{$class}' but function '{$functionName}' was not found for {$debugInfo}");
			}
			else
				throw new TierraTemplateException("Class '{$class}' not found for {$debugInfo}");
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
