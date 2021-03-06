<?php 
/**
 * 用户模块管理
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */

defined('IN_IA') or exit('Access Denied');
checkaccount();
$do = !empty($_GPC['do']) ? $_GPC['do'] : 'display';

$modulelist = account_module(false);
if (!empty($modulelist)) {
	foreach ($modulelist as $mid => &$module) {
		$module = array_merge($module, $_W['modules'][$module['name']]);
	}
	unset($module);
}

if($do == 'display') {
	template('member/module');
} elseif ($do == 'setting') {
	$mid = intval($_GPC['mid']);
	if (!array_key_exists($mid, $modulelist)) {
		message('抱歉，你操作的模块不能被访问！');
	}
	$module = $modulelist[$mid];
	$config = $module['config'];
	$obj = module($module['name']);
	$obj->_saveing_params = array();
	$obj->_saveing_params['weid'] = $_W['weid'];
	$obj->_saveing_params['mid'] = $mid;
	$obj->settingsDisplay($config);
	exit();
} elseif ($do == 'enable') {
	$mid = intval($_GPC['mid']);
	if (!array_key_exists($mid, $modulelist)) {
		message('抱歉，你操作的模块不能被访问！');
	}
	$module = $modulelist[$mid];
	$exist = pdo_fetchcolumn("SELECT id FROM ".tablename('wechats_modules')." WHERE mid = :mid AND weid = :weid", array(':mid' => $mid, ':weid' => $_W['weid']));
	if (empty($exist)) {
		pdo_insert('wechats_modules', array(
			'mid' => $mid,
			'weid' => $_W['weid'],
			'enabled' => empty($_GPC['enabled']) ? 0 : 1,
			'displayorder' => $module['issystem'] ? '-1' : 127,
		));
	} else {
		pdo_update('wechats_modules', array(
			'mid' => $mid,
			'weid' => $_W['weid'],
			'enabled' => empty($_GPC['enabled']) ? 0 : 1,
			'displayorder' => $module['issystem'] ? '-1' : 127,
		), array('id' => $exist));
	}
	message('模块操作成功！', referer(), 'success');
} elseif ($do == 'displayorder') {
	$mid = intval($_GPC['mid']);
	$displayorder = intval($_GPC['displayorder']);
	$displayorder = max($displayorder, 0);
	$displayorder = min($displayorder, 5);
	if (!array_key_exists($mid, $modulelist)) {
		message('抱歉，你操作的模块不能被访问！');
	}
	$module = $modulelist[$mid];
	if ($module['issystem']) {
		message('抱歉，系统模块无法设置优先级！');
	}
	pdo_query("UPDATE ".tablename('wechats_modules')." SET displayorder = 127 WHERE displayorder = '$displayorder' AND weid = '{$_W['weid']}'");
	if (pdo_fetchcolumn("SELECT mid FROM ".tablename('wechats_modules')." WHERE mid = :mid AND weid = :weid", array(':mid' => $mid, ':weid' => $_W['weid']))) {
		pdo_update('wechats_modules', array('displayorder' => $displayorder == 0 ? 127 : $displayorder), array('mid' => $mid ,'weid' => $_W['weid']));
	} else {
		pdo_insert('wechats_modules', array('displayorder' => $displayorder == 0 ? 127 : $displayorder, 'mid' => $mid ,'weid' => $_W['weid'], 'enabled' => 1));
	}
	message('操作成功！', referer());
} elseif ($do == 'form') {
	include model('rule');
	if (empty($_GPC['name'])) {
		message('抱歉，模块不存在或是已经被删除！');
	}
	$modulename = !empty($_GPC['name']) ? $_GPC['name'] : 'basic';
	$exist = false;
	foreach($modulelist as $m) {
		if(strtolower($m['name']) == $modulename && $m['enabled']) {
			$exist = true;
			break;
		}
	}
	if(!$exist) {
		message('抱歉，你操作的模块不能被访问！');
	}
	$module = module($modulename);
	if (is_error($module)) {
		exit($module['errormsg']);
	}
	$rid = intval($_GPC['id']);
	exit($module->fieldsFormDisplay($rid));
}
