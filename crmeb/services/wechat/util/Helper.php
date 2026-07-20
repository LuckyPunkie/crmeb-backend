<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\util;


use Closure;

/**
 * 帮助类
 * Class Helper
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\util
 */
class Helper
{
    /**
     * 获取加密方式
     * @param string $signType
     * @param string $secretKey
     * @return Closure|string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    public static function getEncryptMethod(string $signType, string $secretKey = ''): string|Closure
    {
        if ('HMAC-SHA256' === $signType) {
            return function ($str) use ($secretKey) {
                return hash_hmac('sha256', $str, $secretKey);
            };
        }

        return 'md5';
    }

    /**
     * 生成签名
     * @param array $attributes
     * @param string $key
     * @param string $encryptMethod
     * @return string
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function generateSign(array $attributes, string $key, string $encryptMethod = 'md5'): string
    {
        ksort($attributes);

        $attributes['key'] = $key;

        return strtoupper(call_user_func_array($encryptMethod, [urldecode(http_build_query($attributes))]));
    }

    /**
     * 截取数据
     * @param string $tel
     * @return array|string|string[]
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public static function encryptTel(string $tel)
    {
        return substr_replace($tel, '****', 3, 4);
    }

}
