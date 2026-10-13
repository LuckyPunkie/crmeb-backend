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


use app\common\dao\store\PriceRuleDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\RelevanceRepository;
use think\facade\Db;

/**
 * 价格说明
 */
class PriceRuleRepository extends BaseRepository
{
    public function __construct(PriceRuleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取规则列表
     *
     * 本函数用于根据给定的条件数组$where，获取规则列表。支持分页查询，每页返回$limit条记录，当前页码为$page。
     * 返回的结果包含规则列表和总记录数。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页返回的记录数
     * @return array 包含规则列表和总记录数的数组
     */
    public function lst(array $where, $page, $limit)
    {
        // 根据$where条件搜索规则，并按照'sort'降序和'rule_id'降序排序
        $query = $this->dao->search($where)->order('sort DESC, rule_id DESC');

        // 计算满足条件的总记录数
        $count = $query->count();

        // 获取当前页的规则列表，同时加载每个规则的分类信息
        $list = $query->page($page, $limit)->with(['cate'])->select();

        // 返回包含总记录数和规则列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建规则
     *
     * 本函数用于根据提供的数据创建新的规则，并为其关联指定的分类ID。在创建规则的过程中，会确保规则的默认状态
     * 根据提供的分类ID的数量来决定。如果分类ID的数量大于0，则规则设为非默认；否则，设为默认规则。
     *
     * @param array $data 包含规则信息的数据数组，其中'cate_id'字段被用于关联分类，但会被随后移除。
     * @return mixed 返回创建的规则对象。
     */
    public function createRule(array $data)
    {
        // 将$data['cate_id']转换为数组，用于后续处理
        $cateIds = (array)$data['cate_id'];
        // 移除$data中的'cate_id'字段，因为它不再需要
        unset($data['cate_id']);

        // 使用事务处理来确保数据库操作的一致性
        return Db::transaction(function () use ($cateIds, $data) {
            // 根据$cateIds的长度判断是否设置规则为默认规则
            $data['is_default'] = count($cateIds) ? 0 : 1;
            // 创建新的规则对象
            $rule = $this->dao->create($data);

            // 初始化用于批量插入关联数据的数组
            $inserts = [];
            // 遍历分类ID数组，为每个分类ID创建关联数据
            foreach ($cateIds as $id) {
                $inserts[] = [
                    'left_id' => $rule['rule_id'],
                    'right_id' => (int)$id,
                    'type' => RelevanceRepository::PRICE_RULE_CATEGORY
                ];
            }

            // 如果有需要插入的关联数据，则进行插入操作
            if (count($inserts)) {
                app()->make(RelevanceRepository::class)->insertAll($inserts);
            }

            // 返回创建的规则对象
            return $rule;
        });
    }

    /**
     * 更新规则信息并同步更新关联分类。
     *
     * 本函数主要用于处理规则的更新操作，包括规则本身信息的更新以及规则与分类关联关系的更新。
     * 在更新规则信息时，会检查是否设置了默认规则，并根据情况更新此字段。同时，会清除原有的分类关联关系，
     * 并根据新的分类ID列表重建关联关系。
     *
     * @param int $id 规则ID，用于定位需要更新的规则。
     * @param array $data 包含规则更新信息和分类ID的数据数组。
     * @return bool 更新操作是否成功的标志。
     */
    public function updateRule(int $id, array $data)
    {
        // 将数据中的分类ID提取为数组，后续处理规则的默认状态和关联关系
        $cateIds = (array)$data['cate_id'];
        // 从更新数据中移除 cate_id 字段，因为它将通过其他方式处理
        unset($data['cate_id']);
        // 设置更新时间
        $data['update_time'] = date('Y-m-d H:i:s');

        // 使用事务处理来确保数据的一致性
        return Db::transaction(function () use ($id, $cateIds, $data) {
            // 如果分类ID数量大于0，则设置规则为非默认；否则设置为默认
            $data['is_default'] = count($cateIds) ? 0 : 1;
            // 更新规则本身的信息
            $this->dao->update($id, $data);

            // 准备插入的新分类关联关系数据
            $inserts = [];
            foreach ($cateIds as $cid) {
                $inserts[] = [
                    'left_id' => $id,
                    'right_id' => (int)$cid,
                    'type' => RelevanceRepository::PRICE_RULE_CATEGORY
                ];
            }

            // 删除原有的规则与分类关联关系
            app()->make(RelevanceRepository::class)->query([
                'left_id' => $id,
                'type' => RelevanceRepository::PRICE_RULE_CATEGORY
            ])->delete();

            // 如果有新的分类ID需要关联，则插入新的关联关系
            if (count($inserts)) {
                app()->make(RelevanceRepository::class)->insertAll($inserts);
            }
        });
    }


}
