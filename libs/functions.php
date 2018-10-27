<?php

function charset(&$str) {
	global $CFG;
	if ($CFG['charset'] != 'utf-8') {
		$str = iconv("UTF-8", "GBK//IGNORE", $str);
	}
}
