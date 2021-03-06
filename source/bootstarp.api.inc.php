<?php 
/**
 * 微擎接口初始化文件
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
include_once model('rule');
cache_load('setting');
cache_load('modules');

$sql = "SELECT * FROM " . tablename('wechats') . " WHERE `hash`=:hash LIMIT 1";
$_W['account'] = pdo_fetch($sql, array(':hash' => $_GPC['hash']));
$_W['account']['default_message'] = iunserializer($_W['account']['default_message']);
$_W['account']['access_token'] = iunserializer($_W['account']['access_token']);

if(empty($_W['account'])) {
	exit('initial error hash');
}

if(empty($_W['account']['token'])) {
	exit('initial missing token');
}

$_W['weid'] = $_W['account']['weid'];
$_W['uid'] = $_W['account']['uid'];
$_W['account']['modules'] = array();
$_W['isfounder'] = in_array($_W['uid'], (array)explode(',', $_W['config']['setting']['founder'])) ? true : false;

$rs = pdo_fetchall("SELECT mid,settings,enabled,displayorder FROM ".tablename('wechats_modules')." WHERE weid = '{$_W['weid']}'", array(), 'mid');
$accountmodules = array();
$disabledmodules = array();
foreach($rs as $k => &$m) {
	if(!$m['enabled']) {
		$disabledmodules[$m['mid']] = $m['mid'];
		continue;
	} else {
		$accountmodules[$m['mid']] = array(
			'mid' => $m['mid'],
			'config' => iunserializer($m['settings']),
			'displayorder' => $m['displayorder']
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
			if($accountmodules[$module['mid']]['displayorder']) {
				$_W['account']['modules'][$module['name']]['displayorder'] = $accountmodules[$module['mid']]['displayorder'];
			}
		}
	}
}
unset($membermodules);
unset($_W['modules']);
