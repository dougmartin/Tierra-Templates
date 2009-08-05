<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	
	class TierraTemplateOptimizer {
		
		public static function optimize($ast) {
			
			/*
			 * TODO:
			 * 
			 *   1) on conditional blocks and generators test the condition and remove if the condition uses literals and resolves to false
			 *   2) apply filters and functions to literals and replace with result
			 *   3) replace generators whose head is a literal and has no output blocks with html block
			 *   4) more?
			 *   
			 */
			
			// remove comments, remove empty html and merge html in parent templates and strip html that is not in blocks in child templates 
			$mergedNodes = array();
			$lastNode = false;
			$isParentTemplate = !isset($ast->parentTemplateName);
			$blockStack = array();
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
						if (($node->type != TierraTemplateASTNode::HTML_NODE) || (count($blockStack) > 0))
							$mergedNodes[] = $node;
						if ($node->type == TierraTemplateASTNode::BLOCK_NODE) {
							if (in_array($node->command, array("start", "prepend", "append", "replace")))
								$blockStack[] = $node;
							else if ($node->command == "end")
								array_pop($blockStack);
						}
					}
				}
			}
			
			// clone the ast
			$optimizedAST = new TierraTemplateAST($mergedNodes);
			foreach ($ast as $name => $value) {
				if ($name != "nodes")
					$optimizedAST->$name = $value;
			}
			
			return $optimizedAST;
		}
		
	}