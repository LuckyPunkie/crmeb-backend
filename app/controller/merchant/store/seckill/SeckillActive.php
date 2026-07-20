<?php

namespace app\controller\merchant\store\seckill;
use app\common\repositories\store\StoreSeckillActiveRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 秒杀活动
 */
class SeckillActive extends BaseController
{
    protected  $repository;

    /**
     * SeckillActive constructor.
     * @param App $app
     * @param StoreSeckillActiveRepository $repository
     */
    public function __construct(App $app ,StoreSeckillActiveRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取秒杀活动列表
     * FerryZhao 2024/4/16
     */
    public function list()
    {
        $where = $this->request->params([
            'name',
            'seckill_active_status',
            'date',
            'active_status'
        ]);
        [$page, $limit] = $this->getPage();
        $append = ['status_text', 'seckill_time_text_arr','atmosphere_pic','border_pic'];
        $list = $this->repository->getList($where, $page, $limit, $append);
        return app('json')->success($list);
    }


    /**
     * 返回所有列表数据-下拉
     * FerryZhao 2024/4/16
     */
    public function select()
    {
        return app('json')->success('获取成功',$this->repository->getActiveAll());
    }


    /**
     * 获取秒杀活动详情
     * @return void
     * FerryZhao 2024/4/16
     */
    public function detail($id)
    {
        if (!$id) {
            return app('json')->fail('参数异常');
        }
        $exists = $this->repository->exists($id);
        if(!$exists)
            return app('json')->fail('数据不存在');

        $info = $this->repository->get($id)->append(['status_text', 'seckill_time_text_arr','atmosphere_pic','border_pic']) ?? [];
        return app('json')->success('获取成功', $info);
    }

    /**
     * 活动统计-面板
     * @return array[]|void
     * FerryZhao 2024/4/23
     */
    public function chart_panel($id)
    {
        if( !$id ) {
            return app('json')->fail('参数错误');
        }
        $exists = $this->repository->exists($id);
        if(!$exists)
            return app('json')->fail('活动不存在');

        return app('json')->success($this->repository->chartPanel($id,$this->request->merId()));
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
        $merId = $this->request->merId();
        return app('json')->success($this->repository->chartPeople($id,$merId,$where,$page,$limit));
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
        $where = $this->request->params(['keyword','status','mer_id','date']);
        $merId = $this->request->merId();
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
        $merId = $this->request->merId();
        return app('json')->success($this->repository->chartProduct($id,$where,$merId,$page,$limit));
    }
}
