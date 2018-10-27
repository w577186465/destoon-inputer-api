<?php
defined('IN_DESTOON') or exit('Access Denied');
require DT_ROOT . '/module/' . $module . '/common.inc.php';
$MG['article_limit'] > -1 or $response->failed(lang('message->without_permission_and_upgrade'));
$MTYPE = get_type('product-' . $_userid);
include load($module . '.lang');
include load('my.lang');
require MD_ROOT . '/sell.class.php';
$do = new sell($moduleid);

$FD = cache_read('fields-' . substr($table, strlen($DT_PRE)) . '.php');
if ($FD) {
	require DT_ROOT . '/include/fields.func.php';
}

isset($post_fields) or $post_fields = array();
$CP = $MOD['cat_property'];
if ($CP) {
	require DT_ROOT . '/include/property.func.php';
}

isset($post_ppt) or $post_ppt = array();

$sql = $_userid ? "username='$_username'" : "ip='$DT_IP'";
$limit_used = $limit_free = $need_password = $need_captcha = $need_question = $fee_add = 0;
if (in_array($action, array('', 'add'))) {
	$r = $db->get_one("SELECT COUNT(*) AS num FROM {$table} WHERE $sql AND status>1");
	$limit_used = $r['num'];
	$limit_free = $MG['sell_limit'] > $limit_used ? $MG['sell_limit'] - $limit_used : 0;
}
if (check_group($_groupid, $MOD['group_refresh'])) {
	$MOD['credit_refresh'] = 0;
}

if ($MG['sell_limit'] && $limit_used >= $MG['sell_limit']) {
	$response->failed(lang($L['info_limit'], array($MG[$MOD['module'] . '_limit'], $limit_used)), 400, 'gbk');
}

if ($MG['day_limit']) {
	$today = $today_endtime - 86400;
	$r = $db->get_one("SELECT COUNT(*) AS num FROM {$table} WHERE $sql AND addtime>$today");
	if ($r && $r['num'] >= $MG['day_limit']) {
		$response->failed(lang($L['day_limit'], array($MG['day_limit'])), 400, 'gbk');
	}

}

if ($MG['sell_free_limit'] >= 0) {
	$fee_add = ($MOD['fee_add'] && (!$MOD['fee_mode'] || !$MG['fee_mode']) && $limit_used >= $MG['sell_free_limit'] && $_userid) ? dround($MOD['fee_add']) : 0;
} else {
	$fee_add = 0;
}
$fee_currency = $MOD['fee_currency'];
$fee_unit = $fee_currency == 'money' ? $DT['money_unit'] : $DT['credit_unit'];
$need_password = $fee_add && $fee_currency == 'money';
$need_captcha = $MOD['captcha_add'] == 2 ? $MG['captcha'] : $MOD['captcha_add'];
$need_question = $MOD['question_add'] == 2 ? $MG['question'] : $MOD['question_add'];
$could_elite = check_group($_groupid, $MOD['group_elite']) && $MOD['credit_elite'] && $_userid;
$could_color = check_group($_groupid, $MOD['group_color']) && $MOD['credit_color'] && $_userid;

if ($fee_add && $fee_add > ($fee_currency == 'money' ? $_money : $_credit)) {
	$response->failed($L['balance_lack'], 400, 'gbk');
}

if ($need_password && !is_payword($_username, $password)) {
	$response->failed($L['error_payword'], 400, 'gbk');
}

if (!$_userid) {
	if (strlen($post['company']) < 4) {
		$response->failed($L['type_company'], 400, 'gbk');
	}

	if ($AREA) {
		if (!isset($AREA[$post['areaid']])) {
			$response->failed($L['type_area'], 400, 'gbk');
		}

	} else {
		if (!$post['areaid']) {
			$response->failed($L['type_area'], 400, 'gbk');
		}

	}
	if (strlen($post['truename']) < 4) {
		$response->failed($L['type_truename'], 400, 'gbk');
	}

	if (strlen($post['mobile']) < 7) {
		$response->failed($L['type_mobile'], 400, 'gbk');
	}

}

if ($MG['add_limit']) {
	$last = $db->get_one("SELECT addtime FROM {$table} WHERE $sql ORDER BY itemid DESC");
	if ($last && $DT_TIME - $last['addtime'] < $MG['add_limit']) {
		$response->failed(lang($L['add_limit'], array($MG['add_limit'])), 400, 'gbk');
	}

}

if ($do->pass($post)) {
	$CAT = get_cat($post['catid']);
	if (!$CAT || !check_group($_groupid, $CAT['group_add'])) {
		$response->failed(lang($L['group_add'], array($CAT['catname'])), 400, 'gbk');
	}

	if ($MOD['upload_thumb'] && $MG['upload'] && strlen($post['thumb']) < 5) {
		$response->failed($L['sell_upload_image'], 400, 'gbk');
	}

	$post['addtime'] = $post['level'] = $post['fee'] = 0;
	$post['style'] = $post['template'] = $post['note'] = $post['filepath'] = '';
	if (!$IMVIP && $MG['uploadpt']) {
		$post['thumb1'] = $post['thumb2'] = '';
	}

	$need_check = $MOD['check_add'] == 2 ? $MG['check'] : $MOD['check_add'];
	$post['status'] = get_status(3, $need_check);
	$post['hits'] = 0;
	$post['username'] = $_username;
	if ($FD) {
		fields_check($post_fields);
	}

	if ($CP) {
		property_check($post_ppt);
	}

	if ($could_elite && isset($elite) && $post['thumb'] && $_credit > $MOD['credit_elite']) {
		$post['level'] = 1;
		credit_add($_username, -$MOD['credit_elite']);
		credit_record($_username, -$MOD['credit_elite'], 'system', lang($L['credit_record_elite'], array($MOD['name'])), $post['title']);
	}
	if ($could_color && $color && $_credit > $MOD['credit_color']) {
		$post['style'] = $color;
		credit_add($_username, -$MOD['credit_color']);
		credit_record($_username, -$MOD['credit_color'], 'system', $L['title_color'], '[' . $MOD['name'] . ']' . $post['title']);
	}
	$do->add($post);
	if ($FD) {
		fields_update($post_fields, $table, $do->itemid);
	}

	if ($CP) {
		property_update($post_ppt, $moduleid, $post['catid'], $do->itemid);
	}

	if ($MOD['show_html'] && $post['status'] > 2) {
		$do->tohtml($do->itemid);
	}

	if ($fee_add) {
		if ($fee_currency == 'money') {
			money_add($_username, -$fee_add);
			money_record($_username, -$fee_add, $L['in_site'], 'system', lang($L['credit_record_add'], array($MOD['name'])), 'ID:' . $do->itemid);
		} else {
			credit_add($_username, -$fee_add);
			credit_record($_username, -$fee_add, 'system', lang($L['credit_record_add'], array($MOD['name'])), 'ID:' . $do->itemid);
		}
	}
	$msg = $post['status'] == 2 ? $L['success_check'] : $L['success_add'];
	$js = '';
	if (isset($post['sync_sina']) && $post['sync_sina']) {
		$js .= sync_weibo('sina', $moduleid, $do->itemid);
	}

	if (isset($post['sync_qq']) && $post['sync_qq']) {
		$js .= sync_weibo('qq', $moduleid, $do->itemid);
	}

	$response->message('success', 400, 'gbk');
} else {
	$response->failed($do->errmsg, 400, 'gbk');
}

?>