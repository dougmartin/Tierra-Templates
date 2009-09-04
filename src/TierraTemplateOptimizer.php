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

	require_once dirname(__FILE__) . "/TierraTemplateAST.php";
	
	class TierraTemplateOptimizer {
		
		private $options;
		
		public function __construct($options=array()) {
			$this->options = $options;
		}				
		
		public function optimize($ast) {
			
			/*
			 * TODO:
			 * 
			 *   1) on conditional blocks and conditerators test the condition and remove if the condition uses literals and resolves to false
			 *   2) apply filters and functions to literals and replace with result
			 *   3) replace conditerators whose head is a literal and has no output blocks with html block
			 *   4) more?
			 *   
			 */
			
			// remove comments, remove empty html and merge html in parent templates and strip html that is not in blocks in child templates 
			$mergedNodes = array();
			$lastNode = false;
			$isParentTemplate = !isset($ast->parentTemplateName);
			$blockStack = array();
			foreach ($ast->getNodes() as $node) {
				
				// remove comments
				if ($node->type == TierraTemplateASTNode::COMMENT_NODE)
					continue;
					
				if ($isParentTemplate) {
					if ($node->type == TierraTemplateASTNode::HTML_NODE) {
						
						// strip empty html
						if ($node->html != "") {
							
							// merge adjacent html
							if (($lastNode !== false) && ($lastNode->type == TierraTemplateASTNode::HTML_NODE)) {
								$lastNode->html .= $node->html;
							}
							else {
								// create new html nodes so that when we append we leave the old node the same
								$lastNode = new TierraTemplateASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $node->html));
								$mergedNodes[] = $lastNode;
							}
						}
					}
					else {
						$mergedNodes[] = $node;
						$lastNode = $node;
					}
				}
				else {
					// strip html that is not in blocks in child templates
					if (($node->type != TierraTemplateASTNode::HTML_NODE) || (count($blockStack) > 0))
						$mergedNodes[] = $node;
						
					// track if we are in a block
					if ($node->type == TierraTemplateASTNode::BLOCK_NODE) {
						if (in_array($node->command, array("start", "prepend", "append", "replace")))
							$blockStack[] = $node;
						else if ($node->command == "end")
							array_pop($blockStack);
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