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

namespace app\common\repositories\store\product;

use app\common\dao\store\product\StoreProductCardRuleDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;

class StoreProductCardRuleRepository extends BaseRepository
{
    public const USE_LIMIT_TYPE_FIXED = 1;
    public const USE_LIMIT_TYPE_UNLIMITED = 2;
    public const USE_LIMIT_TYPES = [
        self::USE_LIMIT_TYPE_FIXED,
        self::USE_LIMIT_TYPE_UNLIMITED,
    ];
    public const USE_LIMIT_TYPE_LABELS = [
        self::USE_LIMIT_TYPE_FIXED => '固定次数',
        self::USE_LIMIT_TYPE_UNLIMITED => '有效期内不限次数',
    ];

    public const VALIDITY_TYPE_FOREVER = 1;
    public const VALIDITY_TYPE_AFTER_PAY_DAYS = 2;
    public const VALIDITY_TYPE_DATE_RANGE = 3;
    public const VALIDITY_TYPES = [
        self::VALIDITY_TYPE_FOREVER,
        self::VALIDITY_TYPE_AFTER_PAY_DAYS,
        self::VALIDITY_TYPE_DATE_RANGE,
    ];
    public const VALIDITY_TYPE_LABELS = [
        self::VALIDITY_TYPE_FOREVER => '永久有效',
        self::VALIDITY_TYPE_AFTER_PAY_DAYS => '购买后有效天数',
        self::VALIDITY_TYPE_DATE_RANGE => '固定有效期',
    ];

    public const REFUND_RULE_UNVERIFIED = 1;
    public const REFUND_RULE_DISABLED = 2;
    public const REFUND_RULES = [
        self::REFUND_RULE_UNVERIFIED,
        self::REFUND_RULE_DISABLED,
    ];
    public const REFUND_RULE_LABELS = [
        self::REFUND_RULE_UNVERIFIED => '未核销可退',
        self::REFUND_RULE_DISABLED => '不支持退款',
    ];

    public const SERVICE_TYPE_STORE = 1;
    public const SERVICE_TYPE_HOME = 2;
    public const SERVICE_TYPE_BOTH = 3;
    public const SERVICE_TYPES = [
        self::SERVICE_TYPE_STORE,
        self::SERVICE_TYPE_HOME,
        self::SERVICE_TYPE_BOTH,
    ];
    public const SERVICE_TYPE_LABELS = [
        self::SERVICE_TYPE_STORE => '到店服务',
        self::SERVICE_TYPE_HOME => '上门服务',
        self::SERVICE_TYPE_BOTH => '上门+到店服务',
    ];

    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    public const STATUSES = [
        self::STATUS_DISABLED,
        self::STATUS_ENABLED,
    ];
    public const STATUS_LABELS = [
        self::STATUS_DISABLED => '禁用',
        self::STATUS_ENABLED => '启用',
    ];

    public const IS_DEL_NO = 0;
    public const IS_DEL_YES = 1;
    public const TOTAL_COUNT_EMPTY = 0;
    public const VALID_DAYS_EMPTY = 0;

    public function __construct(StoreProductCardRuleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 保存商品 SKU 对应的次卡/年卡规则。
     *
     * 商品编辑时会先保存 SKU，再调用这里把规则绑定回真实 value_id/unique。
     * 次卡当前只支持单规格，但这里仍按 SKU 维度处理，方便兼容已有商品规格结构：
     * - 优先用 unique 匹配规格。
     * - unique 匹配不到时用 value_id 匹配。
     * - 单规格场景下兜底使用唯一 SKU，避免编辑时 value_id 没回传导致新建重复规则。
     * 已存在的规则只更新，不重新插入；本次没有保存到的旧规则会软删除，防止历史规则继续被下单命中。
     *
     * @param int $merId 商户 ID。
     * @param int $productId 商品 ID。
     * @param array $rules 前端提交的规则集合，key 通常为 SKU unique。
     */
    public function saveProductRules(int $merId, int $productId, array $rules): void
    {
        if (empty($rules)) {
            return;
        }

        $rules = array_filter($rules, function ($rule) {
            return is_array($rule) && !empty($rule);
        });
        if (empty($rules)) {
            return;
        }

        $attrValues = app()->make(ProductAttrValueRepository::class)
            ->search(['product_id' => $productId])
            ->select()
            ->toArray();
        $firstAttrValue = reset($attrValues) ?: null;
        $attrValueMap = array_column($attrValues, null, 'unique');
        $attrValueIdMap = array_column($attrValues, null, 'value_id');
        $activeRules = $this->dao->search([
                'product_id' => $productId,
                'is_del' => self::IS_DEL_NO,
            ])
            ->order('card_rule_id DESC')
            ->select()
            ->toArray();
        $activeRuleIds = array_map('intval', array_column($activeRules, 'card_rule_id'));
        $insert = [];
        $savedRuleIds = [];
        $savedValueIds = [];
        $now = date('Y-m-d H:i:s');

        foreach ($rules as $unique => $rule) {
            $attrValue = $attrValueMap[$unique] ?? null;
            if (!$attrValue && !empty($rule['value_id'])) {
                $attrValue = $attrValueIdMap[(int)$rule['value_id']] ?? null;
            }
            if (!$attrValue && count($attrValues) === 1) {
                $attrValue = $firstAttrValue;
            }
            if (!$attrValue) {
                continue;
            }
            unset($rule['value_id']);
            $unique = $attrValue['unique'];
            $savedValueIds[] = (int)$attrValue['value_id'];
            $saveData = array_merge(
                [
                    'mer_id' => $merId,
                    'product_id' => $productId,
                    'value_id' => $attrValue['value_id'],
                    'unique' => $unique,
                    'is_del' => self::IS_DEL_NO,
                    'update_time' => $now,
                ],
                $this->normalizeRule($rule, $attrValue['sku'] ?? '')
            );

            $oldRule = $this->matchActiveRule($activeRules, (int)$attrValue['value_id'], $unique, count($attrValues) === 1);
            if ($oldRule) {
                $ruleId = (int)$oldRule['card_rule_id'];
                $this->dao->update($ruleId, $saveData);
                $savedRuleIds[] = $ruleId;
            } else {
                $saveData['create_time'] = $now;
                $insert[] = $saveData;
            }
        }

        $staleRuleIds = array_diff($activeRuleIds, array_unique($savedRuleIds));
        if ($staleRuleIds && $savedValueIds) {
            $this->dao->updates($staleRuleIds, ['is_del' => self::IS_DEL_YES, 'update_time' => $now]);
        }

        if ($insert) {
            $this->dao->insertAll($insert);
        }
    }

    /**
     * 获取可用于下单/发卡的启用规则。
     *
     * 下单和发卡必须命中 is_del = 0 且 status = 启用的规则。
     * 查询优先使用 value_id；没有 value_id 时再用 unique，适配部分历史数据或前端只传 unique 的场景。
     *
     * @param int $merId 商户 ID。
     * @param int $productId 商品 ID。
     * @param int $valueId SKU value_id。
     * @param string $unique SKU unique。
     */
    public function getEnabledRule(int $merId, int $productId, int $valueId = 0, string $unique = '')
    {
        $query = $this->dao->search([
            'mer_id' => $merId,
            'product_id' => $productId,
            'status' => self::STATUS_ENABLED,
            'is_del' => self::IS_DEL_NO,
        ]);

        if ($valueId) {
            $query->where('value_id', $valueId);
        } else if ($unique !== '') {
            $query->where('unique', $unique);
        }

        return $query->find();
    }

    /**
     * 从已有未删除规则中匹配本次应更新的规则。
     *
     * 先按 value_id 匹配，再按 unique 匹配；单规格商品最后允许复用任意一条旧规则。
     * 这个兜底是为了避免次卡编辑时因为前端没带旧 value_id 而不断新增规则记录。
     */
    protected function matchActiveRule(array $activeRules, int $valueId, string $unique, bool $isSingleSku): ?array
    {
        foreach ($activeRules as $rule) {
            if ((int)$rule['value_id'] === $valueId) {
                return $rule;
            }
        }

        foreach ($activeRules as $rule) {
            if ((string)$rule['unique'] === $unique) {
                return $rule;
            }
        }

        return $isSingleSku && $activeRules ? $activeRules[0] : null;
    }

    /**
     * 规范化并校验次卡/年卡规则字段。
     *
     * 这里把前端提交值收敛为数据库字段：
     * 固定次数必须填写 total_count；不限次数 total_count 固定为 0。
     * 购买后有效天数必须填写 valid_days；固定有效期必须填写开始和结束日期。
     * 退款规则、服务方式、启用状态都必须在预设枚举范围内。
     *
     * @param array $rule 前端提交的规则数据。
     * @param string $sku 用于拼接错误提示的 SKU 名称。
     * @return array
     */
    protected function normalizeRule(array $rule, string $sku = ''): array
    {
        $useLimitType = (int)($rule['use_limit_type'] ?? self::USE_LIMIT_TYPE_FIXED);
        if (!in_array($useLimitType, self::USE_LIMIT_TYPES, true)) {
            throw new ValidateException($this->skuMessage($sku, '核销次数类型错误'));
        }

        $totalCount = $useLimitType === self::USE_LIMIT_TYPE_FIXED
            ? (int)($rule['total_count'] ?? self::TOTAL_COUNT_EMPTY)
            : self::TOTAL_COUNT_EMPTY;
        if ($useLimitType === self::USE_LIMIT_TYPE_FIXED && $totalCount <= self::TOTAL_COUNT_EMPTY) {
            throw new ValidateException($this->skuMessage($sku, '核销次数必须大于0'));
        }

        $validityType = (int)($rule['validity_type'] ?? self::VALIDITY_TYPE_FOREVER);
        if (!in_array($validityType, self::VALIDITY_TYPES, true)) {
            throw new ValidateException($this->skuMessage($sku, '核销时效类型错误'));
        }

        $validDays = $validityType === self::VALIDITY_TYPE_AFTER_PAY_DAYS
            ? (int)($rule['valid_days'] ?? self::VALID_DAYS_EMPTY)
            : self::VALID_DAYS_EMPTY;
        if ($validityType === self::VALIDITY_TYPE_AFTER_PAY_DAYS && $validDays <= self::VALID_DAYS_EMPTY) {
            throw new ValidateException($this->skuMessage($sku, '有效天数必须大于0'));
        }

        $validStartTime = null;
        $validEndTime = null;
        if ($validityType === self::VALIDITY_TYPE_DATE_RANGE) {
            $validStartTime = $this->normalizeDate($rule['valid_start_time'] ?? null);
            $validEndTime = $this->normalizeDate($rule['valid_end_time'] ?? null);
            if (!$validStartTime || !$validEndTime) {
                throw new ValidateException($this->skuMessage($sku, '固定有效期必须填写开始和结束日期'));
            }
            if (strtotime($validStartTime) > strtotime($validEndTime)) {
                throw new ValidateException($this->skuMessage($sku, '固定有效期开始日期不能大于结束日期'));
            }
        }

        $refundRule = (int)($rule['refund_rule'] ?? self::REFUND_RULE_UNVERIFIED);
        if (!in_array($refundRule, self::REFUND_RULES, true)) {
            throw new ValidateException($this->skuMessage($sku, '退款规则错误'));
        }

        $serviceType = (int)($rule['service_type'] ?? $rule['reservation_type'] ?? self::SERVICE_TYPE_STORE);
        if (!in_array($serviceType, self::SERVICE_TYPES, true)) {
            throw new ValidateException($this->skuMessage($sku, '服务方式错误'));
        }

        $status = (int)($rule['status'] ?? self::STATUS_ENABLED);
        if (!in_array($status, self::STATUSES, true)) {
            throw new ValidateException($this->skuMessage($sku, '规则状态错误'));
        }

        return [
            'use_limit_type' => $useLimitType,
            'total_count' => $totalCount,
            'validity_type' => $validityType,
            'valid_days' => $validDays,
            'valid_start_time' => $validStartTime,
            'valid_end_time' => $validEndTime,
            'refund_rule' => $refundRule,
            'service_type' => $serviceType,
            'status' => $status,
        ];
    }

    /**
     * 规范化规则有效期日期。
     *
     * 支持时间戳或日期字符串；无法解析时返回 null，让上层校验给出业务错误。
     */
    protected function normalizeDate($value): ?string
    {
        if (!$value) {
            return null;
        }
        $time = is_numeric($value) ? (int)$value : strtotime((string)$value);
        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * 生成带 SKU 前缀的规则校验错误文案。
     */
    protected function skuMessage(string $sku, string $message): string
    {
        return $sku === '' ? $message : '规格【' . $sku . '】' . $message;
    }
}
