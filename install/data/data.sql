INSERT INTO `ims_rule` (`id`, `weid`, `name`, `module`) VALUES(1, 1, '默认文字回复', 'basic');
INSERT INTO `ims_rule` (`id`, `weid`, `name`, `module`) VALUES(2, 1, '默认图文回复', 'news');
INSERT INTO `ims_rule_keyword` (`id`, `rid`, `weid`, `module`, `content`, `type`) VALUES
(1, 1, 1, 'basic', '文字', 2),
(2, 2, 1, 'news', '图文', 2);
INSERT INTO `ims_basic_reply` (`id`, `rid`, `content`) VALUES(1, 1, '这里是默认文字回复');
INSERT INTO `ims_news_reply` (`id`, `rid`, `parentid`, `title`, `description`, `thumb`, `content`, `url`) VALUES(1, 2, 0, '这里是默认图文回复', '这里是默认图文描述', 'images/2013/01/d090d8e61995e971bb1f8c0772377d.png', '这里是默认图文原文这里是默认图文原文这里是默认图文原文', '');
INSERT INTO `ims_news_reply` (`id`, `rid`, `parentid`, `title`, `description`, `thumb`, `content`, `url`) VALUES(2, 2, 1, '这里是默认图文回复内容', '', 'images/2013/01/112487e19d03eaecc5a9ac87537595.jpg', '这里是默认图文回复原文这里是默认图文回复原文<br />', '');

INSERT INTO `ims_modules` (`mid`, `name`, `title`, `version`, `ability`, `description`, `author`, `url`, `settings`, `subscribes`, `handles`, `isrulefields`, `home`, `issystem`, `options`, `profile`, `site_menus`, `platform_menus`) VALUES
(1, 'basic', '基本文字回复', '1.0', '和您进行简单对话', '一问一答得简单对话. 当访客的对话语句中包含指定关键字, 或对话语句完全等于特定关键字, 或符合某些特定的格式时. 系统自动应答设定好的回复内容.', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 0, '', '', 1, 0, 1, '', 0, '', ''),
(2, 'news', '基本混合图文回复', '1.0', '为你提供生动的图文资讯', '一问一答得简单对话, 但是回复内容包括图片文字等更生动的媒体内容. 当访客的对话语句中包含指定关键字, 或对话语句完全等于特定关键字, 或符合某些特定的格式时. 系统自动应答设定好的图文回复内容.', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 0, '', '', 1, 0, 1, '', 0, '', ''),
(3, 'music', '基本语音回复', '1.0', '提供语音、音乐等音频类回复', '在回复规则中可选择具有语音、音乐等音频类的回复内容，并根据用户所设置的特定关键字精准的返回给粉丝，实现一问一答得简单对话。', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 0, '', '', 1, 0, 1, '', 0, '', ''),
(4, 'userapi', '自定义接口回复', '1.1', '更方便的第三方接口设置', '自定义接口又称第三方接口，可以让开发者更方便的接入微擎系统，高效的与微信公众平台进行对接整合。', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 0, '', '', 1, 0, 1, '', 0, '', ''),
(5, 'fans', '粉丝管理', '1.0', '提供关注的粉丝管理功能', '具有记录粉丝关注及取消关注功能，并集成粉丝个人中心，提供粉丝的常用个人资料管理', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 0, 'a:8:{i:0;s:4:"text";i:1;s:5:"image";i:2;s:5:"voice";i:3;s:5:"video";i:4;s:8:"location";i:5;s:4:"link";i:6;s:9:"subscribe";i:7;s:11:"unsubscribe";}', 'a:0:{}', 0, 0, 0, 'a:0:{}', 1, 'a:0:{}', 'a:0:{}'),
(6, 'stat', '微擎数据统计', '1.0.2', '提供消息记录及分析统计功能', '能够提供按公众号码查询, 分析统计消息记录, 以及规则关键字命中情况统计', 'WeEngine Team', 'http://bbs.we7.cc/forum.php?mod=forumdisplay&amp;fid=36&amp;filter=typeid&amp;typeid=1', 1, 'a:7:{i:0;s:4:"text";i:1;s:5:"image";i:2;s:8:"location";i:3;s:4:"link";i:4;s:9:"subscribe";i:5;s:11:"unsubscribe";i:6;s:5:"click";}', 'a:7:{i:0;s:4:"text";i:1;s:5:"image";i:2;s:8:"location";i:3;s:4:"link";i:4;s:9:"subscribe";i:5;s:11:"unsubscribe";i:6;s:5:"click";}', 0, 0, 0, 'a:0:{}', 0, 'a:0:{}', 'a:0:{}');

INSERT INTO `ims_site_templates` (`id`, `name`, `title`, `description`, `author`, `url`) VALUES
(1, 'default', '微站默认模板', '由微擎提供默认微站模板套系', '微擎团队', 'http://we7.cc');
	
INSERT INTO `ims_site_styles` (`weid`, `templateid`, `variable`, `content`) VALUES
(1, 1, 'indexbgcolor', '#e06666'),
(1, 1, 'fontfamily', 'Tahoma,Helvetica,''SimSun'',sans-serif'),
(1, 1, 'fontsize', '12px/1.5'),
(1, 1, 'fontcolor', '#434343'),
(1, 1, 'fontnavcolor', '#ffffff'),
(1, 1, 'linkcolor', '#ffffff'),
(1, 1, 'indexbgimg', 'bg_index.jpg');