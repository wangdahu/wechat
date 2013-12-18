<?php
/**
 * 粉丝管理模块定义
 *
 * @author WeEngine Team
 * @url http://bbs.we7.cc/forum.php?mod=forumdisplay&fid=36&filter=typeid&typeid=1
 */
defined('IN_IA') or exit('Access Denied');

class FansModule extends WeModule {
	public function doDisplay() {
		global $_GPC, $_W;
		$pindex = max(1, intval($_GPC['page']));
		$psize = 50;

		$where = '';
		$starttime = empty($_GPC['start']) ? strtotime('-1 month') : strtotime($_GPC['start']);
		$endtime = empty($_GPC['end']) ? TIMESTAMP : strtotime($_GPC['end']) + 86399;
		$where .= " AND createtime >= '$starttime' AND createtime < '$endtime'";

		$fields = fans_fields();
		$select = array();
		if (!empty($_GPC['select'])) {
			foreach ($_GPC['select'] as $field) {
				if (isset($fields[$field])) {
					$select[] = $field;
				}
			}
		}

		$list = pdo_fetchall("SELECT from_user, weid, createtime ".(!empty($select) ? ",`".implode('`,`', $select)."`" : '')." FROM ".tablename('fans')." WHERE follow = 1 AND weid = '{$_W['weid']}' $where ORDER BY id DESC LIMIT " . ($pindex - 1) * $psize . ',' . $psize);
		$total = pdo_fetchcolumn("SELECT COUNT(*) FROM ".tablename('fans')." WHERE follow = 1 AND weid = '{$_W['weid']}' $where ");
		$pager = pagination($total, $pindex, $psize);

		include $this->template('display');
	}
}