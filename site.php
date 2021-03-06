<?php
/**
 * 微站管理
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
define('IN_SYS', true);
require './source/bootstrap.inc.php';
checklogin();
checkaccount();

$actions = array('nav', 'template', 'style', 'icon', 'rule', 'module');

if (in_array($_GPC['act'], $actions)) {
	$action = $_GPC['act'];
} else {
	$action = 'style';
}

$controller = 'site';
require router($controller, $action);
