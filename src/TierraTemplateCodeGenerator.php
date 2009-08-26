<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplateCodeGenerator {
		
		private static $blockStack = array();
		private static $isChildTemplate = false;
		private static $outputTemplateFunctions = array();
		
		private static $decorators = array(
			"nocache" => array("self", "noCacheDecorator"),
			"testwrapper" => array("self", "testWrapperDecorator"),
			"showguid" => array("self", "showGuidDecorator"),
			"memcache" => array("self", "memcacheDecorator")
		);
		
		public static function addDecorator($name, $method) {
			self::$decorators[strtolower($name)] = $method;
		}
		
		public static function noCacheDecorator($context) {
			if ($context["isStart"] && $context["isPage"])
				return "if ({$context["guard"]}) { header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); }";
			return "{$context["guard"]};";
		}
		
		public static function testWrapperDecorator($context, $condition) {
			if ($context["isStart"])
				return "if ({$context["guard"]}) echo '/* start testwrapper({$condition}) */';";
			else
				return "if ({$context["guard"]}) echo '/* end testwrapper({$condition}) */';";
		}
		
		public static function showGuidDecorator($context) {
			if ($context["isStart"])
				return "if ({$context["guard"]}) echo '<p>guid for {$context["blockName"]}: {$context["guid"]}</p>';";
			else
				return "{$context["guard"]};";
		}
		
		public static function memcacheDecorator($context, $options=array()) {
			$vary = isset($options["vary"]) ? addslashes($options["vary"]) : "none";
			$key = $vary != "none" ? "'block_{$context["blockName"]}_{$context["guid"]}_' . \$this->__request->getGuid('{$vary}')" : "'block_{$context["blockName"]}_{$context["guid"]}'";
			$debug = isset($options["debug"]) && $options["debug"] ? "true" : "false";
			if ($context["isStart"]) {
				return <<<CODE
				
					if (!isset(\$this->__request->__scratchPad->memcacheDecorator))
						\$this->__request->__scratchPad->memcacheDecorator = new stdClass;
					if (!isset(\$this->__request->__scratchPad->memcacheDecorator->memcache)) {
						if (class_exists("Memcache")) {
							\$this->__request->__scratchPad->memcacheDecorator->memcache = new Memcache();
							if ($debug)
								echo "<!-- connecting to memcached -->";
							if (!@\$this->__request->__scratchPad->memcacheDecorator->memcache->connect('127.0.0.1')) {
								\$this->__request->__scratchPad->memcacheDecorator->memcache = false;
								if ($debug)
									echo "<!-- unable to connect to memcached -->";
							} 
						}
						else {
							\$this->__request->__scratchPad->memcacheDecorator->memcache = false;
							if ($debug)
								echo "<!-- Memcache class does not exist -->"; 
						}
					}
					if (\$this->__request->__scratchPad->memcacheDecorator->memcache) {
						if ($debug)
							echo "<!-- getting block from memcached: " . $key . " -->";
						\$this->__request->__scratchPad->memcacheDecorator->blockContents = @\$this->__request->__scratchPad->memcacheDecorator->memcache->get({$key});
					}
					else
						\$this->__request->__scratchPad->memcacheDecorator->blockContents = false;
					if (\$this->__request->__scratchPad->memcacheDecorator->blockContents !== false) {
						if ($debug)
							echo "<!-- got block from memcached -->"; 
						echo \$this->__request->__scratchPad->memcacheDecorator->blockContents;
					}
					else {
						if (\$this->__request->__scratchPad->memcacheDecorator->memcache) {
							if ($debug)
								echo "<!-- did not get block, starting memcache output buffering -->"; 
							ob_start();
						}
CODE;
			} 
			else {
				$expire = isset($options["expire"]) ? $options["expire"] : 60;
				if (is_string($expire))
					$expire = "strtotime('" . addslashes($expire) . "') - time()";
				return <<<CODE
				
						if (\$this->__request->__scratchPad->memcacheDecorator->memcache) {
							\$this->__request->__scratchPad->memcacheDecorator->blockContents = ob_get_contents();
							ob_end_clean(); 
							if ($debug)
								echo "<!-- completing memcache output buffering and saving block -->"; 
							@\$this->__request->__scratchPad->memcacheDecorator->memcache->set({$key}, \$this->__request->__scratchPad->memcacheDecorator->blockContents, 0, {$expire});
							echo \$this->__request->__scratchPad->memcacheDecorator->blockContents;
						}
					}
CODE;
			}
			
		}
		
		public static function emit($ast) {
			
			self::$isChildTemplate = isset($ast->parentTemplateName);
			self::$blockStack = array();
			self::$outputTemplateFunctions = array();
			
			$chunks = array();

			// call the decorators in reverse order at the start of the root page
			if (isset($ast->pageBlock)) {
				foreach (self::getDecoratorCode($ast->pageBlock, true, true) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $decoratorCode);
				}
			}
			
			// get the html and code chunks
			foreach ($ast->getNodes() as $node) {
				$code = false;
				switch ($node->type) {
					case TierraTemplateASTNode::HTML_NODE:
						// this is taken care of in the optimizer but in case it isn't called first don't output html not in blocks in child templates
						if (!self::$isChildTemplate || (count(self::$blockStack) > 0))
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::HTML_CHUNK, $node->html);
						break;
						
					case TierraTemplateASTNode::BLOCK_NODE:
						$code = self::emitBlock($node);
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						$code = self::emitGenerator($node);
						break;
						
					case TierraTemplateASTNode::CODE_NODE:
						$code = self::emitCode($node);
						break;
				}
				if (strlen($code) > 0)
					$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $code);
			}
			
			// add the parent template include at the end
			if (self::$isChildTemplate) {
				$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, "\$this->includeTemplate('{$ast->parentTemplateName}');");
			}
			else {
				if (isset($ast->pageBlock)) {
					foreach (self::getDecoratorCode($ast->pageBlock, true, false) as $decoratorCode) {
						if (strlen($decoratorCode) > 0)
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $decoratorCode);
					}
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
						// don't echo top level blocks in child templates
						if (self::$isChildTemplate && (count(self::$blockStack) == 1))
							$code[] = "if (!\$this->__request->haveBlock('{$node->blockName}')) {";
						else
							$code[] = "if (!\$this->__request->echoBlock('{$node->blockName}')) {";
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
			
			// call the decorators in reverse order at the start of the block
			foreach (self::getDecoratorCode($node, false, true) as $decoratorCode) {
				if (strlen($decoratorCode) > 0)
					$code[] = $decoratorCode;
			}
			
			// figure out what do with the past block contents at the end
			if ($node->command == "end") {
				$openingBlock = array_pop(self::$blockStack);
				
				// add code generator decorator calls after the block is closed
				foreach (self::getDecoratorCode($openingBlock, false, false) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$code[] = $decoratorCode;
				}
				
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
						if (!self::$isChildTemplate)
							$code[] = "\$this->__request->echoBlock('{$node->blockName}');";
						break;
				}
				
			}
			
			return implode(" ", $code);
		}
		
		public static function getDecoratorCode($block, $isPage, $isStart) {
			$code = array();
			if (isset($block->decorators)) {
				foreach ($isStart ? $block->decorators : array_reverse($block->decorators) as $decorator) {
					$codeParams = $block->blockName ? "'{$decorator->action}', '{$decorator->method}', '{$block->blockName}'" : "'{$decorator->action}', '{$decorator->method}'"; 
					if ($decorator->action == "remove") {
						if ($isStart)
							$code[] = "\$this->__request->__decorator({$codeParams});";
					}
					else {
						if (isset(self::$decorators[strtolower($decorator->method)])) {
							$params = array_slice($decorator->evaledParams, 0); 
							$context = array(
								"isStart" => $isStart, 
								"isPage" => $isPage, 
								"blockName" => $block->blockName,
								"guid" => $block->guid, 
								"guard" => $isStart ? "\$this->__request->__startDecorator({$codeParams})" : "\$this->__request->__endDecorator()"
							);
							array_unshift($params, $context); 
							$decoratorCode = call_user_func_array(self::$decorators[strtolower($decorator->method)], $params);
							if (strlen($decoratorCode) > 0)
								$code[] = $decoratorCode;
						}
					}
				}
			}
			
			return $code;
		}
		
		public static function emitCode($node) {
			if ($node->code->type == TierraTemplateASTNode::MULTI_EXPRESSION_NODE) {
				$code = array();
				foreach ($node->code as $expression)
					$code[] = self::emitExpression($expression) . ";";
				return implode(" ", $code);
			}
			return self::emitExpression($node->code) . ";";
		}
		
		public static function emitExpression($node) {
			$code = array();
			
			switch ($node->type) {
				case TierraTemplateASTNode::FUNCTION_CALL_NODE:
					$params = self::emitArray($node->params);
					if (function_exists($node->method))
						$code[] = "call_user_func_array('{$node->method}', {$params})";
					else if ($node->isExternal)
						$code[] = "\$this->__runtime->externalCall('{$node->method}', '{$node->filename}', '{$node->virtualDir}', '" . str_replace("\\", "/", $node->subDir) . "', '" . str_replace("\\", "\\\\", $node->debugInfo) . "', {$params})";
					else
						// addslashes() to escape the possible namespace slashes in the method
						$code[] = "\$this->__runtime->call('" . addslashes($node->method) . "', '" . str_replace("\\", "\\\\", $node->debugInfo) . "', {$params})";
					break;		

				case TierraTemplateASTNode::LITERAL_NODE:
					if ($node->tokenType == TierraTemplateTokenizer::STRING_TOKEN)
						$code[] = "'" . addcslashes($node->value, "'") . "'";
					else
						$code[] = $node->value;
					break;
					
				case TierraTemplateASTNode::IDENTIFIER_NODE:
					if (in_array(strtolower($node->identifier), array("true", "false")))
						$code[] = $node->identifier;
					else if ($node->isExternal)
						$code[] = "\$this->__runtime->externalIdentifier('{$node->identifier}', '{$node->filename}', '{$node->virtualDir}', '" . str_replace("\\", "/", $node->subDir) . "', '" . str_replace("\\", "\\\\", $node->debugInfo) . "')";
					else if ($node->identifier[0] == "$") 
						$code[] = "\$this->__runtime->specialIdentifier('" . substr($node->identifier, 1) . "')";
					else
						$code[] = "\$this->__runtime->identifier('{$node->identifier}')";
					break;
					
				case TierraTemplateASTNode::OPERATOR_NODE:
					switch ($node->op) {
						case TierraTemplateTokenizer::COMMA_TOKEN:
							$code[] = self::emitExpression($node->leftNode) . ", " . self::emitExpression($node->rightNode);
							break;
							
						case TierraTemplateTokenizer::EQUAL_TOKEN:
							$attrs = array();
							$identifier = self::getIdentifier($node->leftNode, $attrs);
							$code[] = "\$this->__request->setVar({$identifier}, " . self::emitExpression($node->rightNode) . (count($attrs) > 0 ? ", array(" . implode(", ", $attrs) . ")" : "") . ")";
							break;
							
						case TierraTemplateTokenizer::LEFT_BRACKET_TOKEN:
						case TierraTemplateTokenizer::DOT_TOKEN:
							$leftExpression = self::emitExpression($node->leftNode);
							$rightExpression = ($node->rightNode->type == TierraTemplateASTNode::IDENTIFIER_NODE ? "'{$node->rightNode->identifier}'" : self::emitExpression($node->rightNode)); 
							$code[] = "\$this->__runtime->attr({$leftExpression}, {$rightExpression})";
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
		
		public function getIdentifier($node, &$attrs) {
			// walk down the parse tree to the left as long as its a attribute node 
			if (($node->type == TierraTemplateASTNode::OPERATOR_NODE) && (($node->op == TierraTemplateTokenizer::LEFT_BRACKET_TOKEN) || ($node->op == TierraTemplateTokenizer::DOT_TOKEN)))
				$identifier = self::getIdentifier($node->leftNode, $attrs);
				
			if (!isset($identifier)) {
				// 	get the identifier at the bottom, the parser checks to make sure this is an identifier
				$identifier = "'{$node->identifier}'";
			}
			else {
				// return the right nodes (if any) on the way back up
				if ($node->rightNode->type == TierraTemplateASTNode::IDENTIFIER_NODE)
					$attrs[] = "'{$node->rightNode->identifier}'";
				else
					$attrs[] = self::emitExpression($node->rightNode);
			}
			
			return $identifier;
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

			// the expression can be empty, eg {@ if foo ? bar @}
			$expression = $node->expression;
			if ($node->expression && ($node->expression->type == TierraTemplateASTNode::MULTI_EXPRESSION_NODE)) {
				$expression = array_pop($node->expression->expressions);
				foreach ($node->expression->expressions as $preExpression)
					$code[] = self::emitExpression($preExpression) . ";";
			}
			
			// if this generator has no output then output the head
			if (($node->ifTrue === false) && ($node->ifFalse === false) && (count($node->conditionals) == 0)) {
				if (!$expression)
					$code[] =  $noEcho ? "true" : "echo true;";
				else if ($expression->type == TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE)
					$code[] =  self::emitOutputTemplate($expression, true);
				else
					$code[] =  $noEcho ? self::emitExpression($expression) : "echo " . self::emitExpression($expression) . ";";
			}
			// if the generator explicitly has no output by using a trailing ? then just emit the expression
			else if ($node->ifTrue && (count($node->ifTrue->elements) == 0) && (!$node->ifFalse || (count($node->ifFalse->elements) == 0)) && (count($node->conditionals) == 0)) {
				$code[] = ($expression ? self::emitExpression($expression) : "true") . ";";
			}
			else if (count($node->conditionals) > 0) {
				$code[] = "\$this->__runtime->startGenerator(" .  ($expression ? self::emitExpression($expression) : "true") .  ");";
				$ifs = array();
				foreach ($node->conditionals as $conditional)
					$ifs[] = "if (" .  self::emitExpression($conditional->expression) .  ") { " . ($conditional->ifTrue !== false ? self::emitGeneratorOutput($conditional->ifTrue) : "") . " }";
				if ($node->ifFalse !== false)
					$ifs[] = "{ " . self::emitGenerator($node->ifFalse) . " }";
				$code[] = implode(" else ", $ifs);
				$code[] = "\$this->__runtime->endGenerator();";
			}
			else {
				$code[] = "if (\$this->__runtime->startGenerator(" .  ($expression ? self::emitExpression($expression) : "true") .  ")) {";
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
					$functionName = "otf_" . sha1(implode(" ", $code));
					if (!isset(self::$outputTemplateFunctions[$functionName]))
						self::$outputTemplateFunctions[$functionName] = implode(" ", $code);
					$code = array("{$functionName}(\$this)");
				}
					
			}			
			return implode(" ", $code);
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