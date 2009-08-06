<?php

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateAST.php";

	class TierraTemplateParser {
		
		private $tokenizer;
		private $ast;
		private $blockStack;
		
		private $operatorTable;
		
		public function __construct($src) {
			$this->tokenizer = new TierraTemplateTokenizer($src);
			$this->ast = new TierraTemplateAST();
			$this->blockStack = array();
			
			$this->operatorTable = array(
				array("operators" => array(","), "associative" => "left", "binary" => true),
				array("operators" => array("or"), "associative" => "left", "binary" => true),
				array("operators" => array("xor"), "associative" => "left", "binary" => true),
				array("operators" => array("and"), "associative" => "left", "binary" => true),
				array("operators" => array("="), "associative" => "right", "binary" => true),
				array("operators" => array("||"), "associative" => "left", "binary" => true),
				array("operators" => array("&&"), "associative" => "left", "binary" => true),
				array("operators" => array("==", "!="), "associative" => "left", "binary" => true),
				array("operators" => array("<", "<=", ">", ">=", "<>"), "associative" => "left", "binary" => true),
				array("operators" => array("+", "-"), "associative" => "left", "binary" => true),
				array("operators" => array("*", "/", "%"), "associative" => "left", "binary" => true),
				array("operators" => array("!"), "associative" => "right", "binary" => false),
				array("operators" => array("-"), "associative" => "left", "binary" => false),
			);
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
						switch ($node->command) {
							case "extends":
								if (!isset($this->ast->parentTemplateName))
									$this->ast->parentTemplateName = $node->templateName;
								else
									$this->tokenizer->matchError("Multiple extends blocks found", $steamIndex);
								break;
								
							case "page":
								if (!isset($this->ast->decorators))
									$this->ast->decorators = isset($node->decorators) ? $node->decorators : array();
								else
									$this->tokenizer->matchError("Multiple page blocks found", $steamIndex);
								break;
								
							default:
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
								break;
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
					
				case "page":
					// nothing to do here, no parameters to page
					break;
					
				default:
					$this->tokenizer->matchError("Unknown block command - '{$node->command}'");
			}
					
			// get the conditionals
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
				if (($node->command != "extends") && ($node->command != "end")) {
					$this->tokenizer->match(TierraTemplateTokenizer::IF_TOKEN);
					$node->conditional = $this->expressionNode();
					if ($node->conditional === false) 
						$this->tokenizer->matchError("Block conditional is empty");
				}
				else
					$this->tokenizer->matchError(ucfirst($node->command) . " blocks cannot have conditionals");
			}

			// get the decorators
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::DO_TOKEN)) {
				$this->tokenizer->match(TierraTemplateTokenizer::DO_TOKEN);
				$this->decorators = array();
				while (!$this->tokenizer->nextIs(TierraTemplateTokenizer::BLOCK_END_TOKEN)) {
					$this->decorators[] = $this->blockDecoratorNode();
					$this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN);
				}
			}
			
			return $node;
		}
		
		private function blockDecoratorNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::DECORATOR_NODE);
			
			$node->method = $this->tokenizer->match(TierraTemplateTokenizer::FUNCTION_CALL_TOKEN, "Expected function call for block decorator");
			$this->tokenizer->match(TierraTemplateTokenizer::LEFT_PAREN_TOKEN);
			$node->params = array();
			while (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN)) {
				$param =  $this->expressionNode();
				if ($param !== false)
					$node->params[] = $param;
				$this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN);
			}
			$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);
			
			return $node;
			// TODO: implement
		}

		private function expressionNode() {
			return $this->expressionOperatorNode(0);
		}
		
		/*
			$this->operatorTable = array(
				array("operators" => array(","), "associative" => "left", "binary" => true),
				array("operators" => array("or"), "associative" => "left", "binary" => true),
				array("operators" => array("xor"), "associative" => "left", "binary" => true),
				array("operators" => array("and"), "associative" => "left", "binary" => true),
				array("operators" => array("="), "associative" => "right", "binary" => true),
				array("operators" => array("||"), "associative" => "left", "binary" => true),
				array("operators" => array("&&"), "associative" => "left", "binary" => true),
				array("operators" => array("==", "!="), "associative" => "left", "binary" => true),
				array("operators" => array("<", "<=", ">", ">=", "<>"), "associative" => "left", "binary" => true),
				array("operators" => array("+", "-"), "associative" => "left", "binary" => true),
				array("operators" => array("*", "/", "%"), "associative" => "left", "binary" => true),
				array("operators" => array("!"), "associative" => "right", "binary" => false),
				array("operators" => array("-"), "associative" => "left", "binary" => false),
			);
		 */
		private function expressionOperatorNode($precedence) {
			if ($precedence < count($this->operatorTable)) {
				$operator = $this->operatorTable[$precedence];
				if ($operator["associative"] == "left") {
					if ($operator["binary"]) {
						$leftNode = $this->expressionOperatorNode($precedence + 1);
						while (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$rightNode = $this->expressionOperatorNode($precedence + 1);
							$leftNode = new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode, "rightNode" => $rightNode));
						}
						return $leftNode;
					}
					else {
						if (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$leftNode = $this->expressionOperatorNode($precedence + 1);
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode));
						}
						return $this->expressionOperatorNode($precedence + 1);
					}
				}
				else {
					if ($operator["binary"]) {
						$leftNode = $this->expressionOperatorNode($precedence + 1);
						if (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$rightNode = $this->expressionOperatorNode($precedence); // <-- same precedence level
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode, "rightNode" => $rightNode));
						}
						return $leftNode;
					}
					else {
						if (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$leftNode = $this->expressionOperatorNode($precedence); // <-- same precedence level
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode));
						}
						return $this->expressionOperatorNode($precedence + 1);
					}
				}
			}
			else
				return $this->expressionBaseNode();
		}
		
		private function expressionBaseNode() {
			if ($this->tokenizer->matchIf(TierraTemplateTokenizer::LEFT_PAREN_TOKEN)) {
				$node = $this->expressionOperatorNode(0);
				$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);
				return $node;
			}
			else if ($identifier = $this->tokenizer->matchIf(TierraTemplateTokenizer::IDENTIFIER_TOKEN)) {
				$node = new TierraTemplateASTNode(TierraTemplateASTNode::IDENTIFIER_NODE);
				$node->identifier = $identifier;
					
				if ($this->tokenizer->matchIf(TierraTemplateTokenizer::LEFT_BRACKET_TOKEN)) {
					if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN))
						$node->index = $this->indexNode();
					$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN);
				}
				
				if ($this->tokenizer->nextIs(TierraTemplateTokenizer::COLON_TOKEN)) {
					$node->filters = array();
					while ($this->tokenizer->matchIf(TierraTemplateTokenizer::COLON_TOKEN))
						$node->filters[] = $this->filterNode();
				}
				
				return $node;
			}
			else
				return $this->valueNode();
		}
		
		private function indexNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::INDEX_NODE);
			$node->start = $this->expressionOperatorNode(9); // <- +/- operator and lower
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::COLON_TOKEN)) {
				$node->end = $this->expressionOperatorNode(9); // <- +/- operator and lower
				if ($this->tokenizer->nextIs(TierraTemplateTokenizer::COLON_TOKEN))
					$node->step = $this->expressionOperatorNode(9); // <- +/- operator and lower
			}
			return $node;
		}
	}
	
	class TierraTemplateParserException extends Exception {
		
	}