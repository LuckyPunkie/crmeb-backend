<?php
// +----------------------------------------------------------------------
// | AI 点餐 / 豆包实时语音
// | 密钥用环境变量，勿写入仓库；服务器可直接写在本文件（勿提交）
// +----------------------------------------------------------------------

return [
    // ThinkPHP env('doubao.app_id') 对应 DOUBAO_APP_ID；勿写在 [unipush] 段落后面
    'doubao_app_id' => (string)(env('doubao.app_id', '') ?: env('DOUBAO_APP_ID', '')),
    'doubao_access_token' => (string)(env('doubao.access_token', '') ?: env('DOUBAO_ACCESS_TOKEN', '')),
    'default_rate_per_1k' => 0.10,
    'default_min_balance' => 1.0,
    'dialects' => [
        'mandarin' => '普通话',
        'cantonese' => '粤语',
        'sichuan' => '四川话',
    ],
    'styles' => [
        'friendly' => '热情亲切',
        'professional' => '专业稳重',
        'concise' => '简洁高效',
    ],
];
