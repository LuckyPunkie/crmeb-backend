-- 找到我的方式（2026-10-04）
-- 1) 资料新增内置字段：小红书号、微博ID（值存 eb_user_profile.extra_fields）
-- 2) 用户自选可被「找人」检索的信息项：总开关 + 各项开关
-- 3) 检索索引：仅「总开关 + 该项开关」都开启且已填写的项才有行
-- 执行后需删除 runtime/schema 下 user_profile 的字段缓存（fields_cache=true），再重启 Swoole

INSERT INTO `eb_user_profile_field`
  (`field_key`,`title`,`icon`,`type`,`options`,`placeholder`,`is_show`,`status`,`is_system`,`bind_column`,`sort`)
VALUES
  ('xiaohongshu_id','小红书号','','text','[]','请输入小红书号',1,1,1,'',163),
  ('weibo_id','微博ID','','text','[]','请输入微博ID',1,1,1,'',166)
ON DUPLICATE KEY UPDATE
  `is_system`=VALUES(`is_system`),
  `bind_column`=VALUES(`bind_column`);

ALTER TABLE `eb_user_profile`
  ADD COLUMN `find_me_enabled` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '找到我的方式总开关' AFTER `extra_fields`,
  ADD COLUMN `find_me_items` varchar(255) NOT NULL DEFAULT '' COMMENT '已开启可被找到的信息项 field_key，逗号分隔' AFTER `find_me_enabled`;

CREATE TABLE IF NOT EXISTS `eb_user_find_me_index` (
  `uid` int(10) unsigned NOT NULL,
  `field_key` varchar(32) NOT NULL COMMENT '信息项 id_card/plate_number/douyin_id/wechat_id/xiaohongshu_id/weibo_id/kuaishou_id',
  `value` varchar(64) NOT NULL COMMENT '规范化后的值',
  PRIMARY KEY (`uid`,`field_key`,`value`),
  KEY `idx_key_value` (`field_key`,`value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='找人检索索引（按用户「找到我的方式」设置生成）';
