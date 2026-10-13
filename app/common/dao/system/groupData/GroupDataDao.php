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


namespace app\common\dao\system\groupData;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\groupData\SystemGroupData;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class GroupDataDao
 * @package app\common\dao\system\groupData
 * @author xaboy
 * @day 2020-03-27
 */
class GroupDataDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemGroupData::class;
    }

    /**
     * 根据商家ID和分组ID获取分组数据
     *
     * 本函数旨在查询特定商家和分组对应的系统分组数据。通过指定商家ID和分组ID，
     * 可以从数据库中检索出相应的分组数据，并以特定的排序方式返回。
     *
     * @param int $merId 商家ID，用于指定查询的商家范围
     * @param int $groupId 分组ID，用于指定查询的分组范围
     * @return BaseQuery 返回查询结果的对象，该对象可用于进一步的查询操作或数据获取
     */
    public function getGroupDataWhere($merId, $groupId): BaseQuery
    {
        // 使用SystemGroupData类的getDB方法获取数据库操作对象
        // 并通过withAttr方法处理'value'字段，将其JSON格式化为数组
        // 然后通过where方法指定查询条件：商家ID和分组ID
        // 最后，通过order方法指定查询结果的排序方式为降序排序
        return SystemGroupData::getDB()->withAttr('value', function ($val) {
            // 这里对'value'字段进行JSON解码，以便后续处理
            return json_decode($val, true);
        })->where('mer_id', $merId)->where('group_id', $groupId)->order('sort DESC');
    }

    /**
     * 根据商家ID和分组ID获取分组数据信息。
     *
     * 本函数用于查询系统分组数据中，特定商家和特定分组的相关数据。
     * 它支持分页查询，并返回经过处理的分组数据数组，每个元素包含分组数据ID、商家ID以及其他解码后的分组数据内容。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围。
     * @param string $groupId 分组ID，用于限定查询的分组范围。
     * @param int $page 查询的页码，可选参数，用于分页查询。
     * @param int $limit 每页的数据条数，可选参数，默认为10条。
     * @return array 返回查询结果数组，每个元素包含分组数据的详细信息。
     */
    public function getGroupData($merId, $groupId, ?int $page = null, ?int $limit = 10)
    {
        // 构建查询条件，查询指定商家ID、分组ID，并且状态为启用的分组数据。
        $query = SystemGroupData::getDB()
            ->where('mer_id', $merId)
            ->where('group_id', $groupId)
            ->where('status', 1)
            ->order('sort DESC,group_data_id ASC');

        // 如果指定了页码，则进行分页查询。
        if (!is_null($page)) {
            $query->page($page, $limit);
        }

        // 初始化分组数据数组。
        $groupData = [];

        // 遍历查询结果，对每个分组数据进行处理，包括解码和添加额外字段。
        foreach ($query->column('value,mer_id', 'group_data_id') as $k => $v) {
            // 解码分组数据值，并合并分组数据ID和商家ID到结果中。
            $groupData[] = json_decode($v['value'], true) + ['group_data_id' => $k, 'group_mer_id' => $v['mer_id']];
        }

        // 返回处理后的分组数据数组。
        return $groupData;
    }

    /**
     * 根据ID和状态获取数据
     *
     * 本函数用于从系统分组数据表中检索特定ID和可选状态的数据。它允许灵活地查询数据，
     * 既可以获取所有状态的数据，也可以根据特定状态进行筛选。
     *
     * @param int $id 数据ID，用于精确匹配数据行。
     * @param string|null $status 数据的状态，可选参数，用于按状态筛选数据。
     * @return mixed 返回查询结果，如果未找到数据则返回null。返回的数据包括'group_data_id'和'group_mer_id'，
     *               以及查询结果中的'value'字段，方便调用方直接使用。
     */
    public function getData($id,$status = null)
    {
        // 初始化查询条件，指定数据ID
        $where['group_data_id'] = (int)$id;
        // 如果提供了状态参数，则添加到查询条件中
        if (!is_null($status)) $where['status'] = $status;
        // 执行查询，根据条件获取第一条数据
        $res = SystemGroupData::getDB()->where($where)->find();
        // 如果查询结果为空，则返回null
        if (!$res) return null;
        // 返回查询结果，附加'group_data_id'和'group_mer_id'字段，方便调用方使用
        return $res['value'] + ['group_data_id' => $res['group_data_id'], 'group_mer_id' => $res['mer_id']];
    }

    /**
     * 统计指定商户ID和分组ID的数据条数
     *
     * 此函数用于查询系统分组数据表中，特定商户和特定分组下，状态为有效的数据条数。
     * 主要用于统计或管理目的，例如，展示某个分组下的数据数量，或者作为数据列表的分页依据。
     *
     * @param int $merId 商户ID，用于指定查询的商户范围
     * @param int $groupId 分组ID，用于指定查询的分组范围
     * @return int 返回满足条件的数据条数
     */
    public function groupDataCount($merId, $groupId)
    {
        // 通过SystemGroupData类的getDB方法获取数据库操作对象，并构建查询条件
        // 查询条件包括：商户ID、分组ID和数据状态为有效
        // 最后返回满足条件的数据条数
        return SystemGroupData::getDB()->where('mer_id', $merId)->where('group_id', $groupId)->where('status', 1)->count();
    }

    /**
     * 根据商家ID和分组ID获取分组数据ID列表
     *
     * 本函数用于查询系统分组数据中，特定商家和分组对应的数据显示。
     * 通过分页查询方式，获取指定页码和每页数量的数据列表。返回的数据包括分组数据ID和对应的json格式数据。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围
     * @param string $groupId 分组ID，用于限定查询的分组范围
     * @param int|null $page 查询的页码，可选参数，如果提供则进行分页查询
     * @param int|null $limit 每页的数据条数，可选参数，默认为10条
     * @return array 返回一个包含分组数据ID和数据的数组列表
     */
    public function getGroupDataId($merId, $groupId, ?int $page = null, ?int $limit = 10)
    {
        // 构建查询条件，限定查询商家ID、分组ID，并且状态为启用的分组数据
        $query = SystemGroupData::getDB()->where('mer_id', $merId)->where('group_id', $groupId)->where('status', 1)->order('sort DESC');

        // 如果提供了页码和每页数量，则进行分页查询
        if (!is_null($page)) $query->page($page, $limit);

        // 初始化存储查询结果的数组
        $groupData = [];

        // 遍历查询结果，将分组数据ID和解码后的数据值存入结果数组
        foreach ($query->column('value', 'group_data_id') as $k => $v) {
            $groupData[] = ['id' => $k, 'data' => json_decode($v, true)];
        }

        // 返回处理后的查询结果
        return $groupData;
    }

    /**
     * 更新商家组数据信息
     *
     * 该方法用于根据给定的商家ID、数据ID和数据内容，更新系统组数据中的特定记录。
     * 主要操作包括将数据值转换为JSON格式，然后根据ID和商家ID更新数据库中的相应记录。
     *
     * @param int $merId 商家ID，用于指定更新记录所属的商家。
     * @param int $id 数据ID，用于指定要更新的具体数据记录。
     * @param array $data 数据数组，包含要更新的数据内容。其中的'value'键值对将被编码为JSON格式并更新到数据库中。
     * @return bool 返回更新操作的结果，成功为true，失败为false。
     */
    public function merUpdate($merId, $id, $data)
    {
        // 将$data数组中的'value'值编码为JSON格式，以符合数据库中该字段的存储要求
        $data['value'] = json_encode($data['value']);

        // 使用SystemGroupData类的数据库访问对象，根据$merId和$id查询到指定记录，并更新为$data中的新数据
        // 返回值为更新操作的结果，通常为true（成功）或false（失败）
        return SystemGroupData::getDB()->where('group_data_id', $id)->where('mer_id', $merId)->update($data);
    }

    /**
     * 删除指定商户的数据记录
     *
     * 本函数用于根据给定的商户ID和数据ID，从系统分组数据表中删除相应的数据记录。
     * 这里的“系统分组数据表”可能是存储系统配置或商户特定数据的数据库表。
     *
     * @param int $merId 商户ID，用于指定要删除数据的商户。
     * @param int $id 数据ID，用于指定要删除的具体数据记录。
     * @return int 返回删除操作的影响行数，即被删除的记录数。
     */
    public function merDelete($merId, $id)
    {
        // 根据$merId和$id查询并删除符合条件的数据记录
        return SystemGroupData::getDB()->where('mer_id', $merId)->where('group_data_id', $id)->delete();
    }

    /**
     * 检查指定商户ID和ID组合是否存在对应的记录。
     *
     * 本函数通过查询数据库来确定是否存在一个满足特定条件的记录。
     * 条件包括指定的商户ID（merId）和指定的ID（$id）。
     * 如果存在满足条件的记录，则返回true，表示记录存在；否则返回false。
     *
     * @param int $merId 商户ID，用于限定查询的范围。
     * @param int $id 需要检查的ID，用于进一步限定查询的条件。
     * @return bool 如果存在满足条件的记录则返回true，否则返回false。
     */
    public function merExists(int $merId, int $id)
    {
        // 通过模型获取数据库实例，并构造查询条件，查询满足mer_id和主键$id的记录数量。
        // 如果记录数量大于0，则表示存在对应的记录，返回true；否则返回false。
        return ($this->getModel())::getDB()->where('mer_id', $merId)->where($this->getPk(), $id)->count() > 0;
    }

    /**
     * 清空指定用户组的数据
     *
     * 本函数用于删除数据库中指定用户组的所有数据。这可以是用户组的权限设置、配置项等。
     * 调用此函数将直接影响数据库中与指定用户组相关联的所有记录，因此应谨慎使用。
     *
     * @param int $groupId 用户组的唯一标识符。此参数指定要清空数据的用户组。
     * @return int 返回删除操作的影响行数。这可以用于确定成功删除的记录数量。
     */
    public function clearGroup(int $groupId)
    {
        // 根据$groupId查询并删除数据库中所有属于该用户组的记录
        return SystemGroupData::getDB()->where('group_id', $groupId)->delete();
    }

    /**
     * 根据给定的ID和商户ID获取系统分组数据的值
     *
     * 本函数用于从系统分组数据表中检索指定ID和商户ID对应的数据值。
     * 它首先根据这两个条件查询数据库，然后返回查询结果中'value'字段的值。
     * 如果没有找到匹配的数据，则返回null。
     *
     * @param int $id 系统分组数据的ID
     * @param int $merId 商户的ID
     * @return mixed 返回查询结果中'value'字段的值，如果未找到则返回null
     */
    public function merGet($id, $merId)
    {
        // 根据$group_data_id和$mer_id查询数据库，获取符合条件的第一条数据
        $data = SystemGroupData::getDB()->where('group_data_id', $id)->where('mer_id', $merId)->find();

        // 如果查询结果存在，则返回'value'字段的值，否则返回null
        return $data ? $data['value'] : null;
    }


    /**
     * 清除特定字段值对应的数据记录
     *
     * 本函数用于根据指定的字段值和该值对应的ID，从数据库中删除相应的记录。
     * 这是个通用函数，可以通过传入不同的字段名和ID值来删除不同表中的数据。
     *
     * @param mixed $id 需要删除的数据记录的ID值，可以是数字、字符串等
     * @param string $field 指定的字段名，用于查询和删除数据
     */
    public function clear($id,$field)
    {
        // 使用模型获取数据库实例，并构造删除语句，根据字段和ID删除数据
        $this->getModel()::getDB()->where($field, $id)->delete();
    }


}
