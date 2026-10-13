<?php

namespace app\controller\merchant\member;

use app\common\repositories\merchant\MerchantMemberRepository;
use app\common\repositories\merchant\MerchantMemberStatisticsRepository;
use app\common\repositories\merchant\ShoppingCreditsRepository;
use crmeb\basic\BaseController;
use crmeb\services\ExcelService;
use think\App;

/**
 * 商户后台会员管理控制器：负责会员统计、客户列表/详情、客户等级与购物金记录、等级与购物金调整、购物金冻结、会员权益、开通协议、会员页DIY配置、会员等级增删改查等接口，继承自 BaseController
 */
class Member extends BaseController
{
    protected MerchantMemberRepository $repository;

    private const CUSTOMER_FILTER_FIELDS = [
        'uid', 'nickname', 'phone', 'real_name', 'sex', 'country', 'user_type', 'register_type',
        'is_promoter', 'group_id', 'label_id', 'pay_count', 'user_time_type', 'user_time', 'keyword', 'returner',
        'level', 'freeze_status', 'user_identity', 'time_type', 'start_time', 'end_time',
    ];

    /**
     * 构造函数，初始化控制器
     * @param App $app 应用实例
     * @param MerchantMemberRepository $repository 商户会员仓储
     */
    public function __construct(App $app, MerchantMemberRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 商户后台-会员基础指标：统计当前店铺的会员经营指标（可按日期筛选）。
     * @return \think\response\Json
     */
    public function statisticsBase(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->baseInfo(
            $this->request->merId(),
            (string)$this->request->param('date', '')
        ));
    }

    /**
     * 商户后台-会员等级分布统计。
     * @return \think\response\Json
     */
    public function statisticsLevel(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->levelData(
            $this->request->merId(),
            (string)$this->request->param('date', '')
        ));
    }

    /**
     * 商户后台-会员来源分布统计。
     * @return \think\response\Json
     */
    public function statisticsChannel(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->channelData(
            $this->request->merId(),
            (string)$this->request->param('date', '')
        ));
    }

    /**
     * 商户后台-会员与充值趋势统计（可按日期筛选）。
     * @return \think\response\Json
     */
    public function statisticsMemberTrend(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->memberTrend(
            $this->request->merId(),
            (string)$this->request->param('date', '')
        ));
    }

    /**
     * 商户后台-购物金消费趋势统计（可按日期筛选）。
     * @return \think\response\Json
     */
    public function statisticsConsumeTrend(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->consumeTrend(
            $this->request->merId(),
            (string)$this->request->param('date', '')
        ));
    }

    /**
     * 商户后台-客户列表：按条件分页查询当前店铺的会员客户列表
     * @return \think\response\Json
     */
    public function customers(MerchantMemberStatisticsRepository $statisticsRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($statisticsRepository->customerList(
            $this->request->merId(),
            $this->customerFilters(),
            $page,
            $limit
        ));
    }

    /**
     * 商户后台-客户导出：按客户列表筛选条件分页导出当前店铺会员。
     * @return \think\response\Json
     */
    public function customerExport(ExcelService $excelService)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($excelService->merchantMemberCustomer(
            $this->request->merId(),
            $this->customerFilters(),
            $page,
            $limit
        ));
    }

    /**
     * 商户后台-客户详情：获取当前店铺指定客户的会员详情
     * @param $uid 用户ID
     * @return \think\response\Json
     */
    public function customerDetail($uid, MerchantMemberStatisticsRepository $statisticsRepository)
    {
        return app('json')->success($statisticsRepository->customerDetail($this->request->merId(), (int)$uid));
    }

    private function customerFilters(): array
    {
        return $this->request->params(self::CUSTOMER_FILTER_FIELDS);
    }

    /**
     * 商户后台-客户等级记录：分页获取指定客户的会员等级变更记录
     * @param $uid 用户ID
     * @return \think\response\Json
     */
    public function customerLevels($uid)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->levelRecords($this->request->merId(), (int)$uid, $page, $limit));
    }

    /**
     * 商户后台-客户购物金记录：分页获取指定客户的购物金收支记录
     * @param $uid 用户ID
     * @param ShoppingCreditsRepository $creditsRepository 购物金仓储
     * @return \think\response\Json
     */
    public function customerCredits($uid, ShoppingCreditsRepository $creditsRepository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($creditsRepository->userRecords((int)$uid, $this->request->merId(), $page, $limit));
    }

    /**
     * 商户后台-调整客户等级：将指定客户调整为请求指定的会员等级
     * @param $uid 用户ID
     * @return \think\response\Json
     */
    public function changeLevel($uid)
    {
        $this->repository->changeLevel(
            $this->request->merId(),
            (int)$uid,
            (int)$this->request->param('level'),
            (string)$this->request->param('remark', '')
        );
        return app('json')->success('调整成功');
    }

    /**
     * 商户后台-购物金冻结/解冻：修改指定客户的购物金财务状态（冻结/解冻）
     * @param $uid 用户ID
     * @return \think\response\Json
     */
    public function financialStatus($uid)
    {
        $this->repository->changeFinancialStatus(
            $this->request->merId(),
            (int)$uid,
            (int)$this->request->param('status', 1)
        );
        return app('json')->success('操作成功');
    }

    /**
     * 商户后台-调整客户购物金：调整指定客户的购物金充值金额与赠送金额
     * @param $uid 用户ID
     * @param ShoppingCreditsRepository $creditsRepository 购物金仓储
     * @return \think\response\Json
     */
    public function adjustCredits($uid, ShoppingCreditsRepository $creditsRepository)
    {
        $data = $this->request->params([
            ['recharge_amount', 0], ['gift_amount', 0], ['type', 1], ['remark', ''],
        ]);
        $creditsRepository->adjust(
            (int)$uid,
            $this->request->merId(),
            sprintf('%.2f', (float)$data['recharge_amount']),
            sprintf('%.2f', (float)$data['gift_amount']),
            (int)$data['type'] === 2 ? 2 : 1,
            (string)$data['remark']
        );
        return app('json')->success('修改成功');
    }

    /**
     * 商户后台-会员权益列表：获取当前店铺的会员权益配置列表
     * @return \think\response\Json
     */
    public function benefits()
    {
        return app('json')->success($this->repository->benefitsList($this->request->merId()));
    }

    /**
     * 商户后台-保存会员权益：保存当前店铺的会员权益配置
     * @return \think\response\Json
     */
    public function saveBenefits()
    {
        return app('json')->success('保存成功', $this->repository->saveBenefits(
            $this->request->merId(),
            (array)$this->request->param('benefits', [])
        ));
    }

    /**
     * 商户后台-会员开通协议：获取当前店铺的会员开通协议内容
     * @return \think\response\Json
     */
    public function agreement()
    {
        return app('json')->success([
            'agreement' => $this->repository->memberAgreement($this->request->merId()),
        ]);
    }

    /**
     * 商户后台-保存会员协议：保存当前店铺的会员开通协议内容
     * @return \think\response\Json
     */
    public function saveAgreement()
    {
        $this->repository->saveMemberAgreement(
            $this->request->merId(),
            (string)$this->request->param('agreement', '')
        );
        return app('json')->success('保存成功');
    }

    /**
     * 商户后台-会员页DIY配置：获取当前店铺的会员页DIY配置
     * @return \think\response\Json
     */
    public function diyPage()
    {
        return app('json')->success($this->repository->memberDiyPageConfig($this->request->merId()));
    }

    /**
     * 商户后台-保存会员页配置：保存当前店铺会员页所选DIY模板配置
     * @return \think\response\Json
     */
    public function saveDiyPage()
    {
        return app('json')->success(
            '保存成功',
            $this->repository->saveMemberDiyPage(
                $this->request->merId(),
                (int)$this->request->param('diy_id', 0)
            )
        );
    }

    /**
     * 商户后台-会员等级列表：获取当前店铺的会员等级列表
     * @return \think\response\Json
     */
    public function levels()
    {
        return app('json')->success($this->repository->levelList($this->request->merId()));
    }

    /**
     * 商户后台-会员等级详情：获取当前店铺指定会员等级的详情
     * @param $id 等级ID
     * @return \think\response\Json
     */
    public function levelDetail($id)
    {
        return app('json')->success($this->repository->getLevel($this->request->merId(), (int)$id));
    }

    /**
     * 商户后台-添加会员等级：新增当前店铺的会员等级
     * @return \think\response\Json
     */
    public function createLevel()
    {
        $data = $this->request->params(['level','name','threshold_amount','benefits','coupon_ids','discount']);
        $res = $this->repository->saveLevel($this->request->merId(), $data);
        return app('json')->success('添加成功');
    }

    /**
     * 商户后台-编辑会员等级：更新当前店铺指定会员等级
     * @param $id 等级ID
     * @return \think\response\Json
     */
    public function updateLevel($id)
    {
        return app('json')->success('编辑成功', $this->repository->saveLevel(
            $this->request->merId(),
            $this->request->post(),
            (int)$id
        ));
    }

    /**
     * 商户后台-删除会员等级：删除当前店铺指定会员等级
     * @param $id 等级ID
     * @return \think\response\Json
     */
    public function deleteLevel($id)
    {
        $this->repository->deleteLevel($this->request->merId(), (int)$id);
        return app('json')->success('删除成功');
    }
}
