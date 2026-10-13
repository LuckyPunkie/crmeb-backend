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

namespace app\controller\merchant\store\order;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreOrderReceiptRepository;
use app\common\repositories\user\UserReceiptRepository;
use app\common\repositories\store\order\StoreOrderRepository;

class OrderReceipt extends BaseController
{
    protected $repository;

    public function __construct(App $app, StoreOrderReceiptRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @author Qinii
     * @day 2020-10-17
     */
    public function Lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'date', 'receipt_sn','username','order_type','keyword','uid']);
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 平台列表
     * @return mixed
     * @author Qinii
     * @day 2020-10-17
     */
    public function getList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'date', 'receipt_sn','nickname','order_type','keyword','mer_id','uid','phone']);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }


    /**
     * 设置收据
     *
     * @return \think\response\Json
     */
    public function setRecipt()
    {
        // 获取请求参数中的 ids
        $ids = $this->request->param('ids');
        if (!$ids) return app('json')->fail('请选择需要合并的发票');
        $this->repository->merExists($ids, $this->request->merId());
        return app('json')->success($this->repository->setRecipt($ids, $this->request->merId()));
    }


    /**
     * 开票
     * @return mixed
     * @author Qinii
     * @day 2020-10-17
     */
    public function saveRecipt()
    {
        $data = $this->request->param(['ids','receipt_sn','receipt_price','receipt_no','mer_mark','pic']);
        $this->repository->merExists($data['ids'],$this->request->merId());
        if(!is_numeric($data['receipt_price']) || $data['receipt_price'] < 0)
            return app('json')->fail('发票信息金额格式错误');
        //if(!$data['receipt_no'])return app('json')->fail('请填写发票号');
        $this->repository->save($data);
        return app('json')->success('开票成功');
    }

    /**
     * 备注form
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-17
     */
    public function markForm($id)
    {
        return app('json')->success(formToData($this->repository->markForm($id)));
    }

    /**
     * 备注
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-17
     */
    public function mark($id)
    {
        if(!$this->repository->getWhereCount(['order_receipt_id' => $id,'mer_id' => $this->request->merId()]))
            return app('json')->fail('数据不存在');
        $data = $this->request->params(['mer_mark']);
        $this->repository->update($id,$data);
        return app('json')->success('备注成功');
    }


    /**
     * 获取发票详情
     * @param int $id 发票ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        // 获取商家ID
        $mer_id = $this->request->merId();
        // 构建查询条件
        $where = [$this->repository->getPk() => $id];
        if ($mer_id) $where['mer_id'] = $mer_id;
        // 根据条件查询发票信息
        $data = $this->repository->getSearch($where)->find();
        // 判断发票是否存在
        if (!$data) return app('json')->fail('数据不存在');
        // 根据发票类型设置标题
        if ($data['receipt_info']->receipt_type == 1) {
            $title = $data['receipt_info']->receipt_title_type == 1 ? '个人电子普通发票' : '企业电子普通发票';
        } else {
            $title = '企业专用纸质发票';
        }
        $data['title'] = $title;
        // 返回发票信息
        return app('json')->success($data);
    }

    /**
     * 更新发票信息
     * @param int $id 发票ID
     * @return \think\response\Json
     */
    public function update($id)
    {
        // 获取参数
        $data = $this->request->params(['receipt_no', 'mer_mark']);
        // 如果发票号码不为空，则将状态设置为已付款
        if (!empty($data['receipt_no'])) $data['status'] = 1;
        $where = [$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()];
        $res = $this->repository->getSearch($where)->find();
        if (!$res) return app('json')->fail('数据不存在');
        // 更新发票信息
        $this->repository->updateBySn($res['receipt_sn'], $data);
        // 返回操作结果
        return app('json')->success('编辑成功');
    }

}
