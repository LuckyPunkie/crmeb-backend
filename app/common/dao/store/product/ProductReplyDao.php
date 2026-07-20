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


namespace app\common\dao\store\product;


use app\common\dao\BaseDao;
use app\common\model\store\product\ProductReply;
use crmeb\jobs\UpdateProductReplyJob;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\facade\Db;
use think\facade\Queue;

/**
 * Class ProductReplyDao
 * @package app\common\dao\store\product
 * @author xaboy
 * @day 2020/5/30
 */
class ProductReplyDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/30
     */
    protected function getModel(): string
    {
        return ProductReply::class;
    }

    /**
     * 根据条件搜索产品回复信息。
     *
     * 该方法通过接收一个包含搜索条件的数组，动态地构建查询语句，以检索产品回复数据。
     * 搜索条件可以包括商家ID、回复状态、虚拟状态、昵称、产品ID、产品类型和删除状态等。
     * 每个条件都是可选的，只有当条件存在且不为空时，才会被添加到查询语句中。
     *
     * @param array $where 包含搜索条件的数组。
     * @return BaseQuery|\think\db\Query
     */
    public function search(array $where)
    {
        // 获取数据库查询对象
        return ProductReply::getDB()->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 如果指定了商家ID，则添加到查询条件中
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['is_reply']) && $where['is_reply'] !== '', function ($query) use ($where) {
            // 如果指定了回复状态，则添加到查询条件中
            $query->where('is_reply', $where['is_reply']);
        })->when(isset($where['is_virtual']) && $where['is_virtual'] !== '', function ($query) use ($where) {
            // 如果指定了虚拟状态，则添加到查询条件中
            $query->where('is_virtual', $where['is_virtual']);
        })->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
            // 如果指定了昵称，则添加到查询条件中，使用LIKE进行模糊搜索
            $query->whereLike('nickname', "%{$where['nickname']}%");
        })->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
            // 如果指定了产品ID，则添加到查询条件中
            $query->where('product_id', $where['product_id']);
        })->when(isset($where['product_type']) && $where['product_type'] !== '', function ($query) use ($where) {
            // 如果指定了产品类型，则添加到查询条件中
            $query->where('product_type', 'product_type');
        })->when(isset($where['is_del']) && $where['is_del'] !== '', function ($query) use ($where) {
            // 如果指定了删除状态，则添加到查询条件中
            $query->where('is_del', $where['is_del']);
        })->order('sort DESC'); // 按排序降序排列
    }

    /**
     * 根据条件搜索产品评价信息并进行关联查询。
     * 该方法通过构建一个复杂的SQL查询来实现，它根据传入的条件来过滤产品评价，并且联合查询产品及相关信息。
     *
     * @param array $where 查询条件数组，包含各种过滤参数如是否已回复、昵称、关键词等。
     * @return \think\db\Query 返回一个查询对象，该对象可用于进一步的查询操作或获取数据。
     */
    public function searchJoinQuery(array $where)
    {
        // 从产品评价表中获取数据，表别名为A
        return ProductReply::getDB()->alias('A')
            // 加入产品表（表别名为B），根据产品ID进行关联
            ->join('StoreProduct B', 'A.product_id = B.product_id')
            // 如果条件中指定了是否已回复，则在查询中加入该条件
            ->when(isset($where['is_reply']) && $where['is_reply'] !== '', function ($query) use ($where) {
                $query->where('A.is_reply', $where['is_reply']);
            })
            // 如果条件中指定了昵称，则在查询中加入昵称模糊搜索条件
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('A.nickname', "%{$where['nickname']}%");
            })
            // 如果条件中指定了关键词，则在查询中加入对产品名称或产品ID的搜索条件
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->where(function ($query) use ($where) {
                    $query->where('B.store_name', 'like', "%{$where['keyword']}%")
                        ->whereOr('B.product_id', $where['keyword']);
                });
            })
            // 如果条件中指定了日期范围，则在查询中加入创建时间的条件
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'A.create_time');
            })
            // 如果条件中指定了商家ID，则在查询中加入商家ID的条件
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('A.mer_id', $where['mer_id']);
            })
            // 如果条件中指定了产品ID，则在查询中加入产品ID的条件
            ->when(isset($where['product_id']) && $where['product_id'] !== '', function ($query) use ($where) {
                $query->where('A.product_id', $where['product_id']);
            })
            // 按照评价排序和创建时间倒序排列
            ->order('A.sort DESC, A.create_time DESC')
            // 仅查询未被删除的评价
            ->where('A.is_del', 0)
            // 指定返回的字段
            ->field('A.reply_id,A.is_reply,A.uid,A.product_score,A.service_score,A.postage_score,A.comment,A.pics,A.create_time,A.merchant_reply_content,A.nickname,A.avatar,B.store_name,B.image,B.product_id,A.sort');
    }

    /**
     * 批量插入数据到产品回复表中。
     *
     * 本方法用于一次性插入多条数据到产品回复表中，提高了数据插入的效率。
     * 它通过调用ProductReply类中的getDB方法来获取数据库连接对象，然后进一步调用insertAll方法进行数据插入。
     *
     * @param array $data 包含多条产品回复数据的数组，每条数据是一个关联数组，其中键是字段名，值是字段值。
     * @return bool 插入操作的执行结果，成功返回true，失败返回false。
     */
    public function insertAll(array $data)
    {
        // 调用ProductReply类的静态方法getDB来获取数据库连接对象，并进一步调用insertAll方法插入数据
        return ProductReply::getDB()->insertAll($data);
    }

    /**
     * 检查指定ID的产品回复是否存在且未被删除。
     *
     * 本函数通过查询数据库来确定给定ID的产品回复是否存在，同时确保该记录没有被标记为删除。
     * 存在并未被删除的条件是通过查询满足ID匹配和is_del字段为0（未删除）的记录数来判断的。
     * 如果存在至少一条满足条件的记录，则认为该产品回复存在。
     *
     * @param int $id 需要检查的产品回复的唯一标识ID。
     * @return bool 如果产品回复存在且未被删除，则返回true；否则返回false。
     * @throws DbException
     */
    public function exists(int $id)
    {
        // 构建查询条件，查询满足ID和未删除条件的记录数
        return ProductReply::getDB()->where($this->getPk(), $id)->where('is_del', 0)->count() > 0;
    }

    /**
     * 检查指定商户是否存在指定ID的商品回复
     *
     * 本函数通过查询数据库来确定是否存在某个特定商户（通过$merId指定）发布的商品回复（通过$id指定）
     * 并且该回复未被删除（is_del字段为0）。
     *
     * @param string $merId 商户ID，用于指定查询的商户范围
     * @param int $id 商品回复的ID，用于指定查询的具体回复
     * @return bool 如果存在符合条件的商品回复，则返回true，否则返回false
     * @throws DbException
     */
    public function merExists($merId, int $id)
    {
        // 使用数据库查询工具，根据指定条件查询商品回复记录的数量
        // 条件包括：主键ID为$id，未被删除（is_del为0），以及商户ID为$merId
        return ProductReply::getDB()->where($this->getPk(), $id)->where('is_del', 0)->where('mer_id', $merId)->count() > 0;
    }

    /**
     * 删除产品回复
     *
     * 本函数用于标记一个产品回复为已删除，并将该信息推送到队列中以更新相关产品的回复数量。
     * 删除操作是通过修改数据库中对应记录的is_del字段来实现的，而不是物理删除记录。
     * 使用队列来异步处理产品回复数量的更新，可以提高系统的响应速度和处理能力。
     *
     * @param int $id 回复的唯一标识符，用于在数据库中定位到具体的回复记录。
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function delete(int $id)
    {
        // 根据$id查询数据库中的回复记录
        $reply = ProductReply::getDB()->where('reply_id', $id)->find();
        // 将查询到的回复记录的is_del字段设置为1，标记为已删除
        $reply->is_del = 1;
        // 保存修改，更新数据库中的记录
        $reply->save();
        // 将回复所属产品的ID推送到队列中，用于后续的异步更新产品回复数量
        Queue::push(UpdateProductReplyJob::class, $reply['product_id']);
    }

    /**
     * 返回评论数
     * @Author:Qinii
     * @Date: 2020/6/2
     * @param int $productId
     * @param array $where
     * @return mixed
     */
    public function getProductReplay(int $productId, $where = [0, 5])
    {
        return $this->getModel()::getDB()->where('product_id', $productId)->whereBetween('rate', $where)->select();
    }

    /**
     * 计算产品的总评分率
     *
     * 本函数旨在查询指定产品的所有有效评价的评分总和以及评价总数，
     * 通过这些数据可以计算出产品的总评分率，用于统计和展示产品评分情况。
     *
     * @param int $productId 产品ID
     * @return array 返回包含总评分和总评价数的数组
     * @throws DbException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function productTotalRate($productId)
    {
        // 使用ProductReply模型的数据库查询方法，指定查询条件为产品ID和未被删除的评价
        // 然后计算评分总和并统计评价数量，最后返回查询结果
        return ProductReply::getDB()->where('product_id', $productId)->where('is_del', 0)->field('sum(rate) as total_rate,count(reply_id) as total_count')->find();
    }

    /**
     * 计算商铺平均分
     * @param $merId
     * @return mixed
     * @author Qinii
     * @day 2020-06-11
     */
    public function merchantTotalRate($merId)
    {
        return ($this->getModel()::getDB())->where('mer_id', $merId)->field('avg(product_score) product_score ,avg(service_score) service_score,avg(postage_score) postage_score')->find()->toArray();
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

