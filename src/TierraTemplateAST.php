<?php

	class TierraTemplateAST {
		
		private $nodes;
		
		public function __construct($nodes = array()) {
			$this->nodes = $nodes;
		}
		
		public function addNode($node) {
			$this->nodes[] = $node;	
		}
	}
	
	class TierraTemplateASTNode {
		
		const HTML_NODE = "HTML_NODE";
		const COMMENT_NODE = "COMMENT_NODE";
		const BLOCK_NODE = "BLOCK_NODE";
		
		public $type;
		
		public function __construct($type, $attributes=false) {
			$this->type = $type;
			if ($attributes !== false) {
				foreach ($attributes as $name => $value)
					$this->$name = $value;
			}
		}
	}	