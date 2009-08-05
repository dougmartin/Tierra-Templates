<?php

	class TierraTemplateOptimizer {
		
		private $ast;
		
		public function __construct($ast) {
			$this->ast = $ast;
		}
		
		public function optimize() {
			return $this->ast;
		}
		
	}