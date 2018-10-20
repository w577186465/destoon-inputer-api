<?php
require '../../common.inc.php';
require './libs/ApiResponse.php';
require './libs/functions.php';
// header('Content-Type: application/json;charset=utf-8');

$response = new ApiResponse;

$actions = array('category', 'module', 'inputer');

if (in_array($action, $actions)) {
	require "./{$action}.php";
}
