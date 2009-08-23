<?php

	class __baz_boom {
		
		static $bim = "BIM!";
		
		static function bam() {
			return "BAM!";
		}
		
		static function getObject() {
			$o = new stdClass;
			$o->foo = "foo";
			$o->bar = new stdClass();
			$o->bar->baz = "baz";
			$o->bam = array("bim" => "boom", "bam" => "foom");
			return $o;
		}
		
		static function json($o) {
			return json_encode($o);
		}
	}