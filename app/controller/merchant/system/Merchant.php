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


namespace app\controller\merchant\system;


use app\common\repositories\store\MerchantTakeRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\validate\merchant\MerchantTakeValidate;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\merchant\MerchantUpdateValidate;
use crmeb\jobs\ChangeMerchantStatusJob;
use crmeb\services\ImageWaterMarkService;
use crmeb\services\UploadService;
use think\App;
use think\facade\Queue;

/**
 * Class Merchant
 * @package app\controller\merchant\system
 * @author xaboy
 * @day 2020/6/25
 */
class Merchant extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param MerchantRepository $repository
     */
    public function __construct(App $app, MerchantRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 更新表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm()
    {
        // 调用 formToData 方法将表单转换为数据并返回成功响应
        return app('json')->success(formToData($this->repository->merchantForm($this->request->merchant()->toArray())));
    }


    /**
     * 更新商家信息
     *
     * @param MerchantUpdateValidate $validate 商家更新验证器
     * @param MerchantTakeValidate $takeValidate 商家配送验证器
     * @param MerchantTakeRepository $repository 商家配送仓库
     * @return \think\response\Json
     */
    public function update(MerchantUpdateValidate $validate, MerchantTakeValidate $takeValidate, MerchantTakeRepository $repository)
    {
        $type = $this->request->param('type', 1);
        // 获取当前登录的商家信息
        $merchant = $this->request->merchant();
        // 如果 type 为 2，则表示更新商家配送信息
        if ($type == 2) {
            // 获取请求参数中的指定字段
            $data = $this->request->params([
                'mer_info',
                'mer_certificate',
                'service_phone',
                'mer_avatar',
                'mer_banner',
                'mer_state',
                'mini_banner',
                'mer_keyword',
                'mer_address',
                'long',
                'lat',
                ['delivery_way', [2]],
            ]);
            // 对获取到的数据进行验证
            $validate->check($data);
            // 判断系统配置中的 sys_bases_status 是否为 0，如果是，则将 $sys_bases_status 设为 0，否则设为 1
            $sys_bases_status = systemConfig('sys_bases_status') == 0 ? 0 : 1;
            // 如果 $sys_bases_status 为 true，并且 $data['mer_certificate'] 为空，则返回错误信息
            if ($sys_bases_status && empty($data['mer_certificate']))
                return app('json')->fail('店铺资质不可为空');

            app()->make(ConfigValueRepository::class)->setFormData([
                'mer_certificate' => $data['mer_certificate']
            ], $this->request->merId());
            // 删除 $data 数组中的 mer_certificate 和 services_type 两个元素
            unset($data['mer_certificate']);
            $takeData = $this->request->params(['mer_take_day', 'mer_take_time']);
            $repository->set($this->request->merId(), $takeData);

            sort($data['delivery_way']);
            $delivery_way = implode(',', $data['delivery_way']);
            if (count((array)$data['delivery_way']) == 1 && $data['delivery_way'] != $merchant->delivery_way) {
                app()->make(ProductRepository::class)->getSearch([])
                    ->where('mer_id', $merchant->mer_id)
                    ->update(['delivery_way' => $delivery_way]);
            }
            $data['delivery_way'] = $delivery_way;
        } else {
            $data = $this->request->params(['mer_state']);

            if ($merchant->is_margin == 1 && $data['mer_state'] == 1)
                return app('json')->fail('开启店铺前请先支付保证金');

            //if ($data['mer_state'] && !$merchant->sub_mchid && systemConfig('open_wx_combine'))
            //    return app('json')->fail('开启店铺前请先完成微信子商户入驻');
        }
        $merchant->save($data);

        // 商户编辑记录日志
        event('create_operate_log', [
            'category' => OperateLogRepository::MERCHANT_EDIT_AUDIT_STATUS,
            'data' => [
                'merchant' => $merchant,
                'admin_info' => $this->request->adminInfo(),
                'update_infos' => ['status' => $data['mer_state']]
            ],
        ]);

        Queue::push(ChangeMerchantStatusJob::class, $this->request->merId());
        return app('json')->success('修改成功');
    }


    /**
     *  获取商户信息详情
     * @return mixed
     * @author xaboy
     * @day 2020/7/21
     */
    public function info(MerchantTakeRepository $repository)
    {
        $merchant = $this->request->merchant();
        $adminInfo = $this->request->adminInfo();
        $append = ['merchantCategory', 'merchantType', 'mer_certificate','margin_remind_status'];
        if ($merchant->is_margin == -10)
            $append[] = 'refundMarginOrder';

        $data = $merchant->append($append)->hidden(['mark', 'reg_admin_id', 'sort'])->toArray();
        $delivery = $repository->get($this->request->merId()) + systemConfig(['tx_map_key']);
        $data = array_merge($data,$delivery);
        $data['sys_bases_status'] = systemConfig('sys_bases_status') == 0 ? 0 : 1;
        $data['services_type'] = (int)merchantConfig((int)$merchant->mer_id,'services_type');
        $data['customer_corpId'] = merchantConfig((int)$merchant->mer_id,'customer_corpId');
        $data['customer_url'] = merchantConfig((int)$merchant->mer_id,'customer_url');
        $data['mer_account'] = $adminInfo['account'];

        return app('json')->success($data);
    }

    /**
     * 获取商家提现信息
     *
     * @param MerchantTakeRepository $repository 商家提现仓库
     * @return mixed
     */
    public function takeInfo(MerchantTakeRepository $repository)
    {
        // 获取商家ID
        $merId = $this->request->merId();
        // 调用仓库的get方法获取商家提现信息并加上系统配置中的tx_map_key
        return app('json')->success($repository->get($merId) + systemConfig(['tx_map_key']));
    }

    /**
     * 设置商家提现信息
     *
     * @param MerchantTakeValidate $validate 商家提现验证器
     * @param MerchantTakeRepository $repository 商家提现仓库
     * @return mixed
     */
    public function take(MerchantTakeValidate $validate, MerchantTakeRepository $repository)
    {
        $data = $this->request->params(['mer_take_status', 'mer_take_name', 'mer_take_phone', 'mer_take_address', 'mer_take_location', 'mer_take_day', 'mer_take_time']);
        // 验证商家提现信息
        $validate->check($data);
        // 调用仓库的set方法设置商家提现信息
        $repository->set($this->request->merId(), $data);
        // 返回设置成功的消息
        return app('json')->success('设置成功');
    }


    /**
     * 获取边距二维码
     *
     * @return \think\response\Json
     */
    public function getMarginQrCode()
    {
        // 设置支付类型为1
        $data['pay_type'] = 1;
        $data['type'] = $this->request->param('type', 10);
        // 调用 ServeOrderRepository 类的 QrCode 方法生成二维码
        $res = app()->make(ServeOrderRepository::class)->QrCode($this->request->merId(), 'margin', $data);
        // 返回生成的二维码
        return app('json')->success($res);
    }

    /**
     * 获取边距列表
     *
     * @return \think\response\Json
     */
    public function getMarginLst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 设置查询条件
        $where = [
            'mer_id' => $this->request->merId(),
            'category' => 'mer_margin'
        ];
        // 调用 UserBillRepository 类的 getLst 方法获取列表数据
        $data = app()->make(UserBillRepository::class)->getLst($where, $page, $limit);
        // 返回列表数据
        return app('json')->success($data);
    }



}
