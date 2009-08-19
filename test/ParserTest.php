<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class ParserTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkSyntax($src, $message, $dump=false) {
			try {
				$isValid = true;
				$parser = new TierraTemplateParser($src);
				$parser->parse();
			} 
			catch (TierraTemplateParserException $e) {
				$isValid = false;
			}
			if ($dump) {
				echo "\n{$message}\n";
				echo "src:\n";
				var_dump($src);
				echo "ast:\n";
				var_dump($parser->getAST());
			}
			$this->assertTrue($isValid, $message);
		}
		
		public function checkAST($src, $ast, $message, $dump = false) {
			try {
				$parser = new TierraTemplateParser($src);
				$parser->parse();
			} 
			catch (TierraTemplateParserException $e) {}
			if ($dump) {
				echo "\n{$message}\n";
				echo "parser ast:\n";
				var_dump($parser->getAST());
				echo "passed ast:\n";
				var_dump($ast);
			}
			$this->assertEquals($parser->getAST(), $ast, $message);
		}
		
		public function checkBlockCommand($command, $testName, $blockName=false, $isString=false, $dump=false) {
			$srcBlockName = $blockName !== false ? ($isString ? "'" . $blockName . "'" : $blockName) : "";
			$src = "[@ {$command} {$srcBlockName} @]";
			self::checkSyntax($src, "{$testName} is valid syntax");
			if ($command == "extends") {
				$ast = TestHelpers::MakeAST(array(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => ""))), array("parentTemplateName" => $blockName));
			}
			else {
				if ($command == "include")
					$nodeAttributes = array("command" => $command, "templateName" => $blockName);
				else
					$nodeAttributes = array("command" => $command, "blockName" => $blockName);
				$ast = TestHelpers::MakeAST(array(
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, $nodeAttributes)
									));
			}
			self::checkAST($src, $ast, "{$testName} has correct AST", $dump);
		}
		
		public function testEmpty() {
			self::checkSyntax("", "Empty string is valid syntax");
			$ast = new TierraTemplateAST();
			self::checkAST("", $ast, "Empty string is correct AST");
		}
	
		public function testSingleSpace() {
			$src = " ";
			self::checkSyntax($src, "Single space is valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single space is correct AST");
		}
			
		public function testMultipleSpaces() {
			$src = "     ";
			self::checkSyntax($src, "Multiple spaces are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces has correct AST");
		}	
		
		public function testSingleTab() {
			$src = "	";
			self::checkSyntax($src, "Single tab is valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single tab has correct AST");
		}
			
		public function testMultipleTabs() {
			$src = "			";
			self::checkSyntax($src, "Multiple tabs are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple tabs has correct AST");
		}
			
		public function testMultipleSpacesAndTabs() {
			$src = "  		  		  ";
			self::checkSyntax($src, "Multiple spaces and tabs are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces and tabs has correct AST");
		}	
		
		public function testCommentOnly() {
			$comment = " this is a comment ";
			$src = "[#{$comment}#]";
			self::checkSyntax($src, "Comment only is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment))
								));
			self::checkAST($src, $ast, "Comment only has correct AST");
		}		
		
		public function testCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#] ";
			self::checkSyntax($src, "Comment with spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Comment with spaces has correct AST");
		}

		public function testAdjoiningCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#][#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Adjoining comments with spaces has correct AST");
		}

		public function testAdjoiningCommentWithInteriorSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#]  [#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with interior spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "  ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Adjoining comments with interior spaces has correct AST");
		}			
		
		public function testUnnamedBlockOnly() {
			$src = "[@ start @] bar [@ end @]";
			self::checkSyntax($src, "Unnamed blocks is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => false)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " bar ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => false)),
								));
			self::checkAST($src, $ast, "Unnamed blocks has correct AST");
		}		
				
		public function testUnnamedBlockOnlyForException() {
			$this->setExpectedException('TierraTemplateTokenizerException');
			foreach (array("extends", "include", "prepend", "append", "set") as $command)
				self::checkBlockCommand($command, "Unnamed {$command} block");
		}
				
		public function testNamedBlockOnly() {
			foreach (array("start", "prepend", "append", "set") as $command) {
				$src = "[@ start foo @] bar [@ end foo @]";
				self::checkSyntax($src, "Unnamed blocks is valid syntax");
				$ast = TestHelpers::MakeAST(array(
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "foo")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " bar ")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "foo")),
									));
				self::checkAST($src, $ast, "Unnamed blocks has correct AST");
			}
		}		
				
		public function testNamedStringBlockOnly() {
			foreach (array("start", "prepend", "append", "set") as $command) {
				$src = "[@ start 'foo' @] bar [@ end 'foo' @]";
				self::checkSyntax($src, "Unnamed blocks is valid syntax");
				$ast = TestHelpers::MakeAST(array(
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "foo")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " bar ")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "foo")),
									));
				self::checkAST($src, $ast, "Unnamed blocks has correct AST");
			}
		}	

		public function testStartAndEndBlockWithHTML() {
			$src = " [@ start test @] foo [@ end test @] ";
			self::checkSyntax($src, "Start and end blocks with html is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "test")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " foo ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "test")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Start and end blocks with html has correct AST");
		}	

		public function testDuplicateExtendsBlock() {
			$this->setExpectedException('TierraTemplateTokenizerException');
			$src = " [@ extends test @] foo [@ extends foo @] ";
			self::checkSyntax($src, "Duplicate extends blocks with html is valid syntax");
		}
		
		public function testBlockWithSimpleConditional() {
			$src = "[@ include foo if true @]";	
			self::checkSyntax($src, "Block with simple conditional");
		}

		public function testBlockWithOneConditionalOperator() {
			$src = "[@ include foo if x < 3 @]";	
			self::checkSyntax($src, "Block with one conditional operator");
		}

		public function testBlockWithTwoConditionalOperators() {
			$src = "[@ include foo if x < 2 * 3 @]";	
			self::checkSyntax($src, "Block with two conditional operators");
		}

		public function testBlockWithAssignment() {
			$src = "[@ include foo if x = 3 < 2 * 3 @]";	
			self::checkSyntax($src, "Block with assignment");
		}

		public function testBlockWithAssignmentInParens() {
			$src = "[@ include foo if (x = 3 < 2) * 3 @]";	
			self::checkSyntax($src, "Block with assignment in parens");
		}

		public function testBlockWithSimpleNotConditional() {
			$src = "[@ include foo if !x @]";	
			self::checkSyntax($src, "Block with simple not conditional");
		}
		
		public function testBlockWithSimpleNotConditionalPlusOperator() {
			$src = "[@ include foo if !x != 3 @]";	
			self::checkSyntax($src, "Block with simple not conditional plus operator");
		}	

		public function testBlockWithArrayIndex() {
			$src = "[@ include foo if x[3] @]";	
			self::checkSyntax($src, "Block with array index");
		}
		
		public function testBlockWithTwoArrayIndicies() {
			$src = "[@ include foo if x[3][4] @]";	
			self::checkSyntax($src, "Block with array two array indicies");
		}		
		
		public function testBlockWithArrayIndexExpression() {
			$src = "[@ include foo if x[3 + 1 * 55] @]";	
			self::checkSyntax($src, "Block with array index expression");
		}
		
		public function testBlockWithArrayIndexAndSingleLimit() {
			$src = "[@ include foo if x[3]:1 @]";	
			self::checkSyntax($src, "Block with array index and single limit");
		}
				
		public function testBlockWithFunctionCallNoParams() {
			$src = "[@ include foo if foo() @]";	
			self::checkSyntax($src, "Block with function call and no params");
		}

		public function testBlockWithFunctionOneSimpleParam() {
			$src = "[@ include foo if foo(1) @]";	
			self::checkSyntax($src, "Block with function call with one simple param");
		}			

		public function testBlockWithFunctionExpessionParam() {
			$src = "[@ include foo if foo((x * 3) + 4 / 5) @]";	
			self::checkSyntax($src, "Block with function call with expression param");
		}				
		
		public function testBlockWithDecorator() {
			$src = "[@ include foo do memcache({for: '2 days', port: 12000, password: 'test'}) @]";	
			self::checkSyntax($src, "Block with decorator");
		}

		public function testBlockWithDecorators() {
			$src = "[@ include foo do gzip(), memcache({for: '2 days', port: 12000, password: 'test'}) @]";	
			self::checkSyntax($src, "Block with decorators");
		}	

		public function testBlockWithNamespacedFunction() {
			$src = "[@ include foo if x\\x(2,-2) @]";	
			self::checkSyntax($src, "Block with namespaced function");
		}	
		
		public function testBlockWithOutputTemplateWithEmptyBraces() {
			$src = "[@ include foo if `foo{}bar` @]";	
			self::checkSyntax($src, "Block with output template with empty braces");
		}
		
		public function testBlockWithOutputTemplateWithBraces() {
			$src = "[@ include foo if `foo{bar}` @]";	
			self::checkSyntax($src, "Block with output template with braces");
		}
		
		public function testBlockWithOutputTemplateWithStartGenerators() {
			$src = "[@ include foo if `foo{@ bar @}` @]";	
			self::checkSyntax($src, "Block with output template with start generators");
		}
		
		public function testBlockWithStrictOutputTemplateWithBraces() {
			$src = "[@ include foo if ~foo{bar}~ @]";	
			self::checkSyntax($src, "Block with strict output template with braces");
		}
					
		public function testBlockWithStrictOutputTemplateWithStartGenerators() {
			$src = "[@ include foo if ~foo{@ bar @}~ @]";	
			self::checkSyntax($src, "Block with strict output template with start generators");
		}
		
		public function testBlockWithGeneratorHead() {
			$src = "[@ include foo if `foo {bar ? baz}` @]";	
			self::checkSyntax($src, "Block with generator head");
		}
		
		public function testBlockWithGeneratorSingleConditional() {
			$src = "[@ include foo if `foo {bar if bam ? baz}` @]";	
			self::checkSyntax($src, "Block with generator single conditional");
		}

		public function testBlockWithGeneratorSingleConditionalExpression() {
			$src = "[@ include foo if `foo {bar if bam || boom ? baz}` @]";	
			self::checkSyntax($src, "Block with generator single conditional expression");
		}		

		public function testBlockWithGeneratorMultipleConditional() {
			$src = "[@ include foo if `foo {bar if bam ? baz if boom ? floom if whim ? wham else kaboom}` @]";	
			self::checkSyntax($src, "Block with generator mulitple conditionals");
		}
		
		public function testBlockWithGeneratorSingleConditionalAndFilter() {
			$src = "[@ include foo if `foo {bar if bam ? baz:boom else foom}` @]";	
			self::checkSyntax($src, "Block with generator single conditional and filter");
		}

		public function testBlockWithGeneratorComplexConditionals() {
			$src = <<<SRC
[@ include foo if `foo {@ user 
	if type == 1 ? (foo 
		if a == 1 ? bar
		else if b == 3 ? baz
	)
	else if type == 2 ? (
		if c == 4 ? `user.type == 2 && user.c == 4`
		else if d == 6 ? "user.type == 2 && user.d == 6"
	)
	else if type == 3 ? `bam`
	else `flam`
@}` @]
SRC;
			self::checkSyntax($src, "Block with generator complex conditionals");
		}		
		
	}