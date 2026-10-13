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
namespace app\controller\admin\system\financial;

use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\financial\FinancialRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

/**
 * 商户财务申请提现
 */
class Financial extends BaseController
{
    public $repository;

    public function __construct(App $app, FinancialRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 商户申请转账申请记录列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'status', 'financial_type', 'financial_status', 'keyword', 'is_trader', 'mer_id']);
        $where['type'] = 0;
        $data = $this->repository->getAdminList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  商户保证金列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/5/24
     */
    public function getMarginLst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date', 'status', 'keyword', 'is_trader', 'mer_id', 'type_id', 'category_id']);
        $where['type'] = 1;
        $data = $this->repository->getAdminList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id);
        return app('json')->success($data);
    }

    /**
     * 审核表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function statusForm($id)
    {
        return app('json')->success(formToData($this->repository->statusForm($id)));
    }

    /**
     *  审核
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function switchStatus($id)
    {
        $data = $this->request->params([['status', 0], 'refusal']);
        $type = $this->request->param('type', 0);
        $data['status_time'] = date('Y-m-d H:i:s');
        if (!in_array($data['status'], [1, -1])) {
            return app('json')->fail('审核状态错误');
        }
        if (($data['status'] == -1) && empty($data['refusal'])) {
            return app('json')->fail('请输入拒绝理由');
        }
        if ($data['status'] == 1) $data['refusal'] = '';
        $data['admin_id'] = $this->request->adminId();
        $this->repository->switchStatus($id, $type, $data);
        return app('json')->success('审核完成');
    }

    /**
     * 退款详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function refundShow($id)
    {
        return app('json')->success($this->repository->refundShow($id));
    }


    /**
     *  修改凭证
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function update($id)
    {
        $image = $this->request->param('image');
        if (empty($image)) return app('json')->fail('请上传凭证');
        $res = $this->repository->get($id);
        if ($res['status'] != 1) return app('json')->success('申请未通过审核');
        $data['image'] = implode(',', $image);
        $data['admin_id'] = $this->request->adminId();
        $data['update_time'] = date('Y-m-d H:i:s');
        $data['financial_status'] = 1;
        $this->repository->update($id, $data);
        return app('json')->success('修改完成');
    }

    /**
     * 备注表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function markForm($id)
    {
        return app('json')->success(formToData($this->repository->adminMarkForm($id)));
    }

    /**
     * 保证金备注表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function markMarginForm($id)
    {
        return app('json')->success(formToData($this->repository->adminMarginMarkForm($id)));
    }

    /**
     *  备注
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function mark($id)
    {
        $ret = $this->repository->getWhere([$this->repository->getPk() => $id]);

        if (!$ret) return app('json')->fail('数据不存在');
        $data = $this->request->params(['admin_mark']);
        $this->repository->update($id, $data);

        return app('json')->success('备注成功');
    }

    /**
     *  头部统计
     * @return \think\response\Json
     * @author Qinii
     * @day 4/22/21
     */
    public function title()
    {
        $ret = $this->repository->getTitle(['type' => 0]);
        return app('json')->success($ret);
    }

    /**
     *  导出
     * @return \think\response\Json
     * @author Qinii
     * @day 4/22/21
     */
    public function export()
    {
        $where = $this->request->params(['date', 'status', 'financial_type', 'financial_status', 'keyword', 'is_trader', 'mer_id']);
        [$page, $limit] = $this->getPage();
        $where['type'] = 0;
        $data = app()->make(ExcelService::class)->financialLog($where, $page, $limit);
        return app('json')->success($data);
    }


}
