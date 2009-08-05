<?php

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateAST.php";

	class TierraTemplateParser {
		
		private $tokenizer;
		private $ast;
		private $blockStack;
		
		public function __construct($src) {
			$this->tokenizer = new TierraTemplateTokenizer($src);
			$this->ast = new TierraTemplateAST();
			$this->blockStack = array();
		}
		
		public function getAST() {
			return $this->ast;
		}
		
		public function parse() {
			while (true) {			
				switch ($this->tokenizer->getNextToken()) {
					
					case TierraTemplateTokenizer::EOF_TOKEN:
						// break out of the enclosing while loop - we don't use a check for eof() in the loop as that would make us drop trailing html
						break 2;
						
					case TierraTemplateTokenizer::HTML_TOKEN:
						$this->ast->addNode(new TierraTemplateASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $this->tokenizer->match(TierraTemplateTokenizer::HTML_TOKEN))));
						break;
						
					case TierraTemplateTokenizer::COMMENT_START_TOKEN:
						$node = new TierraTemplateASTNode(TierraTemplateASTNode::COMMENT_NODE);
						$node->start = $this->tokenizer->match(TierraTemplateTokenizer::COMMENT_START_TOKEN);
						$node->comment = $this->tokenizer->matchElse(TierraTemplateTokenizer::COMMENT_TOKEN, false);
						$node->end = $this->tokenizer->match(TierraTemplateTokenizer::COMMENT_END_TOKEN);
						$this->ast->addNode($node);
						break;
						
					case TierraTemplateTokenizer::BLOCK_START_TOKEN:
						$this->tokenizer->match(TierraTemplateTokenizer::BLOCK_START_TOKEN);
						
						$steamIndex = $this->tokenizer->getStreamIndex();
						$node = $this->blockNode();
						if ($node->command == "extends") {
							if (!isset($this->ast->parentTemplateName))
								$this->ast->parentTemplateName = $node->templateName;
							else
								$this->tokenizer->matchError("Multiple extends blocks found", $steamIndex);
						}
						else {
							if ($node->command == "end") {
								if (count($this->blockStack) == 0)
									$this->tokenizer->matchError("Unmatched end block found", $steamIndex);
								else {
									$openingBlock = array_pop($this->blockStack);
									if ($openingBlock->blockName != $node->blockName) 
										$this->tokenizer->matchError("End block does not match opening block name", $steamIndex);
								}
							}
							else if (in_array($node->command, array("start", "prepend", "append", "replace"))) {
								$this->blockStack[] = $node;
							}
							
							$this->ast->addNode($node);
						}
							
						$this->tokenizer->match(TierraTemplateTokenizer::BLOCK_END_TOKEN);
						break;
						
					case TierraTemplateTokenizer::GENERATOR_START_TOKEN:
						// TODO: implement
						break;
				}
			}
			
			// check for unclosed blocks
			$numBlocksInStack = count($this->blockStack); 
			if ($numBlocksInStack != 0)
				$this->tokenizer->matchError($numBlocksInStack == 1 ? "Unclosed block found" : "{$numBlocksInStack} unclosed blocks found");
		}
		
		private function blockNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::BLOCK_NODE);
			$node->command = strtolower($this->tokenizer->match(TierraTemplateTokenizer::TEXT_TOKEN, "Expected block command"));
			
			switch ($node->command) {
				case "extends":
				case "include":
					$node->templateName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), "Expected string or identifier for template name");
					break;
					
				case "start":
				case "else":
				case "end":
					if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN))
						$node->blockName = false;
					else
						$node->blockName = $this->tokenizer->matchesElse(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), false);
					break;
					
				case "prepend":
				case "append":
					$node->blockName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), "Expected string or identifier for block name");
					break;
					
				default:
					$this->tokenizer->matchError("Unknown block command - '{$node->command}'");
			}
					
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
				if (($node->command != "extends") && ($node->command != "end")) {
					$this->tokenizer->match(TierraTemplateTokenizer::IF_TOKEN);
					$node->conditional = $this->blockConditionalNode();
				}
				else
					$this->tokenizer->matchError(ucfirst($node->command) . " blocks cannot have conditionals");
			}
			
			return $node;
		}
		
		private function blockConditionalNode() {
			
		}
	}
	
	class TierraTemplateParserException extends Exception {
		
	}