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


namespace app\controller\admin\system\merchant;


use crmeb\services\pay\Pay;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\product\ProductCopyRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\system\operate\OperateLogRepository;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use app\common\repositories\system\merchant\MerchantCategoryRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\admin\MerchantValidate;
use crmeb\jobs\ChangeMerchantStatusJob;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Queue;
use think\facade\Db;
use app\common\repositories\system\RelevanceRepository;

/**
 * 商户
 */
class Merchant extends BaseController
{
    /**
     * @var MerchantRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param MerchantRepository $repository
     */
    public function __construct(App $app, MerchantRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 商户列表头部统计
     * @return \think\response\Json
     * @return \think\response\Json
     * @author Qinii
     */
    public function count()
    {
        $where = $this->request->params(['keyword', 'date', 'status', 'statusTag', 'is_trader', 'category_id', 'type_id', 'is_best','offline_switch','region_id']);
        return app('json')->success($this->repository->count($where));
    }

    /**
     *  列表
     * @author xaboy
     * @day 2020-04-16
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'status', 'statusTag', 'is_trader', 'category_id', 'type_id', ['order', 'create_time'], 'is_best','offline_switch','region_id', 'business_id','mer_state']);
        return app('json')->success($this->repository->lst($where, $page, $limit));
    }


    /**
     * 统计表单 - 弃用
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-04-16
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form()));
    }

    /**
     * 创建
     * @return mixed
     * @author xaboy
     * @day 2020/7/2
     */
    public function create(MerchantValidate $validate)
    {
        $data = $this->checkParam($validate);
        $data['admin_info'] = $this->request->adminInfo();
        $this->repository->createMerchant($data);
        return app('json')->success('添加成功');
    }


    /**
     * 编辑表单
     * @param int $id
     * @author xaboy
     * @day 2020-04-16
     */
    public function updateForm($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');

        return app('json')->success(formToData($this->repository->updateForm($id)));
    }

    /**
     * 编辑
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-05-06
     */
    public function update($id, MerchantValidate $validate, MerchantCategoryRepository $merchantCategoryRepository)
    {
        $data = $this->checkParam($validate, true);
        if (!$merchant = $this->repository->get($id))
            return app('json')->fail('数据不存在');
        if ($this->repository->fieldExists('mer_name', $data['mer_name'], $id))
            return app('json')->fail('商户名已存在');
        if ($data['mer_phone'] && isPhone($data['mer_phone']))
            return app('json')->fail('请输入正确的手机号');
        if (!$data['category_id'] || !$merchantCategoryRepository->exists($data['category_id']))
            return app('json')->fail('商户分类不存在');

        unset($data['mer_account'], $data['mer_password']);
        $margin = $this->repository->checkMargin($id, $data['type_id']);
        $data['margin'] = $margin['margin'];
        $data['is_margin'] = $margin['is_margin'];
        $data['ot_margin'] = $margin['ot_margin'];

        // 商户编辑记录日志
        event('create_operate_log', [
            'category' => OperateLogRepository::PLATFORM_EDIT_MERCHANT,
            'data' => [
                'merchant' => $merchant,
                'admin_info' => $this->request->adminInfo(),
                'update_infos' => $data
            ],
        ]);

        Db::transaction(function () use ($id, $data) {
            // 处理关联店铺分组
            $relevanceRepository = app()->make(RelevanceRepository::class);
            $storeGroupIds = $data['store_group_ids'] ?? [];
            unset($data['store_group_ids']);
            if ($storeGroupIds) {
                $relevance = [];
                // 先删除旧的关联关系
                $relevanceRepository->batchDeleteByRightId($id, RelevanceRepository::STORE_GROUP);
                foreach ($storeGroupIds as $storeGroupId) {
                    // 新增新的关联关系
                    $relevance[] = [
                        'left_id' => $storeGroupId,
                        'right_id' => $id,
                        'type' => RelevanceRepository::STORE_GROUP,
                    ];
                }
                // 批量新增关联关系
                $relevanceRepository->insertAll($relevance);
            }

            $this->repository->update($id, $data);
        });

        try{
            if (isset($data['applyment_id']) && $data['applyment_id']) {
                (new Pay('partner'))->receiversAdd($data['applyment_id']);
            }
        } catch (\Exception $e) {
            Log::error('添加分账方失败:' . $e->getMessage());
        }
        return app('json')->success('编辑成功');
    }

    /**
     * 删除表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/5/9
     */
    public function deleteForm($id)
    {
        return app('json')->success(formToData($this->repository->deleteForm($id)));
    }

    /**
     * 删除
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-04-17
     */
    public function delete($id)
    {
        $type = $this->request->param('type', 0);
        if (!$merchant = $this->repository->get(intval($id)))
            return app('json')->fail('数据不存在');
        if ($merchant->status)
            return app('json')->fail('请先关闭该商户');
        $this->repository->delete($id);
        if ($type) $this->repository->clearAttachment($id);
        return app('json')->success('删除成功');
    }

    /**
     * @param MerchantValidate $validate
     * @param bool $isUpdate
     * @return array
     * @author xaboy
     * @day 2020-04-17
     */
    public function checkParam(MerchantValidate $validate, $isUpdate = false)
    {
        $data = $this->request->params([['category_id', 0], ['type_id', 0], 'mer_name', 'commission_rate', 'real_name', 'mer_phone', 'mer_keyword', 'mer_address', 'mark', ['sort', 0], ['status', 0], ['is_audit', 0], ['is_best', 0], ['is_bro_goods', 0], ['is_bro_room', 0], ['is_trader', 0], 'sub_mchid', ['commission_switch', 0],['offline_switch',0],['region_id', 0],['business_id', 0],['applyment_id',''],['store_group_ids', []]]);
        if (!$isUpdate) {
            $data += $this->request->params(['mer_account', 'mer_password']);
        } else {
            $validate->isUpdate();
        }
        $validate->check($data);
        return $data;
    }

    /**
     * 修改商户状态
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-31
     */
    public function switchStatus($id)
    {
        $is_best = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, compact('is_best'));
        return app('json')->success('修改成功');
    }

    /**
     * 关闭商户
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-31
     */
    public function switchClose($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$merchant = $this->repository->get($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, compact('status'));
        app()->make(StoreCouponRepository::class)->getSearch([])->where('mer_id', $id)->update(['status' => $status]);
        app()->make(StoreServiceRepository::class)->close($id, 'mer_id');
        Queue::push(ChangeMerchantStatusJob::class, $id);
        // 商户编辑记录日志
        event('create_operate_log', [
            'category' => OperateLogRepository::PLATFORM_EDIT_MERCHANT_AUDIT_STATUS,
            'data' => [
                'merchant' => $merchant,
                'admin_info' => $this->request->adminInfo(),
                'update_infos' => ['status' => $status]
            ],
        ]);
        return app('json')->success('修改成功');
    }

    /**
     * 从平台登陆到商户后台
     * @param $id
     * @author xaboy
     * @day 2020/7/7
     */
    public function login($id, MerchantAdminRepository $adminRepository)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $adminInfo = $adminRepository->merIdByAdmin($id);
        $tokenInfo = $adminRepository->createToken($adminInfo);
        $admin = $adminInfo->toArray();
        unset($admin['pwd']);
        $data = [
            'token' => $tokenInfo['token'],
            'exp' => $tokenInfo['out'],
            'admin' => $admin,
            'url' => '/' . config('admin.merchant_prefix')
        ];

        return app('json')->success($data);
    }

    /**
     *  修改复制次数表单
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function changeCopyNumForm($id)
    {
        return app('json')->success(formToData($this->repository->copyForm($id)));
    }

    /**
     *  修改复制次数
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-08-06
     */
    public function changeCopyNum($id)
    {
        $data = $this->request->params(['type', 'num']);
        $num = $data['num'];
        if ($num <= 0) return app('json')->fail('次数必须为正整数');
        if ($data['type'] == 2) {
            $mer_num = $this->repository->getCopyNum($id);
            if (($mer_num - $num) < 0) return app('json')->fail('剩余次数不足');
            $num = '-' . $data['num'];
        }
        $arr = [
            'type' => 'sys',
            'num' => $num,
            'message' => '平台修改「' . $this->request->adminId() . '」',
        ];
        app()->make(ProductCopyRepository::class)->add($arr, $id);
        return app('json')->success('修改成功');
    }

    /**
     *  清理删除的商户内容
     * @return \think\response\Json
     * @author Qinii
     * @day 5/15/21
     */
    public function clearRedundancy()
    {
        $this->repository->clearRedundancy();
        return app('json')->success('清除完成');
    }

    /**
     *  需补缴保证金商户列表
     * @return \think\response\Json
     * @author Qinii
     * @day 5/15/21
     */
    public function makeUpMarginLst()
    {
        [$page, $limit] = $this->getPage();  
        $where = $this->request->params(['keyword', 'date', 'status', 'statusTag', 'is_trader', 'category_id', 'type_id', ['order', 'create_time'], 'is_best','offline_switch','region_id', 'business_id','mer_state']);
        $where['margin'] = 0;
        $data = $this->repository->lst($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  平台后台商户详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/7/1
     */
    public function detail($id)
    {
        $data = $this->repository->adminDetail($id);
        return app('json')->success($data);
    }

    /**
     * 商户操作记录
     * @param $product_id
     * @return \think\response\Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     *
     * @date 2023/10/19
     * @author yyw
     */
    public function getOperateList($merchant_id)
    {
        $where = $this->request->params([
            ['type', ''],
            ['date', '']
        ]);
        $where['relevance_id'] = $merchant_id;
        $where['relevance_type'] = OperateLogRepository::RELEVANCE_MERCHANT;
        [$page, $limit] = $this->getPage();
        return app('json')->success(app()->make(OperateLogRepository::class)->lst($where, $page, $limit));
    }

    /**
     *  商户下拉筛选功能接口
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/5/15
     */
    public function mer_select()
    {
        $keyword = $this->request->param('keyword','');//'mer_id,mer_name'
        $data = $this->repository->mer_select($keyword);
        return app('json')->success($data);
    }

    /**
     *  商户虚拟关注量表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function careFictiForm($id)
    {
        $form = $this->repository->careFictiForm($id);
        return app('json')->success(formToData($form));
    }

    /**
     * 商户虚拟关注量
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function careFicti($id)
    {
       $data = $this->request->params(['type','num']);
       $this->repository->careFicti($id,$data);
        return app('json')->success('修改成功');
    }

    public function businessCreate()
    {
        $data = $this->request->params([
            'mer_name',
            'mer_password',
            'mer_phone',
            'mer_account',
            'category_id',
            'type_id',
            'real_name',
            ['mark', ''],
            ['business_id', 0]
        ]);

        if ($this->request->isAgent() !== 2) {
            return app('json')->fail('不是商户账号，无法操作');
        }

        $data['admin_info'] = $this->request->adminInfo();
        $this->repository->createMerchant($data);
        return app('json')->success('添加成功');
    }
}
