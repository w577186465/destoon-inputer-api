<?php
defined('IN_DESTOON') or exit('Access Denied');
if ($DT_BOT) {
	dhttp(403);
}
require_once DT_ROOT . '/include/post.func.php';
require DT_ROOT . '/module/member/member.class.php';

$login = new member;

// $authData =

// login
$username = 'admin';
$password = md5('159263asdf');
$user = $login->login($username, $password, 0);

// utf-8 转码
charset($post['title']);
charset($post['content']);

$_userid = $user['userid'];
$_groupid = $user['groupid'];

if (!$_userid) {
	dhttp(403);
}

$MG = cache_read('group-' . $_groupid . '.php'); // 获取会员组信息

$group_editor = $MG['editor'];
in_array($group_editor, array('Default', 'Destoon', 'Simple', 'Basic')) or $group_editor = 'Destoon';
$MST = cache_read('module-2.php');
isset($admin_user) or $admin_user = false;
$show_oauth = $MST['oauth'];
$show_menu = $MST['show_menu'] ? true : false;
$viewport = 0;
//Guest
if ($_groupid > 5 && !$_edittime && $action == 'add') {
	$response->redirect_confirm(false, '请设置用户信息', '去设置', 'edit.php?tab=2');
}

if ($_groupid > 4 && (($MST['vemail'] && $MG['vemail']) || ($MST['vmobile'] && $MG['vmobile']) || ($MST['vtruename'] && $MG['vtruename']) || ($MST['vcompany'] && $MG['vcompany']) || ($MST['deposit'] && $MG['vdeposit']))) {
	$V = $db->get_one("SELECT vemail,vmobile,vtruename,vcompany,deposit FROM {$DT_PRE}member WHERE userid=$_userid");
	if ($MST['vemail'] && $MG['vemail']) {
		$V['vemail'] or $response->redirect_confirm(false, '请先验证邮箱', '去验证', 'validate.php?action=email&itemid=1');
	}
	if ($MST['vmobile'] && $MG['vmobile']) {
		$V['vmobile'] or $response->redirect_confirm(false, '请先验证手机号码', '去验证', 'validate.php?action=mobile&itemid=1');
	}
	if ($MST['vtruename'] && $MG['vtruename']) {
		$V['vtruename'] or $response->redirect_confirm(false, '请认证您的个人信息', '去认证', 'validate.php?action=truename&itemid=1');
	}
	if ($MST['vcompany'] && $MG['vcompany']) {
		$V['vcompany'] or $response->redirect_confirm(false, '请认证您的公司信息', '去认证', 'validate.php?action=company&itemid=1');
	}
	if ($MST['deposit'] && $MG['vdeposit']) {
		$V['deposit'] > 99 or $response->redirect_confirm(false, '请缴纳保证金', '去缴纳', 'deposit.php?action=add&itemid=1');
	}
}
if ($_credit < 0 && $MST['credit_less'] && $action == 'add') {
	$response->redirect_confirm(false, '积分不足', '查看积分记录', 'credit.php?action=less');
}

// 检查提交参数
$BANWORD = cache_read('banword.php');
if ($BANWORD && isset($post)) {
	$keys = array('title', 'tag', 'introduce', 'content');
	foreach ($keys as $v) {
		if (isset($post[$v])) {
			$post[$v] = banword($BANWORD, $post[$v]);
		}

	}
}

$MYMODS = array();
if (isset($MG['moduleids']) && $MG['moduleids']) {
	$MYMODS = explode(',', $MG['moduleids']);
}
if ($MYMODS) {
	foreach ($MYMODS as $k => $v) {
		$v = abs($v);
		if (!isset($MODULE[$v])) {
			unset($MYMODS[$k]);
		}

	}
}
$MENUMODS = $MYMODS;
if ($show_menu) {
	$MENUMODS = array();
	foreach ($MODULE as $m) {
		if ($m['islink']) {
			continue;
		}

		if ($m['moduleid'] > 4 && $m['moduleid'] != 11) {
			$MENUMODS[] = $m['moduleid'];
		}

		if ($m['moduleid'] == 9 && in_array(-9, $MYMODS)) {
			$MENUMODS[] = -9;
		}

	}
}
$vid = $mid;
if ($mid == 9 && isset($resume)) {
	$vid = -9;
}

// 判断发布权限
if (!$MYMODS || !in_array($vid, $MYMODS)) {
	$response->failed('权限不足');
}

$IMVIP = isset($MG['vip']) && $MG['vip'];
$moduleid = $mid;
$module = $MODULE[$moduleid]['module'];
if (!$module) {
	$response->failed('参数不正确，请勿违法操作！');
}

$MOD = cache_read('module-' . $moduleid . '.php'); // 获取模块信息
$my_file = DT_ROOT . '/api/inputer/input_module/' . $module . '.php';
if (is_file($my_file)) {
	require $my_file; // 引入发布模块
} else {
	$response->failed('参数不正确，请勿违法操作！');
}
