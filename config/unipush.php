<?php
// +----------------------------------------------------------------------
// | APP 离线推送（uni-push / 个推）配置
// | 2.0：填 cloud_url + sign_secret，走 uniCloud 云函数 uni-push-send（URL 化）
// | 1.0（旧）：cloud_url 留空时，用 AppID/AppKey/MasterSecret 直连个推 REST
// +----------------------------------------------------------------------

return [
    // 是否启用离线推送
    'enable' => (bool)env('unipush.enable', false),
    // uni-push 2.0：云函数 uni-push-send 的 URL 化地址
    'cloud_url' => (string)env('unipush.cloud_url', ''),
    // uni-push 2.0：签名密钥，须与云函数 config.json 的 signSecret 一致
    'sign_secret' => (string)env('unipush.sign_secret', ''),
    // 个推/uni-push AppID（1.0）
    'app_id' => (string)env('unipush.app_id', ''),
    // AppKey
    'app_key' => (string)env('unipush.app_key', ''),
    // MasterSecret
    'master_secret' => (string)env('unipush.master_secret', ''),
    // 鉴权缓存秒数
    'token_ttl' => 3600 * 20,
];
