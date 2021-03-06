<?php 
if (empty($_GPC['name'])) {
	message('抱歉，模块不存在或是已经被禁用！');
}

$modulename = !empty($_GPC['name']) ? $_GPC['name'] : 'basic';
$exist = false;
foreach($_W['account']['modules'] as $m) {
	if(strtolower($m['name']) == $modulename) {
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

$method = 'do'.$_GPC['do'];
if (method_exists($module, $method)) {
	$rid = intval($_GPC['id']);
	$state = trim($_GPC['state']);
	exit(@$module->$method($rid, $state));
} else {
	exit("访问的方法 {$method} 不存在.");
}
