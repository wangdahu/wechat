<?php
/**
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');
include model('rule');

$modules = array();
foreach($_W['account']['modules'] as $k => $v) {
	if(is_array($_W['modules']) && $_W['modules'][$v['name']]['isrulefields']) {
		$modules[$k] = $_W['modules'][$v['name']];
	}
}

if (checksubmit('submit')) {
	$modulename = '';
	foreach ($modules as $module) {
		if ($module['name'] == $_GPC['module']) {
			$modulename = $_GPC['module'];
			break;
		}
	}
	if (empty($modulename)) {
		message('您未启用、安装该模块或是您没有权限使用！', '', 'error');
	}
	
	$rid = intval($_GPC['id']);
	if (empty($_GPC['name'])) {
		message('抱歉，规则名称为必填项，请选回修改！');
	}
	$cid = $_GPC['cate_2'] ? $_GPC['cate_2'] : $_GPC['cate_1'];
	$rule = array(
		'weid' => $_W['weid'],
		'cid' => $cid,
		'name' => $_GPC['name'],
		'module' => $modulename,
		'status' => intval($_GPC['status']),
	);
	if (!empty($_GPC['istop'])) {
		$rule['displayorder'] = 255;
	} else {
		$rule['displayorder'] = intval($_GPC['displayorder']) > 254 ? 254 : intval($_GPC['displayorder']);
	}
	//调用模块处理
	$module = module($modulename);
	if (is_error($module)) {
		message('抱歉，模块不存在请重新其它模块！');
	}
	$msg = $module->fieldsFormValidate();
	if(is_string($msg) && trim($msg) != '') {
		message($msg);
	}
	if (!empty($rid)) {
		$isexists = pdo_fetch("SELECT id, module FROM ".tablename('rule')." WHERE id = :id", array(':id' => $rid));
		if (empty($isexists)) {
			message('抱歉，要修改的规则不存在或是已经被删除！');
		}
		$rule['module'] = $isexists['module'];
		$result = pdo_update('rule', $rule, array('id' => $rid));
	} else {
		$result = pdo_insert('rule', $rule);
		$rid = pdo_insertid();
	}
	if (!empty($rid)) {
		//更新，添加，删除关键字
		if (!empty($_GPC['keyword-name'])) {
			foreach ($_GPC['keyword-name'] as $id => $row) {
				if (empty($row) && strlen($row) == 0) {
					continue;
				}
				$data = array(
					'content' => $row,
					'type' => intval($_GPC['keyword-type'][$id]),
					'rid' => $rid,
					'id' => $id,
					'weid' => $_W['weid'],
					'module' => $rule['module'],
					'status' => $rule['status'],
					'displayorder' => $rule['displayorder'],
				);
				rule_insert_keyword($data);
			}
		}
		if (!empty($_GPC['keyword-name-new'])) {
			foreach ($_GPC['keyword-name-new'] as $id => $row) {
				if (empty($row) && strlen($row) == 0) {
					continue;
				}
				$data = array(
					'content' => $row,
					'type' => intval($_GPC['keyword-type-new'][$id]),
					'rid' => $rid,
					'weid' => $_W['weid'],
					'module' => $rule['module'],
					'status' => $rule['status'],
					'displayorder' => $rule['displayorder'],
				);
				rule_insert_keyword($data);
			}
		}
		//更新置顶关键字缓存
		cache_write("keywordtop:{$_W['weid']}", pdo_fetchall("SELECT content, type, rid, module FROM ".tablename('rule_keyword')." WHERE status = 1 AND displayorder = '255' ORDER BY type ASC, id ASC"));
		$module->fieldsFormSubmit($rid);
		message('规则操作成功！', 'rule.php?act=post&id='.$rid);
	} else {
		message('规则操作失败, 请联系网站管理员！');
	}
} else {
	$types = array(
		1 => array(
			'name' => '完全等于上述关键字',
			'description' => '用户进行微信交谈时，对话内容完全等于上述关键字才会执行这条规则。',
		),
		2 => array(
			'name' => '包含上述关键字',
			'description' => '用户进行微信交谈时，对话中包含上述关键字就执行这条规则。',
		),
		3 => array(
			'name' => '正则表达式匹配',
			'description' => "用户进行微信交谈时，对话内容符合述关键字中定义的模式才会执行这条规则。<br /><b>/^微擎/</b>匹配以“微擎”开头的语句<br /><b>/微擎$/</b>匹配以“微擎”结尾的语句<br /><b>/^微擎$/</b>匹配等同“微擎”的语句<br /><b>/微擎/</b>匹配包含“微擎”的语句<br /><b>/[0-9\.\-]/</b>匹配所有的数字，句号和减号<br /><b>/^[a-zA-Z_]$/</b>所有的字母和下划线<br /><b>/^[[:alpha:]]{3}$/</b>所有的3个字母的单词<br /><b>/^a{4}$/</b>aaaa<br /><b>/^a{2,4}$/</b>aa，aaa或aaaa<br /><b>/^a{2,}$/</b>匹配多于两个a的字符串",
		)
	);
	$defaultmodule = !empty($_GPC['module']) ? $_GPC['module'] : 'basic';
	$typeslabel = "'".implode("','", $types)."'";
	$rid = intval($_GPC['id']);
	if (!empty($rid)) {
		$rule = rule_single($rid);
		if (empty($rule['rule'])) {
			message('抱歉，您操作的规则不在存或是已经被删除！', create_url('rule/display'), 'error');
		}
		$module = $rule['rule']['module'];
		$module = module($module);
		$rule['reply'] = $module;
	}
	$category = cache_load('category:'.$_W['weid']);
	if (!empty($category)) {
		$children = '';
		foreach ($category as $cid => $cate) {
			if (!empty($cate['parentid'])) {
				$children[$cate['parentid']][] = array($cate['id'], $cate['name']);
			}
		}
	}
	template('rule/post');
}
