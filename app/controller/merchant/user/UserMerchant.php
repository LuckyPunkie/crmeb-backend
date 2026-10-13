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


namespace app\controller\merchant\user;


use app\common\repositories\store\coupon\StoreCouponUserRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\user\UserLabelRepository;
use app\common\repositories\user\UserMerchantRepository;
use crmeb\basic\BaseController;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * Class UserMerchant
 * @package app\controller\merchant\user
 * @author xaboy
 * @day 2020/10/20
 */
class UserMerchant extends BaseController
{
    /**
     * @var UserMerchantRepository
     */
    protected $repository;

    /**
     * UserMerchant constructor.
     * @param App $app
     * @param UserMerchantRepository $repository
     */
    public function __construct(App $app, UserMerchantRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表
     *
     * @return \think\response\Json
     */
    public function getList()
    {
        $where = $this->request->params(['nickname', 'sex', 'is_promoter','user_time_type', 'user_time',
            'pay_count', 'label_id', 'user_type', 'uid','phone','keyword']);
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 添加商家ID查询条件
        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取列表并返回JSON格式的成功响应
        if ($where['user_time'] && $where['user_time_type'] == '') {
            $where['user_time_type'] = 'visit';
        }
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 显示标签修改表单
     *
     * @param int $id 用户ID
     * @return \think\response\Json
     */
    public function changeLabelForm($id)
    {
        // 判断用户是否存在
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        // 调用仓库方法获取标签修改表单数据并返回JSON格式的成功响应
        return app('json')->success(formToData($this->repository->changeLabelForm($this->request->merId(), $id)));
    }

    /**
     * 修改用户标签
     *
     * @param int $id 用户ID
     * @param UserLabelRepository $labelRepository 用户标签仓库
     * @return \think\response\Json
     */
    public function changeLabel($id, UserLabelRepository $labelRepository)
    {
        // 获取要修改的标签ID
        $label_id = (array)$this->request->param('label_id', []);
        // 判断用户是否存在
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        // 获取当前商家ID
        $merId = $this->request->merId();
        // 获取当前商家可用的标签ID
        $label_id = $labelRepository->intersection((array)$label_id, $merId, 0);
        // 合并原有标签和新增标签
        $label_id = array_unique(array_merge($label_id, $this->repository->get($id)->authLabel));
        // 转换为字符串形式
        $label_id = implode(',', $label_id);
        // 更新用户标签
        $this->repository->update($id, compact('label_id'));
        // 返回JSON格式的成功响应
        return app('json')->success('修改成功');
    }


    public function order($uid)
    {
        [$page, $limit] = $this->getPage();
        $data = app()->make(StoreOrderRepository::class)->userMerList($uid, $this->request->merId(), $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 获取用户优惠券列表
     *
     * @param int $uid 用户ID
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function coupon($uid)
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用 StoreCouponUserRepository 类的 userList 方法获取用户优惠券列表
        $data = app()->make(StoreCouponUserRepository::class)->userList(['mer_id' => $this->request->merId(), 'uid' => (int)$uid], $page, $limit);
        // 返回 JSON 格式的成功响应和数据
        return app('json')->success($data);
    }

    /**
     * 获取用户列表 - 公司员工/客服 对应的用户
     * @return \think\response\Json
     * @author Qinii
     */
    public function managerUserLst()
    {
        $where = $this->request->params(['keyword','']);
        [$page, $limit] = $this->getPage();
        $where['mer_id'] = $this->request->merId();
        $where['status'] = 1;
        $where['uids'] = $this->repository->getManagerUid($where);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

}
