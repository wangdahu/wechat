<?php
cache_load('modules');

$sql = "SELECT * FROM " . tablename('wechats') . " WHERE `weid`=:weid LIMIT 1";
$_W['account'] = pdo_fetch($sql, array(':weid' => $_GPC['weid']));
if(empty($_W['account'])) {
	exit('error mobile site');
}
$_W['account']['default_message'] = iunserializer($_W['account']['default_message']);
$_W['account']['access_token'] = iunserializer($_W['account']['access_token']);
$_W['weid'] = $_W['account']['weid'];
$_W['uid'] = $_W['account']['uid'];
$_W['account']['modules'] = array();
$_W['isfounder'] = in_array($_W['uid'], (array)explode(',', $_W['config']['setting']['founder'])) ? true : false;

$template = pdo_fetchcolumn("SELECT name FROM ".tablename('site_templates')." WHERE id = '{$_W['account']['styleid']}';");
$_W['account']['template'] = !empty($template) ? $template : 'default';

$rs = pdo_fetchall("SELECT mid,settings,enabled FROM ".tablename('wechats_modules')." WHERE weid = '{$_W['weid']}'", array(), 'mid');
$accountmodules = array();
$disabledmodules = array();
foreach($rs as $k => &$m) {
	if(!$m['enabled']) {
		$disabledmodules[$m['mid']] = $m['mid'];
		continue;
	} else {
		$accountmodules[$m['mid']] = array(
			'mid' => $m['mid'],
			'config' => iunserializer($m['settings'])
		);
	}
}
if ($_W['isfounder']) {
	$membermodules = pdo_fetchall("SELECT mid, name, issystem FROM ".tablename('modules') . (!empty($disabledmodules) ? " WHERE mid NOT IN (".implode(',', array_keys($disabledmodules)).")" : '') . " ORDER BY issystem DESC, mid ASC", array(), 'mid');
} else {
	$membermodules = pdo_fetchall("SELECT mid FROM ".tablename('members_modules')." WHERE uid = :uid ".(!empty($disabledmodules) ? " AND mid NOT IN (".implode(',', array_keys($disabledmodules)).")" : '')." ORDER BY mid ASC", array(':uid' => $_W['uid']), 'mid');
}

if (!empty($_W['modules'])) {
	foreach ($_W['modules'] as $name => $module) {
		if (isset($membermodules[$module['mid']]) || !empty($module['issystem'])) {
			$modulesimple = array(
				'mid' => $module['mid'],
				'name' => $module['name'],
				'title' => $module['title'],
			);

			$_W['account']['modules'][$module['name']] = $module;
			if($accountmodules[$module['mid']]['config']) {
				$_W['account']['modules'][$module['name']]['config'] = $accountmodules[$module['mid']]['config'];
			}
		}
	}
}
unset($membermodules);
unset($_W['modules']);


$session = json_decode(base64_decode($_GPC['__msess']), true);
if(is_array($session)) {
	$row = fans_search($session['openid'], array('id', 'salt', 'weid', 'from_user', 'follow', 'createtime', 'nickname', 'avatar', 'vip'));
	if(!empty($row) && $row['weid'] == $_W['weid']) {
		$hash = md5("{$session['openid']}{$row['salt']}{$_W['config']['setting']['authkey']}");
		if($session['hash'] == $hash) {
			unset($row['salt']);
			$_W['fans'] = $row;
		}
	}
	if(empty($_W['fans'])) {
		isetcookie('__msess', false, -100);
	}
}
unset($session);
