<?php

namespace app\controller\admin\store;

use app\common\repositories\store\StoreSeckillProductRepository;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\StoreSeckillActiveRepository as repository;
use app\validate\admin\StoreSeckillActiveValidate as validate;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 秒杀活动
 */
class StoreSeckillActive extends BaseController
{
    protected $repository;

    //创建活动参数
    const DATA_PARAMS = [
        'name',
        'seckill_time_ids',
        'start_day',
        'end_day',
        'all_pay_count',
        'once_pay_count',
        'product_category_ids',
        'status',
        'atmosphere_pic',
        'border_pic',
        'product_list',
        'active_status',
        'sign',
        ['start_time',0],
        ['end_time',0]
    ];

    /**
     * StoreSeckillActive constructor.
     * @param App $app
     * @param repository $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取秒杀活动列表
     * @return void
     * FerryZhao 2024/4/11
     */
    public function list()
    {
        $where = $this->request->params([
            'name',
            'date',
            'active_status',
            'seckill_active_status'
        ]);
        [$page, $limit] = $this->getPage();
        $append = ['status_text', 'seckill_time_text_arr', 'atmosphere_pic', 'border_pic'];
        $list = $this->repository->getList($where, $page, $limit, $append);
        return app('json')->success($list);
    }

    /**
     * 返回所有列表数据-下拉
     * FerryZhao 2024/4/12
     */
    public function select()
    {
        return app('json')->success('获取成功', $this->repository->getActiveAll());
    }


    /**
     * 获取秒杀活动详情
     * @return void
     * FerryZhao 2024/4/12
     */
    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('数据不存在');
        $info = $this->repository->get($id)->append(['status_text', 'seckill_time_text_arr', 'atmosphere_pic', 'border_pic']) ?? [];
        return app('json')->success('获取成功', $info);
    }

    /**
     * 创建秒杀活动（平台添加秒杀商品）
     * @param validate $validate
     * @return void
     * FerryZhao 2024/4/11
     */
    public function create(validate $validate)
    {
        $param = $this->request->params(self::DATA_PARAMS);
        $validate->check($param);
        if($param['once_pay_count'] > $param['all_pay_count']){
            return app('json')->fail('单次限购不能大于活动限购');
        }
        $activeInfo = $this->repository->create($param);

        //需要添加商品
        if (isset($param['product_list']) && !empty($param['product_list'])) {
            app()->make(StoreSeckillProductRepository::class)->createSeckillProduct($activeInfo->seckill_active_id, $param['product_list'],1);
            $this->repository->updateActiveChart($activeInfo->seckill_active_id);
        }

        return app('json')->success('添加成功', $activeInfo);
    }

    /**
     * 编辑秒杀活动（平台编辑秒杀商品）
     * @param int $id 活动ID
     * @param validate $validate
     * @return void
     * FerryZhao 2024/4/12
     */
    public function update($id, validate $validate)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }

        $param = $this->request->params(self::DATA_PARAMS);
        $validate->check($param);
        if($param['once_pay_count'] > $param['all_pay_count']){
            return app('json')->fail('单次限购不能大于活动限购');
        }

        $this->repository->updateActive($id, $param);
        //需要更新商品
        if (isset($param['product_list'])) {
//            app()->make(StoreSeckillProductRepository::class)->updateSeckillProduct($id, $param['product_list']);
            app()->make(StoreSeckillProductRepository::class)->createSeckillProduct($id, $param['product_list'],1);
            $this->repository->updateActiveChart($id);
        }
        return app('json')->success('编辑成功');
    }

    /**
     * 修改秒杀活动状态
     * @param $id
     * @return void
     * FerryZhao 2024/4/12
     */
    public function update_status($id)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('数据不存在');
        $result = $this->repository->updateStatus($id, $status);
        if ($result) {
            return app('json')->success('操作成功');
        } else {
            return app('json')->fail('操作失败');
        }
    }

    /**
     * 删除秒杀活动
     * @param $id
     * @return void
     * FerryZhao 2024/4/12
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delete($id)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }
        $result = $this->repository->deleteActive($id);
        if ($result) {
            return app('json')->success('删除成功');
        } else {
            return app('json')->fail('上岗失败');
        }
    }

    /**
     * 活动统计-面板
     * @return array[]|void
     * FerryZhao 2024/4/19
     */
    public function chart_panel($id)
    {
        if (!$id) {
            return app('json')->fail('参数错误');
        }
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('活动不存在');

        return app('json')->success($this->repository->chartPanel($id));
    }

    /**
     * 活动参与人统计列表
     * @param $id
     * FerryZhao 2024/4/22
     */
    public function chart_people($id)
    {
        if(!$id){
            return app('json')->fail('参数异常');
        }
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('活动不存在');
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','date']);
        $merId = $this->request->param('mer_id') ?: null;
        return app('json')->success($this->repository->chartPeople($id,$where,$merId,$page,$limit));
    }


    /**
     * 活动订单统计列表
     * @param $id
     * FerryZhao 2024/4/28
     */
    public function chart_order($id)
    {
        if(!$id){
            return app('json')->fail('参数异常');
        }
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('活动不存在');
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','status','date']);
        $merId = $this->request->param('mer_id') ?: null;
        return app('json')->success($this->repository->chartOrder($id,$where,$merId,$page,$limit));
    }

    /**
     * 活动商品统计列表
     * @param $id
     * FerryZhao 2024/4/28
     */
    public function chart_product($id)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }
        $exists = $this->repository->exists($id);
        if (!$exists)
            return app('json')->fail('活动不存在');
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword']);
        $merId = $this->request->param('mer_id') ?: null;
        return app('json')->success($this->repository->chartProduct($id,$where,$merId,$page,$limit));
    }
}
