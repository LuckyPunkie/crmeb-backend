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

use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\ChangeMerchantStatusJob;
use FormBuilder\Factory\Elm;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\config\ConfigRepository as repository;
use app\common\repositories\system\config\ConfigValueRepository;
use think\facade\Db;
use think\facade\Queue;
use think\facade\Route;

/**
 * 其他配置
 */
class ConfigOthers extends BaseController
{

    public $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 编辑
     * @return \think\response\Json
     * @author Qinii
     */
    public function update()
    {
        $data = $this->request->params([
            'extension_status',
            'extension_two_rate',
            'extension_one_rate',
            'extension_self',
            'extension_limit',
            'extension_limit_day',
            'sys_extension_type',
            'lock_brokerage_timer',
            'max_bag_number',
            'promoter_explain',
            'user_extract_min',
            'withdraw_type',
            'promoter_type',
            'promoter_low_money',
            'extract_switch',
            'extension_pop',
            'transfer_scene_id'
        ]);

        if ($data['extension_two_rate'] < 0 || $data['extension_one_rate'] < 0)
            return app('json')->fail('比例不能小于0');
        if (bccomp($data['extension_one_rate'], $data['extension_two_rate'], 4) == -1)
            return app('json')->fail('一级比例不能小于二级比例');
        if (bccomp(bcadd($data['extension_one_rate'], $data['extension_two_rate'], 3), 1, 3) == 1)
            return app('json')->fail('比例之和不能超过1，即100%');
        if (!ctype_digit((string)$data['extension_limit_day']) || $data['extension_limit_day'] <= 0)
            return app('json')->fail('分销绑定时间必须大于0');
        if ($data['promoter_type'] == 3 && (!ctype_digit((string)$data['promoter_low_money']) || $data['promoter_low_money'] <= 0))
            return app('json')->fail('满额分销最低金额必须大于0');

        $old = systemConfig(['extension_limit', 'extension_limit_day']);

        if (!$old['extension_limit'] && $data['extension_limit']) {
            app()->make(UserRepository::class)->initSpreadLimitDay(intval($data['extension_limit_day']));
        } else if ($old['extension_limit'] && !$data['extension_limit']) {
            app()->make(UserRepository::class)->clearSpreadLimitDay();
        } else if ($data['extension_limit_day'] != $old['extension_limit_day'] && $data['extension_limit']) {
            app()->make(UserRepository::class)->updateSpreadLimitDay(intval($data['extension_limit_day'] - $old['extension_limit_day']));
        }

        app()->make(ConfigValueRepository::class)->setFormData($data, 0);
        app()->make(ConfigValueRepository::class)->syncConfig();

        return app('json')->success('修改成功');
    }


    /**
     *  拼团相关配置
     * @return \think\response\Json
     * @author Qinii
     * @day 4/6/22
     */
    public function getGroupBuying()
    {
        $data = [
            'ficti_status' => systemConfig('ficti_status') ?: 0,
            'group_buying_rate' => systemConfig('group_buying_rate'),
        ];
        return app('json')->success($data);
    }

    /**
     * 拼团相关设置
     * @return \think\response\Json
     * @author Qinii
     */
    public function setGroupBuying()
    {
        $data['ficti_status'] = $this->request->param('ficti_status') == 1 ? 1 : 0;
        $data['group_buying_rate'] = $this->request->param('group_buying_rate');
        if ($data['group_buying_rate'] < 0 || $data['group_buying_rate'] > 100)
            return app('json')->fail('请填写1～100之间的整数');
        app()->make(ConfigValueRepository::class)->setFormData($data, 0);

        return app('json')->success('修改成功');
    }

    /**
     * 自动分账相关设置
     * @return \think\response\Json
     * @author Qinii
     */
    public function getProfitsharing()
    {
        return app('json')->success(array_filter(systemConfig(['extract_maxmum_num', 'extract_minimum_line', 'extract_minimum_num', 'open_wx_combine', 'open_wx_sub_mch', 'mer_lock_time']), function ($val) {
                return $val !== '';
            }) + ['open_wx_sub_mch' => 0, 'open_wx_combine' => 0]);
    }

    /**
     * 提现相关设置
     * @return \think\response\Json
     * @author Qinii
     */
    public function setProfitsharing()
    {
        $data = $this->request->params(['extract_maxmum_num', 'extract_minimum_line', 'extract_minimum_num', 'open_wx_combine', 'open_wx_sub_mch', 'mer_lock_time']);
        if ($data['extract_minimum_num'] < $data['extract_minimum_line'])
            return app('json')->fail('最小提现额度不能小于最低提现金额');
        if ($data['extract_maxmum_num'] < $data['extract_minimum_num'])
            return app('json')->fail('最高提现额度不能小于最小提现额度');
        $config = systemConfig(['open_wx_combine', 'wechat_service_merid', 'wechat_service_key', 'wechat_service_v3key', 'wechat_service_client_cert', 'wechat_service_client_key', 'wechat_service_serial_no','wechat_service_tool']);
        $open_wx_combine = $config['open_wx_combine'];
        unset($config['open_wx_combine']);
        if (($data['open_wx_combine'] || $data['open_wx_sub_mch']) && count(array_filter($config)) < 6) {
            return app('json')->fail('请先配置微信服务商相关参数');
        }

        Db::transaction(function () use ($data, $open_wx_combine,$config) {
            app()->make(ConfigValueRepository::class)->setFormData($data, 0);

            if (!$open_wx_combine && $data['open_wx_combine'] && !$config['wechat_service_tool']) {
                $merchantRepository = app()->make(MerchantRepository::class);
                $column =$merchantRepository->search([])->where('sub_mchid', '')->column('mer_id');
                $merchantRepository->search([])->where('sub_mchid', '')->save(['mer_state' => 0]);
                foreach ($column as $merId) {
                    Queue::push(ChangeMerchantStatusJob::class, $merId);
                }
            }
        });
        return app('json')->success('修改成功');
    }
}
