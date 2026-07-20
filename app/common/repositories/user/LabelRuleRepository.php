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


namespace app\common\repositories\user;


use app\common\dao\user\LabelRuleDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\facade\Db;

/**
 * Class LabelRuleRepository
 * @package app\common\repositories\user
 * @author xaboy
 * @day 2020/10/20
 * @mixin LabelRuleDao
 */
class LabelRuleRepository extends BaseRepository
{
    /**
     * LabelRuleRepository constructor.
     * @param LabelRuleDao $dao
     */
    public function __construct(LabelRuleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组 $where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由 $limit 指定，查询结果将包含数据总数和当前页的数据列表。
     *
     * @param array $where 查询条件数组，用于指定数据库查询的条件。
     * @param int $page 当前页码，用于指定要返回的页码。
     * @param int $limit 每页的数据数量，用于指定每页返回的数据条数。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 为数据总数，'list' 为数据列表。
     */
    public function getList(array $where, $page, $limit)
    {
        // 根据条件查询数据，$where 为查询条件数组
        $query = $this->dao->search($where);

        // 统计满足条件的数据总数
        $count = $query->count();

        // 查询满足条件的数据列表，带有 'label' 关联数据，按 'label_rule_id' 倒序排列
        // 分页查询，返回当前页码的 $limit 条数据，并将结果转换为数组形式
        $list = $query->with(['label'])->order('label_rule_id DESC')->page($page, $limit)->select()->toArray();

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     * 创建用户标签并关联数据
     *
     * 本函数通过事务处理的方式，先创建用户标签，然后将标签ID关联到指定的数据中。
     * 这样做的目的是为了确保标签创建和关联操作的原子性，即要么两个操作都成功，要么都失败。
     *
     * @param array $data 包含标签名称和其他必要数据的信息数组。
     * @return bool|int 返回新创建数据的ID或者false，如果操作失败。
     */
    public function create($data)
    {
        // 使用数据库事务处理来确保操作的原子性
        return Db::transaction(function () use ($data) {
            // 提取标签名称，准备创建用户标签
            $labelName = $data['label_name'];
            // 从$data中移除'label_name'，因为它将用于创建标签，而不是数据本身
            unset($data['label_name']);

            // 创建用户标签，并指定标签的类型为1
            $label = app()->make(UserLabelRepository::class)->create([
                'label_name' => $labelName,
                'mer_id' => $data['mer_id'],
                'type' => 1
            ]);

            // 将新创建的标签ID赋值给$data，准备插入到关联数据表中
            $data['label_id'] = $label->label_id;

            // 使用DAO模式创建关联数据，并返回创建结果
            return $this->dao->create($data);
        });
    }

    /**
     * 更新规则信息。
     * 该方法通过使用事务来确保更新操作的原子性，它首先根据$id$获取规则信息，然后更新规则的某些字段。
     * 特别地，它将'label_name'字段从$data$中提取出来，用于单独更新标签名称，这是因为标签名称的更新涉及到另一个表的操作。
     *
     * @param int $id 规则的唯一标识符。
     * @param array $data 包含需要更新的规则字段的数据数组。
     * @return bool 更新操作的结果，true表示成功，false表示失败。
     */
    public function update($id, $data)
    {
        // 根据$id$获取规则信息
        $rule = $this->dao->get($id);

        // 使用事务来确保数据更新的一致性
        return Db::transaction(function () use ($data, $rule) {
            // 从$data$中提取标签名称，因为标签名称的更新需要单独处理
            $labelName = $data['label_name'];
            // 从$data$中移除不需要的字段，这些字段不是规则更新的一部分
            unset($data['mer_id'], $data['label_name']);
            // 更新标签名称，这涉及到另一个实体的更新
            app()->make(UserLabelRepository::class)->update($rule->label_id, ['label_name' => $labelName]);
            // 更新规则本身的信息
            return $rule->save($data);
        });
    }

    /**
     * 删除规则及其关联的用户标签。
     *
     * 本函数通过事务处理方式，确保删除规则的同时，关联的用户标签也能被正确删除。
     * 这样做是为了维护数据的一致性和完整性，避免因单个操作失败导致的数据不一致问题。
     *
     * @param int $id 规则的ID，用于查找并删除特定的规则。
     * @return bool 返回删除操作的结果，true表示删除成功，false表示删除失败。
     */
    public function delete($id)
    {
        // 根据$id获取规则信息，为后续的删除操作做准备。
        $rule = $this->dao->get($id);

        // 使用事务处理来确保多个数据库操作的原子性。
        return Db::transaction(function () use ($rule) {
            // 删除与规则关联的用户标签。
            app()->make(UserLabelRepository::class)->delete($rule->label_id);
            // 从用户中移除与规则关联的标签。
            app()->make(UserRepository::class)->rmLabel($rule->label_id);
            // 删除规则本身。
            return $rule->delete();
        });
    }

    /**
     * 同步用户数量。
     * 根据给定的规则ID，更新规则的用户数量和标签，并根据规则的类型（订单金额或订单数量）来筛选用户。
     * 使用事务来确保数据库操作的一致性。
     *
     * @param int $id 规则ID
     */
    public function syncUserNum($id)
    {
        // 获取规则信息
        $rule = $this->dao->get($id);
        // 更新规则的更新时间
        $rule->update_time = date('Y-m-d H:i:s');

        // 初始化用户ID数组
        $ids = [];
        // 创建用户商家仓库实例
        $userMerchantRepository = app()->make(UserMerchantRepository::class);

        // 根据规则的类型，获取符合条件的用户ID
        // 订单金额
        if ($rule->type == 1) {
            $ids = $userMerchantRepository->priceUserIds($rule->mer_id, $rule->min, $rule->max);
        // 订单数
        } else if ($rule->type == 0) {
            $ids = $userMerchantRepository->numUserIds($rule->mer_id, $rule->min, $rule->max);
        }

        // 更新规则的用户数量
        $rule->user_num = count($ids);

        // 将用户ID分批处理，每批50个
        $idList = array_chunk($ids, 50);

        // 使用事务来执行数据库操作
        Db::transaction(function () use ($rule, $idList, $userMerchantRepository) {
            // 删除旧的标签
            $userMerchantRepository->rmLabel($rule->label_id);

            // 遍历用户ID分批，更新用户标签
            foreach ($idList as $ids) {
                $userMerchantRepository->search(['uids' => $ids])->update([
                    'A.label_id' => Db::raw('trim(BOTH \',\' FROM CONCAT(IFNULL(A.label_id,\'\'),\',' . $rule->label_id . '\'))')
                ]);
            }

            // 保存更新后的规则信息
            $rule->save();
        });
    }

}
