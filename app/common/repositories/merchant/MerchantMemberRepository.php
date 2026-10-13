<?php

namespace app\common\repositories\merchant;

use app\common\dao\merchant\MerchantMemberBenefitsDao;
use app\common\dao\merchant\MerchantMemberDao;
use app\common\dao\merchant\MerchantMemberLevelDao;
use app\common\dao\merchant\MerchantMemberLevelRecordDao;
use app\common\dao\store\coupon\StoreCouponDao;
use app\common\dao\store\coupon\StoreCouponUserDao;
use app\common\dao\system\config\SystemConfigValueDao;
use app\common\dao\system\diy\DiyDao;
use app\common\dao\system\merchant\MerchantDao;
use app\common\model\merchant\MerchantMemberBenefits;
use app\common\model\merchant\MerchantMemberLevelRecord;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\user\UserMerchantRepository;
use crmeb\services\LockService;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 商户会员仓储：负责商户会员基础配置初始化、等级与权益管理、会员加入/注销/等级变更及升级奖励发放。
 */
class MerchantMemberRepository extends BaseRepository
{
    private MerchantMemberBenefitsDao $benefitsDao;
    private MerchantMemberLevelDao $levelDao;
    private MerchantMemberLevelRecordDao $levelRecordDao;
    private SystemConfigValueDao $configValueDao;
    private DiyDao $diyDao;
    private MerchantDao $merchantDao;
    private StoreCouponDao $couponDao;
    private StoreCouponUserDao $couponUserDao;

    public const MAX_LEVEL = 10;
    public const MAX_BENEFITS = 20;
    public const MEMBER_OPEN_AGREEMENT = 'merchant_member_open_agreement';
    public const MEMBER_DIY_PAGE = 'merchant_member_diy_id';

    private const DEFAULT_MEMBER_OPEN_AGREEMENT = '<p>申请成为本店会员后，您可按照店铺会员等级规则享受相应权益，包括会员专享价、购物金、会员专享券及入会或升级礼等。具体等级规则、权益内容和使用条件以本店页面展示及商户后台配置为准。</p>';

    public function __construct(
        MerchantMemberDao $dao,
        MerchantMemberBenefitsDao $benefitsDao,
        MerchantMemberLevelDao $levelDao,
        MerchantMemberLevelRecordDao $levelRecordDao,
        SystemConfigValueDao $configValueDao,
        DiyDao $diyDao,
        MerchantDao $merchantDao,
        StoreCouponDao $couponDao,
        StoreCouponUserDao $couponUserDao
    ) {
        $this->dao = $dao;
        $this->benefitsDao = $benefitsDao;
        $this->levelDao = $levelDao;
        $this->levelRecordDao = $levelRecordDao;
        $this->configValueDao = $configValueDao;
        $this->diyDao = $diyDao;
        $this->merchantDao = $merchantDao;
        $this->couponDao = $couponDao;
        $this->couponUserDao = $couponUserDao;
    }

    /**
     * 初始化商户会员基础配置（权益、LV1等级、开卡协议），通过分布式锁加事务保证并发下只初始化一次
     * @param int $merId 商户ID
     */
    public function ensureMerchantConfig(int $merId): void
    {
        if ($merId <= 0) {
            throw new ValidateException('商户不存在');
        }

        app()->make(LockService::class)->exec(
            'merchant.member.config.' . $merId,
            function () use ($merId) {
                Db::transaction(function () use ($merId) {
                    $benefitCount = $this->benefitsDao->search()
                        ->where('mer_id', $merId)
                        ->where('is_del', 0)
                        ->count();
                    if (!$benefitCount) {
                        $defaults = $this->benefitsDao->search()
                            ->where('mer_id', 0)
                            ->where('is_del', 0)
                            ->order('sort ASC,id ASC')
                            ->select()
                            ->toArray();
                        foreach ($defaults as $default) {
                            $benefitType = $this->resolveBenefitType($default);
                            unset($default['id'], $default['create_time'], $default['update_time']);
                            $default['mer_id'] = $merId;
                            $default['benefit_type'] = $benefitType;
                            $default['can_del'] = 0;
                            $this->benefitsDao->create($default);
                        }
                    }

                    $level = $this->levelDao->search()
                        ->where('mer_id', $merId)
                        ->where('level', 1)
                        ->where('is_del', 0)
                        ->find();
                    if (!$level) {
                        $benefits = $this->benefitsDao->search()
                            ->where('mer_id', $merId)
                            ->where('is_del', 0)
                            ->order('sort ASC,id ASC')
                            ->column('id');
                        $this->levelDao->create([
                            'mer_id' => $merId,
                            'level' => 1,
                            'name' => '普通会员',
                            'threshold_amount' => 0,
                            'benefits' => implode(',', $benefits),
                            'coupon_ids' => '',
                            'discount' => 100,
                            'is_del' => 0,
                        ]);
                    }

                    $this->ensureMemberAgreement($merId);
                });
            }
        );
    }

    /**
     * 确保商户存在会员开卡协议配置，缺失时写入默认协议
     * @param int $merId 商户ID
     */
    private function ensureMemberAgreement(int $merId): void
    {
        $agreement = $this->configValueDao->query([])
            ->where('mer_id', $merId)
            ->where('config_key', self::MEMBER_OPEN_AGREEMENT)
            ->find();
        if (!$agreement) {
            $this->configValueDao->create([
                'config_key' => self::MEMBER_OPEN_AGREEMENT,
                'value' => self::DEFAULT_MEMBER_OPEN_AGREEMENT,
                'mer_id' => $merId,
            ]);
        }
    }

    /**
     * 获取会员开卡协议内容，无配置时返回默认协议
     * @param int $merId 商户ID
     * @return string 协议HTML内容
     */
    public function memberAgreement(int $merId): string
    {
        $this->ensureMerchantAvailable($merId);
        $this->ensureMerchantConfig($merId);
        $agreement = $this->configValueDao->query([])
            ->where('mer_id', $merId)
            ->where('config_key', self::MEMBER_OPEN_AGREEMENT)
            ->value('value');

        return (string)($agreement ?: self::DEFAULT_MEMBER_OPEN_AGREEMENT);
    }

    /**
     * 保存会员开卡协议，校验非空后更新或创建配置
     * @param int $merId 商户ID
     * @param string $agreement 协议HTML内容
     */
    public function saveMemberAgreement(int $merId, string $agreement): void
    {
        $agreement = trim($agreement);
        if ($agreement === '') {
            throw new ValidateException('请填写会员开卡协议');
        }

        $this->ensureMerchantConfig($merId);
        $query = $this->configValueDao->query([])
            ->where('mer_id', $merId)
            ->where('config_key', self::MEMBER_OPEN_AGREEMENT);
        if ($query->count()) {
            $query->update(['value' => $agreement]);
        } else {
            $this->configValueDao->create([
                'config_key' => self::MEMBER_OPEN_AGREEMENT,
                'value' => $agreement,
                'mer_id' => $merId,
            ]);
        }
    }


    /**
     * 保存会员DIY页面配置，校验页面属于当前店铺后更新或创建配置并返回最新配置
     * @param int $merId 商户ID
     * @param int $diyId DIY页面ID，0表示不选择
     * @return array 最新会员DIY页面配置
     */
    public function saveMemberDiyPage(int $merId, int $diyId): array
    {
        $this->ensureMerchantAvailable($merId);
        if ($diyId > 0) {
            $exists = $this->diyDao->query([])
                ->where('id', $diyId)
                ->where('mer_id', $merId)
                ->where('type', 2)
                ->where('is_diy', 1)
                ->where('is_del', 0)
                ->count();
            if (!$exists) {
                throw new ValidateException('请选择当前店铺可用的DIY页面');
            }
        }

        $query = $this->configValueDao->query([])
            ->where('mer_id', $merId)
            ->where('config_key', self::MEMBER_DIY_PAGE);
        if ($query->count()) {
            $query->update(['value' => (string)$diyId]);
        } else {
            $this->configValueDao->create([
                'config_key' => self::MEMBER_DIY_PAGE,
                'value' => (string)$diyId,
                'mer_id' => $merId,
            ]);
        }

        return $this->memberDiyPageConfig($merId);
    }

    /**
     * 校验店铺是否可用（未删除、状态启用、营业中），不可用时抛出异常
     * @param int $merId 商户ID
     */
    private function ensureMerchantAvailable(int $merId): void
    {
        $merchant = $this->merchantDao->query([])
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('status', 1)
            ->where('mer_state', 1)
            ->find();
        if (!$merchant) {
            throw new ValidateException('店铺暂不可用');
        }
    }

    /**
     * 获取商户会员权益列表，未初始化时先初始化配置
     * @param int $merId 商户ID
     * @return array 权益列表
     */
    public function benefitsList(int $merId): array
    {
        $this->ensureMerchantConfig($merId);
        $list = $this->benefitsDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->order('sort ASC,id ASC')
            ->select()
            ->toArray();
        foreach ($list as &$item) {
            $item['benefit_type'] = $this->resolveBenefitType($item);
        }
        unset($item);
        return $list;
    }

    /**
     * 保存会员权益列表，事务内对权益做新增、更新与删除（预设权益或被等级引用的权益禁止删除），返回最新列表
     * @param int $merId 商户ID
     * @param array $benefits 权益数据数组，每项含id/name/selected_icon/unselected_icon/link
     * @return array 保存后的权益列表
     */
    public function saveBenefits(int $merId, array $benefits): array
    {
        $this->ensureMerchantConfig($merId);
        if (!$benefits || count($benefits) > self::MAX_BENEFITS) {
            throw new ValidateException('会员权益数量应为1至' . self::MAX_BENEFITS . '个');
        }

        $names = [];
        foreach ($benefits as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '') {
                throw new ValidateException('权益名称不能为空');
            }
            if (isset($names[$name])) {
                throw new ValidateException('权益名称不能重复');
            }
            $names[$name] = true;
        }

        Db::transaction(function () use ($merId, $benefits) {
            $currentRows = $this->benefitsDao->search()
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->select()
                ->toArray();
            $current = [];
            foreach ($currentRows as $row) {
                $current[(int)$row['id']] = $row;
            }
            $savedIds = [];

            foreach (array_values($benefits) as $index => $item) {
                $id = (int)($item['id'] ?? 0);
                $data = [
                    'name' => trim((string)$item['name']),
                    'selected_icon' => (string)($item['selected_icon'] ?? $item['selectedIcon'] ?? ''),
                    'unselected_icon' => (string)($item['unselected_icon'] ?? $item['unselectedIcon'] ?? ''),
                    'link' => (string)($item['link'] ?? ''),
                    'sort' => $index + 1,
                ];
                if ($id && isset($current[$id])) {
                    $data['benefit_type'] = $this->resolveBenefitType($current[$id]);
                    $this->benefitsDao->search()->where('id', $id)->update($data);
                    $savedIds[] = $id;
                } else {
                    $data['mer_id'] = $merId;
                    $data['benefit_type'] = null;
                    $data['can_del'] = 1;
                    $data['is_del'] = 0;
                    $savedIds[] = $this->benefitsDao->create($data)->id;
                }
            }

            foreach ($current as $id => $item) {
                if (!in_array((int)$id, $savedIds, true)) {
                    if (!(int)$item['can_del']) {
                        throw new ValidateException('预设会员权益不能删除');
                    }
                    $referencedLevel = $this->levelDao->search()
                        ->where('mer_id', $merId)
                        ->where('is_del', 0)
                        ->whereFindInSet('benefits', (int)$id)
                        ->count() > 0;
                    if ($referencedLevel) {
                        throw new ValidateException('权益“' . $item['name'] . '”已被会员等级引用，不能删除');
                    }
                    $this->benefitsDao->search()->where('id', $id)->update(['is_del' => 1]);
                }
            }
        });

        return $this->benefitsList($merId);
    }

    /**
     * 获取会员等级列表，附带各等级权益明细、优惠券信息和会员数量
     * @param int $merId 商户ID
     * @return array 等级列表
     */
    public function levelList(int $merId): array
    {
        $this->ensureMerchantConfig($merId);
        $benefitRows = $this->benefitsDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->select()
            ->toArray();
        $benefits = [];
        foreach ($benefitRows as $row) {
            $benefits[(int)$row['id']] = $row;
        }
        $levels = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->order('level ASC')
            ->select()
            ->toArray();

        foreach ($levels as &$level) {
            $level['benefits'] = array_values(array_intersect(
                $this->ids($level['benefits'] ?? []),
                array_keys($benefits)
            ));
            $level['coupon_ids'] = $this->ids($level['coupon_ids'] ?? []);
            $level['benefits_list'] = array_values(array_intersect_key($benefits, array_flip($level['benefits'])));
            $level['member_count'] = $this->dao->search()
                ->where('mer_id', $merId)
                ->where('level', $level['level'])
                ->where('is_logoff', 0)
                ->count();
            $level['coupon_list'] = $level['coupon_ids']
                ? $this->couponDao->query([])
                    ->where('mer_id', $merId)
                    ->whereIn('coupon_id', $level['coupon_ids'])
                    ->where('is_del', 0)
                    ->field('coupon_id,title,coupon_price,use_min_price,coupon_type,coupon_time,use_start_time,use_end_time')
                    ->select()
                    ->toArray()
                : [];
        }
        unset($level);
        return $levels;
    }

    /**
     * 获取单个会员等级详情，不存在时抛出异常
     * @param int $merId 商户ID
     * @param int $id 等级ID
     * @return array 等级详情
     */
    public function getLevel(int $merId, int $id): array
    {
        foreach ($this->levelList($merId) as $level) {
            if ((int)$level['id'] === $id) {
                return $level;
            }
        }
        throw new ValidateException('会员等级不存在');
    }

    /**
     * 新增或编辑会员等级，校验升级门槛、折扣、名称唯一性及权益优惠券有效性；门槛变更不调整已有会员等级
     * @param int $merId 商户ID
     * @param array $data 等级数据（name/threshold_amount/discount/benefits/coupon_ids等）
     * @param int $id 等级ID，0表示新增
     * @return array 保存后的等级详情
     */
    public function saveLevel(int $merId, array $data, int $id = 0): array
    {
        $this->ensureMerchantConfig($merId);
        $levels = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->order('level ASC')
            ->select()
            ->toArray();

        $current = null;
        if ($id) {
            foreach ($levels as $item) {
                if ((int)$item['id'] === $id) {
                    $current = $item;
                    break;
                }
            }
            if (!$current) {
                throw new ValidateException('会员等级不存在');
            }
            $levelNo = (int)$current['level'];
        } else {
            if (count($levels) >= self::MAX_LEVEL) {
                throw new ValidateException('会员等级最多设置' . self::MAX_LEVEL . '级');
            }
            $levelNo = count($levels) + 1;
        }
        $restoreId = 0;
        if (!$id) {
            $restoreId = (int)($this->levelDao->search()
                ->where('mer_id', $merId)
                ->where('level', $levelNo)
                ->where('is_del', 1)
                ->value('id') ?: 0);
        }

        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new ValidateException('请输入等级名称');
        }
        $threshold = round((float)($data['threshold_amount'] ?? $data['condition'] ?? 0), 2);
        $discount = round((float)($data['discount'] ?? 100), 2);
        if ($threshold < 0) {
            throw new ValidateException('升级门槛不能小于0');
        }
        if ($discount < 0 || $discount > 100) {
            throw new ValidateException('等级折扣应为0至100');
        }
        if ($levelNo === 1 && $threshold != 0) {
            throw new ValidateException('LV1升级门槛只能为0');
        }

        $previous = $levels[$levelNo - 2] ?? null;
        $next = $levels[$levelNo] ?? null;
        if ($previous && $threshold < (float)$previous['threshold_amount']) {
            throw new ValidateException('升级门槛不能低于前一等级');
        }
        if ($next && $threshold > (float)$next['threshold_amount']) {
            throw new ValidateException('升级门槛不能高于后一等级');
        }

        $duplicate = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('name', $name)
            ->where('is_del', 0)
            ->when($id || $restoreId, function ($query) use ($id, $restoreId) {
                $query->where('id', '<>', $id ?: $restoreId);
            })
            ->count();
        if ($duplicate) {
            throw new ValidateException('等级名称不能重复');
        }

        $benefitIds = $this->ids($data['benefits'] ?? $data['selectedBenefits'] ?? []);
        if (!$benefitIds) {
            throw new ValidateException('请至少选择一个等级权益');
        }
        $benefitNames = $this->benefitsDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->whereIn('id', $benefitIds)
            ->column('name', 'id');
        if (count($benefitNames) !== count($benefitIds)) {
            throw new ValidateException('等级权益包含无效数据');
        }
        $hasUpgradeGift = in_array('入会/升级有礼', array_values($benefitNames), true);
        $couponIds = $hasUpgradeGift
            ? $this->ids($data['coupon_ids'] ?? $data['give_coupon_ids'] ?? [])
            : [];
        if ($couponIds) {
            $validCount = $this->couponDao->query([])
                ->where('mer_id', $merId)
                ->where('is_del', 0)
                ->whereIn('coupon_id', $couponIds)
                ->count();
            if ($validCount !== count($couponIds)) {
                throw new ValidateException('升级奖励包含无效优惠券');
            }
        }

        $save = [
            'mer_id' => $merId,
            'level' => $levelNo,
            'name' => $name,
            'threshold_amount' => $threshold,
            'benefits' => implode(',', $benefitIds),
            'coupon_ids' => implode(',', $couponIds),
            'discount' => $discount,
            'is_del' => 0,
        ];
        Db::transaction(function () use (
            $merId,
            $save,
            $restoreId,
            &$id
        ) {
            if ($id) {
                $this->levelDao->search()->where('id', $id)->where('mer_id', $merId)->update($save);
            } elseif ($restoreId) {
                $this->levelDao->search()
                    ->where('id', $restoreId)
                    ->where('mer_id', $merId)
                    ->update($save);
                $id = $restoreId;
            } else {
                $id = $this->levelDao->create($save)->id;
            }
        });
        return $this->getLevel($merId, $id);
    }

    /**
     * 删除会员等级，仅允许按LV从高到低删除，事务内软删除并自动下调该等级会员的等级
     * @param int $merId 商户ID
     * @param int $id 等级ID
     */
    public function deleteLevel(int $merId, int $id): void
    {
        $this->ensureMerchantConfig($merId);
        $level = $this->levelDao->search()
            ->where('id', $id)
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->find();
        if (!$level) {
            throw new ValidateException('会员等级不存在');
        }
        if ((int)$level->level === 1) {
            throw new ValidateException('LV1会员等级不可删除');
        }
        $maxLevel = (int)$this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->max('level');
        if ((int)$level->level !== $maxLevel) {
            throw new ValidateException('请按LV等级从高到低删除');
        }

        Db::transaction(function () use ($merId, $level) {
            $this->levelDao->search()->where('id', $level->id)->update(['is_del' => 1]);
            $members = $this->dao->search()
                ->where('mer_id', $merId)
                ->where('level', $level->level)
                ->where('is_logoff', 0)
                ->select();
            foreach ($members as $member) {
                $before = (int)$member->level;
                $member->level = max(1, $before - 1);
                $member->save();
                $this->createLevelRecord($member->uid, $merId, $before, $member->level, 'delete', '商户删除等级，会员等级自动调整');
            }
        });
    }

    /**
     * 用户加入店铺会员，通过分布式锁防止并发，事务内创建或恢复会员并初始化购物金账户，随后发放入会/升级奖励
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param bool $agreed 是否已同意会员协议
     * @return array 会员信息及实际领取的优惠券ID列表
     */
    public function becomeMember(int $uid, int $merId, bool $agreed = false): array
    {
        if (!$agreed) {
            throw new ValidateException('请先阅读并同意会员协议');
        }
        return app()->make(LockService::class)->exec(
            'merchant.member.join.' . $merId . '.' . $uid,
            function () use ($uid, $merId) {
                $this->ensureMerchantAvailable($merId);
                $this->ensureMerchantConfig($merId);
                $consumptionAmount = app()->make(MerchantMemberStatisticsRepository::class)
                    ->consumptionAmount($uid, $merId);
                $level = $this->reasonableLevel($merId, (float)$consumptionAmount);

                try {
                    $levelRecordId = Db::transaction(function () use ($uid, $merId, $level) {
                        $member = $this->dao->search()
                            ->where('uid', $uid)
                            ->where('mer_id', $merId)
                            ->lock(true)
                            ->find();
                        if ($member && !(int)$member->is_logoff) {
                            throw new ValidateException('已经是店铺会员了');
                        }

                        if ($member) {
                            $member->level = $level['level'];
                            $member->financial_status = 1;
                            $member->is_logoff = 0;
                            $member->logoff_time = null;
                            $member->save();
                        } else {
                            $this->dao->create([
                                'uid' => $uid,
                                'mer_id' => $merId,
                                'level' => $level['level'],
                                'financial_status' => 1,
                                'is_logoff' => 0,
                            ]);
                        }

                        app()->make(ShoppingCreditsRepository::class)->ensureAccount($uid, $merId);
                        app()->make(UserMerchantRepository::class)->getInfo($uid, $merId);
                        $record = $this->createLevelRecord(
                            $uid,
                            $merId,
                            0,
                            (int)$level['level'],
                            'join',
                            '加入店铺会员',
                            $this->ids($level['coupon_ids'] ?? [])
                        );
                        return (int)$record->id;
                    });
                } catch (\Throwable $e) {
                    $joined = $this->dao->search()
                        ->where('uid', $uid)
                        ->where('mer_id', $merId)
                        ->where('is_logoff', 0)
                        ->count();
                    if ($joined && $this->isDuplicateKeyException($e)) {
                        throw new ValidateException('已经是店铺会员了');
                    }
                    throw $e;
                }

                $this->issueLevelReward($levelRecordId);
                return [
                    'member' => $this->memberInfo($uid, $merId),
                    // 返回当前会员等级配置的优惠券，而不是仅返回本次实际发放成功的券。
                    'coupon_ids' => $this->ids($level['coupon_ids'] ?? []),
                ];
            }
        );
    }

    /**
     * 注销店铺会员，校验购物金余额已清零且无退款中的购物金订单后，事务内标记注销
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     */
    public function logoffMember(int $uid, int $merId): void
    {
        Db::transaction(function () use ($uid, $merId) {
            $member = $this->dao->search()
                ->where('uid', $uid)
                ->where('mer_id', $merId)
                ->where('is_logoff', 0)
                ->lock(true)
                ->find();
            if (!$member) {
                return;
            }

            app()->make(ShoppingCreditsRepository::class)->assertCanCloseAccount($uid, $merId);
            if (app()->make(ShoppingCreditsRefundRepository::class)->hasPendingRefund($uid, $merId, true)) {
                throw new ValidateException('存在退款中的购物金订单，暂不能注销店铺会员');
            }

            $member->is_logoff = 1;
            $member->logoff_time = date('Y-m-d H:i:s');
            $member->save();
        });
    }

    /**
     * 获取用户在某店铺的会员信息（等级、权益、折扣、购物金余额等），未加入时返回非会员默认结构
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @return array 会员信息数组
     */
    public function memberInfo(int $uid, int $merId): array
    {
        $member = $this->dao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('is_logoff', 0)
            ->find();
        if (!$member) {
            return [
                'is_merchant_member' => false,
                'shopping_credits_balance' => '0.00',
                'recharge_amount' => '0.00',
                'gift_amount' => '0.00',
            ];
        }
        $level = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('level', $member->level)
            ->where('is_del', 0)
            ->find();
        $balance = app()->make(ShoppingCreditsRepository::class)->balance($uid, $merId);
        return [
            'is_merchant_member' => true,
            'level' => (int)$member->level,
            'level_name' => (string)($level->level_name ?? ''),
            'benefits' => $this->ids($level->benefits ?? []),
            'discount' => (string)($level->discount ?? '100.00'),
            'financial_status' => (int)$member->financial_status,
            'recharge_amount' => $balance['recharge_amount'],
            'gift_amount' => $balance['gift_amount'],
            'shopping_credits_balance' => $balance['shopping_credits_balance'],
            'join_time' => (string)$member->create_time,
        ];
    }

    /**
     * 分页获取用户已加入会员的店铺列表。
     */
    public function userStoreList(int $uid, int $page, int $limit): array
    {
        $query = $this->dao->search([
            'uid' => $uid,
            'is_logoff' => 0,
        ])->with([
            'merchant' => function ($query) {
                $query->field('mer_id,type_id,mer_name,mer_avatar,sales,mer_info,care_count,status,is_del,mer_state');
            },
        ])->order('create_time DESC,id DESC');

        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        foreach ($list as $item) {
            $item['type_id'] = (int)$item['mer_id'];
            $item['type'] = 10;
            if ($item['merchant']) {
                $item['merchant']['showProduct'] = $item['merchant']['AllRecommend'];
            }
        }

        return compact('count', 'list');
    }

    /**
     * 按累计消费额检查并升级到下一级，未达到下一级门槛时保持当前等级，之后发放升级奖励
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param float|null $consumptionAmount 累计消费额，为空时自动计算
     * @return int 刷新后的等级，非会员返回0
     */
    public function refreshLevel(int $uid, int $merId, ?float $consumptionAmount = null): int
    {
        if ($consumptionAmount === null) {
            $consumptionAmount = (float)app()->make(MerchantMemberStatisticsRepository::class)
                ->consumptionAmount($uid, $merId);
        }

        $result = Db::transaction(function () use ($uid, $merId, $consumptionAmount) {
            $member = $this->dao->search()
                ->where('uid', $uid)
                ->where('mer_id', $merId)
                ->where('is_logoff', 0)
                ->lock(true)
                ->find();
            if (!$member) {
                return ['level' => 0, 'coupon_ids' => []];
            }

            $before = (int)$member->level;
            $nextLevel = $this->levelDao->search()
                ->where('mer_id', $merId)
                ->where('level', $before + 1)
                ->where('is_del', 0)
                ->find();
            if (!$nextLevel || bccomp((string)$consumptionAmount, (string)$nextLevel->threshold_amount, 2) < 0) {
                return ['level' => $before, 'coupon_ids' => []];
            }

            $after = $before + 1;
            $member->level = $after;
            $member->save();
            $record = $this->createLevelRecord(
                $uid,
                $merId,
                $before,
                $after,
                'consume',
                '累计消费达到等级门槛',
                $this->ids($nextLevel->coupon_ids ?? [])
            );
            return [
                'level' => $after,
                'level_record_id' => (int)$record->id,
            ];
        });

        if (!empty($result['level_record_id'])) {
            $this->issueLevelReward((int)$result['level_record_id']);
        }
        return (int)$result['level'];
    }

    /**
     * 发放单条等级变更记录中的待发优惠券。
     *
     * 已经存在于用户券包的优惠券视为完成，避免发券成功后进程中断造成重复发放。
     *
     * @return int[] 本次实际新领取的优惠券ID
     */
    public function issueLevelReward(int $recordId): array
    {
        return app()->make(LockService::class)->exec(
            'merchant.member.level.reward.' . $recordId,
            function () use ($recordId) {
                $record = $this->levelRecordDao->search()->where('id', $recordId)->find();
                if (!$record || (int)$record->reward_status !== 1) {
                    return [];
                }

                $rewardCouponIds = $this->ids($record->reward_coupon_ids ?? []);
                $rewardedCouponIds = $this->ids($record->rewarded_coupon_ids ?? []);
                if (!$rewardCouponIds) {
                    $record->reward_status = 2;
                    $record->reward_error = '';
                    $record->reward_time = date('Y-m-d H:i:s');
                    $record->save();
                    return [];
                }

                $received = [];
                $errors = [];
                foreach (array_diff($rewardCouponIds, $rewardedCouponIds) as $couponId) {
                    if ($this->userHasCoupon((int)$record->uid, $couponId)) {
                        $rewardedCouponIds[] = $couponId;
                        continue;
                    }

                    try {
                        app()->make(StoreCouponRepository::class)->receiveMerchantLevelRewardCoupon(
                            $couponId,
                            (int)$record->uid,
                            (int)$record->mer_id
                        );
                        $rewardedCouponIds[] = $couponId;
                        $received[] = $couponId;
                    } catch (\Throwable $e) {
                        if ($this->userHasCoupon((int)$record->uid, $couponId)) {
                            $rewardedCouponIds[] = $couponId;
                            continue;
                        }
                        $errors[] = $couponId . ':' . $e->getMessage();
                    }
                }

                $rewardedCouponIds = $this->ids($rewardedCouponIds);
                $completed = !array_diff($rewardCouponIds, $rewardedCouponIds);
                $record->rewarded_coupon_ids = implode(',', $rewardedCouponIds);
                $record->reward_status = $completed ? 2 : 1;
                $record->reward_attempts = (int)$record->reward_attempts + 1;
                $record->reward_error = $completed ? '' : mb_substr(implode('; ', $errors), 0, 500);
                $record->reward_time = $completed ? date('Y-m-d H:i:s') : null;
                $record->save();

                return $received;
            }
        );
    }

    /**
     * 批量重试待发放的等级奖励（领取状态为待发放的记录），返回处理统计
     * @param int $limit 最多处理的记录数
     * @return array 含processed/completed/received的统计数组
     */
    public function retryPendingLevelRewards(int $limit = 100): array
    {
        $recordIds = $this->levelRecordDao->search()
            ->where('reward_status', 1)
            ->order('id ASC')
            ->limit(max(1, $limit))
            ->column('id');
        $completed = 0;
        $received = 0;
        foreach ($recordIds as $recordId) {
            $received += count($this->issueLevelReward((int)$recordId));
            $status = $this->levelRecordDao->search()
                ->where('id', (int)$recordId)
                ->value('reward_status');
            if ((int)$status === 2) {
                $completed++;
            }
        }

        return [
            'processed' => count($recordIds),
            'completed' => $completed,
            'received' => $received,
        ];
    }

    /**
     * 获取用户当前会员等级的折扣信息，非会员返回null
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @return array|null 等级信息数组或null
     */
    public function memberDiscount(int $uid, int $merId): ?array
    {
        $member = $this->dao->search()
            ->where('uid', $uid)
            ->where('mer_id', $merId)
            ->where('is_logoff', 0)
            ->find();
        if (!$member) {
            return null;
        }
        $level = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('level', (int)$member->level)
            ->where('is_del', 0)
            ->find();
        if (!$level) {
            return null;
        }
        $level = $level->toArray();
        $level['has_member_price_benefit'] = $this->levelHasBenefitType(
            $merId,
            $level['benefits'] ?? [],
            MerchantMemberBenefits::TYPE_MEMBER_PRICE
        );
        return $level;
    }

    /**
     * 按商品会员价类型计算当前会员的实际 SKU 价格。
     */
    public function calculateProductMemberPrice(
        string $originalPrice,
        int $priceType,
        ?array $memberLevel,
        ?string $fixedPrice = null
    ): string {
        $originalPrice = bcadd($originalPrice, '0', 2);
        if (!$memberLevel) {
            return $originalPrice;
        }
        if ($priceType === 1) {
            if (empty($memberLevel['has_member_price_benefit'])) {
                return $originalPrice;
            }
            $discount = (string)($memberLevel['discount'] ?? '100');
            if (bccomp($discount, '0', 2) < 0 || bccomp($discount, '100', 2) > 0) {
                return $originalPrice;
            }
            return bcmul($originalPrice, bcdiv($discount, '100', 4), 2);
        }
        if ($priceType === 2 && $fixedPrice !== null && is_numeric($fixedPrice)) {
            return bcadd($fixedPrice, '0', 2);
        }
        return $originalPrice;
    }

    /**
     * 判断会员等级是否包含指定系统权益类型。
     */
    private function levelHasBenefitType(int $merId, $benefits, int $benefitType): bool
    {
        $benefitIds = $this->ids($benefits);
        if (!$benefitIds) {
            return false;
        }
        $list = $this->benefitsDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->whereIn('id', $benefitIds)
            ->select()
            ->toArray();
        foreach ($list as $item) {
            if ($this->resolveBenefitType($item) === $benefitType) {
                return true;
            }
        }
        return false;
    }

    /**
     * 兼容 benefit_type 尚未回填的系统预设权益。
     */
    private function resolveBenefitType(array $benefit): ?int
    {
        if (($benefit['benefit_type'] ?? null) !== null) {
            return (int)$benefit['benefit_type'];
        }
        if ((int)($benefit['can_del'] ?? 1) !== 0) {
            return null;
        }
        $type = array_search(
            (string)($benefit['name'] ?? ''),
            MerchantMemberBenefits::SYSTEM_TYPE_NAMES,
            true
        );
        return $type === false ? null : (int)$type;
    }

    /**
     * 判断异常是否为数据库唯一键冲突（duplicate entry等）
     * @param \Throwable $e 捕获的异常
     * @return bool 是否为唯一键冲突
     */
    private function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate entry')
            || str_contains($message, 'sqlstate[23000]')
            || str_contains($message, 'integrity constraint violation');
    }

    /**
     * 分页查询用户在某店铺的会员等级变更记录
     * @param int $merId 商户ID
     * @param int $uid 用户ID
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 含count与list的分页结果
     */
    public function levelRecords(int $merId, int $uid, int $page, int $limit): array
    {
        $query = $this->levelRecordDao->search()->where('mer_id', $merId)->where('uid', $uid);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('id DESC')->select()->toArray();
        foreach ($list as &$item) {
            $item['remark'] = (string)($item['remark'] ?? '');
        }
        unset($item);
        return compact('count', 'list');
    }

    /**
     * 商户手动升级会员等级，校验会员与目标等级后更新并生成变更记录，禁止降级
     * @param int $merId 商户ID
     * @param int $uid 用户ID
     * @param int $level 目标等级
     * @param string $remark 调整备注
     */
    public function changeLevel(int $merId, int $uid, int $level, string $remark = ''): void
    {
        $member = $this->dao->search()->where('mer_id', $merId)->where('uid', $uid)->where('is_logoff', 0)->find();
        if (!$member) {
            throw new ValidateException('该用户不是店铺会员');
        }
        if (!$this->levelDao->search()->where('mer_id', $merId)->where('level', $level)->where('is_del', 0)->count()) {
            throw new ValidateException('会员等级不存在');
        }
        $before = (int)$member->level;
        if ($level < $before) {
            throw new ValidateException('会员等级只能升级，不能降级');
        }
        if ($before === $level) {
            return;
        }
        $member->level = $level;
        $member->save();
        $this->createLevelRecord($uid, $merId, $before, $level, 'merchant', '商户手动调整会员等级', [], trim($remark));
    }

    /**
     * 变更会员财务状态（购物金冻结/解冻）
     * @param int $merId 商户ID
     * @param int $uid 用户ID
     * @param int $status 目标状态，非0为正常、0为冻结
     */
    public function changeFinancialStatus(int $merId, int $uid, int $status): void
    {
        $member = $this->dao->search()->where('mer_id', $merId)->where('uid', $uid)->where('is_logoff', 0)->find();
        if (!$member) {
            throw new ValidateException('该用户不是店铺会员');
        }
        $member->financial_status = $status ? 1 : 0;
        $member->save();
    }

    /**
     * 根据累计消费金额匹配用户应达到的最高会员等级，未设置等级时抛出异常
     * @param int $merId 商户ID
     * @param float $amount 累计消费金额
     * @return array 匹配的等级信息
     */
    private function reasonableLevel(int $merId, float $amount): array
    {
        $levels = $this->levelDao->search()
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('threshold_amount', '<=', $amount)
            ->order('level DESC')
            ->find();
        if (!$levels) {
            throw new ValidateException('请联系商户先设置店铺会员等级');
        }
        return $levels->toArray();
    }

    /**
     * 创建会员等级变更记录，存在奖励优惠券时置为待发放状态
     * @param int $uid 用户ID
     * @param int $merId 商户ID
     * @param int $before 变更前等级
     * @param int $after 变更后等级
     * @param string $type 变更类型（join/consume/merchant/delete等）
     * @param string $message 变更说明
     * @param array $rewardCouponIds 升级奖励优惠券ID列表
     * @param string $remark 调整备注
     * @return MerchantMemberLevelRecord 创建的等级变更记录
     */
    private function createLevelRecord(
        int $uid,
        int $merId,
        int $before,
        int $after,
        string $type,
        string $message,
        array $rewardCouponIds = [],
        string $remark = ''
    ): MerchantMemberLevelRecord
    {
        $rewardCouponIds = $this->ids($rewardCouponIds);
        return $this->levelRecordDao->create([
            'uid' => $uid,
            'mer_id' => $merId,
            'before_level' => $before,
            'after_level' => $after,
            'change_type' => $type,
            'change_message' => $message,
            'remark' => $remark,
            'reward_coupon_ids' => implode(',', $rewardCouponIds),
            'rewarded_coupon_ids' => '',
            'reward_status' => $rewardCouponIds ? 1 : 0,
            'reward_attempts' => 0,
            'reward_error' => '',
            'reward_time' => null,
        ]);
    }

    /**
     * 判断用户是否已领取过指定优惠券
     * @param int $uid 用户ID
     * @param int $couponId 优惠券ID
     * @return bool 是否已拥有
     */
    private function userHasCoupon(int $uid, int $couponId): bool
    {
        return (bool)$this->couponUserDao->query([])
            ->where('uid', $uid)
            ->where('coupon_id', $couponId)
            ->count();
    }

    /**
     * 将逗号分隔字符串或数组规范化为去重去空的整型ID数组
     * @param mixed $value 逗号分隔字符串或数组
     * @return array 整型ID数组
     */
    private function ids($value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }
        return array_values(array_unique(array_filter(array_map('intval', (array)$value))));
    }
}
