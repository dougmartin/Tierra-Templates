<?php
	class TierraTemplateStackFrame {
		
		private $expression;
		private $loopIndex;
		private $loopEndIndex;
		private $loopValue;
		private $loopMod;
		private $loopIndices;
		
		public function __construct($expression) {
			$this->expression = $expression;
			$this->loopIndex = 0;
			$this->loopMod = 1;
			if (is_array($expression)) {
				$this->loopIndices = array_keys($expression);
				$this->loopValue = isset($this->loopIndices[0]) ? $expression[$this->loopIndices[0]] : false;
				$this->loopEndIndex = count($expression);
			}
			else {
				$this->loopValue = $expression;
				$this->loopEndIndex = 0;
			}
		}
		
		public function identifier($name) {
			$value = false;
			if (is_array($this->loopValue) && isset($this->loopValue[$name]))
				$value = $this->loopValue[$name];
			else if (is_object($this->loopValue)) {
				if (   isset($this->loopValue->$name)
					|| (   method_exists($this->loopValue, "izset")
						&& $this->loopValue->izset($name)))
					$value = $this->loopValue->$name;
			}
			return $value;
		}
		
		public function hasIdentifier($name) {
			return     (is_array($this->loopValue) && isset($this->loopValue[$name])) 
					|| (   is_object($this->loopValue) 
						&& (   isset($this->loopValue->$name)
							|| (   method_exists($this->loopValue, "izset")
								&& $this->loopValue->izset($name))));
		}
		
		public function loop() {
			$this->loopIndex++;
			$continue = $this->loopIndex < $this->loopEndIndex;
			if ($continue)
				$this->loopValue = $this->expression[$this->loopIndices[$this->loopIndex]];
			return $continue;
		}
		
		public function setLoopMod($mod) {
			$this->loopMod = $mod;
		}
		
		public function inLoopStep($start, $end, $mod=false) {
			$mod = ($mod === false ? $this->loopMod : $mod);
			$index = ($this->loopIndex % $mod);
			return (($index >= $start) && ($index <= $end));
		}
		
		public function currentValue($index) {
			return $this->loopValue[$index];
		}
		
		public function dollarValue($index) {
			switch ($index) {
				case "":
					$value = $this->loopValue;
					break;
					
				case "0":
					$value = $this->loopIndex;
					break;
					
				case "1":
					$value = $this->loopIndex + 1;
					break;
					
				case "key":
					$value = $this->loopIndices[$this->loopIndex];
					break;
					
				case "first":
					$value = ($this->loopIndex == 0);
					break;
					
				case "last":
					$value = ($this->loopIndex >= $this->loopEndIndex - 1);
					break;
					
				case "even":
					$value = ($this->loopIndex % 2 == 0);
					break;
					
				case "odd":
					$value = ($this->loopIndex % 2 != 0);
					break;
					
				case "count":
					$value = $this->loopEndIndex;
					break;
					
				default:
					$value = false;
			}
			return $value;
		}
	}
