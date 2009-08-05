<?php

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateAST.php";

	class TierraTemplateParser {
		
		private $tokenizer;
		private $ast;
		
		public function __construct($src) {
			$this->tokenizer = new TierraTemplateTokenizer($src);
			$this->ast = new TierraTemplateAST();
		}
		
		public function getAST() {
			return $this->ast;
		}
		
		public function parse() {
			do {			
				switch ($this->tokenizer->getNextToken()) {
					
					case TierraTemplateTokenizer::HTML_TOKEN:
						$this->ast->addNode(new TierraTemplateASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $this->tokenizer->match(TierraTemplateTokenizer::HTML_TOKEN))));
						break;
						
					case TierraTemplateTokenizer::COMMENT_START_TOKEN:
						$this->tokenizer->match(TierraTemplateTokenizer::COMMENT_START_TOKEN);
						$this->ast->addNode(new TierraTemplateASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $this->tokenizer->matchElse(TierraTemplateTokenizer::COMMENT_TOKEN, false))));
						$this->tokenizer->match(TierraTemplateTokenizer::COMMENT_END_TOKEN);
						break;
						
					case TierraTemplateTokenizer::BLOCK_START_TOKEN:
						$this->tokenizer->match(TierraTemplateTokenizer::BLOCK_START_TOKEN);
						$this->ast->addNode($this->blockNode());
						$this->tokenizer->match(TierraTemplateTokenizer::BLOCK_END_TOKEN);
						break;
						
					case TierraTemplateTokenizer::GENERATOR_START_TOKEN:
						break;
				}
			} while (!$this->tokenizer->eof());
		}
		
		private function blockNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::BLOCK_NODE);
			$node->command = strtolower($tokenizer->match(TierraTemplateTokenizer::TEXT_TOKEN, "Expected block command"));
			
			switch ($node->command) {
				case "extends":
				case "include":
					$node->template = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), "Expected string or identifier for template name");
					break;
					
				case "start":
				case "else":
				case "end":
					if (strtolower($tokenizer->nextLexeme) != "if")
						$node->blockName = $this->tokenizer->matchesElse(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), false);
					else
						$node->blockName = false;
					break;
					
				case "prepend":
				case "append":
				case "replace":
					$node->blockName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), "Expected string or identifier for block name");
					break;
					
				default:
					$this->tokenizer->matchError("Unknown block command - '{$node->command}'");
			}
					
			if (strtolower($tokenizer->nextLexeme) == "if") {
				$this->tokenizer->advance();
				$this->tokenizer->setMode(TierraTemplateTokenizer::BLOCK_CONDITIONAL_MODE);
				$node->conditional = $this->blockConditionalNode();
			}
			
			return $node;
		}
	}
	
	class TierraTemplateParserException extends Exception {
		
	}