-- 印象墙 V1
-- 依赖：eb_user、eb_user_relation(type='fans')、eb_user_dialog(is_black_a/is_black_b)

ALTER TABLE `eb_user`
  ADD COLUMN `impression_wall_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '印象墙开关 0关 1开' AFTER `promoter_switch`;

CREATE TABLE IF NOT EXISTS `eb_user_impression` (
  `impression_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `owner_uid` int(10) unsigned NOT NULL COMMENT '墙主uid',
  `from_uid` int(10) unsigned NOT NULL COMMENT '发布者uid',
  `content` varchar(1500) NOT NULL DEFAULT '' COMMENT '文字1-500字',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片URL（0-1张）',
  `like_count` int(10) unsigned NOT NULL DEFAULT '0',
  `comment_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '含楼中楼总数',
  `relation_snapshot` tinyint(2) NOT NULL DEFAULT '0' COMMENT '关系标签快照 0陌生人 1互关 2墙主粉丝 3墙主关注 4墙主拉黑 5拉黑墙主 6互相拉黑',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0正常 1已删',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`impression_id`),
  KEY `idx_owner_time` (`owner_uid`, `is_deleted`, `create_time`),
  KEY `idx_from_time` (`from_uid`, `create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='印象墙印象';

CREATE TABLE IF NOT EXISTS `eb_user_impression_comment` (
  `comment_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `impression_id` int(10) unsigned NOT NULL,
  `owner_uid` int(10) unsigned NOT NULL COMMENT '所属墙主',
  `parent_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '一级评论id，0=一级评论',
  `reply_to_uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '回复的对象uid（楼中楼）',
  `reply_to_comment_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '回复的评论id（楼中楼）',
  `from_uid` int(10) unsigned NOT NULL,
  `content` varchar(900) NOT NULL DEFAULT '' COMMENT '文字1-300字',
  `images` text COMMENT 'JSON 图片数组 0-9张',
  `like_count` int(10) unsigned NOT NULL DEFAULT '0',
  `relation_snapshot` tinyint(2) NOT NULL DEFAULT '0' COMMENT '同印象',
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`),
  KEY `idx_impression_parent` (`impression_id`, `parent_id`, `is_deleted`, `create_time`),
  KEY `idx_from` (`from_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='印象墙评论';

CREATE TABLE IF NOT EXISTS `eb_user_impression_like` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL,
  `target_type` varchar(20) NOT NULL COMMENT 'impression / comment',
  `target_id` int(10) unsigned NOT NULL,
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid_target` (`uid`, `target_type`, `target_id`),
  KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='印象墙点赞';
