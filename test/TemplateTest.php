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
	require_once dirname(__FILE__) . "/../src/TierraTemplate.php";
	 
	class TemplateTest extends PHPUnit_Framework_TestCase {
		
		protected $options;
		
		protected function setUp() {
			$this->options = array(
				"baseTemplateDir" => "templates",
				"readFromCache" => false,
				"cacheDir" => "cache",
				"virtualDirs" => array(
					"flam" => array(
						"path" => "externals",
						"functionPrefix" => "__test_prefix_"
					),
					"flim" => array(
						"path" => "externals/subdir",
						"functionPrefix" => "__test_prefix_"
					)
				)
			);
		}
		
		protected function tearDown() {
		}
		
		public function checkOutput($templateContents, $expectedOutput, $dump=false) {
			$output = TierraTemplate::GetDynamicTemplateOutput($templateContents, $this->options);
			if ($dump) {
				echo "templateContents:\n";
				var_dump($templateContents);
				echo "expectedOutput:\n";
				var_dump($expectedOutput);
				echo "output:\n";
				var_dump($output);
			}
			$this->assertEquals($expectedOutput, $output);
		}
		
		public function checkTemplateOutput($templateFile, $expectedOutput, $dump=false) {
			$this->options["templateFile"] = $templateFile;
			$template = TierraTemplate::LoadTemplate($this->options);
			$output = $template->getOutput();
			if ($dump) {
				echo "templateFile:\n";
				var_dump($templateFile);
				echo "expectedOutput:\n";
				var_dump($expectedOutput);
				echo "output:\n";
				var_dump($output);
				echo "blocks:\n";
				$template->__request->dumpBlocks();
			}
			$this->assertEquals($expectedOutput, $output);
		}
		
		public function testEmpty() {
			$this->checkOutput("", "");
		}
		
		public function testSpace() {
			$this->checkOutput(" ", " ");
		}
		
		public function testConditerator() {
			$this->checkOutput("{@ 'foo' @}", "foo");
		}
		
		public function testConditeratorAddition() {
			$this->checkOutput("{@ 1 + 1 @}", "2");
		}
		
		public function testConditeratorConditional() {
			$this->checkOutput("{@ 1 + 1 == 2 ? `yes` else `no` @}", "yes");
		}

		public function testConditeratorArrayIndex() {
			$this->checkOutput("{@ ['foo', 'bar', 'baz'][1] @}", "bar");
		}
		
		public function testConditeratorArray() {
			$this->checkOutput("{@ ['foo', 'bar', 'baz'] ? $ @}", "foobarbaz");
		}
		
		public function testLoadTemplate() {
			$this->checkTemplateOutput("echofoo.html", "foo");
		}
		
		public function testAssign() {
			$this->checkOutput("{@ foo = 'bar' @}", "bar");
		}

		public function testAssignSimpleOutputTemplate() {
			$this->checkOutput("{@ foo = `bar` @}", "bar");
		}
		
		public function testAssignComplexOutputTemplate() {
			$this->checkOutput("{@ foo = `bar {true ? 'baz'} boom` @}", "bar baz boom");
		}
		
		public function testMultipleConditionals() {
			$this->checkOutput("{@ bam = 2; 'foo' if 1 > 3 ? 'baz' else if bam == 2 ? `boom` else $ @}", "boom");
		}
		
		public function testEmptyHeadConditerator() {
			$this->checkOutput("{@ if 1 > 2 ? 'bar' else 'baz' @}", "baz");
		}

		public function testLoadChildTemplate() {
			$this->checkTemplateOutput("child.html", "grandparent parent child");
		}
		
		public function testInternalFilterCall() {
			$this->checkOutput("{@ 'test':strtoupper @}", "TEST");
		}
				
		public function testExternalFilterCall() {
			$this->checkOutput("{@ 'test':flam\\foo::bar @}", "TEST");
		}
		
		public function testExternalFilterCallNoVirtualDir() {
			$this->checkOutput("{@ 'test':foo::bar @}", "TEST");
		}

		public function testExternalFilterCallInSubDir() {
			$this->checkOutput("{@ 'TEST':flam\\subdir\\bar::baz @}", "test");
		}

		public function testExternalFilterCallNoFilename() {
			$this->checkOutput("{@ 'test':flam\\boom @}", "TEST");
		}
		
		public function testSimpleAttributeAssignment() {
			$this->checkOutput("{@ baz = 3; foo[baz + 1].bar = baz; foo[4].bar == baz ? 'yes' @}", "yes");
		}
		
		public function testSimpleCodeBlock() {
			$this->checkOutput("<@ 'test' @>", "");
		}
		
		public function testBuiltinCalls() {
			$this->checkOutput("{@ 'this is a test':link('http://google.com', {id: 'foo', class: 'bar'}) @}", '<a id="foo" class="bar" href="http://google.com">this is a test</a>');
		}
		
		public function testDecoratorGuid() {
			$this->checkOutput("[@ start foo do showguid() @] bar [@ end foo @]", "<p>guid for foo: 1f9e539887f123d934b77021a14ae3a9468f1f78</p> bar ");
		}
		
		public function testEscapeOutputBlock() {
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo}` @}", "<test>&lt;test&gt;");
			$this->checkOutput("{@ foo = `<test>` @}", "&lt;test&gt;");
			$this->checkOutput("{@ `<test>` @}", "<test>");
			
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}` @}", "<test><test>");
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}{foo}` @}", "<test><test>&lt;test&gt;");
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}<test>{foo}` @}", "<test><test><test>&lt;test&gt;");
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}<test>{foo}<test>` @}", "<test><test><test>&lt;test&gt;<test>");
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}<test>{foo:escape}` @}", "<test><test><test>&lt;test&gt;");
			$this->checkOutput("{@ foo = '<test>'; `<test>{foo:noescape}<test>{foo:noescape}` @}", "<test><test><test><test>");
			$this->checkOutput("{@ foo = `<test>`:noescape @}", "<test>");
		}
		
		public function testEscape() {
			$this->checkOutput("{@ foo = '<test>'; foo @}", "&lt;test&gt;");
			$this->checkOutput("{@ foo = '<test>' @}", "&lt;test&gt;");
			$this->checkOutput("{@ '<test>' @}", "<test>");
			$this->checkOutput("{@ foo = '<test>'; foo:escape @}", "&lt;test&gt;");
			$this->checkOutput("{@ foo = '<test>'; escape(); foo @}", "&lt;test&gt;");
		}
		
		public function testNoEscapeFilter() {
			$this->checkOutput("{@ foo = '<test>'; foo:noescape @}", "<test>");
			$this->checkOutput("{@ foo = '<test>'; noescape(); foo @}", "<test>");
			$this->checkOutput("{@ foo = '<test>'; escape(); noescape(); foo @}", "<test>");
		}
		
		public function testEscapeWithAutoEscapeOff() {
			$this->options["request"]["autoEscapeOutput"] = false;
			$this->checkOutput("{@ foo = '<test>'; foo @}", "<test>");
			$this->checkOutput("{@ foo = '<test>'; foo:noescape @}", "<test>");
			$this->checkOutput("{@ foo = '<test>'; foo:escape @}", "&lt;test&gt;");
		}
		
		public function testEscapeBlocks() {
			$this->checkOutput("[@ start test @]{@ foo = '<test>'; foo @}[@ end test @]", "&lt;test&gt;");
			$this->options["request"]["autoEscapeOutput"] = false;
			$this->checkOutput("[@ start test @]{@ foo = '<test>'; foo @}[@ end test @]", "<test>");
			$this->checkOutput("[@ start test @]{@ foo = '<test>'; foo:escape @}[@ end test @]", "&lt;test&gt;");
		}
		
		public function testEscapeBlockDecorators() {
			$this->checkOutput("[@ start test do escape() @]{@ foo = '<test>'; foo @}[@ end test @]", "&lt;test&gt;");
			$this->checkOutput("[@ start test do noescape() @]{@ foo = '<test>'; foo @}[@ end test @]", "<test>");
			$this->checkOutput("[@ start test do noescape() @]{@ foo = '<test>'; foo:escape @}[@ end test @]", "&lt;test&gt;");
		}
		
		public function testEscapePageDecorators() {
			$this->checkOutput("[@ page do escape() @]{@ foo = '<test>'; foo @}", "&lt;test&gt;");
			$this->checkOutput("[@ page do noescape() @]{@ foo = '<test>'; foo @}", "<test>");
			$this->checkOutput("[@ page do noescape() @]{@ foo = '<test>'; foo @}[@ start test do escape() @]{@ foo @}[@ end test @]", "<test>&lt;test&gt;");
			$this->checkOutput("[@ page do noescape() @]{@ foo = '<test>'; foo @}[@ start test do escape() @]{@ foo:noescape @}[@ end test @]", "<test><test>");
		}
		
		public function testLoadTemplateObject() {
			function getTestTemplate() {
				return TierraTemplate::LoadDynamicTemplate("foo [@ echo bar @] baz", array("readFromCache" => false, "cacheDir" => "cache"));
			}
			$this->checkOutput("[@ extends getTestTemplate() @][@ start bar @]bam[@ end bar @]", "foo bam baz");
		}
		
		public function testCallWithRequest() {
			function testcall($request, $name, $value) {
				$request->setVar($name, $value);
			}
			$this->checkOutput("{@ bar = 'bam'; testcall(request, 'foo', bar); foo @}", "bam");
		}
		
		public function testAssignRequest() {
			$this->setExpectedException("TierraTemplateException");
			$this->checkOutput("{@ request = 1 @}", "");
		}
		
		public function testAssignRequestAttr() {
			$this->checkOutput("{@ request.foo = 1; foo @}", "1");
		}		

		public function testAssignRequestAttr2() {
			$this->checkOutput("{@ request.foo.bar = 'baz'; foo.bar @}", "baz");
		}

		public function testAssignRequestAttr3() {
			$this->checkOutput("{@ request.foo.bar = 'baz'; request.foo.bar @}", "baz");
		}	

		public function testCycle() {
			$this->checkOutput("{@ range(1,5) ? cycle('foo','bar','baz') @}", "foobarbazfoobar");
		}
		
		public function testChanged() {
			$this->checkOutput("{@ foo = 'bar'; `baz{foo}` if true @}", "bazbar");
		}
		
		public function testRawInclude() {
			$this->checkOutput("[@ rawinclude 'includes/rawinclude.txt' @]", "This is a raw include");
		}

		public function testRawIncludeUrl() {
			$this->checkOutput("[@ rawinclude 'http://tierratemplates.com/favicon.ico' @]", base64_decode("AAABAAEAEBAAAAEAGABoAwAAFgAAACgAAAAQAAAAIAAAAAEAGAAAAAAAAAAAABMLAAATCwAAAAAAAAAAAAD/+v3//v///v/7//+5wcF5hYVpendnendyeoGIk5emtrXI2dXz//74//z/+/3//P///v///v/p6+xrb3A/R0dBS0tlcXF6iIeTlp6QmZ2eqqqgr6u9y8fr8u///v/89vv5/v3m6+peY2JCRkcaHyBJTk+jqKvW297f3ufCxcqyurqWoZ6Pmpe8xcLq7/D6/f/6//5ob2wrMTApLS6FiYrq6+/9/v/39/3y7/j9/f/w9PXAx8SLlJF+iYa0vr71/v++xcAvNjEmKyl8gYD5+/z9/f///f9dWmNLSlPs7PL9///z9vTAxcN4f3yGkJDM2Np6fHYoKiQ7Pjzs7u78/f/7/v/z9f1TVF4rMTjz9/z9//////7x8vCVmJaBhoe1vL9NSkIqJiF8eXX////3+v76/f/4/f9LUVw1QUfw+f3w9PX///7///7Mysmpq6y4u78+NC0jGxSloJ39+/v7/v/2/P/0/f9MVmAoO0Du+v76//////7/+vn07+7a19nGxck6LC4wJCKjmpb+/PT///z2+f34+/9PU2Y2O0r4+//6+//9/P//+v3/9vD56Nv/69pURkcqHhx8dnH8+fT9//+5v8aaobA8Q1YhMUKNmqiqs7z7//////v//PH99Oby6NeOgX8tIh4xKybn5eT2+vtJUFkbJDIUHjAOJjgQIzBIVFbr8Of///Xw7tzu69zw7+Hi19NNREAzLitlY2P4+//a4OfEzde/yNW7y9u9yc/S08////T16tbp38307+D+/PL/+/bKxL9gW1ovKitnZGbo6uv2+Pn7////+/////z/+u7/8Nvn0Lry3s3/8ej//vr//fn///zY09KWkZCNhoOGf3bd1cj2797//O3028vdwq7hxrHky7f35Nf/9vD//v79//729/X//v/u5eLk18/GtqXQwKPg0KznyabkyKbjy6/s18H15tb//PP///z//v76///7/f3//v///vr/8uX548ry27Xv2Kry1KPu1anx3rv/+OD///X8//r0+vn2//8AAPADAAAIAAAAAAAAAP//AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"));
		}
	}
	