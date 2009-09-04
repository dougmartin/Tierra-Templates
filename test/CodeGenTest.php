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
	require_once dirname(__FILE__) . "/../src/TierraTemplateCodeGenerator.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateOptimizer.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class CodeGenTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkEmit($src, $testSrc, $message, $dump=false) {
			$ast = TestHelpers::GetParsedAST($src);
			$optimizedAST = TierraTemplateOptimizer::optimize($ast);
			$codeGenerator = new TierraTemplateCodeGenerator();
			$emittedSrc = $codeGenerator->emit($optimizedAST);
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
			$this->assertEquals($testSrc, $emittedSrc, $message);
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
			self::checkEmit("[@ start foo @] bar [@ end foo @]", "<?php if (!\$this->__request->echoBlock('foo')) { ?> bar <?php }", "Block in parent");
		}
					
		public function testBlockInParentWithSpaces() {
			self::checkEmit(" [@ start foo @] bar [@ end foo @] ", " <?php if (!\$this->__request->echoBlock('foo')) { ?> bar <?php } ?> ", "Block in parent with spaces");
		}
					
		public function testBlocksInBlocksInParent() {
			self::checkEmit("[@ start foo @] bar [@ start baz @] bam [@ end baz @] [@ end foo @]", "<?php if (!\$this->__request->echoBlock('foo')) { ?> bar <?php if (!\$this->__request->echoBlock('baz')) { ?> bam <?php } ?> <?php }", "Block in parent");
		}
		
		public function testBlockInChild() {
			self::checkEmit("[@ extends 'bam' @] [@ start foo @] bar [@ end foo @]", "<?php if (!\$this->__request->haveBlock('foo')) { ob_start(); ?> bar <?php \$this->__request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Block in child");
		}			

		public function testBlocksInBlocksInChild() {
			self::checkEmit("[@ extends 'bam' @] [@ start foo @] bar [@ start baz @] bam [@ end baz @] [@ end foo @]", "<?php if (!\$this->__request->haveBlock('foo')) { ob_start(); ?> bar <?php if (!\$this->__request->echoBlock('baz')) { ob_start(); ?> bam <?php \$this->__request->setBlock('baz', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('baz'); } ?> <?php \$this->__request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Blocks in block in child");
		}
		
		public function testAppendInParent() {
			self::checkEmit("[@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->appendBlock('foo', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('foo');", "Append in parent");
		}
				
		public function testAppendInChild() {
			self::checkEmit("[@ extends 'bam' @] [@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->appendBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testPrependInParent() {
			self::checkEmit("[@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->prependBlock('foo', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('foo');", "Append in parent");
		}
				
		public function testPrependInChild() {
			self::checkEmit("[@ extends 'bam' @] [@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->prependBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testBlockWithFunctionCallNoParams() {
			self::checkEmit("[@ include 'foo' if foo() @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array())) { \$this->includeTemplate('foo'); }", "Block with function call and no params");
		}
			
		public function testBlockWithFunctionCallOneParam() {
			self::checkEmit("[@ include 'foo' if foo(2) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(2))) { \$this->includeTemplate('foo'); }", "Block with function call with one param");
		}

		public function testBlockWithFunctionCallVarParam() {
			self::checkEmit("[@ include 'foo' if foo(bar) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(\$this->__runtime->identifier('bar')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var param");
		}

		public function testBlockWithFunctionCallVarWithDotParam() {
			self::checkEmit("[@ include 'foo' if foo(bar.bam) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('bar'), 'bam')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var with dot param");
		}		
		
		public function testBlockWithOperator() {
			self::checkEmit("[@ include 'foo' if x < 3 @]", "<?php if (\$this->__runtime->identifier('x') < 3) { \$this->includeTemplate('foo'); }", "Block with operator");
		}

		public function testBlockWithAssign() {
			self::checkEmit("[@ include 'foo' if x = 1 < 3 @]", "<?php if (\$this->__request->setVar('x', 1 < 3)) { \$this->includeTemplate('foo'); }", "Block with assignment");
		}

		public function testBlockWithIndex() {
			self::checkEmit("[@ include 'foo' if x[1] @]", "<?php if (\$this->__runtime->attr(\$this->__runtime->identifier('x'), 1)) { \$this->includeTemplate('foo'); }", "Block with index");
		}			
		
		public function testBlockWithArrayIndexAndSingleLimit() {
			self::checkEmit("[@ include 'foo' if x[3]:1 @]", "<?php if (\$this->__runtime->limit(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}
				
		public function testBlockWithArrayIndexAndFullLimit() {
			self::checkEmit("[@ include 'foo' if x[3]:1,-3 @]", "<?php if (\$this->__runtime->limit(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1, -3)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}

		public function testBlockWithArrayIndexAndFilter() {
			self::checkEmit("[@ include 'foo' if x[3]:bar() @]", "<?php if (\$this->__runtime->call('bar', 'bar on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3)))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}
					
		public function testBlockWithArrayIndexAndFilters() {
			self::checkEmit("[@ include 'foo' if x[3]:bar(1,2):boom(3):bam() @]", "<?php if (\$this->__runtime->call('bam', 'bam on line 1', array(\$this->__runtime->call('boom', 'boom on line 1', array(\$this->__runtime->call('bar', 'bar on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1, 2)), 3))))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}

		public function testBlockWithBuiltInFilter() {
			self::checkEmit("[@ include 'foo' if x:substr(2,-2) @]", "<?php if (call_user_func_array('substr', array(\$this->__runtime->identifier('x'), 2, -2))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testBlockWithNamespacedFilter() {
			self::checkEmit("[@ include 'foo' if x:bar\\bam() @]", "<?php if (\$this->__runtime->externalCall('bam', '', 'bar', '', 'bar\\\\bam on line 1', array(\$this->__runtime->identifier('x')))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testPageBlockWithDecorator() {
			$code = "<?php if (\$this->__request->__startDecorator('append', 'nocache')) { header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); } ?> foo<?php \$this->__request->__endDecorator();";
			$src = "[@ page do nocache({foo: 1}, 2, 3) @] foo";	
			self::checkEmit($src, $code, "Block with decorator");
		}			

		public function testPageBlockWithBadDecorator() {
			$this->setExpectedException('TierraTemplateException');
			$src = "[@ page do nocache(bar()) @] foo";	
			self::checkEmit($src, "", "Block with bad decorator");
		}

		public function testPageBlockWitNoCacheDecorator() {
			$code = "<?php if (\$this->__request->__startDecorator('append', 'nocache')) { header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); } ?> foo<?php \$this->__request->__endDecorator();";
			$src = "[@ page do nocache() @] foo";	
			self::checkEmit($src, $code, "Block with nocache decorator");
		}

		public function testSimpleConditerator() {
			$src = "{@ foo @}";
			self::checkEmit($src, "<?php \$this->__request->output(\$this->__runtime->identifier('foo'));", "Simple conditerator");
		}

		public function testMultiExpressionConditerator() {
			$src = "{@ foo; bar @}";
			self::checkEmit($src, "<?php \$this->__runtime->identifier('foo'); \$this->__request->output(\$this->__runtime->identifier('bar'));", "Simple conditerator with multiple expressions");
		}
		
		public function testSimpleConditeratorWithOutput() {
			$src = "{@ foo ? bar @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('foo'))) { do { \$this->__request->output(\$this->__runtime->identifier('bar')); } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator();", "Simple conditerator with output");
		}
		
		public function testConditeratorWithOneOutputTemplate() {
			$src = "{@ foo ? `bar` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar'; } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator();", "Conditerator with one output template");
		}
		
		public function testConditeratorWithOneOutputTemplateWithSimpleConditerator() {
			$src = "{@ foo ? `bar {baz} boom` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar '; \$this->__request->output(\$this->__runtime->identifier('baz')); echo ' boom'; } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator();", "Conditerator with one output template with simple conditerator");
		}			

		public function testConditeratorWithOneOutputTemplateWithConditerator() {
			$src = "{@ foo ? `bar {baz ? bam} boom` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar '; if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('baz'))) { do { \$this->__request->output(\$this->__runtime->identifier('bam')); } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator(); echo ' boom'; } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator();", "Conditerator with one output template with conditerator");
		}		
		
		public function testConditeratorWithTwoOutputTemplates() {
			$src = "{@ foo ? `bar` `baz` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startConditerator(\$this->__runtime->identifier('foo'))) { echo 'bar'; do { echo 'baz'; } while (\$this->__runtime->loop()); } \$this->__runtime->endConditerator();", "Conditerator with one output template");
		}
		
		public function testConditeratorWithTemplateAssignment() {
			$src = "{@ foo = `bar {baz ? bam} boom` @}";
			self::checkEmit($src, "<?php if (!function_exists('otf_96ee959a0a4f10f5f569fdddcd090e4fe9982c9e')) { function otf_96ee959a0a4f10f5f569fdddcd090e4fe9982c9e(\$__template) { ob_start(); echo 'bar '; if (\$__template->__runtime->startConditerator(\$__template->__runtime->identifier('baz'))) { do { \$__template->__request->output(\$__template->__runtime->identifier('bam')); } while (\$__template->__runtime->loop()); } \$__template->__runtime->endConditerator(); echo ' boom'; \$__output = ob_get_contents(); ob_end_clean(); return \$__output;} }; \$this->__request->output(\$this->__request->setVar('foo', otf_96ee959a0a4f10f5f569fdddcd090e4fe9982c9e(\$this)));", "Conditerator with template assignment");
		}
		
		public function testConditeratorWithNoOutput() {
			$src = "{@ foo ? @}";
			self::checkEmit($src, "<?php \$this->__runtime->identifier('foo');", "Conditerator with no output");
		}

		public function testConditeratorWithNoOutputAndAssignment() {
			$src = "{@ foo = 1 ? @}";
			self::checkEmit($src, "<?php \$this->__request->setVar('foo', 1);", "Conditerator with no output and assignment");
		}
		
		public function testMultipleConditionals() {
			$src = "{@ foo if bar == 1 ? baz else if bam == 2 ? boom else foom @}";
			self::checkEmit($src, "<?php \$this->__runtime->startConditerator(\$this->__runtime->identifier('foo')); if (\$this->__runtime->identifier('bar') == 1) { do { \$this->__request->output(\$this->__runtime->identifier('baz')); } while (\$this->__runtime->loop()); } else if (\$this->__runtime->identifier('bam') == 2) { do { \$this->__request->output(\$this->__runtime->identifier('boom')); } while (\$this->__runtime->loop()); } else { \$this->__request->output(\$this->__runtime->identifier('foom')); } \$this->__runtime->endConditerator();", "Conditerator with multiple conditionals");
		}
		
		public function testEmptyHeadConditerator() {
			$src = "{@ if foo ? bar @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->identifier('foo')) { \$this->__request->output(\$this->__runtime->identifier('bar')); }", "Conditerator with empty head");
		}
		
		public function testOutputTemplateWithEscapedConditerator() {
			$src = "{@ `\\{foo}\\{@ bar @}` @}";
			self::checkEmit($src, "<?php echo '{foo}{@ bar @}';", "Escaped output template");
		}		
		
		public function testStrictOutputTemplateWithEscapedConditerator() {
			$src = "{@ ~\\{@ bar @}~ @}";
			self::checkEmit($src, "<?php echo '{@ bar @}';", "Escaped output template");
		}

		public function testExternalFilterCall() {
			$src = "{@ 'test':foo::bar @}";
			self::checkEmit($src, "<?php echo \$this->__runtime->externalCall('bar', 'foo', '', '', 'foo::bar on line 1', array('test'));", "External call filter");
		}

		public function testSimpleAttributeAssignment() {
			$src = "{@ foo[baz + 1 ].bar = baz @}";
			self::checkEmit($src, "<?php \$this->__request->output(\$this->__request->setVar('foo', \$this->__runtime->identifier('baz'), array(\$this->__runtime->identifier('baz') + 1, 'bar')));", "Simple attribute assignment");
		}
		
		public function testAttributeAssignmentAndEcho() {
			$src = "{@ baz = 3; foo[baz + 1 ].bar = baz; foo[baz + 1 ].bar @}";
			self::checkEmit($src, "<?php \$this->__request->setVar('baz', 3); \$this->__request->setVar('foo', \$this->__runtime->identifier('baz'), array(\$this->__runtime->identifier('baz') + 1, 'bar')); \$this->__request->output(\$this->__runtime->attr(\$this->__runtime->attr(\$this->__runtime->identifier('foo'), \$this->__runtime->identifier('baz') + 1), 'bar'));", "Simple attribute assignment and echo");
		}
		
		public function testStatementTag() {
			self::checkEmit("<@ foo = 1 @>", "<?php \$this->__request->setVar('foo', 1);", "Test statement tag");
		}
		
		public function testExtendsWithIdentifier() {
			self::checkEmit("[@ extends foo @]", "<?php \$this->includeTemplate(\$this->__runtime->identifier('foo'));", "Extends with identifier");
		}
		
		public function testExtendsWithCall() {
			self::checkEmit("[@ extends foo() @]", "<?php \$this->includeTemplate(\$this->__runtime->call('foo', 'foo on line 1', array()));", "Extends with call");
		}
		
		public function testEchoBlock() {
			self::checkEmit("[@ echo foo @]", "<?php \$this->__request->echoBlock('foo');", "Echo block");
		}
		
		public function testEchoBlockWithConditional() {
			self::checkEmit("[@ echo foo if bar @]", "<?php if (\$this->__runtime->identifier('bar')) { \$this->__request->echoBlock('foo'); }", "Echo block with conditional");
		}
				
		public function testEchoBlockWithConditionalAndDecorator() {
			self::checkEmit("[@ echo foo if bar do testwrapper(1) @]", "<?php if (\$this->__runtime->identifier('bar')) { if (\$this->__request->__startDecorator('append', 'testwrapper', 'foo')) echo '/* start testwrapper(1) */'; \$this->__request->echoBlock('foo'); if (\$this->__request->__endDecorator()) echo '/* end testwrapper(1) */'; }", "Echo block with conditional and decorator");  
		}
		
		public function testEscape() {
			self::checkEmit("{@ foo = '<test>'; foo @}", "<?php \$this->__request->setVar('foo', '<test>'); \$this->__request->output(\$this->__runtime->identifier('foo'));", "Test escape");
		}
		
		public function testNoEscapeFilter() {
			self::checkEmit("{@ foo = '<test>'; foo:noescape @}", "<?php \$this->__request->setVar('foo', '<test>'); \$this->__request->output(\$this->__request->noescape(\$this->__runtime->identifier('foo')));", "Test no scape filter");
		}

		public function testCallWithRequest() {
			self::checkEmit("{@ foo(request) @}", "<?php \$this->__request->output(\$this->__runtime->call('foo', 'foo on line 1', array(\$this->__request)));", "Call with request");
		}
		
		public function testAssignRequestAttr() {
			self::checkEmit("{@ request.foo = 1; foo @}", "<?php \$this->__request->setVar('foo', 1); \$this->__request->output(\$this->__runtime->identifier('foo'));", "Assign request attribute");
		}	
	}
