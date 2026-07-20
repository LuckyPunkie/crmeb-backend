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

namespace app\controller\admin\store\marketing;

use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\RelevanceRepository;
use app\validate\admin\StoreActivityValidate;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\StoreActivityRepository as repository;

/**
 * 活动氛围图
 */
class StoreAtmosphere extends BaseController
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
     * 列表
     * @return mixed
     * @Author: liusl
     * @Date: 2022/6/24
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'status', 'date']);
        $where['activity_type'] = repository::ACTIVITY_TYPE_ATMOSPHERE;
        return app('json')->success($this->repository->getAdminList($where, $page, $limit));
    }

    /**
     * 添加
     * @param StoreActivityValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/16
     */
    public function create(StoreActivityValidate $validate)
    {
        [$data, $extend] = $this->checkParams($validate);
        $this->repository->createActivity($data, $extend);
        return app('json')->success('添加成功');
    }

    /**
     * 参数验证
     * @param StoreActivityValidate $validate
     * @return array
     * @author Qinii
     * @day 2022/9/15
     */
    public function checkParams(StoreActivityValidate $validate)
    {
        $ids = [
            $this->repository::TYPE_ALL => '',
            $this->repository::TYPE_MUST_PRODUCT => 'spu_ids',
            $this->repository::TYPE_MUST_CATEGORY => 'cate_ids',
            $this->repository::TYPE_MUST_STORE => 'mer_ids',
            $this->repository::TYPE_MUST_PRODUCT_LABEL => 'label_ids',
        ];
        $params = ["activity_name", "start_time", "end_time", "is_show", "pic", 'scope_type'];
        $data = $this->request->params($params);
        $validate->check($data);
        $data['activity_type'] = repository::ACTIVITY_TYPE_ATMOSPHERE;
        if (strtotime($data['start_time']) <= time()) {
            $data['status'] = 1;
        }
        $extend = $this->request->params([$ids[$data['scope_type']]]);
        return [$data, $extend];
    }

    /**
     * 编辑
     * @param StoreActivityValidate $validate
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/17
     */
    public function update(StoreActivityValidate $validate, $id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        [$data, $extend] = $this->checkParams($validate);
        $this->repository->updateActivity($id, $data, $extend);
        return app('json')->success('修改成功');
    }

    /**
     * 状态修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function statusSwitch($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $this->repository->update($id, ['is_show' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     *  详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/16
     */
    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->detail($id));
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     * @Author: liusl
     * @Date: 2022/6/27
     */
    public function delete($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->deleteActivity($id);
        return app('json')->success('删除成功');
    }

    /**
     * 商品列表
     * @param SpuRepository $repository
     * @return \think\response\Json
     * @author Qinii
     */
    public function markLst(SpuRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'cate_id',
            'cate_pid',
            'brand_id',
            'product_type',
            'spu_ids',
            'mer_id',
            ['not_type',1]
        ]);
        $where['is_gift_bag'] = 0;
        $data = $repository->makinList($where, $page, $limit);
        return app('json')->success($data);
    }
}
