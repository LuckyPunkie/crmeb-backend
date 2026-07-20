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


use app\common\dao\user\UserSignDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\system\groupData\GroupRepository;
use think\exception\ValidateException;
use think\facade\Db;


class UserSignRepository extends BaseRepository
{
    /**
     * @var UserSignDao
     */
    protected $dao;

    /**
     * UserSignRepository constructor.
     * @param UserSignDao $dao
     */
    public function __construct(UserSignDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取指定日期 用户的连续签到数
     * @param int $uid
     * @param string $day
     * @return array
     * @author Qinii
     * @day 6/8/21
     */
    public function getSign(int $uid,string $day)
    {
        return $this->dao->getSearch(['uid' => $uid,'day' => $day])->value('sign_num');
    }

    /**
     * 根据传入的天数获取签到配置中对应天的奖励信息。
     *
     * 此方法用于根据用户签到的天数，返回对应天数的签到奖励配置。如果传入的天数大于7，
     * 则计算该天数对应的一周内的某一天（循环），如果小于等于7，则直接返回对应天数的配置。
     * 如果配置不存在，则返回默认的无签到奖励配置。
     *
     * @param int $num 签到的天数，从1开始计数。
     * @return array 返回包含签到天数和积分奖励的数组。
     * @throws ValidateException 如果签到配置未开启，则抛出异常。
     */
    public function getDay(int $num)
    {
        // 当签到天数大于7时，计算该天数在一周内对应的实际天数
        if($num > 7) {
            $yu = ($num % 7);
            $num = ($yu == 0) ? 6 : $yu - 1;
        } else {
            // 当签到天数小于等于7时，直接使用传入的天数，如果天数为0，则调整为0
            $num = (($num -1) < 0) ? 0 : ($num -1);
        }

        // 加载签到配置
        $title = $this->signConfig();
        // 如果签到配置为空，则抛出异常
        if(empty($title)) throw new ValidateException('未开启签到功能');

        // 尝试获取传入天数对应的签到奖励配置
        if (isset($title[$num]['value'])) {
            $dat = $title[$num]['value'];
        } else {
            // 如果不存在对应的签到奖励配置，则返回默认的无签到奖励配置
            $dat = [
                'sign_day' => '无',
                'sign_integral' => 0,
            ];
        }
        return $dat;
    }

    /**
     * 签到操作
     * @param int $uid
     * @author Qinii
     * @day 6/8/21
     */
    public function create(int $uid)
    {
        /**
         *  用户昨天的签到情况,如果有就是连续签到，如果没有就是第一天签到
         *  根据签到天数计算签到积分等操作
         *  计算用户剩余积分
         *
         */
        $yesterday = date("Y-m-d",strtotime("-1 day"));
        $sign_num = ($this->getSign($uid,$yesterday) ?: 0) + 1;
        //签到规则计算
        $sign_task = $this->getDay($sign_num);
        $user = app()->make(UserRepository::class)->get($uid);
        $integral = $sign_task['sign_integral'];
        //if ($user->is_svip > 0) {
        //    $makeInteres = app()->make(MemberinterestsRepository::class);
        //    $integral = $integral * $makeInteres->getSvipInterestVal($makeInteres::HAS_TYPE_SIGN);;
        //}
        $user_make = app()->make(UserRepository::class);
        $user = $user_make->get($uid);
        $integral_ = $user['integral'] + $integral;
        $data = [
            'uid'      => $uid,
            'sign_num' => $sign_num,
            'number'   => $integral,
            'integral' => $integral_,
            'title'    =>   '签到',
        ];
        //增加记录
        $arr = [
            'status' => 1,
            'mark'   => '签到,获得积分'. $integral,
            'number' => $integral,
            'balance'=> $integral_,
        ];
        return Db::transaction(function() use($uid,$data,$user_make,$sign_task,$arr,$integral){
           $ret = $this->dao->create($data);
            $user_make->incIntegral($uid,$integral,'签到'.$sign_task['sign_day'],'sign_integral',$arr);
            app()->make(UserBrokerageRepository::class)->incMemberValue($uid, 'member_sign_num', $ret->sign_id);
            return compact('integral');
        });
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件数组、页码和每页的记录数，从数据库中检索并返回列表数据。
     * 这个方法主要用于处理数据的分页查询，支持根据条件进行筛选。
     *
     * @param array $where 查询条件数组，用于构建SQL查询的WHERE子句。
     * @param int $page 当前页码，用于计算查询的起始记录。
     * @param int $limit 每页的记录数，用于限制查询返回的记录数量。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示总记录数，'list' 表示当前页的记录列表。
     */
    public function getList(array $where,int $page,int $limit)
    {
        // 构建查询条件，根据创建时间降序排序
        $query = $this->dao->getSearch($where)->order('create_time DESC');

        // 计算满足条件的总记录数
        $count = $query->count();

        // 根据当前页码和每页的记录数，从查询结果中获取当前页的记录列表
        $list = $query->page($page,$limit)->select();

        // 将总记录数和当前页的记录列表作为一个数组返回
        return compact('count','list');
    }

    /**
     *  连续签到日期展示 1 - 7天
     *  是否签到
     *  累计签到数
     */
    public function info(int $uid = 0)
    {
        $is_sign = false;
        $sign_num = 0;
        $count = 0;
        $userInfo = [];
        if($uid) {
            $ret = $this->signStatus($uid);
            $is_sign = $ret['is_sign'];
            $sign_num = $ret['sign_num'];
            $userInfo = app()->make(UserRepository::class)->getWhere(['uid' => $uid],'uid,avatar,nickname,integral');
            $count = $this->dao->getSearch(['uid' => $uid])->count('*');
        }
        $title = $this->signConfig();
        return compact('userInfo','is_sign','sign_num','count','title');

    }

    /**
     * 获取签名配置
     *
     * 本函数用于获取签到配置的相关信息，特别是针对一周内每一天的签到标题。
     * 它首先通过依赖注入的方式创建了一个GroupRepository的实例，用于后续获取签到天数的配置。
     * 接着，利用GroupDataRepository来查询具体的签到标题数据，这些数据是根据签到天数配置来获取的，并且只包含状态为1（有效）的数据。
     * 最后，将查询结果转换为数组并返回，这个结果包含了签到标题在内的相关信息，而一些敏感或不必要的信息（如ID、创建时间等）被隐藏。
     *
     * @return array 返回包含签到配置信息的数组，特别是每一天的签到标题。
     */
    public function signConfig(){
        // 通过依赖注入创建GroupRepository实例，用于查询签到天数配置
        $group_make = app()->make(GroupRepository::class);
        // 根据ID获取签到天数配置
        $sign_day_config = $group_make->keyById('sign_day_config');

        // 通过依赖注入创建GroupDataRepository实例，用于查询签到标题数据
        $title = app()->make(GroupDataRepository::class)
            // 根据签到天数配置查询签到标题数据，并限制查询结果为最近7天
            ->getGroupDataWhere(0,$sign_day_config)
            // 筛选出状态为有效的数据
            ->where('status',1)
            // 限制查询结果数量为7条
            ->limit(7)
            // 隐藏某些字段
            ->hidden(['group_data_id','group_id','create_time','mer_id'])
            // 执行查询并转换为数组形式返回
            ->select()->toArray();

        // 返回查询结果
        return $title;
    }

    /**
     * 连续签到 获取 1- 7 天
     * @param $uid
     * @return array
     * @author Qinii
     * @day 6/10/21
     */
    public function signStatus($uid)
    {
        $day = date('Y-m-d',time());
        $sign_num = 0;
        $sign_num = $this->getSign($uid,$day);
        $is_sign = $sign_num ? 1 : 0;

        if($sign_num > 7){
            $sign_num = ($sign_num % 7);
            if(!$sign_num) $sign_num = 7;
        }

        if(!$is_sign){
            $yesterday = date("Y-m-d",strtotime("-1 day"));
            $sign_num = $this->getSign($uid,$yesterday) ?: 0;
            if($sign_num > 7){
                $sign_num = ($sign_num % 7);
            }
        }
        return compact('is_sign','sign_num');
    }

    /**
     * 按月显示签到记录
     * @param array $where
     * @return array
     * @author Qinii
     * @day 6/10/21
     */
    public function month(array $where)
    {
        $group = $this->dao->getSearch($where)->field('FROM_UNIXTIME(unix_timestamp(create_time),"%Y-%m") as time')
            ->order('time DESC')->group('time')->select();
        $ret = [];
        foreach ($group as $k => $item){
            $ret[$k]['month'] = $item['time'];
            $query = $this->dao->getSearch($where)->field('title,number,create_time')->whereMonth('create_time',$item['time']);
            $ret[$k]['list'] = $query->order('create_time DESC')->select();
        }
        return $ret;
    }
}
