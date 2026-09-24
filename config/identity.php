<?php

// 阿里云云市场 cmapi00049474「手机三要素核验」
// 文档：POST https://swphone3.market.alicloudapi.com/verify/operator3_precision
// Body: name, id_number, phone_number；Header: Authorization: APPCODE xxx
return [
    'appcode'     => env('identity.appcode', ''),
    'verify_url'  => env('identity.verify_url', 'https://swphone3.market.alicloudapi.com/verify/operator3_precision'),
    'method'      => strtoupper(env('identity.method', 'POST')),
    'param_name'   => env('identity.param_name', 'name'),
    'param_idcard' => env('identity.param_idcard', 'id_number'),
    'param_mobile' => env('identity.param_mobile', 'phone_number'),
];
