<?php
error_reporting(0);
@set_time_limit(1000);
@set_magic_quotes_runtime(0);
ob_start();

define('IN_IA', true);
define('IA_ROOT', str_replace("\\",'/', dirname(dirname(__FILE__))));

require IA_ROOT . '/install/include/global.func.php';
require IA_ROOT . '/install/include/tpl.func.php';
require IA_ROOT . '/install/include/mysql.cls.php';

$actions = array('show_license', 'show_license', 'check_env', 'init_db', 'finish');
$action = $action = in_array($_GET['action'], $actions) ? $_GET['action'] : '';
$step = max(intval($_GET['step']), 1);

if (empty($action)) {
	$action = isset($actions[$step]) ? $actions[$step] : '';
} else {
	$step = array_keys($actions, $action);
}

if (file_exists(IA_ROOT . '/data/install.lock') && $action != 'finish') {
	header('Location: ../index.php');
}

if(empty($action)) {
	
}
header('Content-Type: text/html; charset=utf-8');
if ($action == 'show_license') {
	tpl_install_license();
} elseif ($action == 'check_env') {
	$result = array();
	$result['env_os'] = PHP_OS;
	$result['env_version'] = PHP_VERSION;
	$result['env_siteroot'] = $_W['siteroot'];
	$result['env_server'] = $_SERVER['SERVER_SOFTWARE'];
	$result['env_pathroot'] = IA_ROOT;
	$result['env_uploadsize'] = @ini_get('file_uploads') ? ini_get('upload_max_filesize') : 'unknow';
	if(function_exists('disk_free_space')) {
		$result['env_diskspace'] = floor(disk_free_space(IA_ROOT) / (1024*1024)).'M';
	} else {
		$result['env_diskspace'] = 'unknow';
	}
	$tmp = function_exists('gd_info') ? gd_info() : array();
	$result['env_gd'] = empty($tmp['GD Version']) ? 'noext' : $tmp['GD Version'];
	
	$chk_func = array(
		array(
			'method' => 'ini_get',
			'name' => 'allow_url_fopen',
		), 
		array(
			'method' => 'function_exists',
			'name' => 'mysql_connect',
		),
		array(
			'method' => 'function_exists',
			'name' => 'file_get_contents',
		),
		array(
			'method' => 'function_exists',
			'name' => 'fsockopen',
		),
		array(
			'method' => 'function_exists',
			'name' => 'xml_parser_create',
		),
		array(
			'method' => 'extension_loaded',
			'name' => 'pdo_mysql',
		),
		array(
			'method' => 'function_exists',
			'name' => 'curl_init',
		),
	);
	$result['iscontinue'] = true;
	foreach ($chk_func as $func) {
		$check[$func['name']] = $func['method']($func['name']) ? true : false;
		$result[$func['name']] = $func['method']($func['name']) ? '<font color=green>[√]On</font>' : '<font color=red>[×]Off</font>';
	}
	if (!empty($check['mysql_connect']) || !empty($check['pdo_mysql'])) {
		$result['iscontinue'] = true;
	} else {
		$result['iscontinue'] = false;
	}
	unset($check['mysql_connect']);unset($check['pdo_mysql']);
	
	foreach ($check as $condition) {
		if (empty($condition)) {
			$result['iscontinue'] = false;
		}
	}
	
	$result['chk_dir'] = array(
		'/data',
		'/data/logs',
		'/data/tpl',
		'/data/cookie',
		'/resource',
		'/resource/attachment'  
	);
	foreach ($result['chk_dir'] as $dir) {
		if(!dir_writeable(IA_ROOT.$dir)) {
			if(is_dir(IA_ROOT.$dir)) {
				$result['chk_'.md5($dir)] = '<font color=red>[×]只读</font>';
			} else {
				$result['chk_'.md5($dir)] = '<font color=red>[×]不存在</font>';
			}
		} else {
			$result['chk_'.md5($dir)] = '<font color=green>[√]可写</font>';
		}
	}
	tpl_install_check_env($result);
} elseif ($action == 'init_db') {
	if (!empty($_SERVER['HTTP_BAE_ENV_APPID'])) {
		$platform = 'bae';
	}
	if (!empty($_POST['adminuser'])) {
		$error_msg = '';
		if (!empty($platform)) {
			if (!file_exists(IA_ROOT . '/data/config.'.$platform.'.php')) {
				die('<script type="text/javascript">alert("平台配置文件“/data/config.'.$platform.'.php”不存在，请完善配置！");location.href = "index.php?step=3";</script>');		
			}
			require_once IA_ROOT . '/data/config.'.$platform.'.php';
			if (empty($config['db']['database']) || empty($config['bae']['bucket']) || empty($config['bae']['ak']) || empty($config['bae']['sk'])) {
				die('<script type="text/javascript">alert("请完善“/data/config.'.$platform.'.php”平台配置文件，完成必填配置！");location.href = "index.php?step=3";</script>');
			}
			$dbhost = $config['db']['host'].':'.$config['db']['port'];
			$dbuser = $config['db']['username'];
			$dbpwd = $config['db']['password'];
			$dbprefix = $config['db']['tablepre'];
			$dbname = $config['db']['database'];
			$authkey = $config['setting']['authkey'];
			$adminuser = $_POST['adminuser'];
			$adminpwd = $_POST['adminpwd'];
		} else {
			// for other
			$dbhost = $_POST['dbhost'];
			$dbuser = $_POST['dbuser'];
			$dbpwd = $_POST['dbpwd'];
			$dbname = $_POST['dbname'];
			$dbprefix = $_POST['dbprefix'];
			$adminuser = $_POST['adminuser'];
			$adminpwd = $_POST['adminpwd'];
			$cookiepre = salt(4).'_';
			$authkey = salt(16).'_';
			$link = mysql_connect($dbhost, $dbuser, $dbpwd);
			if(empty($link)) {
				$errno = mysql_errno();
				$error = mysql_error();
				$error_msg = "$error <br />";
			}
			if(mysql_get_server_info() > '4.1') {
				mysql_query("CREATE DATABASE IF NOT EXISTS `{$dbname}` DEFAULT CHARACTER SET utf8", $link);
			} else {
				mysql_query("CREATE DATABASE IF NOT EXISTS `{$dbname}`", $link);
			}
			if(mysql_errno()) {
				$error_msg .= mysql_error() ." <br />";
			}
			mysql_close($link);
			if (empty($error_msg)) {
				//写入配置信息
				$dbport = explode(':', $dbhost);
				$dbport = !empty($dbport[1]) ? $dbport[1] : '3306';
				$config = file_get_contents(IA_ROOT . '/install/data/default.cfg');
				$config = str_replace(array(
					'{dbhost}',
					'{dbuser}',
					'{dbpwd}',
					'{dbport}',
					'{dbname}',
					'{dbtablepre}',
					'{cookiepre}',
					'{authkey}',
					'{attachdir}',
				), array(
					"'$dbhost'",
					"'$dbuser'",
					"'$dbpwd'",
					"'$dbport'",
					$dbname,
					$dbprefix,
					$cookiepre,
					$authkey,
					'resource/attachment/',
				), $config);
			}
		}
		if (empty($error_msg)) {
			$db = new dbstuff;
			$db->connect($dbhost, $dbuser, $dbpwd, $dbname, 'utf8');
			$query = $db->query("SHOW TABLES LIKE '%wechats%';");
			$isempty = $db->result($query);
			if (!empty($isempty)) {
				die('<script type="text/javascript">alert("您的数据库不为空，请重新建立数据库或是清空该数据库！");location.href = "index.php?step=3";</script>');
			}
			//初始化库结构
			$sql = file_get_contents(IA_ROOT . '/install/data/install.sql');
			$sql = str_replace("\r\n", "\n", $sql);
			runquery($sql);
	
			$sql = file_get_contents(IA_ROOT . '/install/data/data.sql');
			$sql = str_replace("\r\n", "\n", $sql);
			runquery($sql);
			//新建管理员帐号
			$salt = salt(8);
			$password = sha1("{$adminpwd}-{$salt}-{$authkey}");
			$db->query("INSERT INTO {$dbprefix}members (username, password, salt, joindate) VALUES('{$adminuser}', '$password', '$salt', '".time()."')");
			$uid = $db->insert_id();
			//新建默认公众号
			$wechat = array(
				'hash' => salt(5),
				'type' => '1',
				'uid' => $uid,
				'token' => salt(32),
				'access_token' => '',
				'name' => '默认公众号',
				'account' => '默认公众号',
				'original' => '',
				'signature' => '',
				'country' => '',
				'province' => '',
				'city' => '',
				'username' => '',
				'password' => '',
				'welcome' => '欢迎信息',
				'default' => '默认回复',
				'default_period' => '0',
				'lastupdate' => '',
				'key' => '',
				'secret' => '',
				'styleid' => '1',
			);
			$db->query("INSERT INTO `ims_wechats` (`".implode("`,`", array_keys($wechat))."`) VALUES ('".implode("','", $wechat)."')");
			if (empty($platform)) {
				file_put_contents(IA_ROOT . '/data/config.php', $config);
			}
			touch(IA_ROOT . '/data/install.lock');
			header('Location: index.php?step=4');
		}
	}
	tpl_install_db($error_msg, $platform);
	
} elseif ($action == 'finish') {
	require_once '../source/bootstrap.inc.php';
	require_once '../source/model/setting.mod.php';
	$_W['uid'] = $_W['isfounder'] = 1;
	setting_cache_account_by_uid();
	cache_build_setting();
	cache_build_announcement();
	cache_build_modules();
	cache_build_fans_struct();
	cache_build_hook();
	tpl_install_finish();
}

function dir_writeable($dir) {
	$writeable = 0;
	if(!is_dir($dir)) {
		@mkdir($dir, 0777);
	}
	if(is_dir($dir)) {
		if($fp = fopen("$dir/test.txt", 'w')) {
			fclose($fp);
			unlink("$dir/test.txt");
			$writeable = 1;
		} else {
			$writeable = 0;
		}
	}
	return $writeable;
}

function runquery($sql) {
	global $db, $dbprefix;

	if(!isset($sql) || empty($sql)) return;

	$sql = str_replace("\r", "\n", str_replace(' ims_', ' '.$dbprefix, $sql));
	$sql = str_replace("\r", "\n", str_replace(' `ims_', ' `'.$dbprefix, $sql));
	$ret = array();
	$num = 0;
	foreach(explode(";\n", trim($sql)) as $query) {
		$ret[$num] = '';
		$queries = explode("\n", trim($query));
		foreach($queries as $query) {
			$ret[$num] .= (isset($query[0]) && $query[0] == '#') || (isset($query[1]) && isset($query[1]) && $query[0].$query[1] == '--') ? '' : $query;
		}
		$num++;
	}
	unset($sql);
	foreach($ret as $query) {
		$query = trim($query);
		if($query) {
			$db->query($query);
		}
	}
}

function salt($length = 8) {
	$result = '';
	while(strlen($result) < $length) {
		$result .= sha1(uniqid('', true));
	}
	return substr($result, 0, $length);
}

?>
