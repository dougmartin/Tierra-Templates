<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplateCodeGenerator {
		
		private static $blockStack = array();
		private static $isChildTemplate = false;
		private static $returnIdentifierName = false;
		private static $returnIdentifierNameStack = array();
		private static $outputTemplateFunctions = array();
		
		private static $decorators = array(
			"nocache" => array("self", "noCacheDecorator"),
			"gzip" => array("self", "gzipDecorator"),
			"testwrapper" => array("self", "testWrapperDecorator")
		);
		
		public static function addDecorator($name, $method) {
			self::$decorators[strtolower($name)] = $method;
		}
		
		public static function noCacheDecorator($context) {
			if ($context["isStart"] && $context["isPage"])
				return "header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache');";
			return false;
		}
		
		public static function gzipDecorator($context) {
			if (($context["isPage"]) && ($context["isStart"]))
				return "ob_start('ob_gzhandler');";
			return false;
		}
		
		public static function testWrapperDecorator($context, $condition) {
			if ($context["isStart"])
				return "if ({$condition}) {";
			else
				return "} /* end if ({$condition}) */";
		}
		
		public static function emit($ast) {
			
			self::$isChildTemplate = isset($ast->parentTemplateName);
			self::$blockStack = array();
			self::$returnIdentifierName = false;
			self::$returnIdentifierNameStack = array();
			self::$outputTemplateFunctions = array();
			
			$chunks = array();

			// call the decorators in reverse order at the start of the root page
			foreach (self::getDecoratorCode($ast, true, true) as $decoratorCode) {
				if (strlen($decoratorCode) > 0)
					$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $decoratorCode);
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
						$code = self::emitBlock($node);
						if (strlen($code) > 0)
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $code);
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						$code = self::emitGenerator($node);
						if (strlen($code) > 0)
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $code);
						break;
				}
			}
			
			// add the parent template include at the end
			if (self::$isChildTemplate) {
				$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, "\$this->includeTemplate('{$ast->parentTemplateName}');");
			}
			else {
				foreach (self::getDecoratorCode($ast, true, false) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $decoratorCode);
				}
			}
			
			// and then add any saved output template functions to the start of the code
			if (count(self::$outputTemplateFunctions) > 0) {
				$code = array();
				foreach (self::$outputTemplateFunctions as $otfName => $otfCode) {
					$otfCode = str_replace('$this->', '$__template->', $otfCode);
					$code[] = "if (!function_exists('{$otfName}')) { function {$otfName}(\$__template) { ob_start(); {$otfCode} \$__output = ob_get_contents(); ob_end_clean(); return \$__output;} };";
				}
				array_unshift($chunks, new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, implode("; ", $code)));
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
			foreach (self::getDecoratorCode($node, false, true) as $decoratorCode) {
				if (strlen($decoratorCode) > 0)
					$code[] = $decoratorCode;
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
				case "set":
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
						$code[] = "if (!\$this->__request->echoBlock('{$node->blockName}')) {";
						
						// buffer all blocks in child templates
						if (self::$isChildTemplate)
							$code[] = "ob_start();";
					}
					break;
					
				case "prepend":
				case "append":
				case "set":
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
								$code[] = "\$this->__request->setBlock('{$node->blockName}', ob_get_contents()); ob_end_clean();";
								// output blocks within blocks so it is buffered in the outer block
								if (count(self::$blockStack) > 0)
									$code[] = "\$this->__request->echoBlock('{$node->blockName}');";
							}
							$code[] = "}";
						}
						break;
						
					case "prepend":
					case "append":
					case "set":
						$code[] = "\$this->__request->{$openingBlock->command}Block('{$node->blockName}', ob_get_contents()); ob_end_clean();";
						break;
				}
				
				// add code generator decorator calls after the block is closed
				foreach (self::getDecoratorCode($openingBlock, false, false) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$code[] = $decoratorCode;
				}
			}
			
			return implode(" ", $code);
		}
		
		public static function getDecoratorCode($block, $isPage, $isStart) {
			$code = array();
			if (isset($block->decorators)) {
				foreach ($isStart ? $block->decorators : array_reverse($block->decorators) as $decorator) {
					if (isset(self::$decorators[strtolower($decorator->method)])) {
						$params = array_slice($decorator->evaledParams, 0); 
						$context = array("isStart" => $isStart, "isPage" => $isPage);
						array_unshift($params, $context); 
						$decoratorCode = call_user_func_array(self::$decorators[strtolower($decorator->method)], $params);
						if (strlen($decoratorCode) > 0)
							$code[] = $decoratorCode;
					}
				}
			}
			
			return $code;
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
						$code[] = "\$this->__runtime->call('" . addslashes($node->method) . "', {$params})";
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
						$code[] = "\$this->__runtime->identifier('{$node->identifier}')";
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
							$code[] = "\$this->__runtime->assign({$var}, " . self::emitExpression($node->rightNode) . ")";
							break;
							
						case TierraTemplateTokenizer::LEFT_BRACKET_TOKEN:
						case TierraTemplateTokenizer::DOT_TOKEN:
							self::setReturnIdentifierName(true);
							$code[] = "\$this->__runtime->attr(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode) . ")";
							self::resetReturnIdentifierName();
							break;
							
						case TierraTemplateTokenizer::COLON_TOKEN:
							if (($node->rightNode->type == TierraTemplateASTNode::OPERATOR_NODE) && ($node->rightNode->op == ",")) {
								$code[] = "\$this->__runtime->limit(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode->leftNode) . ", " . self::emitExpression($node->rightNode->rightNode) . ")";
							}
							else {
								if ($node->rightNode->type == TierraTemplateASTNode::FUNCTION_CALL_NODE) {
									// reverse the order of the function calls and pass the value of the expression as the first parameter
									array_unshift($node->rightNode->params, $node->leftNode);
									$code[] = self::emitExpression($node->rightNode);
								}
								else
									$code[] = "\$this->__runtime->limit(" . self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode) . ")";
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
					
				case TierraTemplateASTNode::ARRAY_NODE:
					$code[] = "array(" . ($node->elements ? self::emitExpression($node->elements) : "" ) . ")";
					break;
					
				case TierraTemplateASTNode::JSON_NODE:
					$code[] = self::emitNamedArray($node->attributes);
					break;
					
				case TierraTemplateASTNode::JSON_ATTRIBUTE_NODE:
					$code[] = $node->name . ": " . self::emitExpression($node->value);
					break;		

				case TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE:
					$code[] = self::emitOutputTemplate($node, false);
					break;					
					
				default:
					throw new TierraTemplateException("Unknown node type in expression: '{$node->type}'");
			}
			
			return implode(" ", $code);
		}
		
		public function emitArray($a) {
			$code = array();
			foreach ($a as $item)
				$code[] = self::emitExpression($item);
			return "array(" . implode(", ", $code) . ")";
		}
		
		private function emitNamedArray($a) {
			$code = array();
			foreach ($a as $name => $value)
				$code[] = "\"{$name}\" => " . self::emitExpression($value);
			return "array(" . implode(", ", $code) . ")";
		}		

		// noEcho is used when emit generators from without an output template
		public static function emitGenerator($node, $noEcho=false) {
			$code = array();
			
			if ($node->expression->type == TierraTemplateASTNode::MULTI_EXPRESSION_NODE) {
				$expression = array_pop($node->expression->expressions);
				foreach ($node->expression->expressions as $preExpression)
					$code[] = self::emitExpression($preExpression) . ";";
			}
			else
				$expression = $node->expression;
			
			// if this generator has no output then output the head
			if (($node->ifTrue === false) && ($node->ifFalse === false) && (count($node->conditionals) == 0)) {
				if ($expression->type == TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE)
					$code[] =  self::emitOutputTemplate($expression, true);
				else
					$code[] =  $noEcho ? self::emitExpression($expression) : "echo " . self::emitExpression($expression) . ";";
			}
			// if the generator explicitly has no output by using a trailing ? then just emit the expression
			else if ($node->ifTrue && (count($node->ifTrue->elements) == 0) && (!$node->ifFalse || (count($node->ifFalse->elements) == 0)) && (count($node->conditionals) == 0)) {
				$code[] = self::emitExpression($expression);
			}
			else if (count($node->conditionals) > 0) {
				$code[] = "\$this->__runtime->startGenerator(" .  self::emitExpression($expression) .  ");";
				$ifs = array();
				foreach ($node->conditionals as $conditional)
					$ifs[] = "if (" .  self::emitExpression($conditional->expression) .  ") { " . ($conditional->ifTrue !== false ? self::emitGeneratorOutput($conditional->ifTrue) : "") . " }";
				if ($node->ifFalse !== false)
					$ifs[] = "{ " . self::emitGenerator($node->ifFalse) . " }";
				$code[] = implode(" else ", $ifs);
				$code[] = "\$this->__runtime->endGenerator();";
			}
			else {
				$code[] = "if (\$this->__runtime->startGenerator(" .  self::emitExpression($expression) .  ")) {";
				if ($node->ifTrue !== false)
					$code[] = self::emitGeneratorOutput($node->ifTrue);
				$code[] = "}";
				if ($node->ifFalse !== false)
					$code[] = "else { " . self::emitGenerator($node->ifFalse) . " }";
				$code[] = "\$this->__runtime->endGenerator();";
			}
			
			// TODO: add code generator decorator calls
			
			return implode(" ", $code);
		}
		
		public static function emitGeneratorOutput($node) {
			$code = array();
			$numElements = count($node->elements);
			
			$preElement = ($numElements > 1 ? $node->elements[0] : false);
			if ($preElement)
				$code[] = self::emitGeneratorOrOutputTemplate($preElement);
				
			$loopElement = ($numElements > 1 ? $node->elements[1] : ($numElements > 0 ? $node->elements[0] : false));
			if ($loopElement)
				$code[] = "do { " . self::emitGeneratorOrOutputTemplate($loopElement) ." } while (\$this->__runtime->loop());";
			
			$postElement = ($numElements > 2 ? $node->elements[2] : false);
			if ($postElement)
				$code[] = self::emitGeneratorOrOutputTemplate($postElement);

			return implode(" ", $code);
		}
		
		public static function emitGeneratorOrOutputTemplate($node) {
			return $node->type == TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE ? self::emitOutputTemplate($node, true) : self::emitGenerator($node);
		}
		
		public static function emitOutputTemplate($node, $echoOutput) {
			$code = array();
			
			if (count($node->outputItems) > 0) {
				$hasGenerator = false;
				$output = array();
				foreach ($node->outputItems as $item) {
					if ($item->type == TierraTemplateASTNode::GENERATOR_NODE) {
						if ($item->ifTrue || $item->ifFalse) {
							// emit the output so far and then emit the generator
							if (count($output) > 0) {
								$code[] = "echo " . implode(" . ", $output) . ";";
								$output = array();
							}
							$code[] = self::emitGenerator($item);
							$hasGenerator = true;
						}
						else
							$output[] = self::emitGenerator($item, true);
					}
					else
						$output[] = self::emitExpression($item);
				}
				if (count($output) > 0)
					$code[] = $echoOutput || $hasGenerator ? ("echo " . implode(" . ", $output) . ";") : implode(" . ", $output);
					
				// we wrap the output template in a function if we are not echoing the output and the template has at least one generator so we can return its value
				if (!$echoOutput && $hasGenerator) {
					$functionName = self::saveOutputTemplate(implode(" ", $code));
					$code = array("{$functionName}(\$this)");
				}
					
			}			
			return implode(" ", $code);
		}
		
		public static function saveOutputTemplate($code) {
			$hash = "otf_" . sha1($code);
			if (!isset(self::$outputTemplateFunctions[$hash]))
				self::$outputTemplateFunctions[$hash] = $code;
			return $hash;			
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