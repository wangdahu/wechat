 <?php 
/**
 * 微站导航管理
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

$position = array(
	1 => '首页',
	2 => '个人中心',
	3 => '快捷菜单',	
);

if ($do == 'post') {
	$id = intval($_GPC['id']);
	if (!empty($id)) {
		$item = pdo_fetch("SELECT * FROM ".tablename('site_nav')." WHERE id = :id" , array(':id' => $id));
		$item['css'] = unserialize($item['css']);
		if (strexists($item['icon'], 'images/')) {
			$item['fileicon'] = $item['icon'];
			$item['icon'] = '';
		}
		if (empty($item)) {
			message('抱歉，导航不存在或是已经删除！', '', 'error');
		}
	}
	if (checksubmit('fileupload-delete')) {
		file_delete($_GPC['fileupload-delete']);
		pdo_update('site_nav', array('icon' => ''), array('id' => $id));
		message('删除成功！', referer(), 'success');
	}
	if (checksubmit('submit')) {
		if (empty($_GPC['name'])) {
			message('抱歉，请输入导航菜单的名称！', '', 'error');
		}
		$data = array(
			'weid' => $_W['weid'],
			'name' => $_GPC['name'],
			'displayorder' => intval($_GPC['displayorder']),
			'position' => intval($_GPC['position']),
			'url' => intval($_GPC['type']) == '1' ? $_GPC['sysurl'] : $_GPC['url'],
			'icon' => $_GPC['icon_old'],
			'issystem' => intval($_GPC['type']) == '1' ? 1 : 0,
			'status' => intval($_GPC['status']),
		);
		$data['css'] = serialize(array(
			'icon' => array(
				'font-size' => $_GPC['icon']['size'],
				'color' => $_GPC['icon']['color'],
				'width' => $_GPC['icon']['size'],
				'icon' => $_GPC['icon']['icon'],
			),
			'name' => array(
				'color' => $_GPC['color'],
			),
		));
		if (!empty($_FILES['icon']['tmp_name'])) {
			file_delete($_GPC['icon_old']);
			$upload = file_upload($_FILES['icon']);
			if (is_error($upload)) {
				message($upload['message'], '', 'error');
			}
			$data['icon'] = $upload['path'];
		}
		if (empty($id)) {
			pdo_insert('site_nav', $data);
		} else {
			pdo_update('site_nav', $data, array('id' => $id));
		}
		message('导航更新成功！', create_url('site/nav'), 'success');
	}
	$modules = pdo_fetchall("SELECT name, title FROM ".tablename('modules')." WHERE home = '1' OR profile = '1'", array(), 'name');
	template('site/menu_post');
} elseif ($do == 'delete') {
	$id = intval($_GPC['id']);
	$item = pdo_fetch("SELECT * FROM ".tablename('site_nav')." WHERE id = :id" , array(':id' => $id));
	if (empty($item)) {
		message('抱歉，导航不存在或是已经删除！', '', 'error');
	}
	if (!empty($item['icon'])) {
		file_delete($item['icon']);
	}
	pdo_delete('site_nav', array('id' => $id));
	message('导航更新成功！', create_url('site/nav'), 'success');
} elseif ($do == 'module') {
	$modulename = $_GPC['name'];
	$keyword = $_GPC['keyword'];
	$position = intval($_GPC['position']);
	if (empty($modulename)) {
		message('模块不存在或是已经被删除！', '', 'ajax');
	}
	$module = WeUtility::createModuleSite($modulename);
    $module->module = array_merge($_W['modules'][$modulename], $_W['account']['modules'][$_W['modules'][$modulename]['mid']]);
    $module->weid = $_W['weid'];
    $module->inMobile = false;

	$result = array();
	$result['home'] = $module->getHomeTiles($keyword);
	$result['profile'] = $module->getProfileTiles();
	template('site/menu_module');
} else {
	if (checksubmit('submit')) {
		if (!empty($_GPC['name'])) {
			foreach ($_GPC['name'] as $id => $row) {
				if (empty($row)) {
					continue;
				}
				pdo_update('site_nav', array(
					'name' => $row,
					'displayorder' => $_GPC['displayorder'][$id],
					'status' => intval($_GPC['status'][$id]),
				), array('id' => $id));
			}
		}
		message('导航更新成功！', create_url('site/nav'), 'success');
	}
	$navs = pdo_fetchall("SELECT * FROM ".tablename('site_nav')." WHERE weid = '{$_W['weid']}' ORDER BY displayorder DESC");
	if (!empty($navs)) {
		foreach ($navs as &$row) {
			$row['css'] = unserialize($row['css']);
		}
		unset($row);
	}
	template('site/menu_display');
}