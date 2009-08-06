<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateCodeGenerator.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class CodeGenTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkEmit($src, $testSrc, $message, $dump=false) {
			$ast = TestHelpers::GetParsedAST($src);
			$optimizedAST = TierraTemplateOptimizer::optimize($ast);
			$emittedSrc = TierraTemplateCodeGenerator::emit($optimizedAST);
			if ($dump) {
				echo "\n{$message}\n";
				echo "src:\n";
				var_dump($src);
				echo "ast:\n";
				var_dump($ast);
				echo "optmized ast:\n";
				var_dump($optimizedAST);
				echo "emittedSrc:\n";
				var_dump($emittedSrc);
				echo "testSrc:\n";
				var_dump($testSrc);
			}
			$this->assertEquals($emittedSrc, $testSrc, $message);
		}
		
		public function testEmpty() {
			self::checkEmit("", "", "Test Empty");
		}		
		
		public function testSingleSpace() {
			$src = " ";
			self::checkEmit($src, $src, "Single space");
		}
			
		public function testMultipleSpaces() {
			$src = "     ";
			self::checkEmit($src, $src, "Multiple spaces");
		}	
		
		public function testSingleTab() {
			$src = "	";
			self::checkEmit($src, $src, "Single tab");
		}
			
		public function testMultipleTabs() {
			$src = "			";
			self::checkEmit($src, $src, "Multiple tabs");
		}
			
		public function testMultipleSpacesAndTabs() {
			$src = "  		  		  ";
			self::checkEmit($src, $src, "Multiple spaces and tabs");
		}	
		
		public function testAllHTML() {
			$src = "<html><head><title>test</title></head><body>test</body></html>";
			self::checkEmit($src, $src, "All HTML");
		}
			
		public function testMultiLineAllHTML() {
			$src = <<<HTML
				<html>
					<head>
						<title>test</title>
					</head>
					<body>
						test
					</body>
				</html>
HTML;
			self::checkEmit($src, $src, "Multiline HTML");
		}		
		
		public function testCommentOnly() {
			self::checkEmit("[# this is a comment #]", "", "Comment Only");
		}		
		
		public function testCommentWithSpaces() {
			self::checkEmit(" [# this is a comment #] ", "  ", "Comment with spaces");
		}

		public function testAdjoiningCommentWithSpaces() {
			self::checkEmit(" [# this is a comment #][# this is also a comment #] ", "  ", "Adjoning comment with spaces");
		}

		public function testAdjoiningCommentWithInteriorSpaces() {
			self::checkEmit(" [# this is a comment #] [# this is also a comment #] ", "   ", "Adjoining comment with interior spaces");
		}

		public function testBlockInParent() {
			self::checkEmit("[@ start foo @] bar [@ end foo @]", "<?php if (!\$this->request->echoBlock('foo')) { ?> bar <?php }", "Block in parent");
		}
					
		public function testBlockInParentWithSpaces() {
			self::checkEmit(" [@ start foo @] bar [@ end foo @] ", " <?php if (!\$this->request->echoBlock('foo')) { ?> bar <?php } ?> ", "Block in parent with spaces");
		}
					
		public function testBlocksInBlocksInParent() {
			self::checkEmit("[@ start foo @] bar [@ start baz @] bam [@ end baz @] [@ end foo @]", "<?php if (!\$this->request->echoBlock('foo')) { ?> bar <?php if (!\$this->request->echoBlock('baz')) { ?> bam <?php } ?> <?php }", "Block in parent");
		}
		
		public function testBlockInChild() {
			self::checkEmit("[@ extends bam @] [@ start foo @] bar [@ end foo @]", "<?php if (!\$this->request->echoBlock('foo')) { ob_start(); ?> bar <?php \$this->request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Block in child");
		}			

		public function testBlocksInBlocksInChild() {
			self::checkEmit("[@ extends bam @] [@ start foo @] bar [@ start baz @] bam [@ end baz @] [@ end foo @]", "<?php if (!\$this->request->echoBlock('foo')) { ob_start(); ?> bar <?php if (!\$this->request->echoBlock('baz')) { ob_start(); ?> bam <?php \$this->request->setBlock('baz', ob_get_contents()); ob_end_clean(); \$this->request->echoBlock('baz'); } ?> <?php \$this->request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Blocks in block in child");
		}
		
		public function testAppendInParent() {
			self::checkEmit("[@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->request->appendBlock('foo', ob_get_contents()); ob_end_clean();", "Append in parent");
		}
				
		public function testAppendInChild() {
			self::checkEmit("[@ extends bam @] [@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->request->appendBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testPrependInParent() {
			self::checkEmit("[@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->request->prependBlock('foo', ob_get_contents()); ob_end_clean();", "Append in parent");
		}
				
		public function testPrependInChild() {
			self::checkEmit("[@ extends bam @] [@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->request->prependBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testBlockWithFunctionCallNoParams() {
			self::checkEmit("[@ include foo if foo() @]", "<?php if (\$this->runtime->call('foo', array())) { \$this->includeTemplate('foo'); }", "Block with function call and no params");
		}
			
		public function testBlockWithFunctionCallOneParam() {
			self::checkEmit("[@ include foo if foo(2) @]", "<?php if (\$this->runtime->call('foo', array(2))) { \$this->includeTemplate('foo'); }", "Block with function call with one param");
		}

		public function testBlockWithFunctionCallVarParam() {
			self::checkEmit("[@ include foo if foo(bar) @]", "<?php if (\$this->runtime->call('foo', array(\$this->runtime->identifier('bar')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var param");
		}

		public function testBlockWithFunctionCallVarWithDotParam() {
			self::checkEmit("[@ include foo if foo(bar.bam) @]", "<?php if (\$this->runtime->call('foo', array(\$this->runtime->attr('bar', 'bam')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var with dot param");
		}		
		
		public function testBlockWithOperator() {
			self::checkEmit("[@ include foo if x < 3 @]", "<?php if (\$this->runtime->identifier('x') < 3) { \$this->includeTemplate('foo'); }", "Block with operator");
		}

		public function testBlockWithAssign() {
			self::checkEmit("[@ include foo if x = 1 < 3 @]", "<?php if (\$this->runtime->assign('x', 1 < 3)) { \$this->includeTemplate('foo'); }", "Block with assignment");
		}

		public function testBlockWithIndex() {
			self::checkEmit("[@ include foo if x[1] @]", "<?php if (\$this->runtime->attr('x', 1)) { \$this->includeTemplate('foo'); }", "Block with index");
		}			
		
		public function testBlockWithArrayIndexAndSingleLimit() {
			self::checkEmit("[@ include foo if x[3]:1 @]", "<?php if (\$this->runtime->limit(\$this->runtime->attr('x', 3), 1)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}
				
		public function testBlockWithArrayIndexAndFullLimit() {
			self::checkEmit("[@ include foo if x[3]:1,-3 @]", "<?php if (\$this->runtime->limit(\$this->runtime->attr('x', 3), 1, -3)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}

		public function testBlockWithArrayIndexAndFilter() {
			self::checkEmit("[@ include foo if x[3]:bar() @]", "<?php if (\$this->runtime->call('bar', array(\$this->runtime->attr('x', 3)))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}
					
		public function testBlockWithArrayIndexAndFilters() {
			self::checkEmit("[@ include foo if x[3]:bar(1,2):boom(3):bam() @]", "<?php if (\$this->runtime->call('bam', array(\$this->runtime->call('boom', array(\$this->runtime->call('bar', array(\$this->runtime->attr('x', 3), 1, 2)), 3))))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}

		public function testBlockWithBuiltInFilter() {
			self::checkEmit("[@ include foo if x:substr(2,-2) @]", "<?php if (call_user_func_array('substr', array(\$this->runtime->identifier('x'), 2, -2))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testBlockWithNamespacedFilter() {
			self::checkEmit("[@ include foo if x:bar\\bam() @]", "<?php if (\$this->runtime->call('bar\\\\bam', array(\$this->runtime->identifier('x')))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testPageBlockWithDecorator() {
			$code = <<<CODE
<?php header("Expires: Sun, 03 Oct 1971 00:00:00 GMT");
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache"); ?> foo
CODE;
			$src = "[@ page do nocache({foo: 1}, 2, 3) @] foo";	
			self::checkEmit($src, $code, "Block with decorator");
		}			
		
	}
	