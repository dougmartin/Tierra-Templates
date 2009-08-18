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
		const IDENTIFIER_NODE = "IDENTIFIER_NODE";
		const LITERAL_NODE = "LITERAL_NODE";
		const OPERATOR_NODE = "OPERATOR_NODE";
		const INDEX_NODE = "INDEX_NODE";
		const LIMIT_NODE = "LIMIT_NODE";
		const FUNCTION_CALL_NODE = "FUNCTION_CALL_NODE";
		const DECORATOR_NODE = "DECORATOR_NODE";
		const JSON_ATTRIBUTE_NODE = "JSON_ATTRIBUTE_NODE";
		const JSON_NODE = "JSON_NODE";
		const OUTPUT_TEMPLATE_NODE = "OUTPUT_TEMPLATE_NODE";
		
		public $type;
		
		public function __construct($type, $attributes=false) {
			$this->type = $type;
			if ($attributes !== false) {
				foreach ($attributes as $name => $value)
					$this->$name = $value;
			}
		}
	}	