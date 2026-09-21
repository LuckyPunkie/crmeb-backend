-- 用户社交档案：全网粉丝（用户自填自然数，NULL=不展示）
ALTER TABLE `eb_user_profile`
  ADD COLUMN `network_fans` int(10) unsigned NULL DEFAULT NULL COMMENT '全网粉丝（用户自填）';
