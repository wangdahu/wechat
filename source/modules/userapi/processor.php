<?php
/**
 * 调用第三方数据接口处理类
 * 
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

class UserapiModuleProcessor extends WeModuleProcessor {

	private function procLocal($item) {
		$local = basename($item['apiurl']);
		$file = IA_ROOT . '/source/modules/userapi/api/' . $local;

		if (!file_exists($file)) {
			return array();
		}
		return include $file;
	}

	private function procRemote($item) {
		if (!strexists($item['apiurl'], '?')) {
			$item['apiurl'] .= '?';
		} else {
			$item['apiurl'] .= '&';
		}
		
		$sign = array(
			'timestamp' => TIMESTAMP,
			'nonce' => random(10, 1),
		);
		$signkey = array($item['token'], $sign['timestamp'], $sign['nonce']);
		sort($signkey, SORT_STRING);
		$sign['signature'] = sha1(implode($signkey));
		$item['apiurl'] .= http_build_query($sign, '', '&');
		$response = ihttp_request($item['apiurl'], $GLOBALS["HTTP_RAW_POST_DATA"], array('CURLOPT_HTTPHEADER' => array('Content-Type: text/xml; charset=utf-8')));
		$result = array();
		if ($response['code'] == '200') {
			$temp = json_decode($response['content'], true);
			if (is_array($temp)) {
				$result = $this->buildResponse($temp);
			} else {
				$result = $response['content'];
			}
			if(stristr($result, '{begin-context}') !== false) {
				$this->beginContext(0);
				$result = str_ireplace('{begin-context}', '', $result);
			}
			if(stristr($result, '{end-context}') !== false) {
				$this->endContext();
				$result = str_ireplace('{end-context}', '', $result);
			}
			return $result;
		} else {
			return array();
		}
	}

	public function respond() {
		global $_W;
		$rid = $this->rule;
		if($this->inContext) {
			$rid = $_SESSION['userapi-rid'];
		}
		$item = array();
		if (!empty($rid)) {
			$sql = "SELECT * FROM " . tablename('userapi_reply') . " WHERE `rid`=:rid ORDER BY id DESC limit 1";
			$item = pdo_fetch($sql, array(':rid' => $rid));
			if (empty($item['id'])) {
				return array();
			}
		}
		if(empty($item)) {
			$module = $_W['modules']['userapi'];
			$module['settings'] = iunserializer($module['settings']);
			$item['apiurl'] = $module['settings']['apiurl'];
			$item['default-text'] = $module['settings']['default'];
		}
		if ($item['cachetime'] > 0) {
			$key = md5($item['id'].$this->message['from']);
			$cache = pdo_fetch("SELECT * FROM " . tablename('userapi_cache') . " WHERE `key` = '$key' LIMIT 1");
			if (!empty($cache) && TIMESTAMP - $cache['lastupdate'] <= $item['cachetime']) {
				return iunserializer($cache['content']);
			}	
		}
		$result = array();
		if (!strexists($item['apiurl'], 'http://') && !strexists($item['apiurl'], 'https://')) {
			$result = $this->procLocal($item);
		} else {
			$result = $this->procRemote($item);
		}
		if(empty($result) && !empty($item['default_text'])) {
			$result = $this->respText($item['default_text']);
		}
		if (!empty($result) && is_array($result)) {
			$result['FromUserName'] = $this->message['to'];
			$result['ToUserName'] = $this->message['from'];

			if ($item['cachetime'] > 0) {
				if (empty($cache)) {
					pdo_insert('userapi_cache', array('key' => $key, 'content' => iserializer($result), 'lastupdate' => TIMESTAMP));
				} else {
					pdo_update('userapi_cache', array('content' => iserializer($result), 'lastupdate' => TIMESTAMP), array('key' => $key));
				}
			}
		}
		return $result;
	}
	
	private function buildResponse($data = array()) {
		$result = array();
		$result['MsgType'] = $data['type'];
		$data = $data['content'];
		
		if ($result['MsgType'] == 'text') {
			$result['Content'] = $data;
		} elseif ($result['MsgType'] == 'news') {
			$result['ArticleCount'] = $data['ArticleCount'];
			$result['Articles'] = array();
			if (!isset($data[0])) {
				$temp[0] = $data;
				$data = $temp;
			}
			foreach ($data as $row) {
				$result['Articles'][] = array(
					'Title' => $row['Title'],
					'Description' => $row['Description'],
					'PicUrl' => $row['PicUrl'],
					'Url' => $row['Url'],
					'TagName' => 'item',
				);
			}
		} elseif ($result['MsgType'] == 'music') {
			$result['Music'] = array(
				'Title'	=> $data['Title'],
				'Description' => $data['Description'],
				'MusicUrl' => $data['MusicUrl'],
				'HQMusicUrl' => $data['HQMusicUrl'],
			);
		}
		return $result;
	}

	protected function beginContext($expire = 3600) {
		if(!$this->inContext) {
			$_SESSION['userapi-rid'] = $this->rule;
			parent::beginContext($expire);
		}
	}
	
}
