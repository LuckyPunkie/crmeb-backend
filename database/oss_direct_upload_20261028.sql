-- 全站图片/视频直传 OSS + CDN 分发 + IMM 转码（2026-10-28）
-- 方案与控制台配置见仓库根 .claude/docs/阿里云OSS-CDN控制台配置清单.md
-- 执行库：cermb
--
-- 线上 Redis 有 merchant_sys_config_configFlag 时 systemConfig() 只读缓存，执行后还要把下面新增的 oss_direct_* key
-- SETNX 进 Redis（merchant_sys_config_0_<key>，值取 eb_system_config_value），或在后台「OSS直传与CDN」页保存一次。

SET NAMES utf8mb4;

-- 直传登记表：OSS 回调验签通过后写入；视频的转码状态、实际播放路径也记在这里
CREATE TABLE IF NOT EXISTS `eb_upload_media` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `client` varchar(8) NOT NULL DEFAULT '' COMMENT '上传端 api/sys/mer/ser',
  `owner_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户 uid / 管理员 id / 客服 id',
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `kind` varchar(8) NOT NULL DEFAULT 'image' COMMENT 'image / video',
  `object_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'OSS 对象路径（源文件）',
  `play_key` varchar(255) NOT NULL DEFAULT '' COMMENT '视频实际播放路径：无需转码=object_key，转码后=video/720p/...',
  `size` bigint unsigned NOT NULL DEFAULT 0,
  `mime` varchar(64) NOT NULL DEFAULT '',
  `width` int unsigned NOT NULL DEFAULT 0,
  `height` int unsigned NOT NULL DEFAULT 0,
  `duration` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '视频时长（秒）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1 可用 / 视频：0 检测中 2 转码中 -1 转码失败（仍播源文件）',
  `imm_task_id` varchar(64) NOT NULL DEFAULT '',
  `meta` text COMMENT 'DetectMediaMeta 摘要 / 失败原因',
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_object_key` (`object_key`),
  KEY `idx_owner` (`client`,`owner_id`),
  KEY `idx_imm_task` (`imm_task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直传 OSS 的图片/视频登记';

-- 配置：设置 → 第三方接口 → OSS直传与CDN（pid 84 = extend_tabs）
INSERT INTO `eb_system_config_classify` (`pid`, `classify_name`, `classify_key`, `info`, `sort`, `create_time`, `icon`, `status`)
SELECT 84, 'OSS直传与CDN', 'tab_oss_direct', '', 0, NOW(), '', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_oss_direct');

SET @oss_cid = (SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_oss_direct');

INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`)
SELECT @oss_cid, t.name, t.k, t.type, '', '', 0, t.info, t.sort, 0, 1, NOW()
FROM (
  SELECT '直传总开关' name, 'oss_direct_open' k, 'switches' type, '关闭时各端继续走原来的上传接口（文件经服务器）；资源配好、联调通过后再打开' info, 100 sort
  UNION ALL SELECT '地域 ID', 'oss_direct_region', 'input', '如 cn-shanghai，须与 ECS、Bucket、IMM 同地域', 95
  UNION ALL SELECT 'Bucket 名称', 'oss_direct_bucket', 'input', '私有读写的 Bucket', 94
  UNION ALL SELECT 'OSS 外网 Endpoint', 'oss_direct_endpoint', 'input', '如 oss-cn-shanghai.aliyuncs.com，客户端直传地址 = https://<bucket>.<endpoint>', 93
  UNION ALL SELECT 'OSS 内网 Endpoint', 'oss_direct_internal_endpoint', 'input', '如 oss-cn-shanghai-internal.aliyuncs.com，服务器侧读写用；留空则用外网 Endpoint', 92
  UNION ALL SELECT 'RAM 用户 AccessKeyId', 'oss_direct_access_key_id', 'input', 'RAM 用户 naimeng-media-server 的 AccessKey，勿用主账号', 90
  UNION ALL SELECT 'RAM 用户 AccessKeySecret', 'oss_direct_access_key_secret', 'input', '同上，请妥善保管', 89
  UNION ALL SELECT '直传角色 ARN', 'oss_direct_role_arn', 'input', '形如 acs:ram::<主账号ID>:role/naimeng-oss-upload', 88
  UNION ALL SELECT '图片 CDN 域名', 'oss_direct_img_cdn', 'input', '如 https://img.ohlegend.com（Referer 防盗链）', 80
  UNION ALL SELECT '视频 CDN 域名', 'oss_direct_video_cdn', 'input', '如 https://video.ohlegend.com（URL 鉴权 A 类）', 79
  UNION ALL SELECT '视频 URL 鉴权 KEY', 'oss_direct_video_auth_key', 'input', 'CDN 视频域名 A 类鉴权的主 KEY（换 KEY 时先改 CDN 备 KEY，再改这里）', 78
  UNION ALL SELECT '视频 URL 鉴权有效时长（秒）', 'oss_direct_video_auth_ttl', 'number', '须与 CDN 控制台「有效时长」一致，建议 10800', 77
  UNION ALL SELECT 'IMM 项目名称', 'oss_direct_imm_project', 'input', '已绑定该 Bucket 的 IMM 项目', 70
  UNION ALL SELECT 'MNS 主题名称', 'oss_direct_mns_topic', 'input', '转码完成通知主题（如 imm-convert-notify）；留空则只靠后端轮询', 69
  UNION ALL SELECT '图片大小上限（MB）', 'oss_direct_image_max_mb', 'number', '单张图片直传上限', 60
  UNION ALL SELECT '视频大小上限（MB）', 'oss_direct_video_max_mb', 'number', '单个视频直传上限', 59
) t
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` c WHERE c.`config_key` = t.k);

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`, `create_time`)
SELECT t.k, t.v, 0, NOW()
FROM (
  SELECT 'oss_direct_open' k, '0' v
  UNION ALL SELECT 'oss_direct_region', 'cn-shanghai'
  UNION ALL SELECT 'oss_direct_bucket', ''
  UNION ALL SELECT 'oss_direct_endpoint', 'oss-cn-shanghai.aliyuncs.com'
  UNION ALL SELECT 'oss_direct_internal_endpoint', 'oss-cn-shanghai-internal.aliyuncs.com'
  UNION ALL SELECT 'oss_direct_access_key_id', ''
  UNION ALL SELECT 'oss_direct_access_key_secret', ''
  UNION ALL SELECT 'oss_direct_role_arn', ''
  UNION ALL SELECT 'oss_direct_img_cdn', ''
  UNION ALL SELECT 'oss_direct_video_cdn', ''
  UNION ALL SELECT 'oss_direct_video_auth_key', ''
  UNION ALL SELECT 'oss_direct_video_auth_ttl', '10800'
  UNION ALL SELECT 'oss_direct_imm_project', ''
  UNION ALL SELECT 'oss_direct_mns_topic', ''
  UNION ALL SELECT 'oss_direct_image_max_mb', '20'
  UNION ALL SELECT 'oss_direct_video_max_mb', '300'
) t
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_value` v WHERE v.`config_key` = t.k AND v.`mer_id` = 0);
