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

	class TierraTemplateStackFrame {
		
		private $expression;
		private $loopIndex;
		private $loopEndIndex;
		private $loopValue;
		private $loopMod;
		private $loopIndices;
		private $parentFrame;
		
		public function __construct($expression, $parentFrame=false) {
			$this->expression = $expression;
			$this->parentFrame = $parentFrame;
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
					
				case "previous":
					if (is_array($this->expression))
						$value = isset($this->loopIndices[$this->loopIndex - 1]) ? $this->expression[$this->loopIndices[$this->loopIndex - 1]] : false;
					else
						$value = false;
					break;
					
				case "next":
					if (is_array($this->expression))
						$value = isset($this->loopIndices[$this->loopIndex + 1]) ? $this->expression[$this->loopIndices[$this->loopIndex + 1]] : false;
					else
						$value = false;
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
					if (($index[0] == "$") && $this->parentFrame)
						$value = $this->parentFrame->specialIdentifier(substr($index, 1));
					else
						$value = false;
			}
			return $value;
		}
		
	}
