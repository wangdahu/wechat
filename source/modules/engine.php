<?php
/**
 * 微擎模块核心类
 *
 * [WeEngine System] Copyright (c) 2013 WE7.CC
 */
defined('IN_IA') or exit('Access Denied');

class WeEngine {

	private $token = '';
	private $events = array();
	private $modules = array();
	private $matcher = null;
	public $message = array();
	public $response = array();
	public $keyword = array();
	public $context = array();
	
	public function __construct() {
		global $_W;
		$this->token = $_W['account']['token'];
		$this->modules = array_keys($_W['account']['modules']);
		$this->modules[] = 'welcome';
		$this->modules[] = 'default';
		$this->modules = array_unique($this->modules);
	}

	public function start() {
		global $_W;
		if(empty($this->token)) {
			exit('Access Denied');
		}
		if(!WeUtility::checkSign($this->token)) {;
			exit('Access Denied');
		}
		if(strtolower($_SERVER['REQUEST_METHOD']) == 'get') {
			exit($_GET['echostr']);
		}
		if(strtolower($_SERVER['REQUEST_METHOD']) == 'post') {
			$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
			$this->message = WeUtility::parse($postStr);
			if (empty($this->message)) {
				WeUtility::logging('waring', 'Request Failed');
				exit('Request Failed');
			}

			$sessionid = md5($this->message['from'] . $this->message['to']);
			session_id($sessionid);
			WeSession::$weid = $_W['weid'];
			WeSession::$from = $this->message['from'];
			WeSession::$expire = 3600;
			WeSession::start();
			
			WeUtility::logging('trace', $this->message);
			$this->response = $this->matcher();
			$this->response['content'] = $this->process();
			if(empty($this->response['content']) || (is_array($this->response['content']) && $this->response['content']['type'] == 'text' && empty($this->response['content']['content'])) || (is_array($this->response['content']) && $this->response['content']['type'] == 'news' && empty($this->response['content']['items']))) {
				$this->response['module'] = 'default';
				$this->response['content'] = $this->process();
			}
			WeUtility::logging('response', $this->response);
			$resp = WeUtility::response($this->response['content']);
			$mapping = array(
				'[from]' => $this->message['from'],
				'[to]' => $this->message['to'],
				'[rule]' => $this->response['rule']
			);
			echo str_replace(array_keys($mapping), array_values($mapping), $resp);
			
			$subscribes = array();
			foreach($_W['account']['modules'] as $m) {
				if(in_array($m['name'], $this->modules) && is_array($m['subscribes']) && !empty($m['subscribes'])) {
					$subscribes[] = $m;
				}
			}
			if(!empty($subscribes)) {
				register_shutdown_function(array(&$this, 'subscribe'), $subscribes);
			}
			exit();
		}
		WeUtility::logging('waring', 'Request Failed');
		exit('Request Failed');
	}

	private function subscribe($subscribes) {
		global $_W;
		foreach($subscribes as $m) {
			$obj = WeUtility::createModuleReceiver($m['name']);
			$obj->message = $this->message;
			$obj->params = $this->response;
			$obj->response = $this->response['content'];
			$obj->keyword = $this->keyword;
			$obj->module = $m;
			unset($this->params['content']);
			$obj->receive();
		}
	}

	private function matcher() {
		global $_W;
		$response = array('module' => '', 'rule' => '');
		//处理置顶
		if ($this->message['msgtype'] == 'text') {
			$keywords = cache_load("keywordtop:{$_W['weid']}");
			$match = array();
			if (!empty($keywords)) {
				foreach ($keywords as $keyword) {
					if ($keyword['type'] == '1' && $keyword['content'] == $this->message['content']) {
						$match = $keyword;
						break;
					} elseif ($keyword['type'] == '2' && stripos($this->message['content'], $keyword['content']) !== false) {
						$match = $keyword;
						break;
					} elseif ($keyword['type'] == '3' && @preg_match($keyword['content'], $this->message['content'], $match) !== 0) {
						$match = $keyword;
						break;
					}
				}
			}
			if (!empty($match)) {
				$response = array('module' => $match['module'], 'rule' => $match['rid']);
				return $response;
			}
		}
		if (!empty($_SESSION['__contextmodule']) && in_array($_SESSION['__contextmodule'], $this->modules)) {
			if($_SESSION['__contextexpire'] > TIMESTAMP) {
				$response = array('module' => $_SESSION['__contextmodule'], 'rule' => $_SESSION['__contextrule'], 'context' => true);
				return $response;
			} else {
				unset($_SESSION);
				session_destroy();
			}
		}
		if (method_exists($this, 'matcher'.$this->message['msgtype'])) {
			$response = call_user_func(array($this, 'matcher'.$this->message['msgtype']));
		}
		return $response;
	}
	/**
	 * 事件模块、规则匹配器
	 */
	private function matcherEvent() {
		$response = array('module' => '', 'rule' => '');
		//订阅
		if($this->message['event'] == 'subscribe') {
			$response['module'] = 'welcome';
		}
		//退订
		if($this->message['event'] == 'unsubscribe') {
			
		}
		//扫描条码
		if($this->message['event'] == 'scan') {

		}
		//位置
		if($this->message['event'] == 'location') {

		}
		//菜单
		if($this->message['event'] == 'CLICK') {
			list($module, $rid, $content) = explode(':', $this->message['eventkey'], 3);
			if (!empty($rid) && !empty($module)) {
				$response['module'] = $module;
				$response['rule'] = $rid;
				$this->message['content'] = @urldecode($content);
			}
		}
		return $response;
	}

	private function matcherText() {
		$response = array('module' => '', 'rule' => '');
		$input = $this->message['content'];
		if (!isset($input)) {
			return $response;
		}
		global $_W;
		/*
		 * @TODO 需要增加缓存
		 */
		$condition = "`weid`='{$_W['weid']}'";
		$keywords = rule_keywords_search($condition . " AND `content` = '" . addslashes($input) . "'  AND (`type` = '1' OR `type` = '2')");
		if (empty($keywords)) {
			$needles = rule_keywords_search($condition . " AND `type` = '3'");
			foreach($needles as $needle) {
				if(@preg_match($needle['content'], $input, $match) !== 0) {
					$keywords[] = array(
						'id' => $needle['id'],
						'rid' => $needle['rid'],
						'content' => $needle['content'],
						'type' => $needle['type'],
						'module' => $needle['module'],
						'weid' => $needle['weid'],
					);
					break;
				}
			}
			if (empty($keywords)) {
				$keywords = pdo_fetchall("SELECT * FROM " . tablename('rule_keyword') . " WHERE status = '1' AND $condition AND `type` = '2' AND INSTR('{$input}', `content`) > 0 ORDER BY displayorder DESC, id DESC LIMIT 100");
			}
		}
		if (empty($keywords)) {
			return $response;
		}
		if (count($keywords) > 1) {
			srand((float) microtime() * 10000000);
			$index = array_rand($keywords);
			$this->keyword = $keywords[$index];
		} else {
			$this->keyword = $keywords[0];
		}
		$response = array(
			'module' => $this->keyword['module'],
			'rule' => $this->keyword['rid'],
		);
		return $response;
	}

	private function matcherImage() {
		$response = array('module' => '', 'rule' => '');
		global $_W;
		$df = $_W['account']['default_message'];
		if(is_array($df) && in_array($df['image'], $this->modules)) {
			return array('module' => $df['image'], 'rule' => '-1');
		}
		return $response;
	}

	private function matcherVoice() {
		$response = array('module' => '', 'rule' => '');
		global $_W;
		$df = $_W['account']['default_message'];
		if(is_array($df) && in_array($df['voice'], $this->modules)) {
			return array('module' => $df['voice'], 'rule' => '-1');
		}
		return $response;
	}

	private function matcherVideo() {
		$response = array('module' => '', 'rule' => '');
		global $_W;
		$df = $_W['account']['default_message'];
		if(is_array($df) && in_array($df['video'], $this->modules)) {
			return array('module' => $df['video'], 'rule' => '-1');
		}
		return $response;
	}

	private function matcherLocation() {
		$response = array('module' => '', 'rule' => '');
		global $_W;
		$df = $_W['account']['default_message'];
		if(is_array($df) && in_array($df['location'], $this->modules)) {
			return array('module' => $df['location'], 'rule' => '-1');
		}
		return $response;
	}

	private function matcherLink() {
		$response = array('module' => '', 'rule' => '');
		global $_W;
		$df = $_W['account']['default_message'];
		if(is_array($df) && in_array($df['link'], $this->modules)) {
			return array('module' => $df['link'], 'rule' => '-1');
		}
		return $response;
	}

	private function process() {
		global $_W;
		$response = false;
		if (empty($this->response['module']) || !in_array($this->response['module'], $this->modules)) {
			return false;
		}
		$processor = WeUtility::createModuleProcessor($this->response['module']);
		$processor->message = $this->message;
		$processor->rule = $this->response['rule'];
		$processor->module = $_W['account']['modules'][$this->response['module']];
		$processor->inContext = $this->response['context'] === true;
		$response = $processor->respond();
		if(empty($response)) {
			return false;
		}
		return $response;
	}
}

class WeSession {
	public static $weid;
	public static $from;
	public static $expire;

	public static function start() {
		$sess = new WeSession();
		session_set_save_handler(array(&$sess, 'open'), array(&$sess, 'close'), array(&$sess, 'read'), array(&$sess, 'write'), array(&$sess, 'destroy'), array(&$sess, 'gc'));
		session_start();
	}

	public function open() {
		return true;
	}

	public function close() {
		return true;
	}

	public function read($sessionid) {
		$sql = 'SELECT * FROM ' . tablename('sessions') . ' WHERE `sid`=:sessid AND `expiretime`>:time';
		$params = array();
		$params[':sessid'] = $sessionid;
		$params[':time'] = TIMESTAMP;
		$row = pdo_fetch($sql, $params);
		if(is_array($row) && !empty($row['data'])) {
			return $row['data'];
		}
		return false;
	}

	public function write($sessionid, $data) {
		$row = array();
		$row['sid'] = $sessionid;
		$row['weid'] = WeSession::$weid;
		$row['from_user'] = WeSession::$from;
		$row['data'] = $data;
		$row['expiretime'] = TIMESTAMP + WeSession::$expire;
		return pdo_insert('sessions', $row, true) == 1;
	}

	public function destroy($sessionid) {
		$row = array();
		$row['sid'] = $sessionid;
		pdo_delete('sessions', $row) == 1;
	}

	public function gc($expire) {
		$sql = 'DELETE FROM ' . tablename('sessions') . ' WHERE `expiretime`<:expire';
		return pdo_query($sql, array(':expire' => TIMESTAMP)) == 1;
	}
}

class WeUtility {
	public static function rootPath() {
		static $path;
		if(empty($path)) {
			$path = dirname(__FILE__);
			$path = str_replace('\\', '/', $path);
		}
		return $path;
	}

	public static function checkSign($token) {
		$signkey = array($token, $_GET['timestamp'], $_GET['nonce']);
		sort($signkey);
		$signString = implode($signkey);
		$signString = sha1($signString);
		if($signString == $_GET['signature']){
			return true;
		}else{
			return false;
		}
	}

	public static function createModuleProcessor($name) {
		$classname = "{$name}ModuleProcessor";
		if(!class_exists($classname)) {
			$file = WeUtility::rootPath() . "/{$name}/processor.php";
			if(!is_file($file)) {
				trigger_error('ModuleProcessor Definition File Not Found '.$file, E_USER_ERROR);
				return null;
			}
			require $file;
		}
		if(!class_exists($classname)) {
			trigger_error('ModuleProcessor Definition Class Not Found', E_USER_ERROR);
			return null;
		}
		$o = new $classname();
		if($o instanceof WeModuleProcessor) {
			return $o;
		} else {
			trigger_error('ModuleProcessor Class Definition Error', E_USER_ERROR);
			return null;
		}
	}

	public static function createModuleReceiver($name) {
		$classname = "{$name}ModuleReceiver";
		if(!class_exists($classname)) {
			$file = WeUtility::rootPath() . "/{$name}/receiver.php";
			if(!is_file($file)) {
				trigger_error('ModuleReceiver Definition File Not Found '.$file, E_USER_ERROR);
				return null;
			}
			require $file;
		}
		if(!class_exists($classname)) {
			trigger_error('ModuleReceiver Definition Class Not Found', E_USER_ERROR);
			return null;
		}
		$o = new $classname();
		if($o instanceof WeModuleReceiver) {
			return $o;
		} else {
			trigger_error('ModuleReceiver Class Definition Error', E_USER_ERROR);
			return null;
		}
	}

	public static function createModuleSite($name) {
		$classname = "{$name}ModuleSite";
		if(!class_exists($classname)) {
			$file = WeUtility::rootPath() . "/{$name}/site.php";
			if(!is_file($file)) {
				trigger_error('ModuleSite Definition File Not Found '.$file, E_USER_ERROR);
				return null;
			}
			require $file;
		}
		if(!class_exists($classname)) {
			trigger_error('ModuleSite Definition Class Not Found', E_USER_ERROR);
			return null;
		}
		$o = new $classname();
		if($o instanceof WeModuleSite) {
			return $o;
		} else {
			trigger_error('ModuleReceiver Class Definition Error', E_USER_ERROR);
			return null;
		}
	}

	/**
	 * 分析请求数据
	 * @param string $request 接口提交的请求数据
	 * 具体数据格式与微信接口XML结构一致
	 *
	 * @return array 请求数据结构
	 */
	public static function parse($message) {
		$packet = array();
		if (!empty($message)){
			$obj = simplexml_load_string($message, 'SimpleXMLElement', LIBXML_NOCDATA);
			if($obj instanceof SimpleXMLElement) {
				$packet['from'] = strval($obj->FromUserName);
				$packet['to'] = strval($obj->ToUserName);
				$packet['time'] = strval($obj->CreateTime);
				$packet['type'] = strval($obj->MsgType);
				$packet['event'] = strval($obj->Event);

				foreach ($obj as $variable => $property) {
					$packet[strtolower($variable)] = (string)$property;
				}
				if($packet['type'] == 'event') {
					$packet['type'] = $packet['event'];
					unset($packet['content']);
				}
			}
		}
		return $packet;
	}

	/**
	 * 按照响应内容组装响应数据
	 * @param array $packet 响应内容
	 *
	 * @return string
	 */
	public static function response($packet) {
		if (!is_array($packet)) {
			return $packet;
		}
		if(empty($packet['CreateTime'])) {
			$packet['CreateTime'] = time();
		}
		if(empty($packet['MsgType'])) {
			$packet['MsgType'] = 'text';
		}
		if(empty($packet['FuncFlag'])) {
			$packet['FuncFlag'] = 0;
		} else {
			$packet['FuncFlag'] = 1;
		}
		return self::array2xml($packet);
	}

	public static function logging($level = 'info', $message = '') {
		if(!DEVELOPMENT) {
			return true;
		}
		$filename = IA_ROOT . '/data/logs/' . date('Ymd') . '.log';
		mkdirs(dirname($filename));
		$content = date('Y-m-d H:i:s') . " {$level} :\n------------\n";
		if(is_string($message)) {
			$content .= "String:\n{$message}\n";
		}
		if(is_array($message)) {
			$content .= "Array:\n";
			foreach($message as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		if($message == 'get') {
			$content .= "GET:\n";
			foreach($_GET as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		if($message == 'post') {
			$content .= "POST:\n";
			foreach($_POST as $key => $value) {
				$content .= sprintf("%s : %s ;\n", $key, $value);
			}
		}
		$content .= "\n";

		$fp = fopen($filename, 'a+');
		fwrite($fp, $content);
		fclose($fp);
	}

	public static function array2xml($arr, $level = 1, $ptagname = '') {
		$s = $level == 1 ? "<xml>" : '';
		foreach($arr as $tagname => $value) {
			if (is_numeric($tagname)) {
				$tagname = $value['TagName'];
				unset($value['TagName']);
			}
			if(!is_array($value)) {
				$s .= "<{$tagname}>".(!is_numeric($value) ? '<![CDATA[' : '').$value.(!is_numeric($value) ? ']]>' : '')."</{$tagname}>";
			} else {
				$s .= "<{$tagname}>".self::array2xml($value, $level + 1)."</{$tagname}>";
			}
		}
		$s = preg_replace("/([\x01-\x08\x0b-\x0c\x0e-\x1f])+/", ' ', $s);
		return $level == 1 ? $s."</xml>" : $s;
	}
}

abstract class WeModule {
	public $modulename;
	public function fieldsFormDisplay($rid = 0) {
		return '';
	}
	public function fieldsFormValidate($rid = 0) {
		return '';
	}
	public function fieldsFormSubmit($rid) {
		//
	}
	public function ruleDeleted($rid) {
		return true;
	}
	public function settingsDisplay($settings) {
		//
	}
	protected function saveSettings($settings) {
		$weid = $this->_saveing_params['weid'];
		$mid = $this->_saveing_params['mid'];
		if(empty($weid) || empty($mid)) {
			message('访问出错, 请返回重试. ');
		}
		if (pdo_fetchcolumn("SELECT mid FROM ".tablename('wechats_modules')." WHERE mid = :mid AND weid = :weid", array(':mid' => $mid, ':weid' => $weid))) {
			return pdo_update('wechats_modules', array('settings' => iserializer($settings)), array('weid' => $weid, 'mid' => $mid)) === 1;
		} else {
			return pdo_insert('wechats_modules', array('settings' => iserializer($settings), 'displayorder' => 127, 'mid' => $mid ,'weid' => $weid, 'enabled' => 1)) === 1;
		}
	}
	protected function template($filename, $flag = TEMPLATE_INCLUDEPATH) {
		global $_W;
		$mn = strtolower($this->modulename);
		$source = IA_ROOT . "/source/modules/{$mn}/template/{$filename}.html";
		$compile = "{$_W['template']['compile']}/{$_W['template']['current']}/modules/{$mn}/{$filename}.tpl.php";
		/**
		 * 此处为兼容0.41版本以前的写法
		 */
		if(!is_file($source)) {
			list($path, $file) = explode('/', $filename);
			$source = IA_ROOT . "/source/modules/$path/template/{$file}.html";
			$compile = "{$_W['template']['compile']}/{$_W['template']['current']}/modules/{$path}/{$file}.tpl.php";
		}
		/**
		 * end
		 */
		if(!is_file($source)) {
			$source = "{$_W['template']['source']}/{$_W['template']['current']}/{$filename}.html";
			$compile = "{$_W['template']['compile']}/{$_W['template']['current']}/{$filename}.tpl.php";
		}
		if(!is_file($source)) {
			exit("Error: template source '{$filename}' is not exist!");
		}
		if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
			template_compile($source, $compile, true);
		}
		switch ($flag) {
			case TEMPLATE_DISPLAY:
			default:
				extract($GLOBALS, EXTR_SKIP);
				include $compile;
				break;
			case TEMPLATE_FETCH:
				extract($GLOBALS, EXTR_SKIP);
				ob_start();
				ob_clean();
				include $compile;
				$contents = ob_get_contents();
				ob_clean();
				return $contents;
				break;
			case TEMPLATE_INCLUDEPATH:
				return $compile;
				break;
			case TEMPLATE_CACHE:
				exit('暂未支持');
				break;
		}
	}
}

abstract class WeModuleProcessor {
	public $inContext;
	protected function beginContext($expire = 1800) {
		if($this->inContext) {
			return false;
		}
		$expire = intval($expire);
		WeSession::$expire = $expire;
		$_SESSION['__contextmodule'] = $this->module['name'];
		$_SESSION['__contextrule'] = $this->rule;
		$_SESSION['__contextexpire'] = TIMESTAMP + $expire;
		$this->inContext = true;
		return true;
	}
	protected function refreshContext($expire = 1800) {
		if(!$this->inContext) {
			return false;
		}
		$expire = intval($expire);
		WeSession::$expire = $expire;
		$_SESSION['__contextexpire'] = TIMESTAMP + $expire;
		return true;
	}
	protected function endContext() {
		unset($_SESSION['__contextmodule']);
		unset($_SESSION['__contextrule']);
		unset($_SESSION['__contextexpire']);
		unset($_SESSION);
		session_destroy();
	}
	public $message;
	public $rule;
	public $module;
	abstract function respond();
	protected function respText($content) {
		preg_match_all("/(mobile\.php(?:.*?))['|\"]/", $content, $urls);
		if (!empty($urls[1])) {
			foreach ($urls[1] as $url) {
				$content = str_replace($url, $this->buildSiteUrl($url), $content);
			}
		}
		$content = str_replace("\r\n", "\n", $content);
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'text';
		$response['Content'] = htmlspecialchars_decode($content);
		return $response;
	}
	protected function respImage($mid) {
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'image';
		$response['Image']['MediaId'] = $mid;
		return $response;
	}
	protected function respVoice($mid) {
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'voice';
		$response['Voice']['MediaId'] = $mid;
		return $response;
	}
	protected function respVideo(array $video) {
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'video';
		$response['Video']['MediaId'] = $video['video'];
		$response['Video']['ThumbMediaId'] = $video['thumb'];
		return $response;
	}
	protected function respMusic(array $music) {
		global $_W;
		$music = array_change_key_case($music);
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'music';
		$response['Music'] = array(
			'Title'	=> $music['title'],
			'Description' => $music['description'],
			'MusicUrl' => strpos($music['musicurl'], 'http://') === FALSE ? $_W['attachurl'] . $music['musicurl'] : $music['musicurl'],
		);
		if (empty($music['hqmusicurl'])) {
			$response['Music']['HQMusicUrl'] = $response['Music']['MusicUrl'];
		} else {
			$response['Music']['HQMusicUrl'] = strpos($music['hqmusicurl'], 'http://') === FALSE ? $_W['attachurl'] . $music['hqmusicurl'] : $music['hqmusicurl'];
		}
		$response['Music']['ThumbMediaId'] = $music['thumb'];
		return $response;
	}
	protected function respNews(array $news) {
		$news = array_change_key_case($news);
		if (!empty($news['title'])) {
			$news = array($news);
		}
		$response = array();
		$response['FromUserName'] = $this->message['to'];
		$response['ToUserName'] = $this->message['from'];
		$response['MsgType'] = 'news';
		$response['ArticleCount'] = count($news);
		$response['Articles'] = array();
		foreach ($news as $row) {
			$response['Articles'][] = array(
				'Title' => $row['title'],
				'Description' => $row['description'],
				'PicUrl' => $row['picurl'],
				'Url' => $this->buildSiteUrl($row['url']),
				'TagName' => 'item',
			);
		}
		return $response;
	}
	
	protected function buildSiteUrl($url) {
		global $_W;
		if (!strexists($url, 'mobile.php')) {
			return $url;
		}

		$mapping = array(
			'[from]' => $this->message['from'],
			'[to]' => $this->message['to'],
			'[rule]' => $this->rule
		);
		$url = str_replace(array_keys($mapping), array_values($mapping), $url);

		$vars = array();
		$pass = array();
		$pass['fans'] = $this->message['from'];
		
		$row = fans_search($pass['fans'], array('salt'));
		if(!is_array($row) || empty($row['salt'])) {
			$row = array('salt' => '');
		}
		$pass['time'] = TIMESTAMP;
		$pass['hash'] = md5("{$pass['fans']}{$pass['time']}{$row['salt']}{$_W['config']['setting']['authkey']}");
		$auth = base64_encode(json_encode($pass));
		$vars['weid'] = $_W['weid'];
		$vars['__auth'] = $auth;
		$vars['forward'] = base64_encode($url);
		return $_W['siteroot'] . create_url('mobile/auth', $vars);
	}
	protected function createMobileUrl($do, $querystring = array()) {
		$querystring['name'] = strtolower($this->module['name']);
		$querystring['do'] = $do;
		$querystring['weid'] = $GLOBALS['_W']['weid'];
		return create_url('mobile/module', $querystring);
	}
	
	protected function createWebUrl($do, $querystring = array()) {
		$querystring['name'] = strtolower($this->module['name']);
		$querystring['do'] = $do;
		$querystring['weid'] = $GLOBALS['_W']['weid'];
		return create_url('site/module', $querystring);
	}
}

abstract class WeModuleReceiver {
	public $message;
	public $params;
	public $response;
	public $keyword;
	public $module;
	abstract function receive();
}

abstract class WeModuleSite {
	public $module;
	public $weid;
	public $inMobile;
	public function getHomeTiles() {
		return array();
	}
	public function getProfileTiles() {
		return array();
	}
	
	protected function createMobileUrl($do, $querystring = array()) {
		$querystring['name'] = strtolower($this->module['name']);
		$querystring['do'] = $do;
		$querystring['weid'] = $this->weid;
		return create_url('mobile/module', $querystring);
	}
	
	protected function createWebUrl($do, $querystring = array()) {
		$querystring['name'] = strtolower($this->module['name']);
		$querystring['do'] = $do;
		$querystring['weid'] = $this->weid;
		return create_url('site/module', $querystring);
	}
	
	protected function template($filename, $flag = TEMPLATE_INCLUDEPATH) {
		global $_W;
		$mn = strtolower($this->module['name']);
		$source = IA_ROOT . "/source/modules/{$mn}/template/mobile/{$filename}.html";
		$compile = "{$_W['template']['compile']}/mobile/{$_W['account']['template']}/{$mn}/{$filename}.tpl.php";
		
		if(!is_file($source)) {
			$source = "{$_W['template']['source']}/mobile/{$_W['account']['template']}/{$filename}.html";
			$compile = "{$_W['template']['compile']}/mobile/{$_W['account']['template']}/{$filename}.tpl.php";
			if(!is_file($source)) {
				$source = "{$_W['template']['source']}/mobile/default/{$filename}.html";
				$compile = "{$_W['template']['compile']}/mobile/default/{$filename}.tpl.php";
			}
			
		}
		
		if(!is_file($source)) {
			exit("Error: template source '{$filename}' is not exist!");
		}
		
		if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
			template_compile($source, $compile, true);
		}
		return $compile;
	}
}
