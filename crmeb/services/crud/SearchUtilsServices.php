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
namespace crmeb\services\crud;

use think\exception\ValidateException;
use crmeb\services\crud\CrudOperatorEnum;
use crmeb\services\crud\CrudFormEnum;

class SearchUtilsServices
{

    /**
     *  每个类型代表的意思，以及是否存在需要输入的筛选条件
     * @return array[]
     * @author Qinii
     */
    public function getComparator()
    {
        return [
            CrudOperatorEnum::OPERATOR_IN => ['label' => '包含', 'children' => true],
            CrudOperatorEnum::OPERATOR_NOT_IN => ['label' => '不包含', 'children' => true],
            CrudOperatorEnum::OPERATOR_GT => ['label' => '大于', 'children' => true],
            CrudOperatorEnum::OPERATOR_LT => ['label' => '小于', 'children' => true],
            CrudOperatorEnum::OPERATOR_EQ => ['label' => '等于', 'children' => true],
            CrudOperatorEnum::OPERATOR_GT_EQ => ['label' => '大于等于', 'children' => true],
            CrudOperatorEnum::OPERATOR_LT_EQ => ['label' => '小于等于', 'children' => true],
            CrudOperatorEnum::OPERATOR_NOT_EQ => ['label' => '不等于', 'children' => true],
            CrudOperatorEnum::OPERATOR_BT => ['label' => '介于', 'children' => true],
            CrudOperatorEnum::OPERATOR_N_DAY => ['label' => 'N天前', 'children' => true],
            CrudOperatorEnum::OPERATOR_LAST_DAY => ['label' => '最近N天', 'children' => true],
            //CrudOperatorEnum::OPERATOR_NEXT_DAY => ['label' => '未来N天', 'children' => true],
            CrudOperatorEnum::OPERATOR_TO_DAY => ['label' => '今天', 'children' => false],
            CrudOperatorEnum::OPERATOR_WEEK => ['label' => '本周', 'children' => false],
            CrudOperatorEnum::OPERATOR_MONTH => ['label' => '本月', 'children' => false],
            CrudOperatorEnum::OPERATOR_QUARTER => ['label' => '本季度', 'children' => false],
            CrudOperatorEnum::OPERATOR_IS_EMPTY => ['label' => '为空', 'children' => false],
            CrudOperatorEnum::OPERATOR_NOT_EMPTY => ['label' => '不为空', 'children' => false],
        ];
    }

    /**
     *  获取搜索
     * @param array $comparator
     * @param $type
     * @param $options
     * @return array
     * @author Qinii
     */
    public function get(array $comparator, $type, $options = [])
    {
        $this->checkType($type);
        $data = $this->getComparator();
        $res = [];
        foreach ($comparator as $omparator) {
            if (!isset($data[$omparator])) {
                throw new ValidateException('类型不存在');
            }
            $res[] = [
                'value' => $omparator,
                'label' => $data[$omparator]['label'],
                'type' => $type,
                'children' => $data[$omparator]['children'],
                'options' => $options
            ];
        }
        return $res;
    }

    /**
     *  时间类型筛选
     * @return array
     * @author Qinii
     */
    public function getDate()
    {
        $date = [
            CrudOperatorEnum::OPERATOR_EQ,
            CrudOperatorEnum::OPERATOR_GT,
            CrudOperatorEnum::OPERATOR_GT_EQ,
            CrudOperatorEnum::OPERATOR_LT,
            CrudOperatorEnum::OPERATOR_LT_EQ,
            CrudOperatorEnum::OPERATOR_NOT_EQ,
            CrudOperatorEnum::OPERATOR_TO_DAY,
            CrudOperatorEnum::OPERATOR_WEEK,
            CrudOperatorEnum::OPERATOR_MONTH,
            CrudOperatorEnum::OPERATOR_QUARTER,
        ];
        $int = [
            //CrudOperatorEnum::OPERATOR_N_DAY,
            CrudOperatorEnum::OPERATOR_LAST_DAY,
            //CrudOperatorEnum::OPERATOR_NEXT_DAY,
        ];
        $res = $this->get($int, CrudFormEnum::FORM_INPUT_NUMBER);
        $resDate = $this->get($date, CrudFormEnum::FORM_DATE_TIME_PICKER, []);
        $resDates = $this->get([CrudOperatorEnum::OPERATOR_BT,], CrudFormEnum::FORM_DATE_TIME_PICKER);
        $res = array_merge($res, $resDates, $resDate);
        return $res;
    }

    /**
     *  数字类型筛选
     * @return array
     * @author Qinii
     */
    public function getNumber()
    {
        $op = [
            CrudOperatorEnum::OPERATOR_EQ,
            CrudOperatorEnum::OPERATOR_GT,
            CrudOperatorEnum::OPERATOR_GT_EQ,
            CrudOperatorEnum::OPERATOR_LT,
            CrudOperatorEnum::OPERATOR_LT_EQ,
            CrudOperatorEnum::OPERATOR_NOT_EQ,
        ];
        $res = $this->get($op, CrudFormEnum::FORM_INPUT_NUMBER, []);
        $res = array_merge($res, $this->get([CrudOperatorEnum::OPERATOR_BT], CrudFormEnum::FORM_INPUT_NUMBER));
        return $res;
    }

    /**
     *  定义筛选的填写方式
     * @param $type
     * @return void
     * @author Qinii
     * @day 2024/6/4
     */
    public function getType()
    {
        return [
            CrudFormEnum::FORM_INPUT_NUMBER,
            CrudFormEnum::FORM_INPUT,
            CrudFormEnum::FORM_SELECT,
            CrudFormEnum::FORM_DATE_TIME_PICKER
        ];
    }



    /**
     *  检测筛选方式是否在设置范围内
     * @param $comparator
     * @return bool
     * @author Qinii
     * @day 2024/6/4
     */
    public function checkComparator($comparator)
    {
        if (!in_array($comparator, array_keys($this->getComparator()))) {
            throw new ValidateException('筛选方式错误：' . $comparator);
        }
        return true;
    }

    /**
     * @param $type
     * @return true
     * @author Qinii
     * @day 2024/6/4
     */
    public function checkType($type)
    {
        if (!in_array($type, $this->getType())) {
            throw new ValidateException('数据类型错误：' . $type);
        }
        return true;
    }

    /**
     *  循环筛选参数，过滤掉不在允许范围内的参数，检查比较器是不是设置中的类型
     * @param $data
     * @param $field
     * @return array
     * @author Qinii
     * @day 2024/6/4
     */
    public function checkFilterConditions($data, $field)
    {
        $res = [];
        if (!empty($data)) {
            foreach ($data as $datum) {
                if (in_array($datum['property'], $field)) {
                    $this->checkComparator($datum['comparator']);
                    $this->checkType($datum['type']);
//                    $datum = $this->checkFilterType($datum);
                    $res[] = $datum;
                }
            }
        }
        return $res;
    }
}
