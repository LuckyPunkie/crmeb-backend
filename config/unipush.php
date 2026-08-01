<?php
// +----------------------------------------------------------------------
// | APP 离线推送（uni-push / 个推）配置
// | 在 DCloud 开通 uni-push 后，把云打包生成的 AppID/AppKey/MasterSecret 填入 .env
// +----------------------------------------------------------------------

return [
    // 是否启用离线推送
    'enable' => (bool)env('unipush.enable', false),
    // 个推/uni-push AppID
    'app_id' => (string)env('unipush.app_id', ''),
    // AppKey
    'app_key' => (string)env('unipush.app_key', ''),
    // MasterSecret
    'master_secret' => (string)env('unipush.master_secret', ''),
    // 鉴权缓存秒数
    'token_ttl' => 3600 * 20,
];
