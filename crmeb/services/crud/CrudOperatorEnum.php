<?php

declare(strict_types=1);
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace crmeb\services\crud;

use MyCLabs\Enum\Enum;

/**
 * 低代码：条件判断
 * Class CrudOperatorEnum.
 * @email 136327134@qq.com
 * @date 2024/3/7
 */
final class CrudOperatorEnum extends Enum
{
    /**
     * 包含.
     */
    const OPERATOR_IN = 'in';

    /**
     * 不包含.
     */
    const OPERATOR_NOT_IN = 'not_in';

    /**
     * 等于.
     */
    const OPERATOR_EQ = 'eq';

    /**
     * 大于.
     */
    const OPERATOR_GT = 'gt';

    /**
     * 大于等于.
     */
    const OPERATOR_GT_EQ = 'gt_eq';

    /**
     * 小于.
     */
    const OPERATOR_LT = 'lt';

    /**
     * 小于等于.
     */
    const OPERATOR_LT_EQ = 'lt_eq';

    /**
     * 不等于.
     */
    const OPERATOR_NOT_EQ = 'not_eq';

    /**
     * 为空.
     */
    const OPERATOR_IS_EMPTY = 'is_empty';

    /**
     * 不为空.
     */
    const OPERATOR_NOT_EMPTY = 'not_empty';

    /**
     * 区间.
     */
    const OPERATOR_BT = 'between';

    /**
     * N天前.
     */
    const OPERATOR_N_DAY = 'n_day';

    /**
     * 最近N天.
     */
    const OPERATOR_LAST_DAY = 'last_day';

    /**
     * 未来N天.
     */
    const OPERATOR_NEXT_DAY = 'next_day';

    /**
     * 今天.
     */
    const OPERATOR_TO_DAY = 'today';

    /**
     * 本周.
     */
    const OPERATOR_WEEK = 'week';

    /**
     * 本月.
     */
    const OPERATOR_MONTH = 'month';

    /**
     * 本季度.
     */
    const OPERATOR_QUARTER = 'quarter';



}
