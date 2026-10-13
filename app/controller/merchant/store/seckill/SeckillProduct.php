<?php

namespace app\controller\merchant\store\seckill;

use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\store\StoreSeckillActiveRepository;
use app\common\repositories\store\StoreSeckillProductRepository;
use app\validate\merchant\StoreSeckillProductValidate as validate;
use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductRepository;
use think\App;
use think\exception\ValidateException;

class SeckillProduct extends BaseController
{
    protected  $repository ;

    /**
     * SeckillProduct constructor.
     * @param App $app
     * @param ProductRepository $repository
     */
    public function __construct(App $app ,ProductRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取添加秒杀商品时的商品列表
     * @return void
     * FerryZhao 2024/4/16
     */
    public function get_product_list()
    {
        $productWhere = $this->request->params(['keyword','sys_labels','category_id','us_status','cate_ids',['in_type','0,1'],
            ['status',1]]);
        $merWhere = $this->request->params(['type_id']);
        $merWhere['status'] = 1;
        $merWhere['mer_state'] = 1;
        $merWhere['is_del'] = 0;
        if($this->request->merId()){
            $merWhere['mer_id'] = $this->request->merId();
            $productWhere['active_id'] = $this->request->param('active_id');
            $productWhere['product_type'] = 0;
        }
        $productWhere['mer_cate_id'] = $productWhere['category_id'];
        unset($productWhere['category_id']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getProductList($merWhere,$productWhere,$page,$limit));
    }

    /**
     * 商户秒杀商品列表
     * FerryZhao 2024/4/17
     */
    public function get_page_list()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','status','seckill_active_id','us_status','active_status','active_name','sys_labels','mer_cate_id','keyword','is_trader','sort','mer_labels']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $merId = $this->request->merId();
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
     * 商户秒杀商品列表
     * FerryZhao 2024/4/17
     */
    public function get_list()
    {
        $where = $this->request->params(['keyword','status','seckill_active_id','us_status','active_status','active_name','sys_labels','mer_cate_id','keyword','is_trader','sort','mer_labels']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $merId = $this->request->merId();
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
     * 商家参与活动添加商品
     * FerryZhao 2024/4/17
     */
    public function create()
    {
        $param = $this->request->param();
        if(!isset($param['active_id']) || !$param['active_id'])
            return app('json')->fail('参数错误');
        if(!isset($param['product_list']))
            return app('json')->fail('参数错误');
        app()->make(StoreSeckillProductRepository::class)->createSeckillProduct($param['active_id'],$param['product_list'],$this->request->merchant()->is_audit ? 0 : 1);
        app()->make(StoreSeckillActiveRepository::class)->updateActiveChart($param['active_id']);//回填商家数和商品数
        return app('json')->success('活动参加成功');

    }

    /**
     * 秒杀商品详情
     * @param $id
     * FerryZhao 2024/4/17
     */
    public function detail($id)
    {
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getAdminOneProduct($id,null));
    }


    /**
     * 改变商品状态
     * @param $id
     * FerryZhao 2024/4/19
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        $this->repository->switchShow($id,  $status,'is_show',$this->request->merId());
        return app('json')->success('修改成功');
    }

    /**
     * 编辑秒杀商品
     * @param $id
     * @param validate $validate
     * FerryZhao 2024/4/19
     */
    public function update($id, validate $validate)
    {
        if(!$id){
            return app('json')->fail('参数错误');
        }
        if(!$this->repository->merExists($this->request->merId(),$id)){
            return app('json')->fail('数据不存在');
        }
        $attrValue = $this->request->param('attr_value');

        if(!is_array($attrValue) || empty($attrValue)){
            return app('json')->fail('sku参数错误');
        }
        $field = ['value_id','price','stock'];
        try {
            $minPrice = $this->repository->saveAllSku($id,$attrValue,$field);
        }catch (\Exception $e){
            return app('json')->fail($e->getMessage());
        }

//        $updateProductStatusResult = app(ProductRepository::class)->getSearch([])->where(['product_id'=>$id])->update(['status'=>0]);

        $sort = $this->request->param('sort');
        $this->repository->updateSort($id,$this->request->merId(),['sort' => $sort ?? 0, 'price' => $minPrice]);

        return app('json')->success('操作成功');
    }


    /**
     * 秒杀状态统计
     * FerryZhao 2024/4/19
     */
    public function getStatusFilter()
    {
        $where = $this->request->params(['keyword','status','seckill_active_id','us_status','active_status','active_name','sys_labels','mer_cate_id','keyword','is_trader','sort','mer_labels']);
        if($where['is_trader'] == ''){
            unset($where['is_trader']);
        }
        $where['product_type'] = 1;
        $data = $this->repository->getFilter($this->request->merId(),$where,'秒杀商品',1);
        return app('json')->success($data);
    }

    /**
     * 排序
     * @param $id
     * FerryZhao 2024/4/22
     */
    public function updateSort($id)
    {
        $sort = $this->request->param('sort');
        $this->repository->updateSort($id,$this->request->merId(),['sort' => $sort]);
        return app('json')->success('修改成功');
    }

    /**
     * 预览
     * FerryZhao 2024/4/22
     */
    public function preview()
    {
        $data = $this->request->param();
        $data['merchant'] = [
            'mer_name' => $this->request->merchant()->mer_name,
            'is_trader' => $this->request->merchant()->is_trader,
            'mer_avatar' => $this->request->merchant()->mer_avatar,
            'product_score' => $this->request->merchant()->product_score,
            'service_score' => $this->request->merchant()->service_score,
            'postage_score' => $this->request->merchant()->postage_score,
            'service_phone' => $this->request->merchant()->service_phone,
            'care_count' => $this->request->merchant()->care_count,
            'type_name' => $this->request->merchant()->type_name->type_name ?? '',
            'care' => true,
            'recommend' => $this->request->merchant()->recommend,
        ];
        $data['mer_id'] = $this->request->merId();
        $data['status'] =  1;
        $data['mer_status'] = 1;
        $data['rate'] = 3;
        return app('json')->success($this->repository->preview($data));
    }


    /**
     * 设置商品标签
     * @param $id
     * FerryZhao 2024/4/19
     */
    public function setLabels($id)
    {
        if(!$id)
            return app('json')->fail('参数错误');
        $data = $this->request->params(['mer_labels']);
        app()->make(SpuRepository::class)->setLabels($id,1,$data,$this->request->merId());
        return app('json')->success('修改成功');
    }


    /**
     * 加入回收站
     * @param $id
     * FerryZhao 2024/4/22
     */
    public function delete($id)
    {
        if(!$id)
            return app('json')->fail('参数错误');
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        if($this->repository->getWhereCount(['product_id' => $id,'is_show' => 1,'status' => 1]))
            return app('json')->fail('商品上架中');
        $activeId = $this->repository->getSearch([])->where(['product_id'=>$id])->value('active_id');
        $this->repository->delete($id);
        $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
        $storeSeckillActiveRepository->updateActiveChart($activeId);
//        $this->repository->destory($id);
        return app('json')->success('已成功加入回收站');
    }


    /**
     * 删除秒杀商品
     * @param $id
     */
    public function destory($id,StoreSeckillActiveRepository $storeSeckillActiveRepository)
    {
        if(!$id)
            return app('json')->fail('参数错误');
        if(!$this->repository->merDeleteExists($this->request->merId(),$id))
            return app('json')->fail('只能删除回收站的商品');
        $activeId = $this->repository->getOnlyTranshed(['product_id' => $id])->value('active_id');
        $this->repository->destory($id);
        $storeSeckillActiveRepository->updateActiveChart($activeId);
        return app('json')->success('删除成功');
    }

    /**
     * 恢复秒杀商品
     * @param $id
     * FerryZhao 2024/4/22
     */
    public function restore($id)
    {
        if(!$this->repository->merDeleteExists($this->request->merId(),$id))
            return app('json')->fail('只能恢复回收站的商品');
        $this->repository->restore($id);
        $activeId = $this->repository->getSearch([])->where(['product_id'=>$id])->value('active_id');
        $storeSeckillActiveRepository = app()->make(StoreSeckillActiveRepository::class);
        $storeSeckillActiveRepository->updateActiveChart($activeId);
        return app('json')->success('商品已恢复');
    }

}
