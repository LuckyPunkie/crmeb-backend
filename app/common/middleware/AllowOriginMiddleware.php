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



namespace app\common\middleware;


use app\Request;
use think\exception\HttpResponseException;
use think\facade\Config;
use think\Response;

/**
 * 跨域中间件
 * Class AllowOriginMiddleware
 * @package app\http\middleware
 */
class AllowOriginMiddleware extends BaseMiddleware
{
    /**
     * header头
     * @var array
     */
    protected $header = [
        'Access-Control-Allow-Origin' => '*',
        'Access-Control-Allow-Headers' => 'X-Token, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With,Form-type,Referer,Connection,Content-Length,Host,Origin,Authorization,Authori-zation,Accept,Accept-Encoding,Uuid,X-Source',
        'Access-Control-Allow-Methods' => 'GET,POST,PATCH,PUT,DELETE,OPTIONS',
        'Access-Control-Max-Age' => '1728000',
        'Access-Control-Allow-Credentials' => 'true'
    ];

    public function before(Request $request)
    {
        $origin = $request->header('origin');
        if ($origin) {
            $this->header['Access-Control-Allow-Origin'] = $origin;
        }
        if ($request->method(true) == 'OPTIONS') {
            $response = Response::create()->code(200)->header($this->header);
            throw new HttpResponseException($response);
        }
    }

    public function after(Response $response)
    {
        $origin = app('request')->header('origin');
        if ($origin) {
            $this->header['Access-Control-Allow-Origin'] = $origin;
        }
        $response->header($this->header);
    }
}
