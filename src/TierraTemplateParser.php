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

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/TierraTemplateCodeGenerator.php";

	class TierraTemplateParser {
		
		private $options;
		private $filename;
		private $tokenizer;
		private $ast;
		private $blockStack;
		private $baseGuid;
		
		private $operatorTable;
		private $codeGenerator;
		
		public function __construct($options, $src, $filename=false) {
			$this->options = $options;
			$this->filename = $filename;
			$this->tokenizer = new TierraTemplateTokenizer($src);
			$this->codeGenerator = new TierraTemplateCodeGenerator($options);
			$this->ast = new TierraTemplateAST();
			$this->blockStack = array();
			$this->baseGuid = sha1($src . $filename); 
			
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
								if (!isset($this->ast->pageBlock))
									$this->ast->pageBlock = $node;
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
						
					case TierraTemplateTokenizer::CONDITERATOR_START_TOKEN:
						$this->tokenizer->matchIf(TierraTemplateTokenizer::CONDITERATOR_START_TOKEN);
						if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN))
							$this->ast->addNode($this->conditeratorNode());
						$this->tokenizer->match(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN);
						break;
						
					case TierraTemplateTokenizer::CODE_START_TOKEN:
						$this->tokenizer->matchIf(TierraTemplateTokenizer::CODE_START_TOKEN);
						if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::CODE_END_TOKEN))
							$this->ast->addNode($this->codeNode());
						$this->tokenizer->match(TierraTemplateTokenizer::CODE_END_TOKEN);
						break;
						
					default:
						$this->tokenizer->matchError("Unexpected " . $this->tokenizer->getNextToken());
						break;
				}
			}
			
			// check for unclosed blocks
			$numBlocksInStack = count($this->blockStack); 
			if ($numBlocksInStack != 0)
				$this->tokenizer->matchError($numBlocksInStack == 1 ? "Unclosed block found" : "{$numBlocksInStack} unclosed blocks found");
				
			return $this->ast;
		}
		
		private function blockNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::BLOCK_NODE);
			$node->command = strtolower($this->tokenizer->matches(array(TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::ELSE_TOKEN), "Expected block command"));
			$node->blockName = false;
			
			switch ($node->command) {
				case "extends":
				case "include":
					$node->templateName = $this->expressionNode();
					break;
					
				case "start":
				case "else":
				case "end":
					if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN))
						$node->blockName = $this->tokenizer->matchesElse(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN), false);
					break;
					
				case "prepend":
				case "append":
				case "echo":
					$node->blockName = $this->tokenizer->matches(array(TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN), "Expected string or identifier for block name");
					break;
					
				case "page":
					// nothing to do here
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
				if (($node->command == "extends") || ($node->command == "include"))
					$this->tokenizer->matchError(ucfirst($node->command) . " blocks cannot have decorators");
					
				$this->tokenizer->match(TierraTemplateTokenizer::DO_TOKEN);
				$node->decorators = array();
				$node->guid = sha1($this->baseGuid . $node->blockName);
				while (!$this->tokenizer->nextIs(TierraTemplateTokenizer::BLOCK_END_TOKEN)) {
					if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IDENTIFIER_TOKEN)) {
						// we don't want these as keywords so match the lexeme not the token
						$action = strtolower($this->tokenizer->advance());
						if (!in_array($action, array("append", "add", "set", "remove")))
							$this->tokenizer->matchError("Expected 'append', 'add', 'set' or 'remove'");
					}
					else
						$action = "append";
						
					if (($node->command != "page") && !$node->blockName)
						$this->tokenizer->matchError("Decorator actions are only valid on named blocks");
						 
					$decorator = $this->functionCallNode();
					$paramsCode = $this->codeGenerator->emitArray($decorator->params);
					if (strpos($paramsCode, "$"))
						$this->tokenizer->matchError("Block decorator parameters are not valid. They cannot contain variable references or function invocations as they are called at compile time.");
					$decorator->evaledParams = eval("return {$paramsCode};");
					$decorator->action = $action;
					$node->decorators[] = $decorator;
					$this->tokenizer->matchIf(TierraTemplateTokenizer::COMMA_TOKEN);
				}
			}

			return $node;
		}
		
		private function expressionNode() {
			$expressions[] = $this->expressionOperatorNode(0);
			while ($this->tokenizer->matchIf(TierraTemplateTokenizer::SEMICOLON_TOKEN)) {
				// for valueless conditerators and empty statements
				if ($this->tokenizer->nextIn(array(TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::CONDITERATOR_END_TOKEN, TierraTemplateTokenizer::CODE_END_TOKEN)))
					break;
				$expressions[] = $this->expressionOperatorNode(0);
			}
				
			if (count($expressions) > 1) {
				$node = new TierraTemplateASTNode(TierraTemplateASTNode::MULTI_EXPRESSION_NODE);
				$node->expressions = $expressions;
				return $node;
			}
			else
				return $expressions[0];
		}
		
		private function expressionOperatorNode($precedence) {
			if ($precedence < count($this->operatorTable)) {
				$operator = $this->operatorTable[$precedence];
				if ($operator["associative"] == "left") {
					if ($operator["binary"]) {
						$leftNode = $this->expressionOperatorNode($precedence + 1);
						while (in_array($this->tokenizer->getNextLexeme(), $operator["operators"])) {
							$op = $this->tokenizer->advance();
							
							// for array references consume the brackets
							if ($op == TierraTemplateTokenizer::LEFT_BRACKET_TOKEN) {
								$rightNode = $this->expressionNode();
								$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN);
							}
							else
								$rightNode = $this->expressionOperatorNode($precedence + 1);
								
							// for filters convert chained identifiers to functions
							if (($op == TierraTemplateTokenizer::COLON_TOKEN) && ($rightNode->type == TierraTemplateASTNode::IDENTIFIER_NODE)) {
								$rightNode->type = TierraTemplateASTNode::FUNCTION_CALL_NODE;
								$rightNode->method = $rightNode->identifier;
								$rightNode->isFilter = true;
								unset($rightNode->identifier);
								$rightNode->params = array();
							}
							
							if (($op == TierraTemplateTokenizer::COLON_TOKEN) && !in_array($rightNode->type, array(TierraTemplateASTNode::FUNCTION_CALL_NODE, TierraTemplateASTNode::OPERATOR_NODE, TierraTemplateASTNode::LITERAL_NODE)))
								throw new TierraTemplateException("Right hand side of a function chain operator (:) needs to be a function or limit");
							
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
							
							// for assignments verify that the left node is an idenfitier
							if ($op == TierraTemplateTokenizer::EQUAL_TOKEN) {
								$leftMostNode = $this->getLeftMostAttributeNode($leftNode);
								if ($leftMostNode->type == TierraTemplateASTNode::IDENTIFIER_NODE) {
									if ($leftMostNode->isExternal)
										$this->tokenizer->matchError("External variables cannot be assigned values");
									if ((strtolower($leftMostNode->identifier) == "request") && ($leftMostNode == $leftNode))
										$this->tokenizer->matchError("The request variable is special and cannot be reassigned");
								}
								else
									$this->tokenizer->matchError("The left side of an assignment must be a variable");
							}
							
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
		
		public function getLeftMostAttributeNode($node) {
			if (($node->type == TierraTemplateASTNode::OPERATOR_NODE) && (($node->op == TierraTemplateTokenizer::LEFT_BRACKET_TOKEN) || ($node->op == TierraTemplateTokenizer::DOT_TOKEN)))
				return $this->getLeftMostAttributeNode($node->leftNode);
			return $node;
		}
		
		private function identifierNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::IDENTIFIER_NODE);
			// save the file and line number for runtime external function call errors for filters 
			$line = $this->tokenizer->getLineNumber();
			$node->identifier = $this->tokenizer->match(TierraTemplateTokenizer::IDENTIFIER_TOKEN);
			$node->debugInfo = "{$node->identifier} on line {$line}" . ($this->filename ? " in the {$this->filename} template" : ""); 
			$node->isExternal = $this->isExternal($node->identifier);
			if ($node->isExternal)
				list($node->identifier, $node->filename, $node->virtualDir, $node->subDir) =  $this->parseExternal($node->identifier);
			
			return $node;
		}
		
		private function functionCallNode() {
			
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::FUNCTION_CALL_NODE);
			$line = $this->tokenizer->getLineNumber();
			$node->method = $this->tokenizer->match(TierraTemplateTokenizer::FUNCTION_CALL_TOKEN);
			$node->isFilter = false;
			$node->params = array();
			$node->debugInfo = "{$node->method} on line {$line}" . ($this->filename ? " in the {$this->filename} template" : ""); 
			
			$node->isExternal = $this->isExternal($node->method);
			if ($node->isExternal)
				list($node->method, $node->filename, $node->virtualDir, $node->subDir) =  $this->parseExternal($node->method);
			
			$this->tokenizer->match(TierraTemplateTokenizer::LEFT_PAREN_TOKEN);
			if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN))
				$node->params[] = $this->expressionNode();
			$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);

			return $node;
		}

		private function isExternal($text) {
			return ((strpos($text, "::") !== false) || (strpos($text, "\\") !== false));
		}
		
		private function parseExternal($text) {
			// if this is an external identifier verify its formatting
			if (substr_count($text, "::") > 1)
				$this->tokenizer->matchError("External identifiers and functions can only have one :: scope operator");
				
			// this matches: "foo::bar", "foo\\bar", "foo\\bar::baz", "foo\\bar\\baz::bam", "foo\\bar\\baz\\bam::boom"
			preg_match('/((([A-Za-z_0-9][A-Za-z_0-9]*)\\\\)(([A-Za-z_0-9][A-Za-z_0-9\\\\]*)\\\\)*)*(([A-Za-z_][A-Za-z_0-9]*)::)*([A-Za-z_][A-Za-z_0-9]*)/', $text, $matches);
			
			return array($matches[8], $matches[7], $matches[3], $matches[5]);
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
					$node->elements = false;
					$this->tokenizer->match(TierraTemplateTokenizer::LEFT_BRACKET_TOKEN);
					if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_BRACKET_TOKEN))
						$node->elements = $this->expressionNode();
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
					
				case TierraTemplateTokenizer::BACKTICK_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE);
					$node->outputItems = array();
					
					$this->tokenizer->match(TierraTemplateTokenizer::BACKTICK_TOKEN);
					while (true) {
						if ($this->tokenizer->getNextToken() == TierraTemplateTokenizer::BACKTICK_TOKEN) {
							break;
						}
						else if ($this->tokenizer->matchIf(TierraTemplateTokenizer::LEFT_BRACE_TOKEN)) {
							if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::RIGHT_BRACE_TOKEN))
								$node->outputItems[] = $this->conditeratorNode();
							$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_BRACE_TOKEN);
						}
						else if ($this->tokenizer->matchIf(TierraTemplateTokenizer::CONDITERATOR_START_TOKEN)) {
							if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN))
								$node->outputItems[] = $this->conditeratorNode();
							$this->tokenizer->match(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN);
						}
						else {
							$item = new TierraTemplateASTNode(TierraTemplateASTNode::LITERAL_NODE);
							$item->tokenType = TierraTemplateTokenizer::STRING_TOKEN;
							$item->value = $this->tokenizer->match(TierraTemplateTokenizer::STRING_TOKEN);
							$node->outputItems[] = $item;
						}
					}
					$this->tokenizer->match(TierraTemplateTokenizer::BACKTICK_TOKEN);
					break;
							
				case TierraTemplateTokenizer::TILDE_TOKEN:
					$node = new TierraTemplateASTNode(TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE);
					$node->outputItems = array();
					
					$this->tokenizer->match(TierraTemplateTokenizer::TILDE_TOKEN);
					while (true) {
						if ($this->tokenizer->getNextToken() == TierraTemplateTokenizer::TILDE_TOKEN) {
							break;
						}
						else if ($this->tokenizer->matchIf(TierraTemplateTokenizer::CONDITERATOR_START_TOKEN)) {
							if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN))
								$node->outputItems[] = $this->conditeratorNode();
							$this->tokenizer->match(TierraTemplateTokenizer::CONDITERATOR_END_TOKEN);
						}
						else {
							$item = new TierraTemplateASTNode(TierraTemplateASTNode::LITERAL_NODE);
							$item->tokenType = TierraTemplateTokenizer::STRING_TOKEN;
							$item->value = $this->tokenizer->match(TierraTemplateTokenizer::STRING_TOKEN);
							$node->outputItems[] = $item;
						}
					}
					$this->tokenizer->match(TierraTemplateTokenizer::TILDE_TOKEN);
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
					$this->tokenizer->matchError("Expected value not found - found " . $this->tokenizer->getNextToken());
					break;
			}
			
			return $node;
		}
		
		private function codeNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::CODE_NODE);
			$node->code = $this->expressionNode();
			return $node;
		}
		
		private function conditeratorNode() {
			
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::CONDITERATOR_NODE);
			
			// conditerator heads are optional if there is a conditional
			$node->expression = $this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN) ? false : $this->expressionNode();
			
			$node->ifTrue = false;
			$node->ifFalse = false;
			$node->conditionals = array();
			
			// grab all the conditionals
			if ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
				while ($this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
					$this->tokenizer->match(TierraTemplateTokenizer::IF_TOKEN);
					$conditionalNode = new TierraTemplateASTNode(TierraTemplateASTNode::CONDITIONAL_NODE);
					$conditionalNode->expression = $this->expressionNode();
					$conditionalNode->ifTrue = $this->tokenizer->matchIf(TierraTemplateTokenizer::QUESTION_MARK_TOKEN) ? $this->conditionalconditeratorNode() : false;
					$conditionalNode->ifFalse = false;
					$node->conditionals[] = $conditionalNode;

					if (($conditionalNode->ifTrue !== false) && $this->tokenizer->matchIf(TierraTemplateTokenizer::ELSE_TOKEN)) {
						if (!$this->tokenizer->nextIs(TierraTemplateTokenizer::IF_TOKEN)) {
							$node->ifFalse = $this->conditeratorNode();
							break;
						}
					}
				}
			}
			else {
				$node->ifTrue = $this->tokenizer->matchIf(TierraTemplateTokenizer::QUESTION_MARK_TOKEN) ? $this->conditionalconditeratorNode() : false;
				// we only match else with ifs and don't allow only elses so that the IF_TOKEN loop code above can use the ELSE_TOKEN as a terminator
				$node->ifFalse = ($node->ifTrue !== false) && $this->tokenizer->matchIf(TierraTemplateTokenizer::ELSE_TOKEN) ? $this->conditeratorNode() : false;
			}
			
			return $node;
		}
		
		private function conditionalconditeratorNode() {
			$node = new TierraTemplateASTNode(TierraTemplateASTNode::CONDITIONAL_CONDITERATOR_NODE);
			$node->elements = array();
			
			for ($i=0; $i<3; $i++) {
				$hasParen = $this->tokenizer->nextIs(TierraTemplateTokenizer::LEFT_PAREN_TOKEN);
				$this->tokenizer->matchIf(TierraTemplateTokenizer::LEFT_PAREN_TOKEN);

				if ($this->tokenizer->nextIn(array(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN, TierraTemplateTokenizer::ELSE_TOKEN, TierraTemplateTokenizer::RIGHT_BRACE_TOKEN, TierraTemplateTokenizer::CONDITERATOR_END_TOKEN))) {
					if ($hasParen)
						$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);
					break;
				} 
				
				$node->elements[] = $this->conditeratorNode();
					
				if ($hasParen)
					$this->tokenizer->match(TierraTemplateTokenizer::RIGHT_PAREN_TOKEN);
			}
			
			return $node;
		}
		
	}
	
	class TierraTemplateParserException extends Exception {
		
	}