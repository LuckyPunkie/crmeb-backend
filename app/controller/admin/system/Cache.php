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
namespace app\controller\admin\system;

use app\common\repositories\system\CacheRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use crmeb\basic\BaseController;
use crmeb\services\RedisCacheService;
use think\App;
use think\facade\Cache as BaseCache;

class Cache extends BaseController
{
    /**
     * @var CacheRepository
     */
    protected $repository;

    /**
     * CacheRepository constructor.
     * @param App $app
     */
    public function __construct(App $app, CacheRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取协议列表字段
     * @return \think\response\Json
     * @author Qinii
     */
    public function getKeyLst()
    {
        $type = $this->request->param('type', 0);
        $data = $this->repository->getAgreeList($type);
        return app('json')->success($data);
    }


    /**
     * 各种协议获取
     * @Author:Qinii
     * @Date: 2020/9/15
     * @return mixed
     */
    public function getAgree($key)
    {
        $allow = $this->repository->getAgreeKey();
        if (!in_array($key, $allow)) return app('json')->fail('数据不存在');
        $data = $this->repository->getResult($key);
        return app('json')->success($data);
    }


    /**
     * 协议保存
     * @Author:Qinii
     * @Date: 2020/9/15
     * @return mixed
     */
    public function saveAgree($key)
    {
        $allow = $this->repository->getAgreeKey();
        if (!in_array($key, $allow)) return app('json')->fail('KEY不存在');

        $value = $this->request->param('agree');
        $this->repository->save($key, $value);

        if ($key == CacheRepository::USER_PRIVACY)
            $this->repository->setUserAgreement($value);
        if ($key == CacheRepository::USER_AGREE)
            $this->repository->setUserRegister($value);

        return app('json')->success('保存成功');
    }

    /**
     *  清除缓存
     * @return \think\response\Json
     * @author Qinii
     * @day 12/9/21
     */
    public function clearCache()
    {
        $type = $this->request->param('type', 1);
        switch ($type) {
            case 2:
                BaseCache::tag('get_product')->clear();
                break;
            case 3:
                BaseCache::delete('get_api_config');
                break;
            default:
                BaseCache::clear();
                break;
        }
        $configValueRepository = app()->make(ConfigValueRepository::class);
        $configValueRepository->syncConfig();
        $configValueRepository->special();
        return app('json')->success('清除缓存成功');
    }
}
