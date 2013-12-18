<?php 
/**
 * 微站频道
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */

defined('IN_IA') or exit('Access Denied');
include model('mobile');
$_W['styles'] = mobile_styles();

$name = $_GPC['name'] ? $_GPC['name'] : 'index';
if ($name == 'index') {
	$position = 1;
	$title = $_W['account']['name'] . '微站';
} elseif ($name == 'home') {
	$title = '个人中心';
	$position = 2;
	if (empty($_W['uid']) && empty($_W['fans']['from_user'])) {
		message('非法访问，请重新点击链接进入个人中心！');
	}
}

$navs = pdo_fetchall("SELECT name, url, icon, css, position FROM ".tablename('site_nav')." WHERE position = '$position' OR position = '3' AND status = 1 AND weid = '{$_W['weid']}' ORDER BY displayorder DESC");
if (!empty($navs)) {
	foreach ($navs as $index => &$row) {
		if (!strexists($row['url'], 'weid=')) {
			$row['url'] .= strexists($row['url'], '?') ?  '&weid='.$_W['weid'] : '?weid='.$_W['weid'];
		}
		$row['css'] = unserialize($row['css']);
		if ($row['position'] == '3') {
			unset($row['css']['icon']['font-size']);
		}
		$row['css']['icon']['style'] = "color:{$row['css']['icon']['color']};font-size:{$row['css']['icon']['font-size']}px;";
		$row['css']['name'] = "color:{$row['css']['name']['color']};";
		if ($row['position'] == '3') {
			$quick[] = $row;
			unset($navs[$index]);
		}
	}
	unset($row);
}
template('mobile/'.$name);