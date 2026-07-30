-- 机器人用户 + 资料审核 + 商家来源
ALTER TABLE `eb_user`
  ADD COLUMN `bot_type` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '0普通 1用户机器人 2创作机器人' AFTER `user_type`,
  ADD COLUMN `profile_review_status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '0无 1AI通过 3人工复审通过 4人工驳回' AFTER `bot_type`,
  ADD COLUMN `profile_review_urgent` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否加急' AFTER `profile_review_status`,
  ADD COLUMN `profile_review_urgent_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '加急时间' AFTER `profile_review_urgent`,
  ADD COLUMN `profile_review_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '进入人工队列时间' AFTER `profile_review_urgent_time`;

ALTER TABLE `eb_merchant`
  ADD COLUMN `create_source` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1后台添加 2用户注册 3批量导入' AFTER `reg_admin_id`;

UPDATE `eb_merchant` SET `create_source` = 1 WHERE `create_source` = 0 OR `create_source` IS NULL;
