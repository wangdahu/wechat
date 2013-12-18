<?php
/**
 * 调用第三方数据接口模块
 *
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

class UserapiModule extends WeModule {
	public $tablename = 'userapi_reply';

	public function fieldsFormDisplay($rid = 0) {
		global $_W;
		if (!empty($rid)) {
			$row = pdo_fetch("SELECT * FROM ".tablename($this->tablename)." WHERE rid = :rid ORDER BY `id` DESC", array(':rid' => $rid));
			if (!strexists($row['apiurl'], 'http://') && !strexists($row['apiurl'], 'https://')) {
				$row['apilocal'] =  $row['apiurl'];
				$row['apiurl'] = '';
			}

		} else {
			$row = array(
				'cachetime' => 0,
			);
		}

		$path = IA_ROOT . '/source/modules/userapi/api';
		if (is_dir($path)) {
			$apis = array();
			if ($handle = opendir($path)) {
				while (false !== ($file = readdir($handle))) {
					if ($file != "." && $file != "..") {
						$apis[] = $file;
					}
				}
			}
		}
		include $this->template('form');
	}

	public function fieldsFormValidate($rid = 0) {
		global $_GPC;
		if (($_GPC['type'] && empty($_GPC['apiurl'])) || (empty($_GPC['type']) && empty($_GPC['apilocal']))) {
			message('请填写接口地址！', create_url('rule/post', array('module' => $_GPC['module'])), 'error');
		}
		if ($_GPC['type'] && empty($_GPC['token'])) {
			message('请填写Token值！', create_url('rule/post', array('module' => $_GPC['module'])), 'error');
		}
		return true;
	}

	public function fieldsFormSubmit($rid = 0) {
		global $_GPC, $_W;
		$id = intval($_GPC['reply_id']);
		$insert = array(
			'rid' => $rid,
			'apiurl' => empty($_GPC['type']) ? $_GPC['apilocal'] : $_GPC['apiurl'],
			'token' => $_GPC['wetoken'],
			'default_text' => $_GPC['default-text'],
			'cachetime' => intval($_GPC['cachetime']),
		);
		if (!empty($insert['apiurl'])) {
			if (empty($id)) {
				pdo_insert($this->tablename, $insert);
			} else {
				pdo_update($this->tablename, $insert, array('id' => $id));
			}
		}
		return true;
	}

	public function ruleDeleted($rid = 0) {
		pdo_delete($this->tablename, array('rid' => $rid));
	}

	public function settingsFormDisplay($settings = array()) {
		include $this->template('userapi/setting');
	}
}
