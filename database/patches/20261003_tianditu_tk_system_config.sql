-- 天地图 KEY：与「腾讯地图KEY」(tx_map_key) 同组配置，可在平台后台修改
-- 执行后：系统设置 → 地图配置（与 tx_map_key 同一分类）→ 填写「天地图KEY」
-- 若需迁移当前硬编码 key，可在后台填入原 tk 值

INSERT INTO `eb_system_config` (
  `config_classify_id`, `config_name`, `config_key`, `config_type`,
  `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`,
  `create_time`, `linked_id`, `linked_status`, `linked_value`
)
SELECT
  88,
  '天地图KEY',
  'tianditu_tk',
  'input',
  '',
  '',
  0,
  '',
  1,
  0,
  1,
  NOW(),
  0,
  0,
  0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'tianditu_tk'
);
