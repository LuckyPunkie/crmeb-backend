<?php

namespace app\common\repositories\delivery;

use think\facade\Route;
use FormBuilder\Factory\Elm;
use app\common\dao\delivery\DeliveryServiceApplyDao;
use app\common\model\delivery\DeliveryServiceApply;
use app\common\model\user\User;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

class DeliveryServiceApplyRepository extends BaseRepository
{
    public function __construct(DeliveryServiceApplyDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(array $where, int $page, int $limit): array
    {
        $query = $this->dao->search($where)->order('a.create_time DESC,a.delivery_service_apply_id DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 统计配送员申请各审核状态数量。
     * STATUS_ALL 作为“全部”标识，申请记录实际状态仍为 -1、0、1。
     */
    public function getStatusStatistics(array $where): array
    {
        unset($where['status']);
        $rows = $this->dao->search($where)
            ->field('a.status, COUNT(a.delivery_service_apply_id) AS count')
            ->group('a.status')
            ->select()
            ->toArray();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int)$row['status']] = (int)$row['count'];
        }

        return [
            ['status' => '', 'title' => '全部', 'count' => array_sum($counts)],
            ['status' => DeliveryServiceApplyDao::STATUS_REVIEW, 'title' => '待审核', 'count' => $counts[DeliveryServiceApplyDao::STATUS_REVIEW] ?? 0],
            ['status' => DeliveryServiceApplyDao::STATUS_APPROVED, 'title' => '已同意', 'count' => $counts[DeliveryServiceApplyDao::STATUS_APPROVED] ?? 0],
            ['status' => DeliveryServiceApplyDao::STATUS_REJECTED, 'title' => '已拒绝', 'count' => $counts[DeliveryServiceApplyDao::STATUS_REJECTED] ?? 0],
        ];
    }

    public function getUserApply(int $uid)
    {
        return $this->dao->query(['uid' => $uid])
            ->with(['auditAdmin'])
            ->order('delivery_service_apply_id DESC')
            ->find();
    }

    public function getDetail(int $id)
    {
        return $this->dao->getWhere(
            ['delivery_service_apply_id' => $id],
            '*',
            ['user', 'auditAdmin']
        );
    }

    public function submit(int $uid, array $data)
    {
        return Db::transaction(function () use ($uid, $data) {
            $user = User::getDB()->where('uid', $uid)->lock(true)->find();
            if (!$user) {
                throw new ValidateException('用户不存在');
            }

            $serviceRepository = app()->make(DeliveryServiceRepository::class);
            if ($serviceRepository->getWhere([
                'uid' => $uid,
                'type' => 0,
                'mer_id' => 0,
                'is_del' => 0,
            ])) {
                throw new ValidateException('您已是平台配送员，无需重复申请');
            }

            $apply = $this->dao->query(['uid' => $uid])
                ->whereIn('status', [
                    DeliveryServiceApplyDao::STATUS_REVIEW,
                    DeliveryServiceApplyDao::STATUS_APPROVED,
                ])->find();
            if ($apply) {
                throw new ValidateException($apply->status == DeliveryServiceApplyDao::STATUS_REVIEW
                    ? '您已有申请正在审核中，请勿重复提交'
                    : '您的配送员申请已通过，请勿重复提交');
            }

            if (count($data['certificate_images']) > 10) {
                throw new ValidateException('证明证书最多上传10张');
            }
            foreach ($data['certificate_images'] as $image) {
                if (!is_string($image) || $image === '') {
                    throw new ValidateException('证明证书格式错误');
                }
            }

            $data['uid'] = $uid;
            $data['avatar'] = $data['avatar'] ?: $user->avatar;
            $data['status'] = DeliveryServiceApplyDao::STATUS_REVIEW;
            return $this->dao->create($data);
        });
    }

    public function audit(int $id, int $adminId, array $data): bool
    {
        return Db::transaction(function () use ($id, $adminId, $data) {
            $apply = DeliveryServiceApply::getDB()
                ->where('delivery_service_apply_id', $id)
                ->lock(true)
                ->find();
            if (!$apply) {
                throw new ValidateException('申请记录不存在');
            }
            if ($apply->status != DeliveryServiceApplyDao::STATUS_REVIEW) {
                throw new ValidateException('该申请已审核，请勿重复操作');
            }

            $serviceId = 0;
            if ($data['status'] == DeliveryServiceApplyDao::STATUS_APPROVED) {
                $serviceRepository = app()->make(DeliveryServiceRepository::class);
                if ($serviceRepository->getWhere([
                    'uid' => $apply->uid,
                    'type' => 0,
                    'mer_id' => 0,
                    'is_del' => 0,
                ])) {
                    throw new ValidateException('该用户已是平台配送员');
                }

                $service = $serviceRepository->create([
                    'uid' => $apply->uid,
                    'type' => 0,
                    'mer_id' => 0,
                    'avatar' => $apply->avatar,
                    'name' => $apply->name,
                    'phone' => $apply->phone,
                    'certificate_front' => $apply->certificate_front,
                    'certificate_back' => $apply->certificate_back,
                    'certificate_images' => implode(',', $apply->certificate_images),
                    'remark' => $apply->remark,
                    'status' => 1,
                    'sort' => 0,
                ]);
                $serviceId = $service->service_id;
            }

            $this->dao->update($id, [
                'service_id' => $serviceId,
                'status' => $data['status'],
                'audit_admin_id' => $adminId,
                'audit_reason' => $data['audit_reason'],
                'audit_time' => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
            return true;
        });
    }

    public function switchStatusForm($id)
    {
        // 构建表单URL，该URL将指向处理提现审核状态切换的控制器方法。
        $url = Route::buildUrl('systemDeliveryServiceApplyAudit', compact('id'))->build();

        // 创建表单对象，并设置表单标题。
        return Elm::createForm($url, [
            // 添加单选按钮组，用于选择提现申请的审核状态：通过或拒绝。
            Elm::radio('status', '审核状态：', 1)->options([
                ['value' => 1, 'label' => '通过'],
                ['value' => -1, 'label' => '拒绝']])->control([
                // 当选择拒绝时，显示文本区域，用于输入拒绝的原因。
                ['value' => -1, 'rule' => [
                    Elm::textarea('audit_reason', '拒绝原因：', '信息有误,请完善')->placeholder('请输入拒绝理由')->required('请输入拒绝理由')
                ]]
            ]),
        ])->setTitle('审核');
    }
}
