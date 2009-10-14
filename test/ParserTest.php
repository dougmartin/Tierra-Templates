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

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class ParserTest extends PHPUnit_Framework_TestCase {
		
		protected $options;
		
		protected function setUp() {
			$this->options = array();
		}
		
		protected function tearDown() {
		}
		
		public function checkSyntax($src, $message, $dump=false) {
			$actualAst = false;
			try {
				$isValid = true;
				$parser = new TierraTemplateParser($this->options, $src);
				$actualAst = $parser->parse();
			} 
			catch (TierraTemplateParserException $e) {
				$isValid = false;
			}
			if ($dump) {
				echo "\n{$message}\n";
				echo "src:\n";
				var_dump($src);
				echo "ast:\n";
				var_dump($actualAst);
			}
			$this->assertTrue($isValid, $message);
		}
		
		public function checkAST($src, $ast, $message, $dump = false) {
			$actualAst = false;
			try {
				$parser = new TierraTemplateParser($this->options, $src);
				$actualAst = $parser->parse();
			} 
			catch (TierraTemplateParserException $e) {}
			if ($dump) {
				echo "\n{$message}\n";
				echo "parser ast:\n";
				var_dump($parser->getAST());
				echo "passed ast:\n";
				var_dump($ast);
			}
			$this->assertEquals($actualAst, $ast, $message);
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
			$this->setExpectedException('TierraTemplateException');
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
			$this->setExpectedException('TierraTemplateException');
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
			$src = "[@ start foo do memcache({for: '2 days', port: 12000, password: 'test'}) @][@ end foo @]";	
			self::checkSyntax($src, "Block with decorator");
		}

		public function testBlockWithDecorators() {
			$src = "[@ start foo do gzip(), memcache({for: '2 days', port: 12000, password: 'test'}) @][@ end foo @]";	
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
		
		public function testBlockWithOutputTemplateWithStartConditerators() {
			$src = "[@ include foo if `foo{@ bar @}` @]";	
			self::checkSyntax($src, "Block with output template with start conditerators");
		}
		
		public function testBlockWithStrictOutputTemplateWithBraces() {
			$src = "[@ include foo if ~foo{bar}~ @]";	
			self::checkSyntax($src, "Block with strict output template with braces");
		}
					
		public function testBlockWithStrictOutputTemplateWithStartConditerators() {
			$src = "[@ include foo if ~foo{@ bar @}~ @]";	
			self::checkSyntax($src, "Block with strict output template with start conditerators");
		}
		
		public function testBlockWithConditeratorHead() {
			$src = "[@ include foo if `foo {bar ? baz}` @]";	
			self::checkSyntax($src, "Block with conditerator head");
		}
		
		public function testBlockWithConditeratorSingleConditional() {
			$src = "[@ include foo if `foo {bar if bam ? baz}` @]";	
			self::checkSyntax($src, "Block with conditerator single conditional");
		}

		public function testBlockWithConditeratorSingleConditionalExpression() {
			$src = "[@ include foo if `foo {bar if bam || boom ? baz}` @]";	
			self::checkSyntax($src, "Block with conditerator single conditional expression");
		}		

		public function testBlockWithConditeratorMultipleConditional() {
			$src = "[@ include foo if `foo {bar if bam ? baz if boom ? floom if whim ? wham else kaboom}` @]";	
			self::checkSyntax($src, "Block with conditerator mulitple conditionals");
		}
		
		public function testBlockWithConditeratorSingleConditionalAndFilter() {
			$src = "[@ include foo if `foo {bar if bam ? baz:boom else foom}` @]";	
			self::checkSyntax($src, "Block with conditerator single conditional and filter");
		}

		public function testBlockWithConditeratorComplexConditionals() {
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
			self::checkSyntax($src, "Block with conditerator complex conditionals");
		}		
		
		public function testBareConditerator() {
			$src = "{@ foo @}";	
			self::checkSyntax($src, "Bare conditerator");
		}
		
		public function testConditeratorDoubleParens() {
			$src = "{@ foo ? (bar ? baz) (bam ? boom else foom) kaboom @}";	
			self::checkSyntax($src, "Conditerator with double parens");
		}
		
		public function testConditeratorMultipleParens() {
			$src = "{@ foo ? ((((bar)))) @}";	
			self::checkSyntax($src, "Conditerator with multiple parens");
		}
		
		public function testConditeratorExpressionParens() {
			$src = "{@ (foo + bar) - baz > 0 ? bam @}";	
			self::checkSyntax($src, "Conditerator with expression parens");
		}
		
		public function testConditeratorWithMultipleOutputTemplates() {
			$src = <<<SRC
{@ users ?
	`<p>{name}`
	logins ? `<ul>` `<li>{date:dateformat("U")}</li>` `</ul>`
	`</p>`
@}
SRC;
			self::checkSyntax($src, "Conditerator with multiple output templates");
		}
		
		public function testConditeratorWithScript() {
			$src = <<<SRC
{@ foo ?
	~<script>
		function bar(baz) {
			return baz > {@ count(bam) @} ? " yes " : " no ";
		}
	</script>~
	`<script>document.write(bar({\$}));</script>`
@}
SRC;

			self::checkSyntax($src, "Conditerator with script");
		}		
		
		public function testConditeratorWithFilter() {
			$src = "{@ foo:1,2 @}";	
			self::checkSyntax($src, "Conditerator with filter");
		}
		
		public function testConditeratorWithMultipleStatements() {
			$src = "{@ foo; bar ? bam @}";	
			self::checkSyntax($src, "Conditerator with multiple statements");
		}		
		
		public function testExternalFilterCall() {
			$src = "{@ 'test':foo::bar @}";
			self::checkSyntax($src, "External call filter");
		}
		
		public function testCodeBlock() {
			$src = "<@ foo @>";
			self::checkSyntax($src, "Code block");
		}
		
		public function testRawInclude() {
			$src = "[@ rawinclude 'foo' @]";
			self::checkSyntax($src, "Raw include");
		}
				

	}