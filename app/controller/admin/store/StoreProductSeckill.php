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

namespace app\controller\admin\store;

use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\StoreProductAdminValidate as validate;
use app\common\repositories\store\product\ProductRepository as repository;
use think\facade\Queue;
/**
 * 秒杀商品
 */
class StoreProductSeckill extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;


    /**
     * StoreProduct constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 分页平台秒杀商品列表
     * FerryZhao 2024/4/24
     */
    public function get_page_list()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['seckill_active_id','active_name','us_status','active_status','sys_labels','mer_cate_id','keyword','is_trader','star','status']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $merId = $this->request->param('mer_id') ?: null;
        $type = $this->request->param('type');
        if($type){
            $statusWhere = $this->repository->switchType($type, $merId, 1);
            unset($statusWhere['star']);
            $where = array_merge($where, $statusWhere);
        }
        $where['product_type'] = 1;
        return app('json')->success($this->repository->getAdminSeckillPageList($merId, $where, $page, $limit));
    }


    /**
     * 平台秒杀商品列表
     * @Author:Qinii
     * @Date: 2020/5/18
     * @return mixed
     */
    public function get_list()
    {
        $where = $this->request->params(['seckill_active_id','active_name','us_status','active_status','sys_labels','mer_cate_id','keyword','is_trader','star','status']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $merId = $this->request->param('mer_id') ?: null;
        $type = $this->request->param('type');
        if($type){
            $statusWhere = $this->repository->switchType($type, $merId, 1);
            unset($statusWhere['star']);
            $where = array_merge($where, $statusWhere);
        }
        $where['product_type'] = 1;

        return app('json')->success($this->repository->getAdminSeckillList($merId, $where));
    }

    /**
     * 秒杀商品状态统计
     * @return mixed
     * @author Qinii
     * @day 2020-08-04
     */
    public function getStatusFilter()
    {
        $where = $this->request->params(['seckill_active_id','active_name','us_status','active_status','sys_labels','mer_cate_id','keyword','is_trader','star','status']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $where['product_type'] = 1;
        $merId = $this->request->param('mer_id') ?: null;

        return app('json')->success($this->repository->getFilter($merId, $where, '秒杀商品', 1));
    }

    /**
     * 秒杀商品详情
     * @param $id
     * FerryZhao 2024/4/23
     */
    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getAdminOneProduct($id, null));
    }

    /**
     * 编辑秒杀商品
     * @param $id
     * @param validate $validate
     * FerryZhao 2024/4/18
     */
    public function update($id, validate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->adminUpdate($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 秒杀商品审核
     * FerryZhao 2024/4/18
     */
    public function switchStatus()
    {
        $id = $this->request->param('id');
        $data = $this->request->params(['status', 'refusal']);

        if ($data['status'] == -1 && empty($data['refusal']))
            return app('json')->fail('请填写拒绝理由');
        if ($data['status'] == -2 && empty($data['refusal']))
            return app('json')->fail('请填写下架原因');
        if (is_array($id)) {
            $this->repository->batchSwitchStatus($id, $data);
        } else {
            $this->repository->switchStatus($id, $data);
        }
        return app('json')->success('操作成功');
    }

    /**
     *  是否隐藏
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-07-17
     */
    public function changeUsed($id)
    {
        if (!$this->repository->merExists(null, $id))
            return app('json')->fail('数据不存在');
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $this->repository->switchShow($id, $status, 'is_used');
        return app('json')->success('修改成功');
    }


    /**
     * 编辑秒杀商品校验数据
     * @param validate $validate
     * @return array
     * FerryZhao 2024/4/18
     */
    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['is_hot', 'is_best', 'is_benefit', 'is_new', 'rank', 'star']);
        $validate->check($data);
        return $data;
    }


    /**
     * 平台秒杀商品列表
     */
    public function lists()
    {
        $make = app()->make(MerchantRepository::class);
        $data = $make->selectWhere(['status' => 1, 'mer_state' => 1, 'is_del' => 0], 'mer_id,mer_name');
        return app('json')->success($data);
    }

    /**
     * 设置标签
     * @param $id
     */
    public function setLabels($id)
    {
        $data = $this->request->params(['sys_labels']);
        app()->make(SpuRepository::class)->setLabels($id, 1, $data, 0);
        return app('json')->success('修改成功');
    }


    /**
     * 秒杀商品审核
     * @param $id
     * @return \think\response\Json
     * FerryZhao 2024/4/18
     */
    public function get_switch_status_form($id)
    {
        return app('json')->success(formToData($this->repository->getSwitchStatusForm($id)));
    }

    /**
     * 批量下架
     * @param $id
     * FerryZhao 2024/4/15
     */
    public function down_product_status_form($id)
    {
        return app('json')->success(formToData($this->repository->downProductForm($id)));
    }

}
