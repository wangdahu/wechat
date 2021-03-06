<?php
/**
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

/**
 * 将设置信息保存至数据库，将会同时更新全局变量 $_W['setting']，过期缓存
 * @param mixed $data 如果提供 $data，则将 $data 做为指定键的 $key 的值来更新
 * @param string $key 如果提供 $key，则至更新指定键名
 * @return void
 */
function setting_save($data = '', $key = '') {
	if (empty($data) && empty($key)) {
		return FALSE;
	}
	if (is_array($data) && empty($key)) {
		foreach ($data as $key => $value) {
			$record[] = "('$key', '".iserializer($value)."')";
		}
		if ($record) {
			$return = pdo_query("REPLACE INTO ".tablename('settings')." (`key`, `value`) VALUES " . implode(',', $record));
		}
	} else {
		$record = array();
		$record['key'] = $key;
		$record['value'] = iserializer($data);
		$return = pdo_insert('settings', $record, TRUE);
	}
	cache_build_setting();
	return $return;
}

function setting_cache_account_by_uid($uid) {
	global $_W;
	$wechats = account_search($uid);
	if (!empty($wechats)) {
		foreach ($wechats as $weid => $wechat) {
			cache_build_category($weid);
		}
	}
	
}
function setting_cache_account_by_founder() {
	$users = pdo_fetchall("SELECT uid FROM ".tablename('members') . " WHERE status > '-1'", array(), 'uid');
	if (!empty($users)) {
		foreach ($users as $uid => $user) {
			setting_cache_account_by_uid($uid);
		}
	}
}

function setting_module_convert($manifest) {
	$module = array(
		'name' => $manifest['application']['identifie'],
		'title' => $manifest['application']['name'],
		'version' => $manifest['application']['version'],
		'ability' => $manifest['application']['ability'],
		'description' => $manifest['application']['description'],
		'author' => $manifest['application']['author'],
		'url' => $manifest['application']['url'],
		'settings'  => intval($manifest['application']['setting']),
		'subscribes' => iserializer(is_array($manifest['platform']['subscribes']) ? $manifest['platform']['subscribes'] : array()),
		'handles' => iserializer(is_array($manifest['platform']['handles']) ? $manifest['platform']['handles'] : array()),
		'isrulefields' => intval($manifest['platform']['isrulefields']),
		'options' => iserializer(is_array($manifest['platform']['options']) ? $manifest['platform']['options'] : array()),
		'platform_menus' => iserializer($manifest['platform']['ismenus'] && is_array($manifest['platform']['menus']) ? $manifest['platform']['menus'] : array()),
		'home' => intval($manifest['site']['home']),
		'profile' => intval($manifest['site']['profile']),
		'site_menus' => iserializer($manifest['site']['ismenus'] && is_array($manifest['site']['menus']) ? $manifest['site']['menus'] : array()),
		'issystem' => 0,
	);
	return $module;
}

function setting_module_manifest($modulename) {
	$manifest = array();
	$filename = IA_ROOT . '/source/modules/' . $modulename . '/manifest.xml';
	if (!file_exists($filename)) {
		return array();
	}
	$xml = str_replace(array('&'), array('&amp;'), file_get_contents($filename));
	$xml = @simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
	if (empty($xml)) {
		return array();
	}
	$dom = new DOMDocument();
	@$dom->load($filename);
	if(@$dom->schemaValidateSource(setting_module_manifest_validate())) {
		// 0.42xml
		$attributes = $xml->attributes();
		$manifest['versions'] = explode(',', strval($attributes['versionCode']));
		if(is_array($manifest['versions'])) {
			foreach($manifest['versions'] as &$v) {
				$v = trim($v);
				if(empty($v)) {
					unset($v);
				}
			}
		}
		$manifest['install'] = strval($xml->install);
		$manifest['uninstall'] = strval($xml->uninstall);
		$manifest['upgrade'] = strval($xml->upgrade);
		$attributes = $xml->application->attributes();
		$manifest['application'] = array(
			'name' => trim(strval($xml->application->name)),
			'identifie' => trim(strval($xml->application->identifie)),
			'version' => trim(strval($xml->application->version)),
			'ability' => trim(strval($xml->application->ability)),
			'description' => trim(strval($xml->application->description)),
			'author' => trim(strval($xml->application->author)),
			'url' => trim(strval($xml->application->url)),
			'setting' => trim(strval($attributes['setting'])) == 'true',
		);
		$rAttrs = array();
		if($xml->platform && $xml->platform->rule) {
			$rAttrs = $xml->platform->rule->attributes();
		}
		$mAttrs = array();
		if($xml->platform && $xml->platform->menus) {
			$mAttrs = $xml->platform->menus->attributes();
		}
		$manifest['platform'] = array(
			'subscribes' => array(),
			'handles' => array(),
			'isrulefields' => trim(strval($rAttrs['embed'])) == 'true',
			'options' => array(),
			'ismenus' => trim(strval($mAttrs['embed'])) == 'true',
			'menus' => array()
		);
		if($xml->platform->subscribes->message) {
			foreach($xml->platform->subscribes->message as $msg) {
				$attrs = $msg->attributes();
				$manifest['platform']['subscribes'][] = trim(strval($attrs['type']));
			}
		}
		if($xml->platform->handles->message) {
			foreach($xml->platform->handles->message as $msg) {
				$attrs = $msg->attributes();
				$manifest['platform']['handles'][] = trim(strval($attrs['type']));
			}
		}
		if($manifest['platform']['isrulefields'] && $xml->platform->rule->option) {
			foreach($xml->platform->rule->option as $msg) {
				$attrs = $msg->attributes();
				$manifest['platform']['options'][] = array('title' => trim(strval($attrs['title'])), 'do' => trim(strval($attrs['do'])), 'state' => trim(strval($attrs['state'])));
			}
		}
		if($manifest['platform']['ismenus'] && $xml->platform->menus->menu) {
			foreach($xml->platform->menus->menu as $msg) {
				$attrs = $msg->attributes();
				$manifest['platform']['menus'][] = array('title' => trim(strval($attrs['title'])), 'do' => trim(strval($attrs['do'])));
			}
		}
		$hAttrs = array();
		if($xml->site && $xml->site->home) {
			$hAttrs = $xml->site->home->attributes();
		}
		$pAttrs = array();
		if($xml->site && $xml->site->profile) {
			$pAttrs = $xml->site->profile->attributes();
		}

		$mAttrs = array();
		if($xml->site && $xml->site->menus) {
			$mAttrs = $xml->site->menus->attributes();
		}
		$manifest['site'] = array(
			'home' => trim(strval($hAttrs['embed'])) == 'true',
			'profile' => trim(strval($pAttrs['embed'])) == 'true',
			'ismenus' => trim(strval($mAttrs['embed'])) == 'true',
			'menus' => array()
		);
		if($manifest['site']['ismenus'] && $xml->site->menus->menu) {
			foreach($xml->site->menus->menu as $msg) {
				$attrs = $msg->attributes();
				$manifest['site']['menus'][] = array('title' => trim(strval($attrs['title'])), 'do' => trim(strval($attrs['do'])));
			}
		}
	} else {
		$err = error_get_last();
		if($err['type'] == 2) {
			return $err['message'];
		}
	}
	return $manifest;
}

function setting_module_manifest_validate() {
	$xsd = <<<TPL
<?xml version="1.0" encoding="utf-8"?>
<xs:schema xmlns="http://www.we7.cc" xmlns:xs="http://www.w3.org/2001/XMLSchema">
	<xs:element name="manifest">
		<xs:complexType>
			<xs:sequence>
				<xs:element name="application" minOccurs="1" maxOccurs="1">
					<xs:complexType>
						<xs:sequence>
							<xs:element name="name" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="identifie" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="version" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="ability" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="description" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="author" type="xs:string"  minOccurs="1" maxOccurs="1" />
							<xs:element name="url" type="xs:string"  minOccurs="1" maxOccurs="1" />
						</xs:sequence>
						<xs:attribute name="setting" type="xs:boolean" />
					</xs:complexType>
				</xs:element>
				<xs:element name="platform" minOccurs="0" maxOccurs="1">
					<xs:complexType>
						<xs:sequence>
							<xs:element name="subscribes" minOccurs="0" maxOccurs="1">
								<xs:complexType>
									<xs:sequence>
										<xs:element name="message" minOccurs="0" maxOccurs="unbounded">
											<xs:complexType>
												<xs:attribute name="type" type="xs:string" />
											</xs:complexType>
										</xs:element>
									</xs:sequence>
								</xs:complexType>
							</xs:element>
							<xs:element name="handles" minOccurs="0" maxOccurs="1">
								<xs:complexType>
									<xs:sequence>
										<xs:element name="message" minOccurs="0" maxOccurs="unbounded">
											<xs:complexType>
												<xs:attribute name="type" type="xs:string" />
											</xs:complexType>
										</xs:element>
									</xs:sequence>
								</xs:complexType>
							</xs:element>
							<xs:element name="rule" minOccurs="0" maxOccurs="1">
								<xs:complexType>
									<xs:sequence>
										<xs:element name="option" minOccurs="0" maxOccurs="unbounded">
											<xs:complexType>
												<xs:attribute name="title" type="xs:string" />
												<xs:attribute name="do" type="xs:string" />
												<xs:attribute name="state" type="xs:string" />
											</xs:complexType>
										</xs:element>
									</xs:sequence>
									<xs:attribute name="embed" type="xs:boolean" />
								</xs:complexType>
							</xs:element>
							<xs:element name="menus" minOccurs="0" maxOccurs="1">
								<xs:complexType>
									<xs:sequence>
										<xs:element name="menu" minOccurs="0" maxOccurs="unbounded">
											<xs:complexType>
												<xs:attribute name="title" type="xs:string" />
												<xs:attribute name="do" type="xs:string" />
											</xs:complexType>
										</xs:element>
									</xs:sequence>
									<xs:attribute name="embed" type="xs:boolean" />
								</xs:complexType>
							</xs:element>
						</xs:sequence>
					</xs:complexType>
				</xs:element>
				<xs:element name="site" minOccurs="0" maxOccurs="1">
					<xs:complexType>
						<xs:sequence>
							<xs:element name="home" minOccurs="1" maxOccurs="1">
								<xs:complexType>
									<xs:attribute name="embed" type="xs:boolean" />
								</xs:complexType>
							</xs:element>
							<xs:element name="profile" minOccurs="1" maxOccurs="1">
								<xs:complexType>
									<xs:attribute name="embed" type="xs:boolean" />
								</xs:complexType>
							</xs:element>
							<xs:element name="menus" minOccurs="1" maxOccurs="1">
								<xs:complexType>
									<xs:sequence>
										<xs:element name="menu" minOccurs="0" maxOccurs="unbounded">
											<xs:complexType>
												<xs:attribute name="title" type="xs:string" />
												<xs:attribute name="do" type="xs:string" />
											</xs:complexType>
										</xs:element>
									</xs:sequence>
									<xs:attribute name="embed" type="xs:boolean" />
								</xs:complexType>
							</xs:element>
						</xs:sequence>
					</xs:complexType>
				</xs:element>
				<xs:element name="install" type="xs:string" minOccurs="1" maxOccurs="1"/>
				<xs:element name="uninstall" type="xs:string" minOccurs="1" maxOccurs="1" />
				<xs:element name="upgrade" type="xs:string" minOccurs="1" maxOccurs="1" />
			</xs:sequence>
			<xs:attribute name="versionCode" type="xs:string" />
		</xs:complexType>
	</xs:element>
</xs:schema>
TPL;
	return $xsd;
}
