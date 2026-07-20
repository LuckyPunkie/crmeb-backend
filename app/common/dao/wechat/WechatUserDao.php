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


namespace app\common\dao\wechat;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\wechat\WechatUser;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class WechatUserDao
 * @package app\common\dao\wechat
 * @author xaboy
 * @day 2020-04-28
 */
class WechatUserDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return WechatUser::class;
    }

    /**
     * 根据微信用户OpenID获取用户信息。
     *
     * 本函数旨在通过微信用户OpenID查询数据库，以获取对应的用户信息。
     * 这对于需要通过微信登录或授权的系统来说是一个关键功能，它允许系统识别和验证微信用户。
     *
     * @param string $openId 微信用户的OpenID，用于唯一标识一个微信用户。
     * @return object 返回包含用户信息的对象，如果找不到用户，则返回空。
     */
    public function openIdByWechatUser(string $openId)
    {
        // 使用WechatUser类的getDB方法获取数据库实例，并根据OpenID查询用户信息。
        return WechatUser::getDB()->where('openid', $openId)->find();
    }

    /**
     * 根据UnionID获取微信用户信息。
     *
     * 本函数通过查询数据库，使用给定的UnionID作为标识，检索对应的微信用户信息。
     * UnionID是微信公众号平台用于标识用户的一个唯一标识，不同公众号或小程序下的同一个人，会拥有相同的UnionID。
     * 这个方法主要用于在多公众号或小程序的场景下，将用户信息进行统一管理。
     *
     * @param string $unionId 微信用户的UnionID，用于唯一标识一个用户。
     * @return object 返回查询结果，包含该UnionID对应的微信用户信息。如果未找到，则返回空。
     */
    public function unionIdByWechatUser(string $unionId)
    {
        // 使用WechatUser类的getDB方法获取数据库连接，并构造查询语句，查询unionid为$unionId的用户信息。
        return WechatUser::getDB()->where('unionid', $unionId)->find();
    }

    /**
     * 根据OpenID获取微信用户ID
     *
     * 本函数旨在通过微信用户的OpenID从数据库中检索对应的微信用户ID。这在与微信平台交互时非常有用，
     * 因为OpenID是微信用户在公众号中的唯一标识，通过这个标识可以关联到具体的用户。
     *
     * @param string $openId 微信用户的OpenID
     * @return int 微信用户的ID
     */
    public function openIdById(string $openId)
    {
        // 使用查询构建器查询数据库，根据OpenID获取wechat_user_id字段的值
        return WechatUser::getDB()->where('openid', $openId)->value('wechat_user_id');
    }

    /**
     * 根据微信用户的OpenID获取小程序用户的ID。
     *
     * 本函数旨在通过微信用户的小程序OpenID来查询并返回该用户在小程序中的ID。
     * 这对于需要对接微信小程序并根据用户的微信信息进行操作的场景非常有用。
     *
     * @param string $openId 微信用户的OpenID，这是用户在小程序中的唯一标识。
     * @return array 返回包含小程序用户ID等相关信息的数组。如果找不到对应用户，则返回空数组。
     */
    public function routineIdByWechatUser(string $openId)
    {
        // 使用WechatUser类的getDB方法获取数据库实例，并根据$openId查询用户信息。
        // 这里的where和find方法用于指定查询条件并执行查询操作。
        return WechatUser::getDB()->where('routine_openid', $openId)->find();
    }

    /**
     * 根据UnionID获取微信用户ID。
     *
     * 本函数旨在通过用户的UnionID，从数据库中检索并返回相应的微信用户ID。
     * UnionID是微信公众号平台为用户分配的唯一标识，不同公众号下同一用户的UnionID是一致的，这允许我们在多个公众号下识别同一用户。
     *
     * @param string $unionId 用户的UnionID。
     * @return string|false 返回微信用户ID，如果未找到则返回false。
     */
    public function unionIdById(string $unionId)
    {
        // 使用WechatUser模型的getDB方法获取数据库查询对象，然后通过where方法指定查询条件为'unionid'等于传入的$unionId，
        // 最后使用value方法仅获取'wechat_user_id'列的值。
        return WechatUser::getDB()->where('unionid', $unionId)->value('wechat_user_id');
    }

    /**
     * 根据用户ID获取微信用户的OpenID
     *
     * 本函数旨在通过内部用户ID，从微信用户表中检索对应的OpenID。OpenID是微信用户的一个唯一标识，
     * 用于在微信生态系统中识别用户。此函数返回查询结果中的OpenID字段值。
     *
     * @param int $id 用户ID，用于查询微信用户表。
     * @return string 查询到的微信用户的OpenID，如果未找到对应用户则返回空。
     */
    public function idByOpenId(int $id)
    {
        // 使用WechatUser类的getDB方法获取数据库实例，并根据wechat_user_id为$id$查询微信用户表中openid对应的值
        return WechatUser::getDB()->where('wechat_user_id', $id)->value('openid');
    }

    /**
     * 根据任务ID获取微信用户的routine_openid
     *
     * 本函数旨在通过任务ID（wechat_user_id）从数据库中检索特定微信用户的相关信息。
     * 具体来说，它查询并返回该用户的routine_openid，这是一个用于标识微信小程序用户的关键字段。
     *
     * @param int $id 任务ID，用于在数据库中定位特定的微信用户记录。
     * @return string 返回查询到的微信用户的routine_openid，如果未找到相关记录，则返回空。
     */
    public function idByRoutineId(int $id)
    {
        // 使用WechatUser类的getDB方法获取数据库对象，并通过where方法指定查询条件，最后使用value方法获取'routine_openid'字段的值
        return WechatUser::getDB()->where('wechat_user_id', $id)->value('routine_openid');
    }

    /**
     * 取消用户的订阅状态
     *
     * 本函数用于更新微信用户表中特定用户的订阅状态，将用户的订阅状态设置为0，即取消订阅。
     * 主要适用于微信公众号的用户管理，当用户选择取消订阅时，通过调用此函数来更新数据库中的用户状态。
     *
     * @param string $openId 用户的OpenID，是微信用户的一个唯一标识。
     * @return int 返回更新操作的影响行数，用于确认更新是否成功。
     */
    public function unsubscribe(string $openId)
    {
        // 通过OpenID查询微信用户表，并更新用户的订阅状态为0（取消订阅）
        return WechatUser::getDB()->where('openid', $openId)->update(['subscribe' => 0]);
    }

    /**
     * 检查微信用户是否订阅了公众号
     *
     * 本函数通过查询微信用户表，确定给定ID的用户是否订阅了公众号。
     * 它使用了多个条件来筛选用户：用户ID、OpenID不为空、订阅状态为1。
     * 如果满足这些条件的用户数量大于0，则认为该用户订阅了公众号。
     *
     * @param int $id 微信用户的ID
     * @return bool 如果用户已订阅公众号则返回true，否则返回false
     */
    public function isSubscribeWechatUser($id)
    {
        // 计算满足条件的用户数量，判断是否大于0
        return WechatUser::getDB()->where('wechat_user_id', $id)->whereNotNull('openid')->where('subscribe', 1)->count() > 0;
    }

    /**
     * 根据条件搜索微信用户信息。
     *
     * 该方法用于构建一个查询微信用户数据的条件，支持多种条件组合查询。查询结果根据微信用户ID降序排列。
     * 参数$where是一个数组，包含各种搜索条件。每个条件都是可选的，可以根据传入的条件动态构建SQL查询语句。
     *
     * @param array $where 搜索条件数组，包含各种可能的搜索条件如昵称、添加时间、标签ID列表、分组ID、性别和订阅状态。
     * @return \think\db\Query 返回构建好的查询对象，可以进一步操作如获取数据。
     */
    public function search(array $where)
    {
        // 初始化查询对象，从WechatUser类中获取数据库实例，并设置查询条件为openid和routine_openid不为空，按wechat_user_id降序排列
        $query = WechatUser::getDB()->whereNotNull('openid')->whereNotNull('routine_openid')->order('wechat_user_id desc');

        // 如果指定了昵称，则添加LIKE条件搜索昵称
        if (isset($where['nickname']) && $where['nickname']) $query->where('nickname', 'LIKE', "%$where[nickname]%");

        // 如果指定了添加时间，则处理并添加时间条件搜索
        if (isset($where['add_time']) && $where['add_time']) getModelTime($query, $where['add_time']);

        // 如果指定了标签ID列表，则遍历列表，为每个标签ID添加LIKE条件搜索
        if (isset($where['tagid_list']) && $where['tagid_list']) {
            $tagid_list = explode(',', $where['tagid_list']);
            foreach ($tagid_list as $v) {
                $query->where('tagid_list', 'LIKE', "%$v%");
            }
        }

        // 如果指定了分组ID，则添加等于条件搜索
        if (isset($where['groupid']) && $where['groupid']) $query->where('groupid', $where['groupid']);

        // 如果指定了性别，则添加等于条件搜索
        if (isset($where['sex']) && $where['sex']) $model = $query->where('sex', $where['sex']);

        // 如果指定了订阅状态，则添加等于条件搜索
        if (isset($where['subscribe']) && $where['subscribe']) $query->where('subscribe', $where['subscribe']);

        // 返回构建好的查询对象
        return $query;
    }
}
