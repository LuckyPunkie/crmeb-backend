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


namespace app\common\dao\system\config;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\config\SystemConfigValue;
use think\db\exception\DbException;

/**
 * Class SystemConfigValueDao
 * @package app\common\dao\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class SystemConfigValueDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemConfigValue::class;
    }

    /**
     * 更新商家配置信息
     *
     * 该方法用于更新特定商家的配置项值。如果传入的数据中包含'value'键，
     * 其值将被转换为JSON格式存储。这允许灵活存储复杂数据结构，
     * 而不需要修改数据库结构。
     *
     * @param int $merId 商家ID，用于确定要更新哪个商家的配置
     * @param string $key 配置项的键，指定要更新的配置项
     * @param array $data 包含要更新的配置项数据的数组，如果'value'键存在，其值将被JSON编码
     * @return int 返回更新操作影响的行数，用于确认更新是否成功
     */
    public function merUpdate(int $merId, string $key, array $data)
    {
        // 检查$data数组中是否包含'value'键，如果存在，则将其值转换为JSON格式
        if (isset($data['value']) && is_array($data['value'])) {
            $data['value'] = json_encode($data['value']);
        }

        // 使用SystemConfigValue类的数据库访问对象，根据$merId和$key更新配置数据
        // 返回更新操作影响的行数
        return SystemConfigValue::getDB()->where('mer_id', $merId)->where('config_key', $key)->update($data);
    }

    /**
     * 根据配置键和商家ID获取配置值
     * 此方法用于从数据库中检索指定商家ID和配置键对应的配置值。
     * 它首先通过配置键和商家ID查询数据库，然后对查询结果进行处理，将JSON格式的配置值解析为数组。
     * 最后，返回一个关联数组，其中配置键作为键，对应的配置值作为值。
     *
     * @param array $keys 配置键的数组
     * @param int $merId 商家ID
     * @return array 解析后的配置值数组，键为配置键，值为配置值
     */
    public function fields(array $keys, int $merId)
    {
        // 使用whereIn和where查询满足条件的配置项，并通过withAttr对查询结果的'value'字段进行处理
        $result = SystemConfigValue::getDB()->whereIn('config_key', $keys)->where('mer_id', $merId)->withAttr('value', function ($val, $data) {
            return json_decode($val, true);
        })->column('value', 'config_key');

        // 遍历查询结果，对每个配置值进行JSON解码
        foreach ($result as $k => &$val) {
            $item = json_decode($val, true, 512, JSON_BIGINT_AS_STRING);
            if (json_last_error() == JSON_ERROR_NONE) {
                $val = $item;
            }
            if(!is_array($val)) {
                $val = (string)$val;
            }
            $result[$k] = $val;
        }

        // 返回处理后的配置值数组
        return $result;
    }

    /**
     * 根据键值数组和商家ID清除系统配置值
     *
     * 此方法用于删除指定商家ID下，与给定键值数组中任何键匹配的系统配置值。
     * 它通过查询数据库中config_key在给定键值数组内的记录，并且mer_id与指定商家ID匹配，
     * 然后删除这些记录来实现清理。
     *
     * @param array $keys 包含需要清除的系统配置键的数组
     * @param int $merId 商家ID，用于限定删除操作的作用范围
     * @return int 返回删除操作影响的记录数
     */
    public function clearBykey(array $keys,int $merId)
    {
        // 使用whereIn和where组合查询条件，然后执行删除操作
        return SystemConfigValue::getDB()->whereIn('config_key', $keys)->where('mer_id', $merId)->delete();
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

    /**
     * 根据键和商户ID获取系统配置的值
     *
     * 此方法用于查询系统配置表中特定键和商户ID对应的配置值。
     * 如果配置值存储为JSON格式，则会将其解码为PHP数组返回。
     * 如果不存在匹配的配置项，则返回NULL。
     *
     * @param string $key 配置的键名，用于查询特定的配置项。
     * @param int $merId 商户ID，用于查询特定商户的配置值。如果配置项是全局的，此参数可能无效或被忽略。
     * @return mixed 返回查询到的配置值，如果值不存在或查询失败，则返回NULL。配置值如果是JSON格式，则返回解码后的PHP数组。
     */
    public function value(string $key, int $merId)
    {
        // 通过键名和商户ID查询配置表中对应的配置值
        $value = SystemConfigValue::getDB()->where('config_key', $key)->where('mer_id', $merId)->value('value');

        // 检查查询结果是否为NULL，如果是，则返回NULL；否则，将JSON格式的配置值解码为PHP数组并返回
        if(is_null($value)) {
            return null;
        }

        $item = json_decode($value, true);
        if (json_last_error() == JSON_ERROR_NONE) {
            $value = $item;
        }
        if(!is_array($value)) {
            $value = (string)$value;
        }

        return $value;
    }

    /**
     * 检查特定商家是否存在指定配置键
     *
     * 本函数用于确定在数据库中是否存在特定商家ID关联的特定配置键。
     * 它通过查询配置表中config_key是否匹配给定的键，并且mer_id是否匹配给定的商家ID来实现。
     * 如果找到至少一条匹配的记录，则表示指定的配置键对于给定的商家ID是存在的，函数返回true；
     * 如果没有找到匹配的记录，则表示指定的配置键对于给定的商家ID不存在，函数返回false。
     *
     * @param string $key 配置键的名称
     * @param int $merId 商家的ID
     * @return bool 如果指定的配置键对于给定的商家ID存在则返回true，否则返回false
     */
    public function merExists(string $key, int $merId): bool
    {
        // 通过SystemConfigValue类的静态方法getDB获取数据库操作对象
        // 然后使用where方法指定查询条件，分别是config_key为$key和mer_id为$merId
        // 最后使用count方法统计满足条件的记录数，如果大于0则表示存在，返回true，否则返回false
        return SystemConfigValue::getDB()->where('config_key', $key)->where('mer_id', $merId)->count() > 0;
    }


}
