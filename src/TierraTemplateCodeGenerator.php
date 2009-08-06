<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplateCodeGenerator {
		
		private static $blockStack = array();
		private static $isChildTemplate = false;
		private static $decorators = array();
		private static $returnIdentifierName = false;
		private static $returnIdentifierNameStack = array();
		
		public static function addDecorator($name, $method) {
			self::$decorators[$name] = $method;
		}
		
		public static function emit($ast) {
			
			self::$isChildTemplate = isset($ast->parentTemplateName);
			self::$blockStack = array();
			
			$chunks = array();

			// call the decorators in reverse order at the start of the root page
			if (!self::$isChildTemplate && isset($ast->decorators)) {
				foreach (array_reverse($ast->decorators) as $decorator) {
					if (isset(self::$decorators[$decorator->name])) {
						$params = array_merge(array(array("start" => true, "page" => true)), $decorator->params);
						$code = call_user_func_array(self::$decorators[$decorator->name], $params);
					}
				}
			}
			
			// get the html and code chunks
			foreach ($ast->getNodes() as $node) {
				switch ($node->type) {
					case TierraTemplateASTNode::HTML_NODE:
						// this is taken care of in the optimizer but in case it isn't called first don't output html not in blocks in child templates
						if (!self::$isChildTemplate || (count(self::$blockStack) > 0))
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::HTML_CHUNK, $node->html);
						break;
						
					case TierraTemplateASTNode::BLOCK_NODE:
						$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, self::emitBlock($node));
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, self::emitGenerator($node));
						break;
				}
			}
			
			// add the parent template include at the end
			if (self::$isChildTemplate) {
				$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, "\$this->includeTemplate('{$ast->parentTemplateName}');");
			}
			else if (isset($ast->decorators)) {
				foreach ($ast->decorators as $decorator) {
					if (isset(self::$decorators[$decorator->name])) {
						$params = array_merge(array(array("start" => false, "page" => true)), $decorator->params);
						$code = call_user_func_array(self::$decorators[$decorator->name], $params);
					}
				}
			}
				
			// merge the like chunks together and add the php start/end tags
			$lastChunk = false;
			$code = array();
			foreach ($chunks as $chunk) {
				$startOfFileOrInHtml = (($lastChunk === false) || ($lastChunk->type == TierraTemplateCodeGeneratorChunk::HTML_CHUNK));
				if ($chunk->type == TierraTemplateCodeGeneratorChunk::HTML_CHUNK)
					$prefix = $startOfFileOrInHtml ? "" : " ?>";
				else
					$prefix = $startOfFileOrInHtml ? "<?php " : " "; 
				$code[] = $prefix . $chunk->contents;
				$lastChunk = $chunk;
			}
			
			return implode("", $code);
		}
		
		private static function emitBlock($node) {
			$code = array();
			
			// call the decorators in reverse order at the start of the block
			if (($node->command != "end") && isset($node->decorators)) {
				foreach (array_reverse($node->decorators) as $decorator) {
					if (isset(self::$decorators[$decorator->name])) {
						$params = array_merge(array(array("start" => true, "page" => false)), $decorator->params);
						$code[] = call_user_func_array(self::$decorators[$decorator->name], $params);
					}
				}
			}
			
			// emit the opening common code by command
			switch ($node->command) {
				case "include":
					if (isset($node->conditional))
						$code[] = "if (" . self::emitExpression($node->conditional) . ") {";
					$code[] = "\$this->includeTemplate('{$node->templateName}');";
					if (isset($node->conditional))
						$code[] = "}";
					break;
					
				case "else":
					if (isset($node->conditional))
						$code[] = "} elseif (" . self::emitExpression($node->conditional) . ") {";
					else
						$code[] = "} else {";
						
					// replace the previous block
					array_pop(self::$blockStack);
					self::$blockStack[] = $node;
					break;
					
				case "start":
				case "prepend":
				case "append":
					if (isset($node->conditional))
						$code[] = "if (" . self::emitExpression($node->conditional) . ") {";
					self::$blockStack[] = $node;
					break;
			}
			
			// figure out what do with the future block contents by command
			switch ($node->command) {
				case "else":
				case "start":
					if ($node->blockName !== false) {
						$code[] = "if (!\$this->request->echoBlock('{$node->blockName}')) {";
						
						// buffer all blocks in child templates
						if (self::$isChildTemplate)
							$code[] = "ob_start();";
					}
					break;
					
				case "prepend":
				case "append":
					$code[] = "ob_start();";
					break;
			}
			
			// figure out what do with the past block contents at the end
			if ($node->command == "end") {
				$openingBlock = array_pop(self::$blockStack);
				switch ($openingBlock->command) {
					case "else":
					case "start":
						if ($node->blockName !== false) {
							// saved the buffered blocks in child templates
							if (self::$isChildTemplate) {
								$code[] = "\$this->request->setBlock('{$node->blockName}', ob_get_contents()); ob_end_clean();";
								// output blocks within blocks so it is buffered in the outer block
								if (count(self::$blockStack) > 0)
									$code[] = "\$this->request->echoBlock('{$node->blockName}');";
							}
							$code[] = "}";
						}
						break;
						
					case "prepend":
					case "append":
						$code[] = "\$this->request->{$openingBlock->command}Block('{$node->blockName}', ob_get_contents()); ob_end_clean();";
						break;
				}
				
				// add code generator decorator calls after the block is closed
				if (isset($openingBlock->decorators)) {
					foreach ($openingBlock->decorators as $decorator) {
						if (isset(self::$decorators[$decorator->name])) {
							$params = array_merge(array(array("start" => false, "page" => false)), $decorator->params);
							$code[] = call_user_func_array(self::$decorators[$decorator->name], $params);
						}
					}
				}
				
			}
			
			return implode(" ", $code);
		}
		
		public static function emitExpression($node) {
			$code = array();
			
			switch ($node->type) {
				case TierraTemplateASTNode::FUNCTION_CALL_NODE:
					$params = self::emitArray($node->params);
					if (function_exists($node->method))
						$code[] = "call_user_func_array('{$node->method}', {$params})";
					else
						// addslashes() to escape the possible namespace slashes in the method
						$code[] = "\$this->runtime->call('" . addslashes($node->method) . "', {$params})";
					break;		

				case TierraTemplateASTNode::LITERAL_NODE:
					if ($node->tokenType == TierraTemplateTokenizer::STRING_TOKEN)
						$code[] = "'" . addcslashes($node->value, "'") . "'";
					else
						$code[] = $node->value;
					break;
					
				case TierraTemplateASTNode::IDENTIFIER_NODE:
					if (self::$returnIdentifierName)
						$code[] = "'{$node->identifier}'";
					else if (in_array(strtolower($node->identifier), array("true", "false")))
						$code[] = $node->identifier;
					else
						$code[] = "\$this->runtime->identifier('" . str_replace("\$", "\\\$", $node->identifier) . "')";
					break;
					
				case TierraTemplateASTNode::OPERATOR_NODE:
					switch ($node->op) {
						case TierraTemplateTokenizer::COMMA_TOKEN:
							$code[] = self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode);
							break;
							
						case TierraTemplateTokenizer::EQUAL_TOKEN:
							self::setReturnIdentifierName(true);
							$var = self::emitExpression($node->leftNode);
							self::resetReturnIdentifierName();
							$code[] = "\$this->runtime->assign({$var}, " . self::emitExpression($node->rightNode) . ")";
							break;
							
						case TierraTemplateTokenizer::LEFT_BRACKET_TOKEN:
						case TierraTemplateTokenizer::DOT_TOKEN:
							self::setReturnIdentifierName(true);
							$code[] = "\$this->runtime->attr(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode) . ")";
							self::resetReturnIdentifierName();
							break;
							
						case TierraTemplateTokenizer::COLON_TOKEN:
							if (($node->rightNode->type == TierraTemplateASTNode::OPERATOR_NODE) && ($node->rightNode->op == ",")) {
								$code[] = "\$this->runtime->limit(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode->leftNode) . ", " . self::emitExpression($node->rightNode->rightNode) . ")";
							}
							else {
								if ($node->rightNode->type == TierraTemplateASTNode::FUNCTION_CALL_NODE) {
									// reverse the order of the function calls and pass the value of the expression as the first parameter
									array_unshift($node->rightNode->params, $node->leftNode);
									$code[] = self::emitExpression($node->rightNode);
								}
								else
									$code[] = "\$this->runtime->limit(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode) . ")";
							}
							break;
							
						default:
							if ($node->binary)
								$code[] = self::emitExpression($node->leftNode) . " " . $node->op . " " . self::emitExpression($node->rightNode);
							else  
								$code[] = $node->op . self::emitExpression($node->rightNode);
							break;
					}
					break;
					
				default:
					throw new TierraTemplateException("Unknown node type in expression: '{$node->type}'");
			}
			
			return implode(" ", $code);
		}
		
		private function emitArray($a) {
			$code = array();
			$numElements = count($a);
			for ($i=0; $i<$numElements; $i++)
				$code[] = self::emitExpression($a[$i]);
			return "array(" . implode(", ", $code) . ")";
		}
				
		public static function emitGenerator($node) {
			// TODO: implement
			// TODO: add code generator decorator calls
			return "";
		}
		
		public static function setReturnIdentifierName($value) {
			self::$returnIdentifierNameStack[] = self::$returnIdentifierName;
			self::$returnIdentifierName = $value;	
		}
		
		public static function resetReturnIdentifierName() {
			self::$returnIdentifierName = array_pop(self::$returnIdentifierNameStack);	
		}
		
	}	
	
	
	class TierraTemplateCodeGeneratorChunk {
		
		const HTML_CHUNK = "HTML_CHUNK";
		const PHP_CHUNK = "PHP_CHUNK";
		
		public $type;
		public $contents;
		
		public function __construct($type, $contents) {
			$this->type = $type;
			$this->contents = $contents;
		}
	}