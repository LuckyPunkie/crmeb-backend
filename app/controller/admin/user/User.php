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
use app\common\repositories\system\config\ConfigRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\user\UserBrokerageRepository;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserSignRepository;
use app\common\repositories\user\UserSpreadLogRepository;
use app\common\repositories\user\UserVisitRepository;
use app\validate\admin\UserRegisterValidate;
use crmeb\basic\BaseController;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserBillRepository;
use app\common\repositories\user\UserGroupRepository;
use app\common\repositories\user\UserLabelRepository;
use app\common\repositories\user\UserRepository;
use app\common\repositories\wechat\WechatNewsRepository;
use app\common\repositories\wechat\WechatUserRepository;
use app\validate\admin\UserNowMoneyValidate;
use app\validate\admin\UserValidate;
use crmeb\services\ExcelService;
use crmeb\services\SearchUtilsServices;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;

/**
 * 用户
 * Class User
 * @package app\controller\admin\user
 * @author xaboy
 * @day 2020-05-07
 */
class User extends BaseController
{
    /**
     * @var UserRepository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param UserRepository $repository
     */
    public function __construct(App $app, UserRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function lst()
    {
        /*
         * 昵称，分组，标签，地址，性别，
         */
       /*
       {
            "filter_conditions" : [
                {
                    "field_name":"uid", //搜索字段
                    "operator":"in", // 搜索方式
                    "value":"10697",    //搜索值
                    "form_value":"input" //搜索框类型
                }
            ],
            "boolean":"and"     //各种条件存在关系 and 或 or
        }
       */
        $filter_conditions = $this->request->param('filter_conditions/a',[]);
        $where = $this->request->params([
            'nickname', 'phone','uid','user_type',
            'label_id','user_type','sex','is_promoter','country','pay_count','user_time_type','user_time','province','city','group_id','is_svip','fields_type','fields_value','member_level','keyword','birthday'
        ]);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getList($where, $page, $limit,$filter_conditions));
    }

    /**
     * 推广人列表
     * @param $uid
     * @return \think\response\Json
     * @author Qinii
     */
    public function spreadList($uid)
    {
        $where = $this->request->params(['level', 'keyword', 'date']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getLevelList($uid, $where, $page, $limit));
    }

    /**
     * 推广订单
     * @param $uid
     * @return \think\response\Json
     * @author Qinii
     */
    public function spreadOrder($uid)
    {
        $where = $this->request->params(['level', 'keyword', 'date']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->subOrder($uid, $page, $limit, $where));
    }

    /**
     * 清除推广人
     * @param $uid
     * @return \think\response\Json
     * @author Qinii
     */
    public function clearSpread($uid)
    {
        $this->repository->update($uid, ['spread_uid' => 0]);
        return app('json')->success('清除成功');
    }


    /**
     * 添加用户 - 弃用
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-05-07
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->createForm()));
    }

    /**
     * 获取扩展字段数据
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function getFields()
    {
        return app('json')->success($this->repository->getFields());
    }

    /**
     * 添加用户
     * @param UserValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020-05-07
     */
    public function create(UserValidate $validate)
    {
        $data = $this->request->params([
            'account',
            'pwd',
            'repwd',
            'nickname',
            'avatar',
            'real_name',
            'phone',
            'sex',
            'status',
            'card_id',
            ['is_promoter', 0],
            ['extend_info', []],
            ['promoter_switch',1],
        ]);

        $validate->scene('create')->check($data);
        $data['pwd'] = $this->repository->encodePassword($data['repwd']);
        unset($data['repwd']);
        if ($data['is_promoter']) $data['promoter_time'] = date('Y-m-d H:i:s');
        if (empty($data['nickname'])) {
            $data['nickname'] = substr_replace($data['account'], '****', 3, 4);;
        }
        $this->repository->create('h5', $data);

        return app('json')->success('添加成功');
    }

    /**
     * 修改用户密码表单
     * @param $id
     * @param UserValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020-05-07
     */
    public function changePasswordForm($id)
    {
        return app('json')->success(formToData($this->repository->changePasswordForm($id)));
    }

    /**
     * 修改用户密码
     * @param $id
     * @param UserValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020-05-07
     */
    public function changePassword($id)
    {
        $data = $this->request->params([
            'pwd',
            'repwd',
        ]);
        if (!$data['pwd'] || !$data['repwd'])
            return app('json')->fail('密码不能为空');
        if ($data['pwd'] !== $data['repwd'])
            return app('json')->fail('密码不一致');
        $data['pwd'] = $this->repository->encodePassword($data['repwd']);
        unset($data['repwd']);
        $this->repository->update($id, $data);

        return app('json')->success('修改成功');
    }


    /**
     * 修改用户表单 - 弃用
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-09
     */
    public function updateForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->userForm($id)));
    }

    /**
     * 修改用户
     * @param $id
     * @param UserValidate $validate
     * @param UserLabelRepository $labelRepository
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-09
     */
    public function update($id, UserValidate $validate, UserLabelRepository $labelRepository, UserGroupRepository $groupRepository)
    {
        $data = $this->request->params(['phone', 'card_id', 'mark', 'group_id', ['label_id', []], ['is_promoter', 0],
            ['status', 0], ['member_level', ''], ['extend_info', []],'promoter_switch']);
        $extend_info = $data['extend_info'];
        unset($data['extend_info']);
        $validate->check($data);
        if (!$user = $this->repository->get($id))
            return app('json')->fail('数据不存在');
        if ($data['group_id'] && !$groupRepository->exists($data['group_id']))
            return app('json')->fail('分组不存在');
        $label_id = (array)$data['label_id'];
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $data['label_id'] = implode(',', $label_id);
        if ($data['is_promoter'])
            $data['promoter_time'] = date('Y-m-d H:i:s');
//        if (!$data['birthday']) unset($data['birthday']);

        if ($data['member_level'] !== '') {
            $make = app()->make(UserBrokerageRepository::class);
            if ($data['member_level'] == $user->member_level) {
                unset($data['member_level']);
            } else {
                $has = $make->fieldExists('brokerage_level', $data['member_level'], null, 1);
                if (!$has) return app('json')->fail('等级不存在');
                $data['member_value'] = 0;

                // 记录用户等级变化时成长值变化
                if ($user->member_value > 0) {
                    app()->make(UserBillRepository::class)->decBill($user->uid, 'sys_members', 'platform_clearing', [
                        'number' => $user->member_value,
                        'title' => '平台修改等级',
                        'balance' => $data['member_value'],
                        'status' => 0,
                        'mark' => '平台修改等级清除成长值' . ':' . $user->member_value,
                    ]);
                }
            }
        } else {
            unset($data['member_level']);
        }
        // 更新扩展字段
        $this->repository->saveFields((int)$id, $extend_info);
        $this->repository->update($id, $data);

        return app('json')->success('编辑成功');
    }


    /**
     * 修改用户标签
     * @param $id
     * @param UserLabelRepository $labelRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-08
     */
    public function changeLabel($id, UserLabelRepository $labelRepository)
    {
        $label_id = (array)$this->request->param('label_id', []);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $label_id = implode(',', $label_id);
        $this->repository->update($id, compact('label_id'));
        return app('json')->success('修改成功');
    }

    /**
     * 批量修改用户标签
     * @param UserLabelRepository $labelRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeLabel(UserLabelRepository $labelRepository)
    {
        $label_id = (array)$this->request->param('label_id', []);
        $ids = (array)$this->request->param('ids', []);
        if (!count($ids))
            return app('json')->fail('数据不存在');
        foreach ($label_id as $k => $value) {
            $label_id[$k] = (int)$value;
            if (!$labelRepository->exists((int)$value))
                return app('json')->fail('标签不存在');
        }
        $this->repository->batchChangeLabelId($ids, $label_id);
        return app('json')->success('修改成功');
    }


    /**
     * 修改用户标签表单
     * @param $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function changeLabelForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeLabelForm($id)));
    }


    /**
     * 批量修改用户标签表单
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeLabelForm()
    {
        $ids = $this->request->param('ids', '');
        $ids = array_filter(explode(',', $ids));
        if (!count($ids))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeLabelForm($ids)));
    }


    /**
     * 批量修改用户分组表单
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-08
     */
    public function batchChangeGroupForm()
    {
        $ids = $this->request->param('ids', '');
        $ids = array_filter(explode(',', $ids));
        if (!count($ids))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeGroupForm($ids)));
    }

    /**
     * 修改用户分组
     * @param $id
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeGroup($id, UserGroupRepository $groupRepository)
    {
        $group_id = (int)$this->request->param('group_id', 0);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        if ($group_id && !$groupRepository->exists($group_id))
            return app('json')->fail('分组不存在');
        $this->repository->update($id, compact('group_id'));
        return app('json')->success('修改成功');
    }

    /**
     * 批量修改用户分组
     * @param UserGroupRepository $groupRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-07
     */
    public function batchChangeGroup(UserGroupRepository $groupRepository)
    {
        $group_id = (int)$this->request->param('group_id', 0);
        $ids = (array)$this->request->param('ids', []);
        if (!count($ids))
            return app('json')->fail('数据不存在');
        if ($group_id && !$groupRepository->exists($group_id))
            return app('json')->fail('分组不存在');
        $this->repository->batchChangeGroupId($ids, $group_id);
        return app('json')->success('修改成功');
    }

    /**
     * 修改用户分组表单
     * @param $id
     * @return mixed
     * @throws FormBuilderException
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeGroupForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeGroupForm($id)));
    }

    /**
     * 修改用户余额表单
     * @param $id
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeNowMoneyForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeNowMoneyForm($id)));
    }

    /**
     * 修改用户积分表单
     * @param $id
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeIntegralForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeIntegralForm($id)));
    }

    /**
     * 修改用户余额
     * @param $id
     * @param UserNowMoneyValidate $validate
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeNowMoney($id, UserNowMoneyValidate $validate)
    {
        $data = $this->request->params(['now_money', 'type']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeNowMoney($id, $this->request->adminId(), $data['type'], $data['now_money']);

        return app('json')->success('修改成功');
    }

    /**
     * 修改用户积分
     * @param $id
     * @param UserNowMoneyValidate $validate
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-05-07
     */
    public function changeIntegral($id, UserNowMoneyValidate $validate)
    {
        $data = $this->request->params(['now_money', 'type']);
        $validate->check($data);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->changeIntegral($id, $this->request->adminId(), $data['type'], $data['now_money']);

        return app('json')->success('修改成功');
    }

    /**
     * 发送微信消息
     * @param WechatNewsRepository $wechatNewsRepository
     * @param WechatUserRepository $wechatUserRepository
     * @return mixed
     * @author xaboy
     * @day 2020-05-11
     */
    public function sendNews(WechatNewsRepository $wechatNewsRepository, WechatUserRepository $wechatUserRepository)
    {
        $ids = $this->request->param('ids');
        if (!is_array($ids)) $ids = explode(',', $this->request->param('ids'));
        $ids = array_filter(array_unique($ids));
        $news_id = (int)$this->request->param('news_id', 0);
        if (!$news_id)
            return app('json')->fail('请选择图文消息');
        if (!$wechatNewsRepository->exists($news_id))
            return app('json')->fail('数据不存在');
        if (!count($ids))
            return app('json')->fail('请选择微信用户');
        $wechatUserRepository->sendNews($news_id, $ids);
        return app('json')->success('发送成功');
    }

    /**
     * 推广人列表
     * @return mixed
     * @author xaboy
     * @day 2020-05-11
     */
    public function promoterList()
    {
        $where = $this->request->params(['keyword', 'date', 'brokerage_level','uid','nickname','phone','real_name']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->promoterList($where, $page, $limit));
    }

    /**
     * 推广人统计
     * @return mixed
     * @author xaboy
     * @day 2020-05-11
     */
    public function promoterCount()
    {
        $where = $this->request->params(['keyword', 'date', 'brokerage_level']);
        return app('json')->success($this->repository->promoterCount($where));
    }

    /**
     * 推广订单列表
     * @return mixed
     * @author xaboy
     * @day 2020-05-11
     */
    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->userOrderDetail($id));
    }

    public function order($id, StoreOrderRepository $repository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList($id, $page, $limit));
    }

    public function coupon($id, StoreCouponUserRepository $repository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList(['uid' => $id], $page, $limit));
    }

    public function bill($id, UserBillRepository $repository)
    {
        if (!$this->repository->exists(intval($id)))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList([
            'now_money' => 0,
            'status' => 1
        ], $id, $page, $limit));
    }

    public function spreadLog($id)
    {
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success(app()->make(UserSpreadLogRepository::class)->getList(['uid' => $id], $page, $limit));
    }

    public function spreadForm($id)
    {
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->changeSpreadForm($id)));
    }

    public function spread($id)
    {
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        $spid = $this->request->param('spid');
        $spid = (int)($spid['id'] ?? $spid);
        if ($spid == $id)
            return app('json')->fail('不能选自己');
        if ($spid && !$this->repository->exists($spid))
            return app('json')->fail('推荐人不存在');
        $this->repository->changeSpread($id, $spid, $this->request->adminId());
        return app('json')->success('修改成功');
    }

    public function searchLog()
    {
        $where = $this->request->params(['date', 'keyword', 'nickname', 'user_type','uid','phone','real_name']);
        $merId = $this->request->merId();
        $where['type'] = ['searchMerchant', 'searchProduct'];
        if ($merId) {
            $where['mer_id'] = $merId;
        }
        [$page, $limit] = $this->getPage();
        return app('json')->success(app()->make(UserVisitRepository::class)->getSearchLog($where, $page, $limit));
    }

    public function clearSearchLog()
    {
        $merId = $this->request->merId();
        $where['type'] = ['searchMerchant', 'searchProduct'];
        if ($merId) {
            $where['mer_id'] = $merId;
        }

        $res = app()->make(UserVisitRepository::class)->clearSearchLog($where);
        if (!$res) {
            return app('json')->fail('清除失败');
        }

        return app('json')->success('清除成功');
    }

    public function exportSearchLog()
    {
        $where = $this->request->params(['date', 'keyword', 'nickname', 'user_type']);
        $merId = $this->request->merId();
        $where['type'] = ['searchMerchant', 'searchProduct'];
        if ($merId) {
            $where['mer_id'] = $merId;
        }
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->searchLog($where, $page, $limit);
        return app('json')->success($data);

    }

    public function memberForm($id)
    {
        return app('json')->success(formToData($this->repository->memberForm($id, 1)));
    }

    public function memberSave($id)
    {
        $data = $this->request->params(['member_level']);
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        $this->repository->updateLevel($id, $data, 1);
        return app('json')->success('修改成功');
    }

    public function spreadLevelForm($id)
    {
        return app('json')->success(formToData($this->repository->memberForm($id, 0)));
    }

    public function spreadLevelSave($id)
    {
        $brokerage_level = $this->request->params(['brokerage_level']);
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        $this->repository->updateLevel($id, $brokerage_level, 0);
        return app('json')->success('修改成功');
    }

    public function svipForm($id)
    {
        return app('json')->success(formToData($this->repository->svipForm($id)));
    }

    public function svipUpdate($id)
    {
        $data = $this->request->params(['is_svip', 'add_time', 'type']);
        $this->repository->svipUpdate($id, $data, $this->request->adminId());
        return app('json')->success('修改成功');
    }

    /**
     * 积分记录
     * @param $id
     * @author Qinii
     * @day 2023/4/25
     */
    public function integralList($id, UserBillRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        $data = $repository->userList(['category' => 'integral'], $id, $page, $limit);
        return app('json')->success($data);
    }


    /**
     * 签到记录
     * @param $id
     * @param UserSignRepository $signRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/4/25
     */
    public function sign_log($id, UserSignRepository $signRepository)
    {
        [$page, $limit] = $this->getPage();
        $where = ['uid' => $id];
        $data = $signRepository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    public function history($id, UserHistoryRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->getList($page, $limit, $id, 1));
    }

    public function excel()
    {
        /*
         * 昵称，分组，标签，地址，性别，
         */

        $where = $this->request->params([
            'label_id',
            'user_type',
            'sex',
            'is_promoter',
            'country',
            'pay_count',
            'user_time_type',
            'user_time',
            'nickname',
            'province',
            'city',
            'group_id',
            'phone',
            'uid',
            'is_svip',
            'fields_type',
            'fields_value',
            'ids'
        ]);
        $where['uids'] = $where['ids'];
        $viewSearch = $this->request->param('filter_conditions',[]);
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->user($where, $page, $limit, $viewSearch);
        return app('json')->success($data);
    }

    public function getMemberLevelSelectList()
    {
        return app('json')->success($this->repository->getMemberLevelSelectList());
    }

    /**
     * 批量设置分销员表单
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/26
     */
    public function batchSpreadForm()
    {
        $ids = $this->request->param('ids', '');
        $ids = array_filter(explode(',', $ids));
        if (!count($ids))
            return app('json')->fail('数据不存在');
        $data = $this->repository->batchSpreadForm($ids);
        return app('json')->success(formToData($data));
    }

    /**
     * 批量设置分销员
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/10/26
     */
    public function batchSpread()
    {
        $uids = $this->request->param('uids', []);
        $is_promoter = $this->request->param('is_promoter') == 1 ? 1 : 0;
        $promoter_switch = $this->request->param('promoter_switch') == 1 ? 1 : 0;
        $this->repository->updates($uids, compact('promoter_switch','is_promoter'));
        return app('json')->success('修改成功');
    }

    /**
     *  保存用户注册配置信息
     * @return \think\response\Json
     * @author Qinii
     */
    public function saveRegisterConfig()
    {
        $configKey = app()->make(ConfigRepository::class)->getConfigKey('user_register');
        $params = array_column($configKey['config_keys'],'config_key');
        //$params = ['first_avatar_switch','is_phone_login','newcomer_status','open_update_info',
        //'register_coupon_status','register_give_coupon','register_give_integral','register_give_money','register_integral_status','register_money_status','register_popup_pic','wechat_phone_switch'];
        $data = $this->request->params($params);
        if ($data['register_money_status'] && !$data['register_give_money'])
            return app('json')->fail('请填写余额赠送金额');
        if ($data['register_integral_status'] && !$data['register_give_integral'])
            return app('json')->fail('请填写积分赠送金额');
        app()->make(UserRegisterValidate::class)->check($data);
        if (is_array($data['register_give_coupon'])) $data['register_give_coupon'] = implode(',',$data['register_give_coupon']);
        app()->make(ConfigValueRepository::class)->setFormData($data,0);
        return app('json')->success('修改成功');
    }

    /**
     *  获取注册赠送优惠券列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function getRegisterCoupon()
    {
        [$page, $limit] = $this->getPage();
        $storeCouponRepository = app()->make(StoreCouponRepository::class);
        if (!systemConfig('register_give_coupon')) return app('json')->success([]);
        $data = $storeCouponRepository->getList(0,['coupon_ids' => systemConfig('register_give_coupon')],$page, $limit);
        return app('json')->success($data);
    }

}
