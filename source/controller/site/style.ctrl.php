<?php 
/**
 * 微站风格管理
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');
$templateid = intval($_GPC['templateid']);

class PreviewWeModuleProcessor extends WeModuleProcessor {
	public $message = array('from' => 'fromUser');
	public function respond() {}
	public function index() {
		return $this->buildSiteUrl(create_url('mobile/channel', array('name' => 'index', 'weid' => $GLOBALS['_W']['weid'])));
	}
}

if ($do == 'default') {
	$template = pdo_fetch("SELECT * FROM ".tablename('site_templates')." WHERE id = '{$templateid}'");
	if (empty($template)) {
		message('抱歉，模板不存在或是已经被删除！', '', 'error');
	}
	pdo_update('wechats', array('styleid' => $templateid), array('weid' => $_W['weid']));
	message('默认模板更新成功！', create_url('site/style'), 'success');
} elseif ($do == 'designer') {
	$template = pdo_fetch("SELECT * FROM ".tablename('site_templates')." WHERE id = '{$templateid}'");
	if (empty($template)) {
		message('抱歉，模板不存在或是已经被删除！', '', 'error');
	}
	$styles = pdo_fetchall("SELECT variable, content FROM ".tablename('site_styles')." WHERE templateid = :templateid  AND weid = '{$_W['weid']}'", array(':templateid' => $templateid), 'variable');
	if (checksubmit('submit')) {
		if (!empty($_GPC['style'])) {
			foreach ($_GPC['style'] as $variable => $value) {
				if (!empty($value)) {
					if (!empty($styles[$variable])) {
						if ($styles[$variable] != $value) {
							pdo_update('site_styles', array('content' => $value), array('templateid' => $templateid, 'variable' => $variable, 'weid' => $_W['weid']));
						}
						unset($styles[$variable]);
					} else {
						pdo_insert('site_styles', array('content' => $value, 'templateid' => $templateid, 'variable' => $variable, 'weid' => $_W['weid']));
					}
				}
			}
			if (!empty($styles)) {
				pdo_query("DELETE FROM ".tablename('site_styles')." WHERE variable IN ('".implode("','", array_keys($styles))."') AND weid = '{$_W['weid']}'");
			}
		}
		message('更新风格成功！', create_url('site/style/designer', array('templateid' => $templateid)), 'success');
	}
	template('site/designer');
} else {
	$preview = new PreviewWeModuleProcessor();
	$templates = pdo_fetchall("SELECT * FROM ".tablename('site_templates'));
	template('site/style');
}
