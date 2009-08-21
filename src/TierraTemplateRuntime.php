<?php
	require_once dirname(__FILE__) . "/TierraTemplateStackFrame.php";
	
	class TierraTemplateRuntime {
	
		private $stackFrame;
		private $currentFrame;
		private $request;
		
		public function __construct($request) {
			$this->stackFrame = array();
			$this->currentFrame = false;
			$this->request = $request;
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
		
			// see if this is a special value
			if (($name[0] == "$") || ($name[0] == "%")) 
				return ($this->currentFrame ? $this->currentFrame->specialValue(substr($name,1)) : false);
				
			// walk down the stack looking for the identifier
			for ($i=count($this->stackFrame)-1; $i>=0; $i--) {
				$currentFrame = $this->stackFrame[$i];
				if ($currentFrame->hasIdentifier($name))
					return $currentFrame->identifier($name);
			}
	
			// finally look in the request
			return $this->request->getVar($name, false);
		}

		public function canGenerate($expression) {
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
		
		public function startGenerator($expression) {
			$this->currentFrame = new TierraTemplateStackFrame($expression);
			$this->stackFrame[] = $this->currentFrame;
			
			return $this->canGenerate($expression);
		}
		
		public function startConditionalGenerator($expression, $value) {
			$this->currentFrame = new TierraTemplateStackFrame($value);
			$this->stackFrame[] = $this->currentFrame;
			
			return $this->canGenerate($expression);
		}
		
		public function endGenerator() {
			array_pop($this->stackFrame);
			$this->currentFrame = count($this->stackFrame) > 0 ? $this->stackFrame[count($this->stackFrame) - 1] : false;
		}
		
		public function call($functionName, $params=array()) {
			// TODO: convert from CMS
			array_unshift($params, $functionName);
			return call_user_func_array(array("CMS", "Call"), $params);
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
