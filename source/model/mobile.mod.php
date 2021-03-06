<?php
/**
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

function mobile_styles() {
	global $_W;
	$styles = pdo_fetchall("SELECT variable, content FROM ".tablename('site_styles')." WHERE templateid = '{$_W['account']['styleid']}'", array(), 'variable');
	if (!empty($styles)) {
		foreach ($styles as $variable => $value) {
			if (strexists($value['content'], 'images/')) {
				$value['content'] = $_W['attachurl'] . $value['content'];
			}
			if (($variable == 'logo' || $variable == 'indexbgimg' || $variable == 'ucbgimg') && !strexists($value['content'], 'http://')) {
				$value['content'] = $_W['siteroot'] . 'themes/mobile/'.$_W['account']['template'].'/images/' . $value['content'];
			}
			$styles[$variable] = $value['content'];
		}
	}
	return $styles;
}