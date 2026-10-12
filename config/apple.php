<?php
// +----------------------------------------------------------------------
// | Sign in with Apple 配置
// | identityToken / 服务器通知 JWT 的 aud 必须在 client_ids 内（iOS 为 BundleID）
// +----------------------------------------------------------------------

return [
    // 允许的 aud，逗号分隔
    'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string)env('apple.client_ids', 'com.naimeng.io'))))),
    // 苹果公钥缓存秒数
    'jwks_ttl' => 3600 * 12,
];
