<?php
/**
 * 粉丝管理模块微站定义
 *
 * @author WeEngine Team
 * @url http://bbs.we7.cc/forum.php?mod=forumdisplay&fid=36&filter=typeid&typeid=1
 */
defined('IN_IA') or exit('Access Denied');

class FansModuleSite extends WeModuleSite {
	
	public function getProfileTiles() {
		//这个操作被定义用来呈现微站个人中心上的管理链接，返回值为数组结构, 每个元素将被呈现为一个链接. 元素结构为 array('title'=>'标题', 'image'=>'图标', 'url'=>'链接目标', 'displayorder'=>'排序权重, 越高越往前')
		return array(
			array('title'=>'我的资料', 'url'=> $this->createMobileUrl('profile'))
		);
	}
	
	public function doMobileProfile() {
		global $_W, $_GPC;
		if (empty($_W['fans']['from_user'])) {
			message('非法访问，请重新点击链接进入个人中心！');
		}
		$title = '我的资料';
		if (checksubmit('submit')) {
			if (!empty($_GPC)) {
				$from_user = $_W['fans']['from_user'];
				foreach ($_GPC as $field => $value) {
					if (empty($value) || in_array($field, array('from_user','act', 'name', 'token', 'submit', 'session'))) {
						unset($_GPC[$field]);
						continue;
					}
				}
				fans_update($from_user, $_GPC);
			}
			message('更新资料成功！', referer(), 'success');
		}
		$profile = fans_search($_W['fans']['from_user']);
		
		$form = array(
			'birthday' => array(
				'year' => array(date('Y'), '1914'),
			),
			'bloodtype' => array('A', 'B', 'AB', 'O', '其它'),
			'education' => array('博士','硕士','本科','专科','中学','小学','其它'),
			'constellation' => array('水瓶座','双鱼座','白羊座','金牛座','双子座','巨蟹座','狮子座','处女座','天秤座','天蝎座','射手座','摩羯座'),
			'zodiac' => array('鼠','牛','虎','兔','龙','蛇','马','羊','猴','鸡','狗','猪'),
		);
		include $this->template('profile');
	}
}