<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	
	class TierraTemplateCodeGen {
		
		public static function emit($ast) {
			
			$code = array();
			
			foreach ($ast->getNodes() as $node) {
				switch ($node->type) {
					case TierraTemplateASTNode::HTML_NODE:
						$code[] = $node->html;
						break;
						
					case TierraTemplateASTNode::BLOCK_NODE:
						// TODO: implement
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						// TODO: implement
						break;
				}
			}
			
			return implode("", $code);
		}
		
	}	