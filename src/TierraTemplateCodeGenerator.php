<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplateCodeGenerator {
		
		private $options;
		private $blockStack;
		private $isChildTemplate;
		private $outputTemplateFunctions;
		
		public function __construct($options=array()) {
			$this->options = $options;
			$this->blockStack = array();
			$this->isChildTemplate = false;
			$this->outputTemplateFunctions = array();
		}		
		
		private $decorators = array(
			"nocache" => array("self", "noCacheDecorator"),
			"testwrapper" => array("self", "testWrapperDecorator"),
			"showguid" => array("self", "showGuidDecorator"),
			"memcache" => array("self", "memcacheDecorator")
		);
		
		public function addDecorator($name, $method) {
			$this->decorators[strtolower($name)] = $method;
		}
		
		public function noCacheDecorator($context) {
			if ($context["isStart"] && $context["isPage"])
				return "if ({$context["guard"]}) { header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); }";
			return "{$context["guard"]};";
		}
		
		public function testWrapperDecorator($context, $condition) {
			if ($context["isStart"])
				return "if ({$context["guard"]}) echo '/* start testwrapper({$condition}) */';";
			else
				return "if ({$context["guard"]}) echo '/* end testwrapper({$condition}) */';";
		}
		
		public function showGuidDecorator($context) {
			if ($context["isStart"])
				return "if ({$context["guard"]}) echo '<p>guid for {$context["blockName"]}: {$context["guid"]}</p>';";
			else
				return "{$context["guard"]};";
		}
		
		public function memcacheDecorator($context, $options=array()) {
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
		
		public function emit($ast) {
			
			$this->isChildTemplate = isset($ast->parentTemplateName);
			$this->blockStack = array();
			$this->outputTemplateFunctions = array();
			
			$chunks = array();

			// call the decorators in reverse order at the start of the root page
			if (isset($ast->pageBlock)) {
				foreach ($this->getDecoratorCode($ast->pageBlock, true, true) as $decoratorCode) {
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
						if (!$this->isChildTemplate || (count($this->blockStack) > 0))
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::HTML_CHUNK, $node->html);
						break;
						
					case TierraTemplateASTNode::BLOCK_NODE:
						$code = $this->emitBlock($node);
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						$code = $this->emitGenerator($node);
						break;
						
					case TierraTemplateASTNode::CODE_NODE:
						$code = $this->emitCode($node);
						break;
				}
				if (strlen($code) > 0)
					$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $code);
			}
			
			// add the parent template include at the end
			if ($this->isChildTemplate) {
				$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, "\$this->includeTemplate(" . $this->emitExpression($ast->parentTemplateName) . ");");
			}
			else {
				if (isset($ast->pageBlock)) {
					foreach ($this->getDecoratorCode($ast->pageBlock, true, false) as $decoratorCode) {
						if (strlen($decoratorCode) > 0)
							$chunks[] = new TierraTemplateCodeGeneratorChunk(TierraTemplateCodeGeneratorChunk::PHP_CHUNK, $decoratorCode);
					}
				}
			}
			
			// and then add any saved output template functions to the start of the code
			if (count($this->outputTemplateFunctions) > 0) {
				$code = array();
				foreach ($this->outputTemplateFunctions as $otfName => $otfCode) {
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
		
		private function emitBlock($node) {
			$code = array();
			
			// emit the opening common code by command
			switch ($node->command) {
				case "include":
					if (isset($node->conditional))
						$code[] = "if (" . $this->emitExpression($node->conditional) . ") {";
					$code[] = "\$this->includeTemplate(" . $this->emitExpression($node->templateName) . ");";
					if (isset($node->conditional))
						$code[] = "}";
					break;
					
				case "echo":
					if (isset($node->conditional))
						$code[] = "if (" . $this->emitExpression($node->conditional) . ") {";
						
					foreach ($this->getDecoratorCode($node, false, true) as $decoratorCode) {
						if (strlen($decoratorCode) > 0)
							$code[] = $decoratorCode;
					}
					
					$code[] = "\$this->__request->echoBlock('{$node->blockName}');";
						
					foreach ($this->getDecoratorCode($node, false, false) as $decoratorCode) {
						if (strlen($decoratorCode) > 0)
							$code[] = $decoratorCode;
					}

					if (isset($node->conditional))
						$code[] = "}";
					break;
					
				case "else":
					if (isset($node->conditional))
						$code[] = "} elseif (" . $this->emitExpression($node->conditional) . ") {";
					else
						$code[] = "} else {";
						
					// replace the previous block
					array_pop($this->blockStack);
					$this->blockStack[] = $node;
					break;
					
				case "start":
				case "prepend":
				case "append":
				case "set":
					if (isset($node->conditional))
						$code[] = "if (" . $this->emitExpression($node->conditional) . ") {";
					$this->blockStack[] = $node;
					break;
			}
			
			// figure out what do with the future block contents by command
			switch ($node->command) {
				case "else":
				case "start":
					if ($node->blockName !== false) {
						// don't echo top level blocks in child templates
						if ($this->isChildTemplate && (count($this->blockStack) == 1))
							$code[] = "if (!\$this->__request->haveBlock('{$node->blockName}')) {";
						else
							$code[] = "if (!\$this->__request->echoBlock('{$node->blockName}')) {";
						if ($this->isChildTemplate)
							$code[] = "ob_start();";
					}
					break;
					
				case "prepend":
				case "append":
				case "set":
					$code[] = "ob_start();";
					break;
			}
			
			if ($node->command != "echo") {
				// call the decorators in reverse order at the start of the block
				foreach ($this->getDecoratorCode($node, false, true) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$code[] = $decoratorCode;
				}
			}
			
			// figure out what do with the past block contents at the end
			if ($node->command == "end") {
				$openingBlock = array_pop($this->blockStack);
				
				// add code generator decorator calls after the block is closed
				foreach ($this->getDecoratorCode($openingBlock, false, false) as $decoratorCode) {
					if (strlen($decoratorCode) > 0)
						$code[] = $decoratorCode;
				}
				
				switch ($openingBlock->command) {
					case "else":
					case "start":
						if ($node->blockName !== false) {
							// saved the buffered blocks in child templates
							if ($this->isChildTemplate) {
								$code[] = "\$this->__request->setBlock('{$node->blockName}', ob_get_contents()); ob_end_clean();";
								// output blocks within blocks so it is buffered in the outer block
								if (count($this->blockStack) > 0)
									$code[] = "\$this->__request->echoBlock('{$node->blockName}');";
							}
							$code[] = "}";
						}
						break;
						
					case "prepend":
					case "append":
					case "set":
						$code[] = "\$this->__request->{$openingBlock->command}Block('{$node->blockName}', ob_get_contents()); ob_end_clean();";
						if (!$this->isChildTemplate)
							$code[] = "\$this->__request->echoBlock('{$node->blockName}');";
						break;
				}
				
				if (isset($openingBlock->conditional))
					$code[] = "}";
			}
			
			return implode(" ", $code);
		}
		
		public function emitTemplateInclude($node) {
			return "\$this->includeTemplate(" . $this->emitExpression($node->templateName) . ");";
		}
		
		public function getDecoratorCode($block, $isPage, $isStart) {
			$code = array();
			if (isset($block->decorators)) {
				foreach ($isStart ? $block->decorators : array_reverse($block->decorators) as $decorator) {
					$codeParams = $block->blockName ? "'{$decorator->action}', '{$decorator->method}', '{$block->blockName}'" : "'{$decorator->action}', '{$decorator->method}'"; 
					if ($decorator->action == "remove") {
						if ($isStart)
							$code[] = "\$this->__request->__decorator({$codeParams});";
					}
					else {
						if (isset($this->decorators[strtolower($decorator->method)])) {
							$params = array_slice($decorator->evaledParams, 0); 
							$context = array(
								"isStart" => $isStart, 
								"isPage" => $isPage, 
								"blockName" => $block->blockName,
								"guid" => $block->guid, 
								"guard" => $isStart ? "\$this->__request->__startDecorator({$codeParams})" : "\$this->__request->__endDecorator()"
							);
							array_unshift($params, $context); 
							$decoratorCode = call_user_func_array($this->decorators[strtolower($decorator->method)], $params);
							if (strlen($decoratorCode) > 0)
								$code[] = $decoratorCode;
						}
					}
				}
			}
			
			return $code;
		}
		
		public function emitCode($node) {
			if ($node->code->type == TierraTemplateASTNode::MULTI_EXPRESSION_NODE) {
				$code = array();
				foreach ($node->code as $expression)
					$code[] = $this->emitExpression($expression) . ";";
				return implode(" ", $code);
			}
			return $this->emitExpression($node->code) . ";";
		}
		
		public function emitExpression($node) {
			$code = array();
			
			switch ($node->type) {
				case TierraTemplateASTNode::FUNCTION_CALL_NODE:
					$params = $this->emitArray($node->params);
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
							$code[] = $this->emitExpression($node->leftNode) . ", " . $this->emitExpression($node->rightNode);
							break;
							
						case TierraTemplateTokenizer::EQUAL_TOKEN:
							$attrs = array();
							$identifier = $this->getIdentifier($node->leftNode, $attrs);
							$code[] = "\$this->__request->setVar({$identifier}, " . $this->emitExpression($node->rightNode) . (count($attrs) > 0 ? ", array(" . implode(", ", $attrs) . ")" : "") . ")";
							break;
							
						case TierraTemplateTokenizer::LEFT_BRACKET_TOKEN:
						case TierraTemplateTokenizer::DOT_TOKEN:
							$leftExpression = $this->emitExpression($node->leftNode);
							$rightExpression = ($node->rightNode->type == TierraTemplateASTNode::IDENTIFIER_NODE ? "'{$node->rightNode->identifier}'" : $this->emitExpression($node->rightNode)); 
							$code[] = "\$this->__runtime->attr({$leftExpression}, {$rightExpression})";
							break;
							
						case TierraTemplateTokenizer::COLON_TOKEN:
							if (($node->rightNode->type == TierraTemplateASTNode::OPERATOR_NODE) && ($node->rightNode->op == ",")) {
								$code[] = "\$this->__runtime->limit(" . $this->emitExpression($node->leftNode) . ", " . $this->emitExpression($node->rightNode->leftNode) . ", " . $this->emitExpression($node->rightNode->rightNode) . ")";
							}
							else {
								if ($node->rightNode->type == TierraTemplateASTNode::FUNCTION_CALL_NODE) {
									// reverse the order of the function calls and pass the value of the expression as the first parameter
									array_unshift($node->rightNode->params, $node->leftNode);
									$code[] = $this->emitExpression($node->rightNode);
								}
								else
									$code[] = "\$this->__runtime->limit(" . $this->emitExpression($node->leftNode) . ", " . $this->emitExpression($node->rightNode) . ")";
							}
							break;
							
						default:
							if ($node->binary)
								$code[] = $this->emitExpression($node->leftNode) . " " . $node->op . " " . $this->emitExpression($node->rightNode);
							else  
								$code[] = $node->op . $this->emitExpression($node->rightNode);
							break;
					}
					break;
					
				case TierraTemplateASTNode::ARRAY_NODE:
					$code[] = "array(" . ($node->elements ? $this->emitExpression($node->elements) : "" ) . ")";
					break;
					
				case TierraTemplateASTNode::JSON_NODE:
					$code[] = $this->emitNamedArray($node->attributes);
					break;
					
				case TierraTemplateASTNode::JSON_ATTRIBUTE_NODE:
					$code[] = $node->name . ": " . $this->emitExpression($node->value);
					break;		

				case TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE:
					$code[] = $this->emitOutputTemplate($node, false);
					break;					
					
				default:
					throw new TierraTemplateException("Unknown node type in expression: '{$node->type}'");
			}
			
			return implode(" ", $code);
		}
		
		public function getIdentifier($node, &$attrs) {
			// walk down the parse tree to the left as long as its a attribute node 
			if (($node->type == TierraTemplateASTNode::OPERATOR_NODE) && (($node->op == TierraTemplateTokenizer::LEFT_BRACKET_TOKEN) || ($node->op == TierraTemplateTokenizer::DOT_TOKEN)))
				$identifier = $this->getIdentifier($node->leftNode, $attrs);
				
			if (!isset($identifier)) {
				// 	get the identifier at the bottom, the parser checks to make sure this is an identifier
				$identifier = "'{$node->identifier}'";
			}
			else {
				// return the right nodes (if any) on the way back up
				if ($node->rightNode->type == TierraTemplateASTNode::IDENTIFIER_NODE)
					$attrs[] = "'{$node->rightNode->identifier}'";
				else
					$attrs[] = $this->emitExpression($node->rightNode);
			}
			
			return $identifier;
		}
		
		public function emitArray($a) {
			$code = array();
			foreach ($a as $item)
				$code[] = $this->emitExpression($item);
			return "array(" . implode(", ", $code) . ")";
		}
		
		private function emitNamedArray($a) {
			$code = array();
			foreach ($a as $name => $value)
				$code[] = "\"{$name}\" => " . $this->emitExpression($value);
			return "array(" . implode(", ", $code) . ")";
		}		

		// noEcho is used when emit generators from without an output template
		public function emitGenerator($node, $noEcho=false) {
			$code = array();

			// the expression can be empty, eg {@ if foo ? bar @}
			$expression = $node->expression;
			if ($node->expression && ($node->expression->type == TierraTemplateASTNode::MULTI_EXPRESSION_NODE)) {
				$expression = array_pop($node->expression->expressions);
				foreach ($node->expression->expressions as $preExpression)
					$code[] = $this->emitExpression($preExpression) . ";";
			}
			
			// if this generator has no output then output the head
			if (($node->ifTrue === false) && ($node->ifFalse === false) && (count($node->conditionals) == 0)) {
				if (!$expression)
					$code[] =  $noEcho ? "true" : "echo true;";
				else if ($expression->type == TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE)
					$code[] =  $this->emitOutputTemplate($expression, true);
				else
					$code[] =  $noEcho ? $this->emitExpression($expression) : "echo " . $this->emitExpression($expression) . ";";
			}
			// if the generator explicitly has no output by using a trailing ? then just emit the expression
			else if ($node->ifTrue && (count($node->ifTrue->elements) == 0) && (!$node->ifFalse || (count($node->ifFalse->elements) == 0)) && (count($node->conditionals) == 0)) {
				$code[] = ($expression ? $this->emitExpression($expression) : "true") . ";";
			}
			else if (count($node->conditionals) > 0) {
				$code[] = "\$this->__runtime->startGenerator(" .  ($expression ? $this->emitExpression($expression) : "true") .  ");";
				$ifs = array();
				foreach ($node->conditionals as $conditional)
					$ifs[] = "if (" .  $this->emitExpression($conditional->expression) .  ") { " . ($conditional->ifTrue !== false ? $this->emitGeneratorOutput($conditional->ifTrue) : "") . " }";
				if ($node->ifFalse !== false)
					$ifs[] = "{ " . $this->emitGenerator($node->ifFalse) . " }";
				$code[] = implode(" else ", $ifs);
				$code[] = "\$this->__runtime->endGenerator();";
			}
			else {
				$code[] = "if (\$this->__runtime->startGenerator(" .  ($expression ? $this->emitExpression($expression) : "true") .  ")) {";
				if ($node->ifTrue !== false)
					$code[] = $this->emitGeneratorOutput($node->ifTrue);
				$code[] = "}";
				if ($node->ifFalse !== false)
					$code[] = "else { " . $this->emitGenerator($node->ifFalse) . " }";
				$code[] = "\$this->__runtime->endGenerator();";
			}
			
			// TODO: add code generator decorator calls
			
			return implode(" ", $code);
		}
		
		public function emitGeneratorOutput($node) {
			$code = array();
			$numElements = count($node->elements);
			
			$preElement = ($numElements > 1 ? $node->elements[0] : false);
			if ($preElement)
				$code[] = $this->emitGeneratorOrOutputTemplate($preElement);
				
			$loopElement = ($numElements > 1 ? $node->elements[1] : ($numElements > 0 ? $node->elements[0] : false));
			if ($loopElement)
				$code[] = "do { " . $this->emitGeneratorOrOutputTemplate($loopElement) ." } while (\$this->__runtime->loop());";
			
			$postElement = ($numElements > 2 ? $node->elements[2] : false);
			if ($postElement)
				$code[] = $this->emitGeneratorOrOutputTemplate($postElement);

			return implode(" ", $code);
		}
		
		public function emitGeneratorOrOutputTemplate($node) {
			return $node->type == TierraTemplateASTNode::OUTPUT_TEMPLATE_NODE ? $this->emitOutputTemplate($node, true) : $this->emitGenerator($node);
		}
		
		public function emitOutputTemplate($node, $echoOutput) {
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
							$code[] = $this->emitGenerator($item);
							$hasGenerator = true;
						}
						else
							$output[] = $this->emitGenerator($item, true);
					}
					else
						$output[] = $this->emitExpression($item);
				}
				if (count($output) > 0)
					$code[] = $echoOutput || $hasGenerator ? ("echo " . implode(" . ", $output) . ";") : implode(" . ", $output);
					
				// we wrap the output template in a function if we are not echoing the output and the template has at least one generator so we can return its value
				if (!$echoOutput && $hasGenerator) {
					$functionName = "otf_" . sha1(implode(" ", $code));
					if (!isset($this->outputTemplateFunctions[$functionName]))
						$this->outputTemplateFunctions[$functionName] = implode(" ", $code);
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