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


namespace app\controller\merchant\system\serve;

use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use think\App;
use think\facade\Cache;

class Config extends BaseController
{
    /**
     * @var ServeOrderRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param ServeOrderRepository $repository
     */
    public function __construct(App $app, ServeOrderRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取商家信息和配置信息
     *
     * @return \think\response\Json
     */
    public function info()
    {
        // 获取短信服务账号信息
        $sms_info = systemConfigNoCache('serve_account');
        // 获取当前商家ID
        $mer_id = $this->request->merId();
        // 根据商家ID获取商家信息
        $ret = app()->make(MerchantRepository::class)->get($mer_id);
        // 构造返回数据
        $data['mer_id'] = $ret['mer_id'];
        $data = [
            'info' => $sms_info,
            'copy_product_status' => systemConfig('copy_product_status'),
            'copy_product_num' => $ret['copy_product_num'],
            'crmeb_serve_dump' => systemConfig('crmeb_serve_dump'),
            'export_dump_num' => $ret['export_dump_num'],
        ];
        // 返回JSON格式的数据
        return app('json')->success($data);
    }

    /**
     * 获取商家配置信息
     *
     * @return \think\response\Json
     */
    public function getConfig()
    {
        $merId = $this->request->merId();
        // 需要获取的配置项
        $config = [
            'mer_from_com',
            'mer_from_name',
            'mer_from_tel',
            'mer_from_addr',
            'mer_config_siid',
            'mer_config_temp_id',
            'serve_account',
            'serve_token',
        ];
        // 获取商家配置信息
        $data = merchantConfig($merId, $config);
        // 返回JSON格式的数据
        return app('json')->success($data);
    }


    /**
     * 设置配置信息
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function setConfig()
    {
        // 配置项数组
        $config = [
            'mer_from_com', // 商家公司名称
            'mer_from_name', // 商家联系人姓名
            'mer_from_tel', // 商家联系电话
            'mer_from_addr', // 商家联系地址
            'mer_config_siid', // 商家配送服务ID
            'mer_config_temp_id', // 商家配送模板ID
            'serve_account', // 服务账号
            'serve_token', // 服务令牌
        ];
        // 从请求参数中获取配置项数据
        $data = $this->request->params($config);

        app()->make(ConfigValueRepository::class)->setFormData($data, $this->request->merId());

        // 返回操作成功的 JSON 响应
        return app('json')->success('保存成功');
    }


}
