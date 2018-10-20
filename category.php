<?php
defined('IN_DESTOON') or exit('Access Denied');

$modules = array();
foreach ($MODULE as $key => $value) {
	if ($value['ismenu']) {
		$name = iconv("GBK", "UTF-8//IGNORE", $value['name']);
		$modules[] = array('name' => $name, 'id' => $value['moduleid']);
	}
}

$categorys = array();
foreach ($modules as $mk => $value) {
	$maincat = get_maincat(0, $value['id']);
	$categorys[$mk] = $value;
	foreach ($maincat as $key => $value) {
		$name = iconv('GBK', 'UTF-8', $value['catname']);
		$categorys[$mk]['child'][] = array('id' => $value['catid'], 'name' => $name);
	}
}

$response->success($categorys);