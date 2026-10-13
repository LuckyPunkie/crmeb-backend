<?php

namespace app\controller\admin\delivery;

use app\common\model\delivery\DeliveryService as DeliveryServiceModel;
use app\common\model\delivery\DeliveryServiceWithdraw;
use app\common\model\delivery\DeliveryStation;
use app\common\model\merchant\MerchantMemberLevel;
use app\common\repositories\delivery\DeliveryOrderRepository;
use app\common\repositories\delivery\DeliveryServiceRepository;
use app\common\repositories\delivery\DeliveryServiceWithdrawRepository;
use app\validate\merchant\DeliveryServiceValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 平台配送员
 */
class DeliveryService extends BaseController
{
    protected $repository;

    public function __construct(App $app, DeliveryServiceRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'name', 'status', 'uid', 'phone', 'nickname']);
        $where['type'] = 0;
        $where['mer_id'] = 0;
        $where['is_del'] = 0;
        $data = $this->repository->getList($where, $page, $limit);
        $data['list']->hidden(['certificate_front', 'certificate_back', 'certificate_images']);
        return app('json')->success($data);
    }

    public function detail($id)
    {
        $data = $this->getPlatformService((int)$id, true);
        if (!$data) {
            return app('json')->fail('数据不存在');
        }
        $data = array_merge($data->toArray(), $this->repository->getOrderCount((int)$id));
        return app('json')->success($data);
    }

    public function orderList($id, DeliveryOrderRepository $repository)
    {
        if (!$this->getPlatformService((int)$id)) {
            return app('json')->fail('数据不存在');
        }
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'station_name', 'status', 'date', 'order_sn']);
        $where['service_id'] = (int)$id;
        $where['station_type'] = DeliveryStation::TYPE_PLATFORM;
        return app('json')->success($repository->sysList($where, $page, $limit));
    }

    public function withdrawSuccessList($id, DeliveryServiceWithdrawRepository $repository)
    {
        if (!$this->getPlatformService((int)$id)) {
            return app('json')->fail('数据不存在');
        }
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'date', 'payment_method', ['create_time', []]]);
        $where['service_id'] = (int)$id;
        $where['service_type'] = DeliveryServiceModel::TYPE_PLATFORM;
        $where['mer_id'] = 0;
        $where['status'] = DeliveryServiceWithdraw::STATUS_PAID;
        return app('json')->success($repository->getList($where, $page, $limit));
    }

    /**
     * 获取平台配送员统计列表：支持 date 统一日期筛选及旧版配送日期参数
     * @return \think\response\Json 配送员统计列表
     */
    public function statisticsList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['name', 'uid', 'phone', ['date', ''], ['delivery_date', []]]);
        $where['type'] = 0;
        $where['mer_id'] = 0;
        $where['is_del'] = 0;

        return app('json')->success($this->repository->getStatisticsList($where, $page, $limit));
    }

    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(null, 0, 0)));
    }

    public function updateForm($id)
    {
        return app('json')->success(formToData($this->repository->form((int)$id, 0, 0)));
    }

    public function create()
    {
        $data = $this->checkParams();
        if ($this->repository->getWhere(['uid' => $data['uid'], 'type' => 0, 'mer_id' => 0, 'is_del' => 0])) {
            throw new ValidateException('该配送员已存在,请勿重复创建');
        }
        $data['type'] = 0;
        $data['mer_id'] = 0;
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    public function update($id)
    {
        $data = $this->checkParams();
        if (!$this->getPlatformService($id)) {
            return app('json')->fail('数据不存在');
        }
        $isExist = $this->repository->getSearch([
            'uid' => $data['uid'],
            'type' => 0,
            'mer_id' => 0,
            'is_del' => 0,
        ])->where('service_id', '<>', $id)->find();
        if ($isExist) {
            throw new ValidateException('该配送员已存在,请勿重复创建');
        }
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    public function switchWithStatus($id)
    {
        if (!$this->getPlatformService($id)) {
            return app('json')->fail('数据不存在');
        }
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    public function changeStatus($id)
    {
        if (!$this->getPlatformService($id)) {
            return app('json')->fail('数据不存在');
        }
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->update($id, ['is_open' => $status]);
        return app('json')->success('修改成功');
    }

    public function delete($id)
    {
        if (!$this->getPlatformService($id)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    protected function checkParams(): array
    {
        $data = $this->request->params([['uid', []], 'avatar', 'certificate_front', 'certificate_back', ['certificate_images', []], 'name', 'phone', 'remark', 'status', 'sort']);
        app()->make(DeliveryServiceValidate::class)->check($data);
        if (empty($data['uid']['id'])) {
            throw new ValidateException('请选择关联用户');
        }
        if (empty($data['certificate_front'])) {
            throw new ValidateException('请上传证件照正面');
        }
        if (empty($data['certificate_back'])) {
            throw new ValidateException('请上传证件照反面');
        }
        if (!is_array($data['certificate_images'])) {
            throw new ValidateException('证明证书格式错误');
        }
        if (count($data['certificate_images']) > 10) {
            throw new ValidateException('证明证书最多上传10张');
        }
        foreach ($data['certificate_images'] as $image) {
            if (!is_string($image) || $image === '') {
                throw new ValidateException('证明证书格式错误');
            }
        }
        $data['certificate_images'] = implode(',', $data['certificate_images']);
        if (!$data['avatar']) {
            $data['avatar'] = $data['uid']['src'] ?? '';
        }
        $data['uid'] = $data['uid']['id'];
        return $data;
    }

    protected function getPlatformService($id, bool $withApply = false)
    {
        $where = [
            'service_id' => $id,
            'type' => 0,
            'mer_id' => 0,
            'is_del' => 0,
        ];
        $with = [];
        if ($withApply) {
            $with = [
                'apply',
                'user' => function ($query) {
                    $query->field('uid,nickname,avatar,phone');
                    $query->with([
                        'merchantMembers' => function ($query) {
                            $query->field('id,uid,mer_id,level,financial_status,is_logoff,create_time');
                        },
                    ]);
                },
            ];
        }
        $service = $this->repository->getWhere($where, '*', $with);
        if ($withApply && $service && $service->user) {
            $this->attachMemberLevels($service->user->merchantMembers);
        }
        return $service;
    }

    protected function attachMemberLevels($members): void
    {
        if (!$members || count($members) === 0) {
            return;
        }

        $merIds = [];
        $levels = [];
        foreach ($members as $member) {
            $merIds[] = (int)$member->mer_id;
            $levels[] = (int)$member->level;
        }

        $levelMap = [];
        $levelList = MerchantMemberLevel::getDB()
            ->whereIn('mer_id', array_values(array_unique($merIds)))
            ->whereIn('level', array_values(array_unique($levels)))
            ->where('is_del', 0)
            ->select();
        foreach ($levelList as $level) {
            $levelMap[(int)$level->mer_id . ':' . (int)$level->level] = $level;
        }

        foreach ($members as $member) {
            $key = (int)$member->mer_id . ':' . (int)$member->level;
            $member->setRelation('levelInfo', $levelMap[$key] ?? null);
        }
    }
}
