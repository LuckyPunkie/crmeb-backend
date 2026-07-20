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


namespace app\controller\admin\system\config;


use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 系统配置
 */
class ConfigValue extends BaseController
{
    /**
     * @var ConfigClassifyRepository
     */
    private $repository;

    /**
     * ConfigValue constructor.
     * @param App $app
     * @param ConfigValueRepository $repository
     */
    public function __construct(App $app, ConfigValueRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 保存配置
     * @param string $key
     * @return mixed
     * @author xaboy
     * @day 2020-04-22
     */
    public function save($key)
    {
        $formData = $this->request->post();
        if (!count($formData))
            return app('json')->fail('保存失败');
        /** @var ConfigClassifyRepository $make */
        $make = app()->make(ConfigClassifyRepository::class);
        if (!($cid = $make->keyById($key)))
            return app('json')->fail('保存失败');
        $children = array_column($make->children($cid, 'config_classify_id')->toArray(), 'config_classify_id');
        $children[] = $cid;
        $this->repository->save($children, $formData, $this->request->merId());
        //$this->repository->after_review_mode($cid,$formData,$this->request->merId(),1);
        return app('json')->success('保存成功');
    }
}
