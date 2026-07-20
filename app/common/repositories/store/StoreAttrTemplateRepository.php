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


namespace app\common\repositories\store;


use app\common\dao\BaseDao;
use app\common\dao\store\StoreAttrTemplateDao;
use app\common\repositories\BaseRepository;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\Model;

/**
 * Class StoreAttrTemplateRepository
 * @package app\common\repositories\store
 * @author xaboy
 * @day 2020-05-06
 * @mixin StoreAttrTemplateDao
 */
class StoreAttrTemplateRepository extends BaseRepository
{
    /**
     * @var StoreAttrTemplateDao
     */
    protected $dao;

    /**
     * StoreAttrTemplateRepository constructor.
     * @param StoreAttrTemplateDao $dao
     */
    public function __construct(StoreAttrTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取分页列表数据。
     *
     * 本函数用于查询特定条件下的数据列表，并支持分页。它首先根据提供的商家ID和查询条件进行查询，
     * 然后计算符合条件的数据总数，最后根据指定的页码和每页数据量获取对应的数据列表。
     *
     * @param int $merId 商家ID，用于限定查询的数据范围。
     * @param array $where 查询条件数组，用于指定查询的详细条件。
     * @param int $page 当前页码，用于指定要返回的数据页。
     * @param int $limit 每页数据数量，用于指定每页返回的数据条数。
     * @return array 返回包含数据总数和数据列表的数组。
     */
    public function getList($merId, array $where, $page, $limit)
    {
        // 根据商家ID和查询条件进行查询
        $query = $this->dao->search($merId, $where);

        // 计算符合条件的数据总数
        $count = $query->count($this->dao->getPk());

        // 根据当前页码和每页数据量获取数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 检查传入数据的值是否符合规则。
     * 此方法用于验证模板值的合法性，包括检查规则名称是否存在、规则值是否重复、规则属性是否重复等。
     *
     * @param array $data 包含模板值的数据数组。
     * @return array 经过验证并可能经过修改的数据数组。
     * @throws ValidateException 如果数据验证失败，抛出此异常。
     */
    protected function checkValue(array $data)
    {
        // 初始化一个数组，用于存储已存在的规则值，以检查是否有重复。
        $arr = [];
        // 遍历模板值数组，对每个规则进行详细检查。
        foreach ($data['template_value'] as $k => $value) {
            // 如果当前规则值不是数组，则抛出异常，表示规则有误。
            if (!is_array($value)) throw new ValidateException('规则有误');
            // 如果当前规则的名称为空，则抛出异常，提示输入规则名称。
            if (!($value['value'] ?? null)) throw new ValidateException('请输入规则名称');
            // 如果当前规则的详细值为空或为空数组，则抛出异常，提示添加规则值。
            if (!($value['detail'] ?? null) || !count($value['detail'])) throw new ValidateException('请添加规则值');
            // 如果当前规则值已存在于已记录的规则值数组中，则抛出异常，表示规格重复。
            if(in_array($value['value'],$arr)) throw new ValidateException('规格重复');
            // 将当前规则值添加到已记录的规则值数组中。
            $arr[] = $value['value'];
            // 如果当前规则的详细值经去除重复后长度不等于原数组长度，则抛出异常，表示属性重复。
            if (count($value['detail']) != count(array_unique($value['detail']))) throw new ValidateException('属性重复') ;
            // 更新数据数组中的当前规则值，保留验证通过的值和详细信息。
            $data['template_value'][$k] = [
                'value' => $value['value'],
                'detail' => $value['detail'],
            ];
        }
        // 返回经过验证和可能修改后的数据数组。
        return $data;
    }

    /**
     * 创建新记录
     *
     * 本函数用于根据提供的数据创建新的记录。它首先对数据进行检查，然后通过数据访问对象（DAO）执行创建操作。
     * 这是一个重要的业务逻辑操作，确保了数据在插入数据库前的正确性和完整性。
     *
     * @param array $data 包含新记录数据的数组。数组的键应与数据库表的字段名对应，值为要插入的字段值。
     * @return mixed 返回DAO创建操作的结果。具体类型取决于DAO的实现，可能是一个布尔值、影响行数或新创建的记录ID。
     */
    public function create(array $data)
    {
        // 检查并可能修改传入的数据，以确保其符合要求
        $data = $this->checkValue($data);
        // 使用DAO创建新记录
        return $this->dao->create($data);
    }

    /**
     * 更新模板数据。
     *
     * 本函数用于根据给定的ID和新数据更新数据库中的相应记录。它首先对传入的数据进行有效性检查，
     * 然后将模板值转换为JSON格式，最后调用DAO层的方法执行更新操作。
     *
     * @param int $id 记录的唯一标识符，用于定位要更新的记录。
     * @param array $data 包含新数据的数组，其中必须包含template_value键。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     */
    public function update(int $id, array $data)
    {
        // 检查并处理传入的数据，确保其符合要求。
        $data = $this->checkValue($data);

        // 将模板值转换为JSON格式，以便存储和处理。
        $data['template_value'] = json_encode($data['template_value']);

        // 调用DAO层的update方法，执行实际的数据库更新操作。
        return $this->dao->update($id, $data);
    }

    /**
     * 根据商家ID列出相关数据
     *
     * 本函数通过调用DAO层的方法，获取指定商家ID的相关列表数据。
     * 主要用于业务逻辑中对数据的查询操作，不涉及复杂的数据处理。
     *
     * @param int $merId 商家ID，用于指定查询的数据范围。
     * @return array 返回查询结果列表，包含指定商家的相关数据。
     */
    public function list(int $merId)
    {
        // 调用DAO层的方法，获取指定商家ID的列表数据
        return $this->dao->getList($merId);
    }
}
