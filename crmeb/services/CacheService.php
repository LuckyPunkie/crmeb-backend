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

namespace crmeb\services;

use think\facade\Cache;
use think\facade\Config;

class CacheService
{
    /**
     * 数据统计相关
     */
    //订单顶部统计 带环比
    const ANALYTICS_ORDER_TOP = 'analytics_order:top:{merId}:{date}:{adminId}';
    //订单折线图
    const ANALYTICS_ORDER_LINE_CHART = 'analytics_order:line_chart:{merId}:{date}:{adminId}';
    //订单类型统计
    const ANALYTICS_ORDER_TYPE_PIE = 'analytics_order:type_pie:{merId}:{date}:{adminId}';
    //订单发货统计
    const ANALYTICS_ORDER_DELIVER_PIE = 'analytics_order:deliver_pie:{merId}:{date}:{adminId}';

    //商品顶部统计 带环比
    const ANALYTICS_PRODUCT_TOP = 'analytics_product:top:{merId}:{date}:{adminId}';
    //商品折线图
    const ANALYTICS_PRODUCT_LINE_CHART = 'analytics_product:line_chart:{merId}:{date}:{adminId}';
    //商品分类统计
    const ANALYTICS_PRODUCT_CATEGORY_CHART = 'analytics_product:category_pie:{merId}:{date}:{adminId}';
    //商品类型统计
    const ANALYTICS_PRODUCT_TYPE_CHART = 'analytics_product:category_pie:{merId}:{adminId}';


    //新增用户统计
    const ANALYTICS_USER_TOP = 'analytics_user:top:{merId}:{date}';
    //新增用户折线图
    const ANALYTICS_USER_LINE_CHART = 'analytics_user:line_chart:{type}:{date}';
    //新增用户柱状图
    const ANALYTICS_USER_BAR_CHART = 'analytics_user:bar_chart:{date}';

    // 配置定义标签（可选）
    private static $keyTags = [
        //订单统计tag
        self::ANALYTICS_ORDER_TOP => ['analytics_order','order:top:merchant:{mer_id}'],
        self::ANALYTICS_ORDER_LINE_CHART => ['analytics_order', 'order:line:merchant:{merId}'],
        self::ANALYTICS_ORDER_TYPE_PIE => ['analytics_order', 'order:type:pie:merchant:{merId}'],
        self::ANALYTICS_ORDER_DELIVER_PIE => ['analytics_order', 'order:deliver:pie:merchant:{merId}'],

        //商品统计tag
        self::ANALYTICS_PRODUCT_TOP => ['analytics_product', 'product:top:merchant:{merId}'],
        self::ANALYTICS_PRODUCT_LINE_CHART => ['analytics_product', 'product:line:merchant:{merId}'],
        self::ANALYTICS_PRODUCT_CATEGORY_CHART => ['analytics_product', 'product:category:pie:merchant:{merId}'],
        self::ANALYTICS_PRODUCT_TYPE_CHART => ['analytics_product', 'product:type:pie:merchant:{merId}'],

        //用户统计tag
        self::ANALYTICS_USER_TOP => ['analytics_user', 'user:top:merchant:{merId}'],
        self::ANALYTICS_USER_LINE_CHART => ['analytics_user', 'user:line:merchant:{type}:{merId}'],
        self::ANALYTICS_USER_BAR_CHART => ['analytics_user', 'user:bar:merchant:{date}'],
    ];





    /**
     * 缓存key生成
     * @param string $template
     * @param array $params
     * @return string
     */
    public static function build(string $template, array $params = []): string
    {
        $key = $template;
        foreach ($params as $param => $value) {
            $key = str_replace('{' . $param . '}', $value, $key);
        }
        return $key;
    }

    /**
     * 获取缓存标签
     */
    public static function getTags(string $template, array $params = []): array
    {
        $tags = self::$keyTags[$template] ?? [];
        $processedTags = [];

        foreach ($tags as $tag) {
            $processedTag = $tag;
            foreach ($params as $param => $value) {
                $processedTag = str_replace('{' . $param . '}', (string)$value, $processedTag);
            }
            $processedTags[] = $processedTag;
        }

        return $processedTags;
    }

    /**
     * 缓存key生成&设置标签
     * @param string $template
     * @param array $params
     * @return array
     */
    public static function setWithTags(string $template, array $params = []): array
    {
        $key = self::build($template, $params);
        $tags = self::getTags($template, $params);
        return [$key, $tags];
    }
}
