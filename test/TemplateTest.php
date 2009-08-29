<?php

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
		
		public function testGenerator() {
			$this->checkOutput("{@ 'foo' @}", "foo");
		}
		
		public function testGeneratorAddition() {
			$this->checkOutput("{@ 1 + 1 @}", "2");
		}
		
		public function testGeneratorConditional() {
			$this->checkOutput("{@ 1 + 1 == 2 ? `yes` else `no` @}", "yes");
		}

		public function testGeneratorArrayIndex() {
			$this->checkOutput("{@ ['foo', 'bar', 'baz'][1] @}", "bar");
		}
		
		public function testGeneratorArray() {
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
		
		public function testEmptyHeadGenerator() {
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
		

	}
	