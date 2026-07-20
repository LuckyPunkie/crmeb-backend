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
 * 低代码：字段类型.
 */
final class CrudFormEnum extends Enum
{
    /**
     * 布尔类型.
     */
    const FORM_SWITCH = 'switch';

    /**
     * 整数类型.
     */
    const FORM_INPUT_NUMBER = 'input_number';

    /**
     * 精度小数.
     */
    const FORM_INPUT_FLOAT = 'input_float';

    /**
     * 百分比.
     */
    const FORM_INPUT_PERCENTAGE = 'input_percentage';

    /**
     * 金额.
     */
    const FORM_INPUT_PRICE = 'input_price';

    /**
     * 文本.
     */
    const FORM_INPUT = 'input';

    /**
     * 长文本.
     */
    const FORM_TEXTAREA = 'textarea';

    /**
     * 单选项.
     */
    const FORM_RADIO = 'radio';

    /**
     * 级联单选.
     */
    const FORM_CASCADER_RADIO = 'cascader_radio';

    /**
     * 地址选择.
     */
    const FORM_CASCADER_ADDRESS = 'cascader_address';

    /**
     * 复选项.
     */
    const FORM_CHECKBOX = 'checkbox';

    /**
     * 标签组.
     */
    const FORM_TAG = 'tag';

    /**
     * 级联复选.
     */
    const FORM_CASCADER = 'cascader';

    /**
     * 日期
     */
    const FORM_DATE_PICKER = 'date_picker';

    /**
     * 日期时间.
     */
    const FORM_DATE_TIME_PICKER = 'date_time_picker';

    /**
     * 图片.
     */
    const FORM_IMAGE = 'image';

    /**
     * 文件.
     */
    const FORM_FILE = 'file';

    /**
     * 一对一关联.
     */
    const FORM_INPUT_SELECT = 'input_select';

    /**
     * 下拉.
     */
    const FORM_SELECT = 'select';
}
