<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        //自动同步路由权限
        'menu' => 'app\command\updateAuth',
        //将所有商品加入到spu表
        'spu' => 'app\command\updateSpu',
        //整理路由权限
        'menu:format' => 'app\command\FormatMenuPath',
        //清除缓存素材
        'clear:attachment' => 'app\command\ClearCacheAttachment',
        //版本更新
        'version:update' => 'app\command\VersionUpdate',
        //清除所有 除配置相关之外的数据
        'clear:merchant' => 'app\command\ClearMerchantData',
        //清除所有已删除的商户的商品相关数据
        'clear:redundancy' => 'app\command\ClearRedundancy',
        //重制平台管理员的密码
        'reset:password' => 'app\command\resetPassword',
        //修改图片地址前缀
        'reset:imagePath' => 'app\command\resetImagePath',
        //清除登录限制
        'clear:cache' => 'app\command\clearCache',
        //清理 ThinkORM 表结构缓存（ALTER 加字段后）
        'schema:clear' => 'app\command\ClearSchemaCache',
        //更新热卖榜单
        'change:hotTop' => 'app\command\changeHotTop',
        //更新城市数据，导入/更新子集
        'update:city' => 'app\command\updateCityArea',
        //简易脚手架
        'createTool' => 'app\command\createTool',
        //更新城市数据，导入/更新子集
        'cm_cli' => 'app\command\crmebCommand',
        //商品导入
        'product:import' => 'app\command\ProductImport',
        //导入SQL文件
        'sql:import' => 'app\command\ImportSql',
        //保证金自动解冻
        'animal_rescue:deposit_thaw' => 'app\command\AnimalRescueDepositThaw',
        //筹款到期检查
        'animal_rescue:expire_check' => 'app\command\AnimalRescueExpireCheck',
        //救助站月捐结算
        'animal_rescue:monthly_settlement' => 'app\command\AnimalRescueMonthlySettlement',
    ],
];
