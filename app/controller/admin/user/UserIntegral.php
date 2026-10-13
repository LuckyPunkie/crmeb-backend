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


namespace app\controller\admin\user;


use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\CacheRepository;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\user\UserBillRepository;
use app\validate\admin\IntegralConfigValidate;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

/**
 * Class UserIntegral
 * app\controller\admin\user
 * 用户积分
 */
class UserIntegral extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserBillRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  积分日志
     * @return \think\response\Json
     * @author Qinii
     * @day 6/9/21
     */
    public function getList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date']);
        $where['category'] = 'integral';
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 头部统计
     * @return \think\response\Json
     * @author Qinii
     * @day 6/9/21
     */
    public function getTitle()
    {
        return app('json')->success($this->repository->getStat());
    }

    /**
     * 导出
     * @return \think\response\Json
     * @author Qinii
     * @day 6/9/21
     */
    public function excel()
    {
        $where = $this->request->params(['keyword', 'date']);
        $where['category'] = 'integral';

        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->integralLog($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 配置
     * @return \think\response\Json
     * @author Qinii
     */
    public function getConfig()
    {
        $config = systemConfig(
            [
                'integral_status',
                'integral_clear_time',
                'integral_order_rate',
                'integral_freeze',
                'integral_user_give',
                'integral_money',
                'integral_community_give',
                'integral_community_give_limit'
            ]
        );
        $config = array_filter($config, function ($v) {
                return $v !== '';
            }) + ['integral_status' => 0, 'integral_clear_time' => 0, 'integral_order_rate' => 0, 'integral_freeze' => 0, 'integral_user_give' => 0, 'integral_money' => 0, 'integral_community_give' => 0, 'integral_community_give_limit' => 1];
        $config['rule'] = app()->make(CacheRepository::class)->getResultByKey(CacheRepository::INTEGRAL_RULE);
        return app('json')->success($config);
    }

    /**
     * 保存配置
     * @param IntegralConfigValidate $validate
     * @return \think\response\Json
     * @author Qinii
     */
    public function saveConfig(IntegralConfigValidate $validate)
    {
        $config = $this->request->params(['integral_status', 'integral_clear_time', 'integral_order_rate', 'integral_freeze', 'integral_user_give', 'integral_money', 'integral_community_give', 'integral_community_give_limit', 'rule']);
        $validate->check($config);
        app()->make(CacheRepository::class)->save('sys_integral_rule', $config['rule']);
        unset($config['rule']);
        if (!($cid = app()->make(ConfigClassifyRepository::class)->keyById('integral'))) return app('json')->fail('保存失败');
        app()->make(ConfigValueRepository::class)->save($cid, $config, 0);
        return app('json')->success('保存成功');
    }
}
