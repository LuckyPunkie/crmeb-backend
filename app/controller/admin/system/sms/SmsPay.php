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


namespace app\controller\admin\system\sms;


use crmeb\basic\BaseController;
use crmeb\services\YunxinSmsService;
use think\App;

/**
 * 短信购买 - 弃用
 * Class SmsPay
 * @package app\controller\admin\system\sms
 * @author xaboy
 * @day 2020-05-18
 */
class SmsPay extends BaseController
{
    /**
     * @var YunxinSmsService
     */
    protected $service;

    /**
     * Sms constructor.
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = YunxinSmsService::create();
    }

    /**
     * 获取账号数量和发送总量的信息
     *
     * 本函数通过调用服务层来获取账号数量和发送总量的数据，如果请求成功，则返回相关的统计信息；
     * 如果请求失败，则返回错误信息。这个函数主要用于展示或统计用户的账号使用情况。
     *
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含账号信息和发送总量。
     */
    public function number()
    {
        // 调用服务层方法获取统计信息
        $countInfo = $this->service->count();

        // 检查请求是否成功，如果状态码为400，则返回错误信息
        if ($countInfo['status'] == 400) return app('json')->fail($countInfo['msg']);

        // 组装返回的数据，包括账号信息和统计的发送总量
        $info['account'] = $this->service->account();
        $info['number'] = $countInfo['data']['number'];
        $info['send_total'] = $countInfo['data']['send_total'];

        // 返回成功的JSON响应，包含统计信息
        return app('json')->success($info);
    }

    /**
     * 获取餐品价格信息
     *
     * 本方法用于从服务层获取指定页码和每页数量的餐品价格信息。
     * 如果请求成功，将返回餐品的详细价格数据；如果请求失败，将返回错误信息。
     *
     * @return \Illuminate\Http\JsonResponse
     * 返回一个JSON响应，包含成功时的餐品价格数据，或失败时的错误信息。
     */
    public function price()
    {
        // 获取分页信息
        [$page, $limit] = $this->getPage();

        // 从服务层获取餐品信息
        $mealInfo = $this->service->meal($page, $limit);

        // 检查请求是否成功
        if ($mealInfo['status'] == 400) {
            // 如果请求失败，返回失败的JSON响应
            return app('json')->fail($mealInfo['msg']);
        }

        // 如果请求成功，返回成功的JSON响应，包含餐品价格数据
        return app('json')->success($mealInfo['data']);
    }

    /**
     * 处理支付请求
     *
     * 本函数负责接收支付请求，解析请求参数，调用支付服务进行支付操作，并根据支付结果返回相应的响应。
     * 支付请求中应包含支付方式、餐品ID及价格等信息。
     *
     * @return json 支付成功时返回支付详情，支付失败时返回错误信息。
     */
    public function pay()
    {
        // 解析请求参数，包括支付方式、餐品ID和价格，默认值分别为微信支付、0和0。
        list($payType, $mealId, $price) = $this->request->params([
            ['payType', 'weixin'],
            ['mealId', 0],
            ['price', 0],
        ], true);

        // 调用支付服务进行支付操作，传入支付方式、餐品ID、价格以及管理员ID。
        // 管理员ID通过请求对象的adminId方法获取。
        $payInfo = $this->service->pay($payType, $mealId, $price, $this->request->adminId());

        // 根据支付结果的状态码进行响应。
        // 如果状态码为400，表示支付失败，返回错误信息。
        // 否则，返回支付成功的详细信息。
        if ($payInfo['status'] == 400) return app('json')->fail($payInfo['msg']);
        return app('json')->success($payInfo['data']);
    }

    /**
     * @author xaboy
     * @day 2020-05-18
     */
    public function notice()
    {
        //TODO 短信支付成功回调
    }
}
