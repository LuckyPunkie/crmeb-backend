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


namespace app\common\dao\system;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\Cache;
use think\db\exception\DbException;

/**
 * Class CacheDao
 * @package app\common\dao\system
 * @author xaboy
 * @day 2020-04-24
 */
class CacheDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Cache::class;
    }

    /**
     * 根据键获取缓存结果
     *
     * 本函数旨在通过指定的键从缓存数据库中检索相应的结果。如果找到结果，它将尝试解析JSON格式的数据并返回解码后的数组；
     * 如果未找到结果，则返回null。使用此函数可以方便地获取和处理缓存中的数据，而无需直接与缓存存储交互。
     *
     * @param string $key 缓存中的键，用于唯一标识缓存的数据。
     * @return array|null 返回解码后的JSON数据作为数组，如果未找到则返回null。
     */
    public function getResult($key)
    {
        // 从缓存数据库中根据键获取结果值
        $val = Cache::getDB()->where('key', $key)->value('result');

        // 如果值存在则解码JSON数据并返回数组，否则返回null
        return $val ? json_decode($val, true) : null;
    }

    /**
     * 更新缓存中的数据。
     * 此方法用于根据给定的键更新缓存数据。特别是，如果数据数组中包含一个名为'result'的元素，
     * 它将被转换为JSON格式，以便在数据库中以更紧凑、可搜索的形式存储。
     *
     * @param string $key 缓存条目的键。用于唯一标识缓存数据。
     * @param array $data 包含要更新的数据的数组。如果数组中包含'result'键，其值将被转换为JSON格式。
     */
    public function keyUpdate(string $key, $data)
    {
        // 检查$data数组中是否包含'result'键
        // 如果存在，则将其值转换为JSON格式，使用JSON_UNESCAPED_UNICODE选项保留多字节字符
        if (isset($data['result']))
            $data['result'] = json_encode($data['result'], JSON_UNESCAPED_UNICODE);

        // 使用Cache的getDB方法获取数据库实例，并根据$key更新数据
        // 这里假设Cache::getDB()返回一个可用于执行数据库操作的对象
        Cache::getDB()->where('key', $key)->update($data);
    }


    /**
     * 根据一组键搜索缓存数据。
     *
     * 本函数旨在通过提供的键数组，从缓存数据库中检索对应的结果。它首先构造一个查询，以键为条件检索缓存，
     * 然后对检索到的数据进行处理，将其转换为PHP对象，最后返回处理后的数据数组。
     *
     * @param array $keys 键的数组，用于查找缓存数据。
     * @return array 返回一个键值对数组，其中每个键对应的值是从缓存中检索到的数据对象。
     */
    public function search(array $keys)
    {
        // 从缓存数据库中查询键对应的缓存结果，以键为索引。
        $cache = $this->getModel()::getDB()->whereIn('key',$keys)->column('result','key');
        $ret = [];

        // 遍历查询结果，将JSON格式的缓存数据转换为PHP对象。
        foreach ($cache as $k => $v) {
            $ret[$k] = json_decode($v);
        }
        return $ret;
    }



}
