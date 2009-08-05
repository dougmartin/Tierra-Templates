<?php

	class TierraTemplateAST {
		
		private $nodes;
		
		public function __construct($nodes = array(), $attributes=false) {
			$this->nodes = $nodes;
			if ($attributes !== false) {
				foreach ($attributes as $name => $value)
					$this->$name = $value;
			}			
		}
		
		public function addNode($node) {
			$this->nodes[] = $node;	
		}
		
		public function getNodes() {
			return $this->nodes;
		}
	}
	
	class TierraTemplateASTNode {
		
		const HTML_NODE = "HTML_NODE";
		const COMMENT_NODE = "COMMENT_NODE";
		const BLOCK_NODE = "BLOCK_NODE";
		const GENERATOR_NODE = "GENERATOR_NODE";
		
		public $type;
		
		public function __construct($type, $attributes=false) {
			$this->type = $type;
			if ($attributes !== false) {
				foreach ($attributes as $name => $value)
					$this->$name = $value;
			}
		}
	}	