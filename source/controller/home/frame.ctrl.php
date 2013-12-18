<?php
/**
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */

defined('IN_IA') or exit('Access Denied');
checklogin(true);
$do = !empty($_GPC['do']) && in_array($_GPC['do'], array('profile', 'global')) ? $_GPC['do'] : '';
if($do == '') {
	$do = $_W['weid'] ? 'profile' : 'global';
}

if($_GPC['iframe']) {
	$iframe='?'.str_replace('&amp;', '&', $_GPC['iframe']);
} else {
	$iframe='?act=welcome&do=' . $do;
}

$menus = array();
if($do == 'profile') {
	cache_load('menus:');
	$ms = array();
	foreach($_W['account']['modules'] as $m) {
		$ms[] = strtolower($m['name']);
	}
	$extend_menus = array();
	foreach($_W['cache']['menus']['platform'] as $m) {
		if(in_array(strtolower($m['name']), $ms)) {
			$extend_menus['platform'][] = $m;
		}
	}
	foreach($_W['cache']['menus']['site'] as $m) {
		if(in_array(strtolower($m['name']), $ms)) {
			$extend_menus['site'][] = $m;
		}
	}

	$menus[] = array(
					'title' => '基础信息',
					'items' => array(
						array('规则管理', create_url('rule/display'),
							'childItems' => array('添加规则', create_url('rule/post')),
						),
						array('自定义菜单设置', create_url('menu')),
						array('特殊回复设置', create_url('rule/system')),
						array('分类管理', create_url('setting/category')),
						array('粉丝管理', create_url('index/module/display', array('name' => 'fans'))),
						array('模块设置', create_url('member/module')),
						array('当前公众号设置', create_url('account/post', array('id' => 'current'))),
					)
				);
	$pMenus = array(
					'title' => '扩展功能',
					'items' => array(
					)
				);
	if(is_array($extend_menus['platform']) && !empty($extend_menus)) {
		foreach($extend_menus['platform'] as $row) {
			$pMenus['items'][] = array(
				$row['title'],
				create_url("index/module/{$row['do']}", array('name'=>$row['name']))
			);
		}
	}
	$menus[] = $pMenus;

	$sMenus = array(
					'title' => '微站功能',
					'items' => array(
						array('风格设置', create_url('site/style')),
						array('导航管理', create_url('site/nav')),
						array('设置微站入口', create_url('site/rule')),
					)
				);
	if(is_array($extend_menus['site']) && !empty($extend_menus)) {
		foreach($extend_menus['site'] as $row) {
			$sMenus['items'][] = array(
				$row['title'],
				create_url("site/module/{$row['do']}", array('name'=>$row['name']))
			);
		}
	}
	$menus[] = $sMenus;

	$menus[] = array(
					'title' => '统计分析',
					'items' => array(
						array('聊天记录', create_url('index/module/history', array('name'=>'stat'))),
						array('规则使用率', create_url('index/module/rule', array('name'=>'stat'))),
						array('关键字使用率', create_url('index/module/keyword', array('name'=>'stat'))),
					)
				);
}
if($do == 'global') {
	$menus[] = array(
					'title' => array('公众号管理', create_url('account/display')),
				);
	if (!empty($_W['isfounder'])) {
		$menus[] = array(
						'title' => array('用户管理', create_url('member/display')),
					);
		$menus[] = array(
						'title' => array('模块管理', create_url('setting/module')),
					);
	}
	$system = array(
					'title' => '系统管理',
					'items' => array(
						array('账户管理', create_url('setting/profile')),
					)
				);
	if (!empty($_W['isfounder'])) {
		//$system['items'][] = array('模块管理', create_url('setting/module'));
		$system['items'][] = array('其它设置', create_url('setting/common'));
	}
	$system['items'][] = array('更新缓存', create_url('setting/updatecache'));
	$menus[] = $system;
}
template('home/frame');
