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

use app\common\repositories\user\UserGroupRepository;
use app\common\repositories\user\UserLabelRepository;
use app\common\repositories\user\UserRepository;
use crmeb\basic\BaseController;
use crmeb\services\crud\SearchUtilsServices;
use crmeb\services\crud\CrudFormEnum;
use crmeb\services\crud\CrudOperatorEnum;
use think\App;

/**
 * 用户高级搜索
 * @package app\controller\admin\user
 * @author xaboy
 * @day 2020-05-07
 */
class UserSearch extends BaseController
{

    protected $repository;

    public function __construct(App $app, UserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  用户搜索字段
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/6/4
     */
    public function filters()
    {
        $data = $this->repository->filters();
        return app('json')->success($data);
    }

    /**
     *  用户搜索每个字段的可用搜索关系
     * @param $key
     * @param SearchUtilsServices $services
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/6/4
     */
    public function search($key, SearchUtilsServices $services)
    {
        switch ($key) {
            case 'uid' :
                $data = $services->getNumber();
                break;
            case 'nickname' :
                //notbreak
            case 'phone' :
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_INPUT);
                break;
            case 'sex' :
                $options = [['label' => '男','value' => 1],['label' => '女','value' => 2],['label' => '保密','value' => 0]];
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'is_promoter' :
                $options = [['label' => '普通用户','value' => 0],['label' => '推广员','value' => 1]];
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'is_svip' :
                $options = [
                    ['label' => '普通用户', 'value' =>-1],
                    ['label' => '过期会员', 'value' => 0],
                    ['label' => '体验会员', 'value' => 1],
                    ['label' => '付费会员', 'value' => 2],
                    ['label' => '永久会员', 'value' => 3],
                ];
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'group_id' :
                $options = app()->make(UserGroupRepository::class)->search([])->limit(50)->column('group_name label,group_id value');
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'label_id' :
                $options = app()->make(UserLabelRepository::class)->search([])->limit(50)->column('label_name label,label_id value');
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'member_level' :
                $options = $this->repository->getMemberLevelSelectList();
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_SELECT, $options);
                break;
            case 'create_time' :
                //notbreak
            case 'last_time' :
                //notbreak
            case 'birthday' :
                $data = $services->getDate();
                break;
            case 'pay_count' :
                //notbreak
            case 'now_money' :
                $data = $services->getNumber();
                break;
            default:
                $data = $services->get([CrudOperatorEnum::OPERATOR_IN], CrudFormEnum::FORM_INPUT);
                break;
        }
        return app('json')->success($data);
    }

}
