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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\user\UserVisit;
use think\facade\Db;
use think\Model;

/**
 * Class UserVisitDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/5/27
 */
class UserVisitDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/27
     */
    protected function getModel(): string
    {
        return UserVisit::class;
    }

    /**
     * 添加访问记录
     *
     * 该方法用于创建并添加一个访问记录到数据存储中。它通过接收用户ID、访问类型、类型ID以及可选的内容描述，
     * 来综合形成一个访问记录，并将其存储。此方法的设计旨在简化访问记录的添加流程，通过一次调用即可完成。
     *
     * @param int $uid 用户ID。标识访问记录的用户。
     * @param string $type 访问类型。描述访问的具体类型，例如页面访问、资源下载等。
     * @param int $type_id 类型ID。与访问类型相关联的ID，用于更具体地标识访问的对象。
     * @param string $content 可选的内容描述。对访问记录的额外描述信息，可为空。
     * @return mixed 返回创建的访问记录的结果。具体类型取决于create方法的实现。
     */
    public function addVisit(int $uid, string $type, int $type_id, ?string $content = '')
    {
        // 使用compact函数收集参数并创建访问记录
        return $this->create(compact('uid', 'type', 'type_id', 'content'));
    }


    /**
     * 记录用户访问产品的行为
     *
     * 本函数用于在系统中记录某个用户访问特定产品的行为。通过调用内部的addVisit方法，
     * 实现对用户访问行为的统计和记录。这有助于分析用户兴趣和产品受欢迎程度，为后续的
     * 产品优化和推荐提供数据支持。
     *
     * @param int $uid 用户ID。标识访问产品的用户。
     * @param int $productId 产品ID。标识被访问的产品。
     * @return mixed 返回addVisit方法的执行结果。具体类型取决于addVisit方法的实现。
     */
    public function visitProduct(int $uid, int $productId)
    {
        // 调用addVisit方法记录用户的访问行为
        return $this->addVisit($uid, 'product', $productId);
    }


    /**
     * 访问页面的方法
     *
     * 本方法用于记录用户访问特定页面的行为。它通过调用addVisit方法来实现，
     * 将用户的ID、访问类型、无用的整数0（此处可能是为了固定参数格式）、以及被访问的页面URL作为参数。
     * 这样做是为了统计用户访问页面的次数，帮助分析用户行为和页面受欢迎程度。
     *
     * @param int $uid 用户ID。用于唯一标识访问页面的用户。
     * @param string $page 被访问的页面URL。用于标识用户访问的具体页面。
     * @return mixed 返回addVisit方法的执行结果。具体类型取决于addVisit方法的实现。
     */
    public function visitPage(int $uid, string $page)
    {
        // 调用addVisit方法来记录页面访问
        return $this->addVisit($uid, 'page', 0, $page);
    }

    /**
     * 访问小程序页面的方法
     *
     * 该方法用于记录用户访问小程序页面的行为。通过调用addVisit方法，将访问信息添加到数据库或其他记录介质中。
     * 参数$uid表示用户的唯一标识，$page表示被访问的页面名称或路径。此方法特别之处在于它指定访问类型为'smallProgram'，
     * 并且不考虑访问时长（设置为0），这符合小程序访问的特点，即通常不需要记录详细的访问时长。
     *
     * @param int $uid 用户的唯一标识符。用于识别访问行为的发起者。
     * @param string $page 被访问的小程序页面名称或路径。用于记录用户访问的具体内容。
     * @return mixed 返回addVisit方法的执行结果。具体类型取决于addVisit方法的实现。
     */
    public function visitSmallProgram(int $uid, string $page)
    {
        return $this->addVisit($uid, 'smallProgram', 0, $page);
    }

    /**
     * 搜索产品
     *
     * 本函数用于根据用户ID、关键字和商家ID进行产品搜索。
     * 它主要通过调用添加访问记录的函数来实现，同时记录用户的搜索行为。
     *
     * @param int $uid 用户ID，用于识别进行搜索的用户。
     * @param string $keyword 搜索关键字，用户输入的用于搜索产品的内容。
     * @param int $merId 商家ID，可选参数，默认为0。用于指定搜索范围，如果为0则表示全局搜索。
     * @return mixed 返回添加访问记录的结果，具体类型取决于addVisit函数的实现。
     */
    public function searchProduct(int $uid, string $keyword, int $merId = 0)
    {
        // 调用addVisit函数记录用户的搜索行为并返回结果
        return $this->addVisit($uid, 'searchProduct', $merId, $keyword);
    }

    /**
     * 搜索商家
     *
     * 本函数用于执行对商家的搜索操作。它通过用户的ID和搜索关键字来执行搜索，并记录用户的访问行为。
     * 搜索不涉及具体的商家数据检索逻辑，而是作为一个触发访问记录添加的操作。
     *
     * @param int $uid 用户ID，用于标识进行搜索操作的用户。
     * @param string $keyword 搜索关键字，用户搜索商家时输入的关键词。
     * @return mixed 返回添加访问记录的结果，具体类型取决于addVisit方法的实现。
     */
    public function searchMerchant(int $uid, string $keyword)
    {
        // 添加访问记录，记录用户ID，访问类型为'searchMerchant'，无特定访问ID，以及搜索关键字
        return $this->addVisit($uid, 'searchMerchant', 0, $keyword);
    }

    /**
     * 计算指定用户的商品访问总数
     *
     * 本函数通过查询用户访问记录表，统计指定用户对商品的访问次数。
     * 这里的“商品”是指类型为“product”的访问记录。
     *
     * @param int $uid 用户ID
     * @return int 用户访问商品的总次数
     */
    public function userTotalVisit($uid)
    {
        // 使用UserVisit类的静态方法getDB来获取数据库实例，并构造查询条件，统计指定用户uid且访问类型为product的记录总数
        return UserVisit::getDB()->where('uid', $uid)->where('type', 'product')->count();
    }



    /**
     * 用户访问搜索
     * @param array $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/18
     */
    public function search(array $where)
    {
        $query = UserVisit::hasWhere('user', function ($query) use ($where) {
            $query = $query->where(true);
            $query->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('User.nickname', "%{$where['nickname']}%");
            })->when(isset($where['user_type']) && $where['user_type'] !== '', function ($query) use ($where) {
                $query->where('User.user_type', $where['user_type']);
            })->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('User.phone', "%{$where['phone']}%");
            });
        });
        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('UserVisit.type_id', $region);
        }
        $query = $query->when(isset($where['uid'])  && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('UserVisit.uid', $where['uid']);
        })->when(isset($where['mer_id'])  && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('UserVisit.type_id', $where['mer_id']);
        })->when(isset($where['type'])  && $where['type'] !== '', function ($query) use ($where) {
            if(is_array($where['type'])){
                $query->where('UserVisit.type','in', $where['type']);
            }else{
                $query->where('UserVisit.type', $where['type']);
            }
        })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date'], 'UserVisit.create_time');
        })->when(isset($where['mom_date']) && $where['mom_date'] !== '', function ($query) use ($where) {
            momTime($query, $where['mom_date'], 'UserVisit.create_time');
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->whereLike('UserVisit.content', "%{$where['keyword']}%");
        });

        return $query->order('create_time DESC');
    }

    /**
     * 计算指定日期内，每个用户的访问次数。
     *
     * 本函数用于统计在给定日期内，每个用户的访问产品数量。
     * 如果提供了商家ID，则只统计该商家的产品访问情况。
     *
     * @param string $date 统计日期，格式为YYYY-MM-DD。
     * @param int|null $merId 商家ID，可选参数，用于过滤特定商家的数据。
     * @return int 返回访问用户数量。
     */
    public function dateVisitUserNum($date, $merId = null)
    {
        // 从UserVisit模型中获取数据库实例，并设置别名为A
        return UserVisit::getDB()->alias('A')
            // 加入StoreProduct表，以获取产品相关信息
            ->join('StoreProduct B', 'A.type_id = B.product_id')
            // 当$date提供时，根据$date条件查询
            ->when($date, function ($query, $date) {
                // 设置查询条件，根据$date筛选创建时间
                getModelTime($query, $date, 'A.create_time');
            })
            // 当$merId提供时，添加条件查询特定商家的产品
            ->when($merId, function ($query, $merId) {
                // 筛选商家ID为$merId的记录
                $query->where('B.mer_id', $merId);
            })
            // 筛选类型为产品的记录
            ->where('A.type', 'product')
            // 按用户ID分组，以统计每个用户的访问次数
            ->group('uid')
            // 统计记录总数，即访问的用户数量
            ->count();
    }


    /**
     * 获取指定日期范围内访问商家的数量
     *
     * 本函数通过查询用户访问记录，结合商家产品信息，统计在指定日期范围内每个商家被访问的次数。
     * 默认查询最近7天的数据，可自定义查询日期范围。返回结果按访问次数降序排列。
     *
     * @param string $date 查询日期范围，格式为'YYYY-MM-DD'。如果不提供，则查询最近7天的数据。
     * @param int $limit 返回结果的数量限制，默认为7。
     * @return array 返回一个包含每个商家访问次数的数组，每个元素包含商家ID（mer_id）、商家名称（mer_name）和总访问次数（total）。
     */
    public function dateVisitMerchantNum($date, $limit = 7)
    {
        // 初始化查询，设置别名为A，以便在后续的JOIN操作中引用
        return UserVisit::getDB()->alias('A')
            // 加入StoreProduct表，通过type_id与A表关联，别名为B
            ->join('StoreProduct B', 'A.type_id = B.product_id')
            // 加入Merchant表，通过mer_id与B表关联，别名为C
            ->join('Merchant C', 'C.mer_id = B.mer_id')
            // 定义要返回的字段，包括访问次数、商家ID和商家名称
            ->field(Db::raw('count(A.type) as total,B.mer_id,C.mer_name,C.care_ficti, C.care_count'))
            // 当传入$date时，根据$date调整查询的创建时间范围
            ->when($date, function ($query, $date) {
                getModelTime($query, $date, 'A.create_time');
            })
            // 限制查询的类型为'product'
            ->where('A.type', 'product')
            // 限制返回的结果数量
            ->limit($limit)
            // 按商家ID分组，统计每个商家的访问次数
            ->group('B.mer_id')
            // 按总访问次数降序排列
            ->order('total DESC')
            // 执行查询并返回结果
            ->select();
    }

    /**
     * 根据指定日期和商家ID，获取该商家产品访问量排名前$limit位的产品信息。
     *
     * 本函数通过查询用户访问记录和产品信息，统计指定日期内每个产品的访问量，并返回访问量排名前$limit的产品列表。
     * 这里的访问量是指用户点击产品的次数。
     *
     * @param string $date 查询的日期，格式为YYYY-MM-DD。如果未指定日期，则查询最近$limit天的数据。
     * @param int $merId 商家ID，用于限定查询商家下的产品。
     * @param int $limit 返回结果的数量限制，默认为7，即返回访问量排名前7的产品。
     * @return array 返回一个包含产品访问量排名前$limit的产品列表，每个元素包含产品访问量、产品图片、产品名称。
     */
    public function dateVisitProductNum($date, $merId, $limit = 7)
    {
        // 从用户访问记录表中查询数据，关联产品信息表和商家信息表
        return UserVisit::getDB()->alias('A')->join('StoreProduct B', 'A.type_id = B.product_id')
            ->join('Merchant C', 'C.mer_id = B.mer_id')
            // 选择查询的字段，包括产品访问量、产品图片和产品名称
            ->field(Db::raw('count(A.type_id) as total,B.image,B.store_name'))
            // 根据传入的日期条件进行查询，如果指定了日期，则只查询该日期的数据
            ->when($date, function ($query, $date) {
                getModelTime($query, $date, 'A.create_time');
            })
            // 限定只查询访问类型为产品的记录，并且产品所属商家为传入的商家ID
            ->where('A.type', 'product')->where('B.mer_id', $merId)
            ->where('B.is_del', 0)->where('B.status', '1')
            // 按产品ID分组，统计每个产品的访问量
            ->group('A.type_id')
            // 按产品访问量降序排序
            ->order('total DESC')
            // 限制返回的结果数量
            ->limit($limit)
            // 执行查询并返回结果
            ->select();
    }

    /**
     * 计算指定日期内访问商家的总用户数
     *
     * 本函数通过查询用户访问记录，统计在指定日期内所有访问类型为产品的用户的数量。
     * 这里的访问记录是指用户与商家交互的行为记录，类型为产品表示用户访问了商家的产品。
     *
     * @param string $date 需要查询的日期，格式为YYYY-MM-DD
     * @return int 指定日期内访问商家的总用户数
     */
    public function dateVisitMerchantTotal($date)
    {
        // 使用UserVisit类中的getDB方法获取数据库连接
        // 当$date有值时，执行闭包函数进行查询条件的添加
        return UserVisit::getDB()->when($date, function ($query, $date) {
            // 添加查询条件，根据$date查询创建时间在该日期内的记录
            getModelTime($query, $date, 'create_time');
        })->whereIn('type', 'product')->count();
        // 筛选类型为产品的记录，然后统计满足条件的记录总数
    }


    /**
     * 计算指定日期的访问数量。
     *
     * 本函数用于统计在给定日期内，用户访问页面和小程序的总次数。
     * 它通过查询UserVisit模型的数据库记录，筛选出类型为'page'或'smallProgram'的访问记录，
     * 并统计这些记录的数量。
     *
     * @param string $date 需要统计访问数量的日期。
     * @return int 指定日期的访问数量。
     */
    public function dateVisitNum($date)
    {
        // 使用when方法条件性地加入日期查询条件，如果$date不为空，则根据$date查询创建时间
        return UserVisit::getDB()->when($date, function ($query, $date) {
            // 这里将查询条件应用到创建时间字段，用于筛选指定日期的记录
            getModelTime($query, $date, 'create_time');
        })->whereIn('type', ['page', 'smallProgram'])->count();
        // 筛选类型为'page'或'smallProgram'的记录，统计它们的数量
    }


    /**
     * 根据指定日期分组统计每日访问用户数。
     *
     * 本函数用于查询在指定日期范围内的每日独立访问用户数。
     * 通过分组查询，将创建时间转换为每天的格式，并统计每天的独立访问用户数。
     * 如果指定了日期，则查询该日期的访问数据；如果没有指定日期，则查询所有数据。
     *
     * @param string $date 指定的日期，格式为YYYY-MM-DD。如果为空，则查询所有日期的数据。
     * @return array 返回一个数组，每个元素包含每天的日期和独立访问用户数。
     */
    public function dateVisitNumGroup($date)
    {
        // 使用UserVisit模型的getDB方法获取数据库对象
        return UserVisit::getDB()->when($date, function ($query, $date) {
            // 如果指定了日期，则在查询中添加时间范围条件
            getModelTime($query, $date, 'create_time');
        })->field(Db::raw('from_unixtime(unix_timestamp(create_time),\'%m-%d\') as time, count(DISTINCT uid) as total'))
            // 按日期分组
            ->group('time')
            // 按日期升序排序
            ->order('time ASC')->select();
    }

    /**
     * 批量删除用户访问记录。
     *
     * 本函数提供两种方式批量删除用户访问记录：一种是根据访问ID数组进行删除，另一种是根据用户ID删除指定类型的访问记录。
     * - 当提供$ids参数时，会删除指定访问ID的记录。
     * - 当提供$uid参数时，会删除指定用户ID的所有产品访问记录。
     *
     * @param array|null $ids 访问ID数组，用于指定删除哪些访问记录。
     * @param int|null $uid 用户ID，用于指定删除属于哪个用户的访问记录。
     * @return int 删除的记录数。
     */
    public function batchDelete(? array $ids,?int  $uid)
    {
        // 如果提供了访问ID数组，则根据这些ID删除对应的访问记录。
        if($ids) return UserVisit::getDB()->where($this->getPk(),'in',$ids)->delete();

        // 如果提供了用户ID但没有提供访问ID数组，则删除该用户的所有产品访问记录。
        if($uid) return UserVisit::getDB()->where('uid',$uid)->where('type','product')->delete();
    }
    /**
     * 获取搜索日志
     *
     * @param array $where
     * @return void
     */
    public function clearSearchLog(array $where)
    {
        $query = $this->getModel()::when(isset($where['mer_id'])  && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('type_id', $where['mer_id']);
        })->when(isset($where['type'])  && $where['type'] !== '', function ($query) use ($where) {
            if(is_array($where['type'])){
                $query->where('type','in', $where['type']);
            }else{
                $query->where('type', $where['type']);
            }
        });

        return $query->delete();
    }
}
