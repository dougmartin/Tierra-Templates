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
		const CONDITERATOR_NODE = "CONDITERATOR_NODE";
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
		const CONDITIONAL_NODE = "CONDITIONAL_NODE";
		const CONDITIONAL_CONDITERATOR_NODE = "CONDITIONAL_CONDITERATOR_NODE";
		const MULTI_EXPRESSION_NODE = "MULTI_EXPRESSION_NODE";
		const ARRAY_NODE = "ARRAY_NODE";
		const CODE_NODE = "CODE_NODE";
		
		public $type;
		
		public function __construct($type, $attributes=false) {
			$this->type = $type;
			if ($attributes !== false) {
				foreach ($attributes as $name => $value)
					$this->$name = $value;
			}
		}
	}	