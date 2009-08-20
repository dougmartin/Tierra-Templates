<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplate.php";
	 
	class TemplateTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkOutput($templateContents, $expectedOutput, $dump=false) {
			$output = TierraTemplate::GetDynamicTemplateOutput($templateContents);
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
		
	}
	