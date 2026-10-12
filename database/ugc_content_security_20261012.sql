-- UGC content security tables
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `eb_ugc_wechat_media_policy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` int NOT NULL,
  `suggest` varchar(16) NOT NULL,
  `action` varchar(16) NOT NULL DEFAULT 'manual',
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_label_suggest` (`label`,`suggest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `eb_ugc_aliyun_baseline_label` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `label` varchar(64) NOT NULL,
  `title` varchar(128) NOT NULL DEFAULT '',
  `pass_max` tinyint unsigned NOT NULL DEFAULT 20,
  `review_max` tinyint unsigned NOT NULL DEFAULT 40,
  `sort` int NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `eb_ugc_moderation_ticket` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `scene` varchar(32) NOT NULL DEFAULT '',
  `media_type` varchar(16) NOT NULL DEFAULT 'image',
  `entity_type` varchar(32) NOT NULL DEFAULT '',
  `entity_id` int unsigned NOT NULL DEFAULT 0,
  `media_url` varchar(500) NOT NULL DEFAULT '',
  `trace_id` varchar(64) NOT NULL DEFAULT '',
  `machine_driver` varchar(32) NOT NULL DEFAULT '',
  `machine_summary` varchar(500) NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 0,
  `admin_id` int unsigned NOT NULL DEFAULT 0,
  `resolve_time` int unsigned NOT NULL DEFAULT 0,
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status_scene` (`status`,`scene`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `eb_ugc_wechat_media_policy` (`label`,`suggest`,`action`,`update_time`) VALUES
(100,'pass','pass',UNIX_TIMESTAMP()),
(100,'review','manual',UNIX_TIMESTAMP()),
(100,'risky','reject',UNIX_TIMESTAMP()),
(20001,'pass','manual',UNIX_TIMESTAMP()),
(20001,'review','manual',UNIX_TIMESTAMP()),
(20001,'risky','reject',UNIX_TIMESTAMP()),
(20002,'pass','manual',UNIX_TIMESTAMP()),
(20002,'review','manual',UNIX_TIMESTAMP()),
(20002,'risky','reject',UNIX_TIMESTAMP()),
(20006,'pass','manual',UNIX_TIMESTAMP()),
(20006,'review','manual',UNIX_TIMESTAMP()),
(20006,'risky','reject',UNIX_TIMESTAMP()),
(21000,'pass','manual',UNIX_TIMESTAMP()),
(21000,'review','manual',UNIX_TIMESTAMP()),
(21000,'risky','reject',UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `action`=VALUES(`action`),`update_time`=VALUES(`update_time`);

INSERT INTO `eb_ugc_aliyun_baseline_label` (`label`,`title`,`pass_max`,`review_max`,`sort`,`update_time`) VALUES
('nonLabel','nonLabel',20,40,0,UNIX_TIMESTAMP()),
('pornographic_adultContent','pornographic_adultContent',20,40,10,UNIX_TIMESTAMP()),
('pornographic_adultContent_lib','pornographic_adultContent_lib',20,40,11,UNIX_TIMESTAMP()),
('sexual_partialNudity','sexual_partialNudity',20,40,12,UNIX_TIMESTAMP()),
('sexual_partialNudity_lib','sexual_partialNudity_lib',20,40,13,UNIX_TIMESTAMP()),
('political_politicalFigure','political_politicalFigure',20,40,20,UNIX_TIMESTAMP()),
('political_politicalFigure_lib','political_politicalFigure_lib',20,40,21,UNIX_TIMESTAMP()),
('violent_explosion','violent_explosion',20,40,30,UNIX_TIMESTAMP()),
('violent_explosion_lib','violent_explosion_lib',20,40,31,UNIX_TIMESTAMP()),
('violent_weapon','violent_weapon',20,40,32,UNIX_TIMESTAMP()),
('violent_weapon_lib','violent_weapon_lib',20,40,33,UNIX_TIMESTAMP()),
('contraband_drug','contraband_drug',20,40,40,UNIX_TIMESTAMP()),
('contraband_drug_lib','contraband_drug_lib',20,40,41,UNIX_TIMESTAMP()),
('contraband_gamble','contraband_gamble',20,40,42,UNIX_TIMESTAMP()),
('contraband_gamble_lib','contraband_gamble_lib',20,40,43,UNIX_TIMESTAMP()),
('fraud_scam','fraud_scam',20,40,50,UNIX_TIMESTAMP()),
('fraud_scam_lib','fraud_scam_lib',20,40,51,UNIX_TIMESTAMP()),
('abuse_abuse','abuse_abuse',20,40,60,UNIX_TIMESTAMP()),
('spam_spam','spam_spam',20,40,70,UNIX_TIMESTAMP()),
('ad_ad','ad_ad',20,40,80,UNIX_TIMESTAMP()),
('other_other','other_other',20,40,90,UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`),`pass_max`=VALUES(`pass_max`),`review_max`=VALUES(`review_max`),`sort`=VALUES(`sort`),`update_time`=VALUES(`update_time`);