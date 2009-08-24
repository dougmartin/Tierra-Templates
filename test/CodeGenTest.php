<?php

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
			self::checkEmit("[@ extends bam @] [@ start foo @] bar [@ end foo @]", "<?php if (!\$this->__request->echoBlock('foo')) { ob_start(); ?> bar <?php \$this->__request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Block in child");
		}			

		public function testBlocksInBlocksInChild() {
			self::checkEmit("[@ extends bam @] [@ start foo @] bar [@ start baz @] bam [@ end baz @] [@ end foo @]", "<?php if (!\$this->__request->echoBlock('foo')) { ob_start(); ?> bar <?php if (!\$this->__request->echoBlock('baz')) { ob_start(); ?> bam <?php \$this->__request->setBlock('baz', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('baz'); } ?> <?php \$this->__request->setBlock('foo', ob_get_contents()); ob_end_clean(); } \$this->includeTemplate('bam');", "Blocks in block in child");
		}
		
		public function testAppendInParent() {
			self::checkEmit("[@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->appendBlock('foo', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('foo');", "Append in parent");
		}
				
		public function testAppendInChild() {
			self::checkEmit("[@ extends bam @] [@ append foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->appendBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testPrependInParent() {
			self::checkEmit("[@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->prependBlock('foo', ob_get_contents()); ob_end_clean(); \$this->__request->echoBlock('foo');", "Append in parent");
		}
				
		public function testPrependInChild() {
			self::checkEmit("[@ extends bam @] [@ prepend foo @] bar [@ end foo @]", "<?php ob_start(); ?> bar <?php \$this->__request->prependBlock('foo', ob_get_contents()); ob_end_clean(); \$this->includeTemplate('bam');", "Append in child");
		}

		public function testBlockWithFunctionCallNoParams() {
			self::checkEmit("[@ include foo if foo() @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array())) { \$this->includeTemplate('foo'); }", "Block with function call and no params");
		}
			
		public function testBlockWithFunctionCallOneParam() {
			self::checkEmit("[@ include foo if foo(2) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(2))) { \$this->includeTemplate('foo'); }", "Block with function call with one param");
		}

		public function testBlockWithFunctionCallVarParam() {
			self::checkEmit("[@ include foo if foo(bar) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(\$this->__runtime->identifier('bar')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var param");
		}

		public function testBlockWithFunctionCallVarWithDotParam() {
			self::checkEmit("[@ include foo if foo(bar.bam) @]", "<?php if (\$this->__runtime->call('foo', 'foo on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('bar'), 'bam')))) { \$this->includeTemplate('foo'); }", "Block with function call with one var with dot param");
		}		
		
		public function testBlockWithOperator() {
			self::checkEmit("[@ include foo if x < 3 @]", "<?php if (\$this->__runtime->identifier('x') < 3) { \$this->includeTemplate('foo'); }", "Block with operator");
		}

		public function testBlockWithAssign() {
			self::checkEmit("[@ include foo if x = 1 < 3 @]", "<?php if (\$this->__runtime->assign('x', 1 < 3)) { \$this->includeTemplate('foo'); }", "Block with assignment");
		}

		public function testBlockWithIndex() {
			self::checkEmit("[@ include foo if x[1] @]", "<?php if (\$this->__runtime->attr(\$this->__runtime->identifier('x'), 1)) { \$this->includeTemplate('foo'); }", "Block with index");
		}			
		
		public function testBlockWithArrayIndexAndSingleLimit() {
			self::checkEmit("[@ include foo if x[3]:1 @]", "<?php if (\$this->__runtime->limit(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}
				
		public function testBlockWithArrayIndexAndFullLimit() {
			self::checkEmit("[@ include foo if x[3]:1,-3 @]", "<?php if (\$this->__runtime->limit(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1, -3)) { \$this->includeTemplate('foo'); }", "Block with array index and single limit");
		}

		public function testBlockWithArrayIndexAndFilter() {
			self::checkEmit("[@ include foo if x[3]:bar() @]", "<?php if (\$this->__runtime->call('bar', 'bar on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3)))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}
					
		public function testBlockWithArrayIndexAndFilters() {
			self::checkEmit("[@ include foo if x[3]:bar(1,2):boom(3):bam() @]", "<?php if (\$this->__runtime->call('bam', 'bam on line 1', array(\$this->__runtime->call('boom', 'boom on line 1', array(\$this->__runtime->call('bar', 'bar on line 1', array(\$this->__runtime->attr(\$this->__runtime->identifier('x'), 3), 1, 2)), 3))))) { \$this->includeTemplate('foo'); }", "Block with array index and filter");
		}

		public function testBlockWithBuiltInFilter() {
			self::checkEmit("[@ include foo if x:substr(2,-2) @]", "<?php if (call_user_func_array('substr', array(\$this->__runtime->identifier('x'), 2, -2))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testBlockWithNamespacedFilter() {
			self::checkEmit("[@ include foo if x:bar\\bam() @]", "<?php if (\$this->__runtime->externalCall('bam', '', 'bar', '', 'bar\\\\bam on line 1', array(\$this->__runtime->identifier('x')))) { \$this->includeTemplate('foo'); }", "Block with built in filter");
		}
		
		public function testPageBlockWithDecorator() {
			$code = "<?php header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); ?> foo";
			$src = "[@ page do nocache({foo: 1}, 2, 3) @] foo";	
			self::checkEmit($src, $code, "Block with decorator");
		}			

		public function testPageBlockWithBadDecorator() {
			$this->setExpectedException('TierraTemplateException');
			$src = "[@ page do nocache(bar()) @] foo";	
			self::checkEmit($src, "", "Block with bad decorator");
		}

		public function testPageBlockWithGzipDecorator() {
			$src = "[@ page do gzip() @] foo";	
			self::checkEmit($src, "<?php ob_start('ob_gzhandler'); ?> foo", "Page block with gzip decorator");
		}
		
		public function testNormalBlockWithGzipDecorator() {
			$src = "[@ start do gzip() @] foo [@ end @]";	
			self::checkEmit($src, " foo ", "Normal block with gzip decorator");
		}
		
		public function testPageBlockWithGzipAndNoCacheDecorator() {
			$code = "<?php ob_start('ob_gzhandler'); header('Expires: Sun, 03 Oct 1971 00:00:00 GMT'); header('Cache-Control: no-store, no-cache, must-revalidate'); header('Cache-Control: post-check=0, pre-check=0', false); header('Pragma: no-cache'); ?> foo";
			$src = "[@ page do gzip(), nocache() @] foo";	
			self::checkEmit($src, $code, "Block with gzip and nocache decorators");
		}

		public function testBlockWithWrapperDecorator() {
			$src = "[@ start do testWrapper('1') @] foo [@ end @]";
			self::checkEmit($src, "<?php if (1) { ?> foo <?php } /* end if (1) */", "Block with test wrapper decorators");
		}
			
		public function testBlockWithDoubleWrapperDecorator() {
			$src = "[@ start do testWrapper('1'), testWrapper('2') @] foo [@ end @]";
			self::checkEmit($src, "<?php if (1) { if (2) { ?> foo <?php } /* end if (2) */ } /* end if (1) */", "Block with two test wrapper decorators");
		}
		
		public function testSimpleGenerator() {
			$src = "{@ foo @}";
			self::checkEmit($src, "<?php echo \$this->__runtime->identifier('foo');", "Simple generator");
		}

		public function testMultiExpressionGenerator() {
			$src = "{@ foo; bar @}";
			self::checkEmit($src, "<?php \$this->__runtime->identifier('foo'); echo \$this->__runtime->identifier('bar');", "Simple generator with multiple expressions");
		}
		
		public function testSimpleGeneratorWithOutput() {
			$src = "{@ foo ? bar @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('foo'))) { do { echo \$this->__runtime->identifier('bar'); } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Simple generator with output");
		}
		
		public function testGeneratorWithOneOutputTemplate() {
			$src = "{@ foo ? `bar` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar'; } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Generator with one output template");
		}
		
		public function testGeneratorWithOneOutputTemplateWithSimpleGenerator() {
			$src = "{@ foo ? `bar {baz} boom` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar ' . \$this->__runtime->identifier('baz') . ' boom'; } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Generator with one output template with simple generator");
		}			

		public function testGeneratorWithOneOutputTemplateWithGenerator() {
			$src = "{@ foo ? `bar {baz ? bam} boom` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('foo'))) { do { echo 'bar '; if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('baz'))) { do { echo \$this->__runtime->identifier('bam'); } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator(); echo ' boom'; } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Generator with one output template with generator");
		}		
		
		public function testGeneratorWithTwoOutputTemplates() {
			$src = "{@ foo ? `bar` `baz` @}";
			self::checkEmit($src, "<?php if (\$this->__runtime->startGenerator(\$this->__runtime->identifier('foo'))) { echo 'bar'; do { echo 'baz'; } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Generator with one output template");
		}
		
		public function testGeneratorWithTemplateAssignment() {
			$src = "{@ foo = `bar {baz ? bam} boom` @}";
			self::checkEmit($src, "<?php if (!function_exists('otf_25babf696a1f4e5644f774f8145ebb54f75d5671')) { function otf_25babf696a1f4e5644f774f8145ebb54f75d5671(\$__template) { ob_start(); echo 'bar '; if (\$__template->__runtime->startGenerator(\$__template->__runtime->identifier('baz'))) { do { echo \$__template->__runtime->identifier('bam'); } while (\$__template->__runtime->loop()); } \$__template->__runtime->endGenerator(); echo ' boom'; \$__output = ob_get_contents(); ob_end_clean(); return \$__output;} }; echo \$this->__runtime->assign('foo', otf_25babf696a1f4e5644f774f8145ebb54f75d5671(\$this));", "Generator with template assignment");
		}
		
		public function testGeneratorWithNoOutput() {
			$src = "{@ foo ? @}";
			self::checkEmit($src, "<?php \$this->__runtime->identifier('foo');", "Generator with no output");
		}

		public function testGeneratorWithNoOutputAndAssignment() {
			$src = "{@ foo = 1 ? @}";
			self::checkEmit($src, "<?php \$this->__runtime->assign('foo', 1);", "Generator with no output and assignment");
		}
		
		public function testMultipleConditionals() {
			$src = "{@ foo if bar == 1 ? baz else if bam == 2 ? boom else foom @}";
			self::checkEmit($src, "<?php \$this->__runtime->startGenerator(\$this->__runtime->identifier('foo')); if (\$this->__runtime->identifier('bar') == 1) { do { echo \$this->__runtime->identifier('baz'); } while (\$this->__runtime->loop()); } else if (\$this->__runtime->identifier('bam') == 2) { do { echo \$this->__runtime->identifier('boom'); } while (\$this->__runtime->loop()); } else { echo \$this->__runtime->identifier('foom'); } \$this->__runtime->endGenerator();", "Generator with multiple conditionals");
		}
		
		public function testEmptyHeadGenerator() {
			$src = "{@ if foo ? bar @}";
			self::checkEmit($src, "<?php \$this->__runtime->startGenerator(true); if (\$this->__runtime->identifier('foo')) { do { echo \$this->__runtime->identifier('bar'); } while (\$this->__runtime->loop()); } \$this->__runtime->endGenerator();", "Generator with empty head");
		}
		
		public function testOutputTemplateWithEscapedGenerator() {
			$src = "{@ `\\{foo}\\{@ bar @}` @}";
			self::checkEmit($src, "<?php echo '{foo}{@ bar @}';", "Escaped output template");
		}		
		
		public function testStrictOutputTemplateWithEscapedGenerator() {
			$src = "{@ ~\\{@ bar @}~ @}";
			self::checkEmit($src, "<?php echo '{@ bar @}';", "Escaped output template");
		}

		public function testExternalFilterCall() {
			$src = "{@ 'test':foo::bar @}";
			self::checkEmit($src, "<?php echo \$this->__runtime->externalCall('bar', 'foo', '', '', 'foo::bar on line 1', array('test'));", "External call filter");
		}

		public function testSimpleAttributeAssignment() {
			$src = "{@ foo[baz + 1 ].bar = baz @}";
			self::checkEmit($src, "<?php echo \$this->__runtime->assign('foo', \$this->__runtime->identifier('baz'), array(\$this->__runtime->identifier('baz') + 1, 'bar'));", "Simple attribute assignment");
		}
		
		public function testAttributeAssignmentAndEcho() {
			$src = "{@ baz = 3; foo[baz + 1 ].bar = baz; foo[baz + 1 ].bar @}";
			self::checkEmit($src, "<?php \$this->__runtime->assign('baz', 3); \$this->__runtime->assign('foo', \$this->__runtime->identifier('baz'), array(\$this->__runtime->identifier('baz') + 1, 'bar')); echo \$this->__runtime->attr(\$this->__runtime->attr(\$this->__runtime->identifier('foo'), \$this->__runtime->identifier('baz') + 1), 'bar');", "Simple attribute assignment and echo");
		}
		
		public function testStatementTag() {
			self::checkEmit("<@ foo = 1 @>", "<?php \$this->__runtime->assign('foo', 1);", "Test statement tag");
		}
		
	}
	