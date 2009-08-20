<?php
	class TierraTemplateRuntime {
	
		private $stack;
		private $context;
		private $request;
		
		public function __construct($request) {
			$this->stack = array();
			$this->context = null;
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
			if ($name[0] == "$")
				return ($this->context ? $this->context->specialValue(substr($name,1)) : false);
				
			// walk down the stack looking for the identifier
			for ($i=count($this->stack)-1; $i>=0; $i--) {
				$frame = $this->stack[$i];
				if ($frame->hasIdentifier($name))
					return $frame->identifier($name);
			}
	
			// finally look in the request
			return $this->request->getVar($name, false);
		}
		
		public function startGenerator($expression) {
			$result = false;
			if (is_array($expression) || is_object($expression))
				$result = count($expression) > 0;
			else if (is_string($expression))
				$result = strlen($expression) > 0;
			else if (is_int($expression))
				$result != 0;
			else 
				$result = $expression;
				
			$this->context = new TemplateStackFrame($expression);
			$this->stack[] = $this->context;
			
			return $result;
		}
		
		public function endGenerator() {
			array_pop($this->stack);
			$this->context = count($this->stack) > 0 ? $this->stack[count($this->stack) - 1] : false;
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
			return $this->context->loop();
		}
		
		public function setLoopMod($mod) {
			$this->context->setLoopMod($mod);
		}
		
		public function inLoopStep($start, $end, $mod) {
			return $this->context->inLoopStep($start, $end, $mod);
		}

		
	}
