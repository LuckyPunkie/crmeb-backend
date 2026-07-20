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

use app\common\repositories\system\serve\ServeMealRepository;
use app\common\repositories\system\serve\ServeOrderRepository;
use crmeb\basic\BaseController;
use think\App;
use think\facade\Cache;

class Serve extends BaseController
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
     * 获取二维码
     *
     * @return \think\response\Json
     */
    public function getQrCode()
    {
        // 获取服务账号信息
        $sms_info = systemConfigNoCache('serve_account');
        if (!$sms_info) {
            return app('json')->fail('平台未登录一号通');
        }
        // 获取请求参数
        $data = $this->request->params(['meal_id', 'pay_type']);
        // 调用仓库方法生成二维码
        $ret = $this->repository->QrCode($this->request->merId(), 'meal', $data);
        // 返回结果
        return app('json')->success($ret);
    }

    /**
     * 获取菜品列表
     *
     * @return \think\response\Json
     */
    public function meal()
    {
        // 获取服务账号信息
        $sms_info = systemConfigNoCache('serve_account');
        if (!$sms_info) {
            return app('json')->fail('平台未登录一号通');
        }

        // 获取分页参数和查询条件
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', 'copy');

        // 判断类型是否合法
        if ($type == 'copy' && !systemConfig('copy_product_status')) {
            return app('json')->fail('平台未开启一号通商品复制');
        }

        if ($type == 'dump' && systemConfig('crmeb_serve_dump') != 1) {
            return app('json')->fail('平台未开启一号通电子面单');
        }

        // 构建查询条件
        $where['type'] = $type == 'copy' ? 1 : 2;
        $where['status'] = 1;

        // 调用仓库方法获取菜品列表
        $data = app()->make(ServeMealRepository::class)->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 获取列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数和查询条件
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'type']);
        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取列表
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }


}
