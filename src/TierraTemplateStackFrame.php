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
			if (is_array($this->loopValue) && isset($this->loopValue[$name]))
				return $this->loopValue[$name];
			if (is_object($this->loopValue) && isset($this->loopValue->$name))
				return $this->loopValue->$name;
			return false;
		}
		
		public function hasIdentifier($name) {
			return (is_array($this->loopValue) && isset($this->loopValue[$name])) || (is_object($this->loopValue) && isset($this->loopValue->$name));
		}
		
		public function loop() {
			$this->loopIndex++;
			$continue = $this->loopIndex < $this->loopEndIndex;
			if ($continue)
				$this->loopValue = $this->expression[$this->loopIndices[$this->loopIndex]];
			return $continue;
		}
		
		public function currentValue() {
			return $this->loopValue;
		}
		
		public function currentIndex() {
			return $this->loopIndex;
		}
		
		public function specialIdentifier($index) {
			switch ($index) {
				case "":
					$value = $this->loopValue;
					break;
					
				case "0":
					$value = $this->loopIndex;
					break;
					
				case "end0":
					$value = $this->loopEndIndex - $this->loopIndex - 1; 
					break;
					
				case "1":
					$value = $this->loopIndex + 1;
					break;
					
				case "end1":
					$value = $this->loopEndIndex - $this->loopIndex;
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
