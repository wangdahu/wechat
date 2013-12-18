<?php
/**
 * 微擎管理后台初始化文件
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */

$session = json_decode(base64_decode($_GPC['__session']), true);
if(is_array($session)) {
	$member = member_single(array('uid'=>$session['uid']));
	if(is_array($member) && $session['hash'] == md5($member['password'] . $member['salt'])) {
		$_W['uid'] = $member['uid'];
		$_W['username'] = $member['username'];
		$member['currentvisit'] = $member['lastvisit'];
		$member['currentip'] = $member['lastip'];
		$member['lastvisit'] = $session['lastvisit'];
		$member['lastip'] = $session['lastip'];
		$_W['member'] = $member;
		$founder = explode(',', $_W['config']['setting']['founder']);
		$_W['isfounder'] = in_array($_W['uid'], $founder) ? true : false;
	} else {
		isetcookie('__session', false, -100);
	}
	unset($member);
}
unset($session);

//cache_load('setting'); #暂时没有全局设置项
cache_load('modules');

if (!empty($_GPC['__weid'])) {
	$_W['weid'] = intval($_GPC['__weid']);
} else {
	cache_load('weid:'.$_W['uid']);
	$_W['weid'] = intval($_W['cache']['weid'][$_W['uid']]);
}

if (!empty($_W['uid'])) {
	//获取当前用户可操作的公众号
	$_W['wechats'] = account_search();
} else {
	$_W['wechats'][$_W['weid']] = pdo_fetch("SELECT * FROM " . tablename('wechats') . " WHERE weid = :weid", array(':weid' => $_W['weid']));
}
foreach($_W['wechats'] as &$w) {
	$w['default_message'] = iunserializer($w['default_message']);
	$w['access_token'] = iunserializer($w['access_token']);
}
if (!empty($_W['weid'])) {
	$_W['account'] = $_W['wechats'][$_W['weid']];
	$_W['account']['template'] = pdo_fetchcolumn("SELECT name FROM ".tablename('site_templates')." WHERE id = '{$_W['account']['styleid']}'");
	$default = iunserializer($_W['account']['default']);
	$welcome = iunserializer($_W['account']['welcome']);
	$_W['account']['default'] = empty($default) ? $_W['account']['default'] : $default;
	$_W['account']['welcome'] = empty($welcome) ? $_W['account']['welcome'] : $welcome;
	$_W['account']['modules'] = account_module();
}
$action = $_GPC['act'];
$do = $_GPC['do'];
