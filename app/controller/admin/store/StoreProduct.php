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
use app\common\repositories\system\operate\OperateLogRepository;
use think\App;
use think\exception\ValidateException;
use crmeb\basic\BaseController;
use app\validate\merchant\StoreProductAdminValidate as validate;
use app\common\repositories\store\product\ProductRepository as repository;
use think\facade\Queue;

/**
 * 主商品
 */
class StoreProduct extends BaseController
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
     * @Author:Qinii
     * @Date: 2020/5/18
     * @return mixed
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', 1);
        $where = $this->request->params(['cate_id', 'keyword', 'mer_cate_id', 'pid', 'store_name', 'is_trader', 'us_status', 'product_id', 'star', 'sys_labels', 'hot_type', 'svip_price_type', 'is_ficti', 'product_ids', 'form_id','cate_hot','brand_id','activity_label_ids']);
        $mer_id = $this->request->param('mer_id', '');
        $merId = $mer_id ?: null;
        $where['is_gift_bag'] = 0;
        $_where = $this->repository->switchType($type, null, 0);
        unset($_where['star']);
        $where = array_merge($where, $_where);
        return app('json')->success($this->repository->getAdminList($merId, $where, $page, $limit));
    }

    /**
     * 礼包列表
     * @Author:Qinii
     * @Date: 2020/5/18
     * @return mixed
     */
    public function bagList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['cate_id', 'keyword', 'mer_cate_id', 'is_trader', 'us_status']);
        $merId = $this->request->param('mer_id') ? $this->request->param('mer_id') : null;
        $type = $this->request->param('type', 1);
        $_where = $this->repository->switchType($type, null, 10);
        $where = array_merge($where, $_where);
        $where['order'] = 'rank';
        unset($where['star']);
        return app('json')->success($this->repository->getAdminList($merId, $where, $page, $limit));
    }

    /**
     *  顶部统计 和 状态区分
     * @Author:Qinii
     * @Date: 2020/5/18
     * @return mixed
     */
    public function getStatusFilter()
    {
        $where = $this->request->params([
            'cate_id',
            'keyword',
            'mer_cate_id',
            'pid',
            'store_name',
            'is_trader',
            'us_status',
            'product_id',
            'sys_labels',
            'hot_type',
            'svip_price_type',
            'is_ficti',
            'product_ids',
            'form_id',
            'cate_hot',
            'brand_id',
            'activity_label_ids',
        ]);
        $merId = $this->request->param('mer_id') ? $this->request->param('mer_id') : null;

        return app('json')->success($this->repository->getFilter($merId, $where, '商品', 0));
    }

    /**
     *  礼包表头
     * @Author:Qinii
     * @Date: 2020/5/18
     * @return mixed
     */
    public function getBagStatusFilter()
    {
        $where = $this->request->params(['cate_id', 'keyword', 'mer_cate_id', 'is_trader', 'us_status']);
        $merId = $this->request->param('mer_id') ? $this->request->param('mer_id') : null;

        return app('json')->success($this->repository->getFilter($merId, $where, '礼包', 10));
    }

    /**
     * 详情
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param $id
     * @return mixed
     */
    public function detail($id)
    {
        if (!$this->repository->merExists(null, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->getAdminOneProduct($id, 0));
    }

    /**
     * 编辑
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param $id
     * @param validate $validate
     * @return mixed
     */
    public function update($id, validate $validate)
    {
        $data = $this->checkParams($validate);
        $this->repository->adminUpdate($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     *  审核 / 下架
     * @Author:Qinii
     * @Date: 2020/5/18
     * @param int $id
     * @return mixed
     */
    public function switchStatus()
    {
        //0：审核中，1：审核通过 -1: 未通过 -2: 下架
        $id = $this->request->param('id');
        $data = $this->request->params(['status', 'refusal']);
        if (in_array($data['status'], [1, 0, -2, -1]))
            if ($data['status'] == -1 && empty($data['refusal']))
                return app('json')->fail('请填写拒绝理由');
        if (is_array($id)) {
            $this->repository->batchSwitchStatus($id, $data, $this->request->adminInfo());
        } else {
            $this->repository->switchStatus($id, $data, $this->request->adminInfo());
        }
        return app('json')->success('操作成功');
    }


    /**
     * 字段验证
     * @Author:Qinii
     * @Date: 2020/5/11
     * @param validate $validate
     * @return array
     */
    public function checkParams(validate $validate)
    {
        $data = $this->request->params(['cate_hot','is_hot', 'is_best', 'is_benefit', 'is_new', 'store_name', 'content',
        'rank', ['star',0],'cate_hot']);
        $validate->check($data);
        return $data;
    }

    /**
     *  检测商品佣金队列 - 弃用
     * @author Qinii
     * @day 2020-06-24
     */
    public function checkProduct()
    {
        Queue::push(CheckProductExtensionJob::class, []);
        return app('json')->success('后台已开始检测');
    }

    /**
     * 商户下啦筛选 - 弃用
     * @return \think\response\Json
     * @author Qinii
     */
    public function lists()
    {
        $make = app()->make(MerchantRepository::class);
        $data = $make->selectWhere(['is_del' => 0], 'mer_id,mer_name');
        return app('json')->success($data);
    }

    /**
     * 增加虚拟销量表单
     * @Author:Qinii
     * @Date: 2020/10/9
     * @param $id
     * @return mixed
     */
    public function addFictiForm($id)
    {
        if (!$this->repository->merExists(null, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->fictiForm($id)));
    }

    /**
     *  修改虚拟销量
     * @Author:Qinii
     * @Date: 2020/10/9
     * @param $id
     * @return mixed
     *
     */
    public function addFicti($id)
    {
        $data = $this->request->params(['type', 'ficti']);
        if (!in_array($data['type'], [1, 2])) return app('json')->fail('类型错误');
        if (!$data['ficti'] || $data['ficti'] < 0) return app('json')->fail('已售数量必须大于0');
        $res = $this->repository->getWhere(['product_id' => $id], 'ficti,sales');
        if (!$res) return app('json')->fail('数据不存在');
        if ($data['type'] == 2 && $res['ficti'] < $data['ficti']) return app('json')->fail('已售数量不足');
        $ficti = ($data['type'] == 1) ? $data['ficti'] : '-' . $data['ficti'];
        $data = [
            'ficti' => $res['ficti'] + $ficti,
            'sales' => $res['sales'] + $ficti
        ];
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 修改排序
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/17/21
     */
    public function updateSort($id)
    {
        $sort = $this->request->param('sort');
        $this->repository->updateSort($id, null, ['rank' => $sort]);
        return app('json')->success('修改成功');
    }

    /**
     * 设置标签
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function setLabels($id)
    {
        $data = $this->request->params(['sys_labels']);
        app()->make(SpuRepository::class)->setLabels($id, 0, $data, 0);
        return app('json')->success('修改成功');
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
        $this->repository->switchShow($id, $status, 'is_used', 0, $this->request->adminInfo());
        return app('json')->success('修改成功');
    }

    /**
     *  批量显示隐藏
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/11/14
     */
    public function batchShow()
    {
        $ids = $this->request->param('ids');
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->batchSwitchShow($ids, $status, 'is_used', 0, $this->request->adminInfo());
        return app('json')->success('修改成功');
    }

    /**
     *  批量标签
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    public function batchLabels()
    {
        $ids = $this->request->param('ids');
        $data = $this->request->params(['sys_labels']);
        if (empty($ids)) return app('json')->fail('请选择商品');
        app()->make(SpuRepository::class)->batchLabels($ids, $data, 0);
        return app('json')->success('修改成功');
    }

    /**
     *  批量设置推荐类型
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/9/6
     */
    //public function batchHot()
    //{
    //    $ids = $this->request->param('ids');
    //    $data = $this->request->params([['is_hot', 0], ['is_benefit', 0], ['is_best', 0], ['is_new', 0]]);
    //    if (empty($ids)) return app('json')->fail('请选择商品');
    //    $this->repository->updates($ids, $data);
    //    return app('json')->success('修改成功');
    //}

    /**
     * 获取商品操作记录
     * @param $product_id
     *
     * @date 2023/10/13
     * @author yyw
     */
    public function getOperateList($product_id)
    {
        $where = $this->request->params([
            ['type', ''],
            ['date', '']
        ]);
        $where['relevance_id'] = $product_id;
        $where['relevance_type'] = OperateLogRepository::RELEVANCE_PRODUCT;
        [$page, $limit] = $this->getPage();
        return app('json')->success(app()->make(OperateLogRepository::class)->lst($where, $page, $limit));
    }

    /**
     * 获取平台自营产品
     * @return void
     * FerryZhao 2024/4/12
     */
    public function get_self_product_list()
    {
        $productWhere = $this->request->params(['keyword', 'sys_labels', 'us_status', 'cate_id', 'active_id']);
        $productWhere['cate_pid'] = $this->request->param('level_one_cate_ids');
        $productWhere['product_type'] = 0;
        $productWhere['is_ficti'] = '0,1';
        $merWhere = $this->request->params(['type_id', 'category_id', 'mer_id']);
        $merWhere['status'] = 1;
        $merWhere['mer_state'] = 1;
        $merWhere['is_del'] = 0;
        $merWhere['is_trader'] = 1;//自营
        [$page, $limit] = $this->getPage();
        $result = $this->repository->getProductList($merWhere, $productWhere, $page, $limit,true);
        return app('json')->success('获取成功', $result);
    }

    /**
     *  批量设置 分类推荐
     * @return \think\response\Json
     * @author Qinii
     * @day 2024/6/5
     */
    public function batchHot()
    {
        $ids = $this->request->param('ids',[]);
        if (empty($ids)) return app('json')->fail('请选择商品');
        $ids = !is_array($ids) ? [$ids] : $ids;
        $data = $this->request->params([['cate_hot',''],['is_hot',''], ['is_best',''], ['is_benefit',''], ['is_new','']]);
        $res = [];
        foreach ($data as $k => $v) {
            if ($v !== '') {
                $res[$k] = $v;
            }
        }
        if (!$res) return app('json')->fail('无信息提交');
        $this->repository->updates($ids, $res);
        return app('json')->success('修改成功');
    }
    /**
     * 批量复制商品到指定店铺
     *
     * @param [type] $id
     * @return void
     */
    public function batchCopyProductsToStore()
    {
        $params = $this->request->params([
            ['product_ids', []],
            ['store_id', ''],
        ]);
        // 验证商品ID列表是否为空
        if (empty($params['product_ids'])) {
            throw new ValidateException('请选择要复制的商品');
        }
        // 验证店铺ID是否为空
        if (empty($params['store_id'])) {
            throw new ValidateException('请选择要复制到的店铺');
        }

        $this->repository->batchCopyProductsToStore($params['product_ids'], $params['store_id']);
        return app('json')->success('复制成功');
    }
}
