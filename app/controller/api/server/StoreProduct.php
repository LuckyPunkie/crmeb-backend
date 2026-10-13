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
namespace app\controller\api\server;

use app\common\repositories\store\order\StoreCartRepository;
use app\common\repositories\store\product\ProductLabelRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\merchant\StoreProductValidate;
use crmeb\basic\BaseController;
use crmeb\services\UploadService;
use think\App;
use think\exception\HttpResponseException;
use think\exception\ValidateException;

/**
 * Class StoreProduct
 * app\controller\api\server
 *  移动客服  商品管理
 */
class StoreProduct extends BaseController
{
    protected $merId;
    protected $repository;

    public function __construct(App $app, ProductRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->merId = $this->request->route('merId');
    }

    /**
     *  头部统计
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function title($merId)
    {
        return app('json')->success($this->repository->getFilter($merId, [], '', 0));
    }

    /**
     *  列表
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function lst($merId)
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['cate_id', 'keyword', 'mer_cate_id', 'is_gift_bag', 'status', 'us_status', 'product_id', 'mer_labels',['order','sort'],['type','']]);
        $type = $where['type'];
        unset($where['type']);
        $where = array_merge($where, $this->repository->switchType($type, $merId, 0));
        return app('json')->success($this->repository->getList($merId, $where, $page, $limit));
    }

    /**
     *  添加
     * @param $merId
     * @param StoreProductValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function create($merId, StoreProductValidate $validate)
    {
        $res = $this->request->params($this->repository::CREATE_PARAMS);
        $res['is_gift_bag'] = 0;
        $res['mer_id'] = $merId;

        $data = $this->repository->checkParams($res,$merId);
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        $data['status'] = $merchant->is_audit ? 0 : 1;
        $data['mer_status'] = ($merchant['is_del'] || !$merchant['mer_state'] || !$merchant['status']) ? 0 : 1;
        $data['rate'] = 5;
        $this->repository->create($data, 0);
        return app('json')->success('添加成功');
    }

    /**
     *  编辑
     * @param $merId
     * @param $id
     * @param StoreProductValidate $validate
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function update($merId, $id, StoreProductValidate $validate)
    {
        $res = $this->request->params($this->repository::CREATE_PARAMS);
        $data = $this->repository->checkParams($res,$merId,$id);

        $merchant = app()->make(MerchantRepository::class)->get($merId);
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        $pro = $this->repository->getWhere(['product_id' => $id]);
        if ($pro->status == -2) {
            $data['status'] = 0;
        } else {
            $data['status'] = $merchant->is_audit ? 0 : 1;
        }
        $data['mer_status'] = ($merchant['is_del'] || !$merchant['mer_state'] || !$merchant['status']) ? 0 : 1;
        $data['mer_id'] = $merId;
        $this->repository->edit($id, $data, $merId, 0, 1);
        return app('json')->success('编辑成功');
    }

    /**
     *  详情
     * @param $merId
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function detail($merId, $id)
    {
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getAdminOneProduct($id, 0, 1));
    }

    /**
     *  修改状态
     * @param $merId
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function switchStatus($merId, $id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        $this->repository->switchShow($id,$status, 'is_show',$merId);
        return app('json')->success('修改成功');
    }

    /**
     *  加入回收站
     * @param $merId
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function delete($merId, $id)
    {
        if (!$this->repository->merExists($merId, $id))
            return app('json')->fail('数据不存在');
        if ($this->repository->getWhereCount(['product_id' => $id, 'is_show' => 1, 'status' => 1]))
            return app('json')->fail('商品上架中');
        $this->repository->delete($id);
        return app('json')->success('转入回收站');
    }

    /**
     *  商品配置
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function config($merId)
    {
        $data['extension_status'] = systemConfig('extension_status');
        $data['integral_status'] = 0;
        $data['integral_rate'] = 0;
        if(systemConfig('integral_status') && merchantConfig($merId,'mer_integral_status')) {
            $data['integral_status'] = 1;
            $data['integral_rate'] = merchantConfig($merId,'mer_integral_rate');
        }
        $merchant = app()->make(MerchantRepository::class)->get($merId);
        $data['delivery_way'] = $merchant->delivery_way;
        return app('json')->success($data);
    }

    /**
     *  商品加入回收站
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function restore($id)
    {
        if (!$this->repository->merDeleteExists($this->merId, $id))
            return app('json')->fail('只能删除回收站的商品');
        $this->repository->restore($id);
        return app('json')->success('商品已恢复');
    }

    /**
     *  商品彻底删除
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function destory($id)
    {
        if (!$this->repository->merDeleteExists($this->merId, $id))
            return app('json')->fail('只能删除回收站的商品');
//        if (app()->make(StoreCartRepository::class)->getProductById($id))
//            return app('json')->fail('商品有被加入购物车不可删除');
        $this->repository->destory($id);
        return app('json')->success('删除成功');
    }

    /**
     *  商品推荐
     * @param $merId
     * @return \think\response\Json
     * @author Qinii
     * @day 8/24/21
     */
    public function updateGood($id)
    {
        $is_good = $this->request->param('is_good', 0) == 1 ? 1 : 0;
        if (!$this->repository->merExists($this->merId, $id))
            return app('json')->fail('数据不存在');

        $this->repository->update($id, ['is_good' => $is_good]);
        return app('json')->success('修改成功');
    }

    /**
     *  修改商品的平台分类
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function editCate($id)
    {
        $param = $this->request->params(['cate_id']);
        if (!$this->repository->merExists($this->merId, $id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id,$param);
        return app('json')->success('修改成功');
    }

    /**
     *  修改商品的商户分类
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function editMerCate($id)
    {
        $ids = $this->request->param('ids',[]);
        if (!$this->repository->merExists($this->merId, $id))
            return app('json')->fail('数据不存在');
        $this->repository->editMerCate($id,$ids,$this->merId);
        return app('json')->success('修改成功');
    }

    /**
     *  获取商品的规格
     * @return void
     * @author Qinii
     */
    public function getValue($id)
    {
        $res = $this->repository->getAttrValue($id,$this->merId);
        return app('json')->success($res);
    }


    /**
     *  修改商品规格
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function setValue($id)
    {
        $params = $this->request->param('attr_value');
        $this->repository->changeAttrValue($id,$params);
        return app('json')->success('修改成功');
    }
}
