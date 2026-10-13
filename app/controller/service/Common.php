<?php

namespace app\controller\service;

use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\basic\BaseController;

class Common extends BaseController
{
    /**
     * 获取商家信息
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function info()
    {
        $merId = $this->request->merId();
        if ($merId) {
            $merchant = app()->make(MerchantRepository::class)->get($merId);
            $data = [
                'mer_id' => $merchant['mer_id'],
                'avatar' => $merchant['mer_avatar'],
                'name'  => $merchant['mer_name'],
            ];
        } else {
            $config = systemConfig(['site_logo', 'site_name','login_logo']);
            $data = [
                'mer_id' => 0,
                'avatar' => $config['site_logo'],
                'name' => $config['site_name'],
            ];
        }
        return app('json')->success($data);
    }

    /**
     * 获取管理员信息
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function user()
    {
        $admin = $this->request->adminInfo();
        return app('json')->success($admin->hidden(['pwd', 'merchant'])->toArray());
    }

    /**
     * 获取站点配置
     * @return \think\response\Json
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/8
     */
    public function config()
    {
        return app('json')->success(systemConfig(['site_name', 'site_logo', 'beian_sn']));
    }
}
