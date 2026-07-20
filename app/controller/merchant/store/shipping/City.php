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

namespace app\controller\merchant\store\shipping;

use app\common\repositories\store\CityAreaRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\shipping\CityRepository as repository;
use think\facade\Log;

class City extends BaseController
{
    protected $repository;

    /**
     * City constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 调用 repository 类的 getFormatList 方法获取列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success($this->repository->getFormatList());
    }

    /**
     * 获取指定 pid 下的子级列表
     *
     * @param int $pid 父级 ID
     * @return \think\response\Json
     */
    public function lstV2($pid)
    {
        // 调用 CityAreaRepository 类的 getChildren 方法获取子级列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success(app()->make(CityAreaRepository::class)->getChildren(intval($pid)));
    }

    /**
     * 根据地址查询城市列表
     *
     * @return \think\response\Json
     */
    public function cityList()
    {
        // 从请求参数中获取地址
        $address = $this->request->param('address');

        // 如果地址不存在，则返回失败状态和错误信息
        if (!$address){
            Log::info('用户定位对比失败，地址不存在:' . var_export($address, true));
            return app('json')->fail('地址不存在');
        }

        // 创建 CityAreaRepository 实例
        $make = app()->make(CityAreaRepository::class);
        // 根据地址查询城市信息
        $city = $make->search(compact('address'))->order('id DESC')->find();
        // 如果城市信息不存在，则记录日志并返回失败状态和错误信息
        if (!$city) {
            Log::info('用户定位对比失败，请在城市数据中增加:' . var_export($address, true));
            return app('json')->fail('地址不存在');
        }
        // 调用 CityAreaRepository 类的 getCityList 方法获取城市列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success($make->getCityList($city));
    }

    /**
     * 获取列表数据
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getlist()
    {
        // 调用 repository 类的 getFormatList 方法获取 is_show 字段为 1 的列表数据，并通过 json 组件返回成功状态和数据
        return app('json')->success($this->repository->getFormatList(['is_show' => 1]));
    }

}
