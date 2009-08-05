<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	
	class TierraTemplateOptimizer {
		
		public static function optimize($ast) {
			
			// remove comments, remove empty html and merge html in parent templates and strip html in child templates 
			$mergedNodes = array();
			$lastNode = false;
			$isParentTemplate = !isset($ast->parentTemplateName);
			foreach ($ast->getNodes() as $node) {
				if ($node->type != TierraTemplateASTNode::COMMENT_NODE) {
					if ($isParentTemplate) {
						if (($node->type == TierraTemplateASTNode::HTML_NODE) && ($lastNode !== false) && ($lastNode->type == TierraTemplateASTNode::HTML_NODE)) {
							$lastNode->html .= $node->html;
						}
						else if (($node->type != TierraTemplateASTNode::HTML_NODE) || ($node->html != "")) {
							$mergedNodes[] = $node;
							$lastNode = $node;
						}
					}
					else {
						if ($node->type != TierraTemplateASTNode::HTML_NODE)
							$mergedNodes[] = $node;
					}
				}
			}
			
			/*
			 * TODO:
			 * 
			 *   1) on conditional blocks and generators test the condition and remove if the condition uses literals and resolves to false
			 *   2) apply filters and functions to literals and replace with result
			 *   3) more?
			 *   
			 */
			
			// clone the ast
			$optimizedAST = new TierraTemplateAST($mergedNodes);
			foreach ($ast as $name => $value) {
				if ($name != "nodes")
					$optimizedAST->$name = $value;
			}
			
			return $optimizedAST;
		}
		
	}