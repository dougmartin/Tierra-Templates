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
			while (true) {			
				switch ($this->tokenizer->getNextToken()) {
					
					case TierraTemplateTokenizer::EOF_TOKEN:
						// break out of the enclosing while loop
						break 2;
						
					case TierraTemplateTokenizer::HTML_TOKEN:
						$html = $this->tokenizer->match(TierraTemplateTokenizer::HTML_TOKEN);
						if (strlen($html) > 0)
							$this->ast->addNode(new TierraTemplateASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $html)));
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
			}
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
				case "replace":
					$node->blockName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TEXT_TOKEN), "Expected string or identifier for block name");
					break;
					
				default:
					$this->tokenizer->matchError("Unknown block command - '{$node->command}'");
			}
					
			if ($this->tokenizer->matchIf(TierraTemplateTokenizer::IF_TOKEN))
				$node->conditional = $this->blockConditionalNode();
			
			return $node;
		}
	}
	
	class TierraTemplateParserException extends Exception {
		
	}