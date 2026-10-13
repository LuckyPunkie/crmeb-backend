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
namespace app\controller\merchant\system\financial;

use app\common\repositories\store\ExcelRepository;
use app\common\repositories\system\financial\FinancialRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\validate\merchant\MerchantFinancialAccountValidate;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

class Financial extends BaseController
{
    /**
     * @var FinancialRepository
     */
    protected $repository;

    /**
     * Merchant constructor.
     * @param App $app
     * @param FinancialRepository $repository
     */
    public function __construct(App $app, FinancialRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 转账信息Form
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/18/21
     */
    public function accountForm()
    {
        return app('json')->success(formToData($this->repository->financialAccountForm($this->request->merId())));
    }

    /**
     * 转账信息保存
     * @param MerchantFinancialAccountValidate $accountValidate
     * @return \think\response\Json
     * @author Qinii
     * @day 3/18/21
     */
    public function accountSave(MerchantFinancialAccountValidate $accountValidate)
    {
        $data = $this->request->params(['account','financial_type','name','bank','bank_code','wechat','wechat_code','alipay','alipay_code']); //idcard
        $accountValidate->check($data);

        $this->repository->saveAccount($this->request->merId(),$data);
        return app('json')->success('保存成功');
    }

    /**
     * 申请转账form
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->applyForm($this->request->merId())));
    }

    /**
     * 申请转账保存
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function createSave()
    {
        $data = $this->request->param(['extract_money','financial_type','mark']);
        $data['mer_admin_id'] = $this->request->adminId();
        $this->repository->saveApply($this->request->merId(),$data);
        return app('json')->success('保存成功');
    }

    /**
     * 退保金额
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refundMargin()
    {
        // 调用 repository 中的 checkRefundMargin 方法，传入当前商户 ID 和管理员 ID，返回结果并转化为 JSON 格式
        return app('json')->success($this->repository->checkRefundMargin($this->request->merId(), $this->request->adminId()));
    }

    /**
     * 退保金额申请
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refundMarginApply()
    {
        // 从请求参数中获取 type、name、code、pic 四个字段的值
        $data = $this->request->params(['type', 'name', 'code', 'pic']);
        // 调用 repository 中的 refundMargin 方法，传入当前商户 ID、管理员 ID 和请求参数 data，无返回值
        $this->repository->refundMargin($this->request->merId(), $this->request->adminId(), $data);
        // 返回一个成功的 JSON 响应，消息为 '提交成功'
        return app('json')->success('提交成功');
    }


    /**
     * 列表
     * @author Qinii
     * @day 3/19/21
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date','status','financial_type','financial_status','keyword']);
        $where['keywords_'] = $where['keyword'];
        unset($where['keyword']);
        $where['mer_id'] = $this->request->merId();
        $data = $this->repository->getAdminList($where,$page,$limit);
        return app('json')->success($data);
    }

    /**
     * 取消申请
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function delete($id)
    {
        $this->repository->cancel($this->request->merId(),$id,['is_del' => 1]);
        return app('json')->success('取消申请');
    }

    /**
     *
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 3/19/21
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id,$this->request->merId());
        if(!$data)  return app('json')->fail('数据不存在');
        return app('json')->success($data);
    }


    /**
     * 标记表单
     *
     * @param int $id 表单ID
     * @return \think\response\Json
     */
    public function markForm($id)
    {
        // 调用 formToData 方法将表单转换为数据并返回 JSON 格式的成功响应
        return app('json')->success(formToData($this->repository->markForm($id)));
    }

    /**
     * 标记
     *
     * @param int $id 表单ID
     * @return \think\response\Json
     */
    public function mark($id)
    {
        // 根据主键和商家ID获取表单数据
        $ret = $this->repository->getWhere([$this->repository->getPk() => $id, 'mer_id' => $this->request->merId()]);

        // 如果数据不存在则返回 JSON 格式的失败响应
        if (!$ret) return app('json')->fail('数据不存在');
        // 获取备注数据
        $data = $this->request->params(['mark']);
        // 更新表单数据
        $this->repository->update($id, $data);

        // 返回 JSON 格式的成功响应
        return app('json')->success('备注成功');
    }


    /**
     * 导出财务日志
     *
     * @return \think\response\Json
     */
    public function export()
    {
        $where = $this->request->params(['date', 'status', 'financial_type', 'financial_status', 'keyword']);
        // 将关键字转换为小写并添加前缀
        $where['keywords_'] = $where['keyword'];
        // 删除原有的关键字
        unset($where['keyword']);
        // 添加商家ID
        $where['mer_id'] = $this->request->merId();

        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $data = app()->make(ExcelService::class)->financialLog($where, $page, $limit);
        // 返回成功状态和数据
        return app('json')->success($data);

    }

}
