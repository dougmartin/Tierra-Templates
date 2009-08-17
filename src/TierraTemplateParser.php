<?php

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/TierraTemplateCodeGenerator.php";

	class TierraTemplateParser {
		
		private $tokenizer;
		private $ast;
		private $blockStack;
		
		private $operatorTable;
		
		public function __construct($src) {
			$this->tokenizer = new TierraTemplateTokenizer($src);
			$this->ast = new TierraTemplateAST();
			$this->blockStack = array();
			
			// all operators in ascending order of precedence
			$this->operatorTable = array(
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
				array("operators" => array(":"), "associative" => "left", "binary" => true),
				array("operators" => array(","), "associative" => "left", "binary" => true),
				array("operators" => array("-"), "associative" => "left", "binary" => false),
				array("operators" => array("."), "associative" => "left", "binary" => true),
				array("operators" => array("["), "associative" => "left", "binary" => true),
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
								else if (in_array($node->command, array("start", "prepend", "append", "set"))) {
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
			$node->command = strtolower($this->tokenizer->match(TierraTemplateTokenizer::IDENTIFIER_TOKEN, "Expected block command"));
			
			switch ($node->command) {
				case "extends":
				case "include":
					$node->templateName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN), "Expected string or identifier for template name");
					break;
					
				case "start":
				case "else":
				case "end":
					if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN))
						$node->blockName = false;
					else
						$node->blockName = $this->tokenizer->matchesElse(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN), false);
					break;
					
				case "prepend":
				case "append":
					$node->blockName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN), "Expected string or identifier for block name");
					break;
					
				case "page":
					// nothing to do here, no parameters to page
					break;
					
				default:
					$this->tokenizer->matchError("Unknown block command - '{$node->command}'");
			}
					
			// get the conditionals
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
				if (($node->command == "extends") || ($node->command == "end"))
					$this->tokenizer->matchError(ucfirst($node->command) . " blocks cannot have conditionals");
					
				$this->tokenizer->match(TierraTemplateTokenizer::IF_TOKEN);
				$node->conditional = $this->expressionNode();
				if ($node->conditional === false) 
					$this->tokenizer->matchError("Block conditional is empty");
			}

			// get the decorators
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::DO_TOKEN)) {
				$this->tokenizer->match(TierraTemplateTokenizer::DO_TOKEN);
				$node->decorators = array();
				while (!$this->tokenizer->nextIs(TierraTemplateTokenizer::BLOCK_END_TOKEN)) {
					$decorator = $this->functionCallNode();
					$paramsCode = TierraTemplateCodeGenerator::emitArray($decorator->params);
					if (strpos($paramsCode, "$"))
						throw new TierraTemplateException("Block decorator parameters are not valid. They cannot contain variable references or function invocations as they are called at compile time.");
					$decorator->evaledParams = eval("return {$paramsCode};");
					$node->decorators[] = $decorator;
					$this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN);
				}
			}

			return $node;
		}
		
		private function expressionNode() {
			return $this->expressionOperatorNode(0);
		}
		
		private function expressionOperatorNode($precedence) {
			if ($precedence < count($this->operatorTable)) {
				$operator = $this->operatorTable[$precedence];
				if ($operator["associative"] == "left") {
					if ($operator["binary"]) {
						$leftNode = $this->expressionOperatorNode($precedence + 1);
						while (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							if ($op == TierraTemplateTokenizer::LEFT_BRACKET_TOKEN) {
								$rightNode = $this->expressionNode();
								$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN);
							}
							else
								$rightNode = $this->expressionOperatorNode($precedence + 1);
							$leftNode = new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode, "rightNode" => $rightNode, "binary" => true));
						}
						return $leftNode;
					}
					else {
						if (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$leftNode = $this->expressionOperatorNode($precedence + 1);
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "rightNode" => $leftNode, "binary" => false));
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
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "leftNode" => $leftNode, "rightNode" => $rightNode, "binary" => true));
						}
						return $leftNode;
					}
					else {
						if (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							$leftNode = $this->expressionOperatorNode($precedence); // <-- same precedence level
							return new TierraTemplateASTNode(TierraTemplateASTNode::OPERATOR_NODE, array("op" => $op, "rightNode" => $leftNode, "binary" => false));
						}
						return $this->expressionOperatorNode($precedence + 1);
					}
				}
			}
			else if ($this->tokenizer->matchIf(TierraTemplateTokenizer::LEFT_PAREN_TOKEN)) {
				$node = $this->expressionNode();
				$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);
				return $node;
			}
			else if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IDENTIFIER_TOKEN))
				return $this->identifierNode();
			else
				return $this->valueNode();
		}
		
		private function identifierNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::IDENTIFIER_NODE);
			$node->identifier = $this->tokenizer->match(TierraTemplateTokenizer::IDENTIFIER_TOKEN);
			return $node;
		}
		
		private function jsonAttributeNode() {
			$nextToken = $this->tokenizer->getNextToken();
			if (($nextToken != TierraTemplateTokenizer::IDENTIFIER_TOKEN) && ($nextToken != TierraTemplateTokenizer::STRING_TOKEN))
				$this->tokenizer->matchError("Json attribute names must be an identifier or string");
				
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::JSON_ATTRIBUTE_NODE);
			$node->name = $this->tokenizer->advance();
			$this->tokenizer->match(TierraTemplateTokenizer::COLON_TOKEN);
			$node->value = $this->valueNode();
			return $node;
		}
	
		private function valueNode() {
			switch ($this->tokenizer->getNextToken()) {
				case TierraTemplateTokenizer::LEFT_BRACKET_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::ARRAY_NODE);
					$node->statements = array();
					$this->tokenizer->match(TierraTemplateTokenizer::LEFT_BRACKET_TOKEN);
					if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN)) {
						$node->statements[] = $this->statementNode();
						while ($this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN))
							$node->statements[] = $this->statementNode();
					}
					$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN);
					break;
					
				case TierraTemplateTokenizer::LEFT_BRACE_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::JSON_NODE);
					$attributes = array();
					$this->tokenizer->match(TierraTemplateTokenizer::LEFT_BRACE_TOKEN);
					if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_BRACE_TOKEN)) {
						$attributes[] = $this->jsonAttributeNode();
						while ($this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN))
							$attributes[] = $this->jsonAttributeNode();
					}
					$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACE_TOKEN);
					
					$namedAttributes = array();
					if (count($attributes) > 0) {
						foreach ($attributes as $attribute)
							$namedAttributes[$attribute->name] = $attribute->value;
					}
					$node->attributes = $namedAttributes;
					break;
					
				case TierraTemplateTokenizer::IDENTIFIER_TOKEN:
					$node = $this->identifierNode();
					break;
					
				case TierraTemplateTokenizer::STRING_TOKEN:
				case TierraTemplateTokenizer::INTEGER_TOKEN:
				case TierraTemplateTokenizer::FLOAT_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::LITERAL_NODE);
					$node->tokenType = $this->tokenizer->getNextToken();
					$node->value = $this->tokenizer->advance();
					break;
					
				case TierraTemplateTokenizer::FUNCTION_CALL_TOKEN:
					$node = $this->functionCallNode();
					break;
					
				default:
					$this->tokenizer->matchError("Expected value not found");
					break;
			}
			
			return $node;
		}
		
		private function functionCallNode() {
			
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::FUNCTION_CALL_NODE);
			$node->method = $this->tokenizer->match(TierraTemplateTokenizer::FUNCTION_CALL_TOKEN);
			$node->params = array();
			
			$this->tokenizer->match(TierraTemplateTokenizer::LEFT_PAREN_TOKEN);
			if (!$this->tokenizer->nextIs((TierraTemplateTokenizer::RIGHT_PAREN_TOKEN)))
				$node->params[] = $this->expressionNode();
			$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);

			return $node;
		}
		
		private function filterNode() {
			switch ($this->tokenizer->getNextToken()) {
				case TierraTemplateTokenizer::IDENTIFIER_TOKEN:
				case TierraTemplateTokenizer::FUNCTION_CALL_TOKEN:
					$node = $this->functionCallNode();
					break;
					
				case TierraTemplateTokenizer::INTEGER_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::LIMIT_NODE);
					$node->start = $this->tokenizer->advance();
					if ($this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN))
						$node->num = $this->tokenizer->match(TierraTemplateTokenizer::INTEGER_TOKEN);
					break;
					
				default:
					$this->tokenizer->matchError("expected filter not found");
					break;
			}
			return $node;
		}
		
	}
	
	class TierraTemplateParserException extends Exception {
		
	}