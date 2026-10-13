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

namespace app\common\repositories\community;

use app\common\dao\community\CommunityDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\store\product\SpuRepository;
use app\common\repositories\system\RelevanceRepository;
use app\common\repositories\user\UserBrokerageRepository;
use app\common\repositories\user\UserRepository;
use crmeb\services\QrcodeService;
use FormBuilder\Factory\Elm;
use app\common\repositories\user\UserBillRepository;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Route;

/**
 * 社区图文
 */
class CommunityRepository extends BaseRepository
{
    /**
     * @var CommunityDao
     */
    protected $dao;

    const IS_SHOW_WHERE = [
        'is_show' => 1,
        'status' => 1,
        'is_del' => 0,
    ];

    public const COMMUNIT_TYPE_FONT = '1';
    public const COMMUNIT_TYPE_VIDEO = '2';

    /**
     * CommunityRepository constructor.
     * @param CommunityDao $dao
     */
    public function __construct(CommunityDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 后台列表头部统计
     * @param array $where
     * @return array
     * @author Qinii
     */
    public function title(array $where)
    {
        $where['is_type'] = self::COMMUNIT_TYPE_FONT;
        $list[] = [
            'count' => $this->dao->search($where)->count(),
            'title' => '图文列表',
            'type' => self::COMMUNIT_TYPE_FONT,
        ];
        $where['is_type'] = self::COMMUNIT_TYPE_VIDEO;
        $list[] = [
            'count' => $this->dao->search($where)->count(),
            'title' => '短视频列表',
            'type' => self::COMMUNIT_TYPE_VIDEO,
        ];
        return $list;
    }

    /**
     * 获取列表数据
     * 根据给定的条件、分页和限制获取数据列表，包括作者、主题和分类信息。
     *
     * @param array $where 查询条件
     * @param int $page 当前页码
     * @param int $limit 每页数据数量
     * @return array 返回包含总数和列表数据的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件查询数据，并包含关联信息：作者、主题和分类
        $query = $this->dao->search($where)->with([
            'author' => function ($query) {
                // 选择作者的相关字段，包括uid, real_name, status, avatar, nickname, count_start
                $query->field('uid,real_name,status,avatar,nickname,count_start');
            },
            'topic' => function ($query) {
                // 筛选主题的状态为1，且未删除的数据，选择特定字段
                $query->where('status', 1)->where('is_del', 0);
                $query->field('topic_id,topic_name,status,category_id,pic,is_del');
            },
            'category' // 包含分类信息，没有指定字段，则默认包含所有字段
        ]);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据数量进行分页查询，并获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }


    /**
     *  移动端列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param $userInfo
     * @return array
     * @author Qinii
     */
    public function getApiList(array $where, int $page, int $limit, $userInfo)
    {
        $config = systemConfig("community_app_switch");
        if (!isset($where['is_type']) && $config) $where['is_type'] = $config;
        $where['is_del'] = 0;
        $query = $this->dao->search($where);
        $query->with([
            'author' => function ($query) use ($userInfo) {
                $query->field('uid,real_name,phone,status,avatar,nickname,count_start,count_fans,count_content');
            },
            'is_start' => function ($query) use ($userInfo) {
                $query->where('left_id', $userInfo->uid ?? null);
            },
            'topic' => function ($query) {
                $query->where('status', 1)->where('is_del', 0);
                $query->field('topic_id,topic_name,status,category_id,pic,is_del');
            },
            'relevance' => [
                'spu' => function ($query) {
                    $query->field('spu_id,store_name,image,price,product_type,activity_id,product_id');
                }
            ],
            'is_fanss' => function ($query) use ($userInfo) {
                $query->where('left_id', $userInfo->uid ?? 0);
            }
        ]);
        if (isset($where['search_type']) && $where['search_type'] == 'user') {
            $query->group('Community.uid');
        }
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field', [])
            ->field('community_id,title,image,topic_id,Community.count_start,count_reply,start,Community.create_time,Community.uid,Community.status,Community.pv,is_show,content,video_link,is_type,refusal')
            ->select()->append(['time']);
        return compact('count', 'list');
    }

    /**
     * 视频下滑列表第一个视频
     * @param $community_id
     * @param $userInfo
     * @return array|mixed|\think\db\BaseQuery|\think\Model|null
     * @author Qinii
     */
    public function getFirst($community_id, $userInfo)
    {
        $where['is_del'] = 0;
        $where['community_id'] = $community_id;
        $info = $this->dao->search($where)
            ->with([
                'author' => function ($query) use ($userInfo) {
                    $query->field('uid,real_name,status,avatar,nickname,count_start');
                },
                'is_start' => function ($query) use ($userInfo) {
                    $query->where('left_id', $userInfo->uid ?? null);
                },
                'topic' => function ($query) {
                    $query->where('status', 1)->where('is_del', 0);
                    $query->field('topic_id,topic_name,status,category_id,pic,is_del');
                },
                'relevance' => [
                    'spu' => function ($query) {
                        $query->field('spu_id,store_name,image,price,product_type,activity_id,product_id');
                    }
                ],
                'is_fanss' => function ($query) use ($userInfo) {
                    $query->where('left_id', $userInfo->uid ?? 0);
                }
            ])
            ->field('community_id,title,image,topic_id,Community.count_start,count_reply,start,Community.create_time,Community.uid,Community.status,is_show,content,video_link,is_type,refusal')
            ->find();
        if ($info) {
            $info = $info->append(['time']);
        }

        return $info;
    }

    /**
     *  视频列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param $userInfo
     * @param $type
     * @return array
     * @author Qinii
     */
    public function getApiVideoList(array $where, int $page, int $limit, $userInfo, $type = 0)
    {
        $where['is_type'] = self::COMMUNIT_TYPE_VIDEO;
        $first = $this->getFirst($where['community_id'], $userInfo);

        if ($type) { // 点赞过的内容
            $where['uid'] = $userInfo->uid;
            $where['community_ids'] = $this->dao->joinUser($where)->column('community_id');
        } else { // 条件视频
            if (!isset($where['uid']) && $first) $where['topic_id'] = $first['topic_id'];
        }
        if ($first && $page == 1) {
            $where['not_id'] = $where['community_id'];
            $limit--;
        }

        unset($where['community_id']);
        $data = $this->getApiList($where, $page, $limit, $userInfo);
        if ($data['list']->isEmpty() && isset($where['topic_id'])) {
            unset($where['topic_id']);
            $data = $this->getApiList($where, $page, $limit, $userInfo);
        }

        if ($first && $page == 1) {
            $data['list']->unshift($first);
            $data['count']++;
        }
        return $data;
    }

    /**
     *  后台详情
     * @param int $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 10/28/21
     */
    public function detail(int $id)
    {
        $where = [
            $this->dao->getPk() => $id,
            'is_del' => 0
        ];
        $config = systemConfig("community_app_switch");
        if ($config) $where['is_type'] = $config;
        $res = $this->dao->getSearch($where)->with([
            'author' => function ($query) {$query->field('uid,real_name,status,avatar,nickname,count_start');},
            'topic', 'category', 'relevance'
        ])->find()->toArray();
        $spu_ids = array_column($res['relevance'], 'right_id');
        if($spu_ids) {
            $product = app()->make(ProductRepository::class)->search($res['mer_id'], ['spu_ids' => $spu_ids])->select()->toArray();
            $res['spu_id'] = array_column($product,'spu_id');
            $res['product'] = $product;
        }
        unset($res['relevance']);
        return  $res;
    }

    /**
     *  移动端详情展示
     * @param int $id
     * @param $user
     * @return array|\think\Model|null
     * @author Qinii
     * @day 10/27/21
     */

    public function show(int $id, $user)
    {
        $where = self::IS_SHOW_WHERE;
        $is_author = 0;
        if ($user && $this->dao->uidExists($id, $user->uid)) {
            $where = ['is_del' => 0];
            $is_author = 1;
        }
        $config = systemConfig("community_app_switch");
        if ($config) $where['is_type'] = $config;
        $where[$this->dao->getPk()] = $id;
        $data = $this->dao->getSearch($where)
            ->with([
                'author' => function ($query) {
                    $query->field('uid,real_name,status,avatar,nickname,count_start,member_level');
                    if (systemConfig('member_status')) $query->with(['member' => function ($query) {
                        $query->field('brokerage_icon,brokerage_level');
                    }]);
                },
                'relevance' => [
                    'spu' => function ($query) {
                        $query->field('spu_id,store_name,image,price,product_type,activity_id,product_id');
                    }
                ],
                'topic' => function ($query) {
                    $query->where('status', 1)->where('is_del', 0);
                    $query->field('topic_id,topic_name,status,category_id,pic,is_del');
                },
                'is_start' => function ($query) use ($user) {
                    $query->where('left_id', $user->uid ?? '');
                },
            ])->hidden(['is_del'])->find();
        $relevance  = [];
        if ($data['relevance']) {
            foreach ($data['relevance'] as $item) {
                if ($item['spu']) $relevance[] = $item;
            }
        }
        $data['relevance'] = $relevance;
        if (!$data) throw new ValidateException('内容不存在，可能已被删除了哦～');

        $data['is_author'] = $is_author;
        $is_fans = 0;
        if ($user && !$data['is_author'])
            $is_fans = app()->make(RelevanceRepository::class)->getWhereCount([
                'left_id' => $user->uid,
                'right_id' => $data['uid'],
                'type' => RelevanceRepository::TYPE_COMMUNITY_FANS,
            ]);
        $data['is_fans'] = $is_fans;
        //增加浏览量
        if($data['status'] == 1) {
            $this->dao->incField($id, 'pv');
        }
        return $data;
    }

    /**
     * 根据订单信息 获取订单下的商品信息
     * @param $id
     * @return array
     * @author Qinii
     */
    public function getSpuByOrder($id)
    {
        $where = app()->make(StoreOrderProductRepository::class)->selectWhere(['order_id' => $id]);
        if (!$where) throw new  ValidateException('商品已下架');

        $make = app()->make(SpuRepository::class);
        foreach ($where as $item) {
            switch ($item['product_type']) {
                case 0:
                    $sid = $item['product_id'];
                // nobreak;
                case 1:
                    $sid = $item['product_id'];
                    break;
                case 2:
                    $sid = $item['activity_id'];
                    break;
                case 3:
                    $sid = $item['cart_info']['productAssistSet']['product_assist_id'];
                    break;
                case 4:
                    $sid = $item['cart_info']['product']['productGroup']['product_group_id'];
                    break;
                default:
                    $sid = $item['product_id'];
                    break;
            }
            $data[] = $make->getSpuData($sid, $item['product_type'], 0);
        }
        return $data;
    }

    /**
     *  创建
     * @param array $data
     * @author Qinii
     * @day 10/29/21
     */
    public function create(array $data)
    {
        event('community.create.before', compact('data'));
        if ($data['topic_id']) {
            $getTopic = app()->make(CommunityTopicRepository::class)->get($data['topic_id']);
            if (!$getTopic || !$getTopic->status) throw new ValidateException('话题不存在或已关闭');
            $data['category_id'] = $getTopic->category_id;
        }
        return Db::transaction(function () use ($data) {
            $community = $this->dao->create($data);
            if ($data['spu_id']) $this->joinProduct($community->community_id, $data['spu_id']);
            event('community.create', compact('community'));
            // 内容数统计
            app()->make(UserRepository::class)->incField((int)$data['uid'], 'count_content');
            if ($data['status'] == 1) {  // 免审核 增加经验值
                $make = app()->make(UserBrokerageRepository::class);
                $make->incMemberValue($data['uid'], 'member_community_num', $community->community_id);
                $this->giveIntegral($community);
            }
            return $community->community_id;
        });
    }

    /**
     *  编辑
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 10/29/21
     */
    public function edit(int $id, array $data)
    {
        event('community.update.before', compact('id', 'data'));
        if ($data['topic_id']) {
            $getTopic = app()->make(CommunityTopicRepository::class)->get($data['topic_id']);

            if (!$getTopic || !$getTopic->status) throw new ValidateException('话题不存在或已关闭');
            $data['category_id'] = $getTopic->category_id;
        }

        Db::transaction(function () use ($id, $data) {
            $spuId = $data['spu_id'];
            unset($data['spu_id']);
            $community = $this->dao->update($id, $data);
            if ($spuId) $this->joinProduct($id, $spuId);
            event('community.update', compact('id', 'community'));
        });
    }

    /**
     *  关联商品
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 10/29/21
     */
    public function joinProduct($id, array $data)
    {
        $make = app()->make(RelevanceRepository::class);
        $data = array_unique($data);
        $res = [];
        foreach ($data as $value) {
            if ($value) {
                $res[] = [
                    'left_id' => $id,
                    'right_id' => $value,
                    'type' => RelevanceRepository::TYPE_COMMUNITY_PRODUCT
                ];
            }
        }
        $make->clear($id, RelevanceRepository::TYPE_COMMUNITY_PRODUCT, 'left_id');
        if ($res) $make->insertAll($res);
    }

    /**
     *  获取某用户信息
     * @param int $uid
     * @param null $self
     * @return mixed
     * @author Qinii
     * @day 10/29/21
     */
    public function getUserInfo(int $uid, $self = null)
    {
        $relevanceRepository = app()->make(RelevanceRepository::class);
        $data['focus'] = $relevanceRepository->getFieldCount('left_id', $uid, RelevanceRepository::TYPE_COMMUNITY_FANS);


        $is_start = $is_self = false;
        if ($self && $self->uid == $uid) {
            $user = $self;
            $is_self = true;
        } else {
            $user = app()->make(UserRepository::class)->get($uid);
            $is_start = $relevanceRepository->checkHas($self->uid, $uid, RelevanceRepository::TYPE_COMMUNITY_FANS) > 0;
        }
        $data['start'] = $user->count_start;
        $data['uid'] = $user->uid;
        $data['avatar'] = $user->avatar;
        $data['nickname'] = $user->nickname;
        $data['is_start'] = $is_start;
        $data['member_icon'] = systemConfig('member_status') ? ($user->member->brokerage_icon ?? '') : '';
        $data['is_self'] = $is_self;
        $data['fans'] = $user->count_fans;

        return $data;
    }

    /**
     *  关注
     * @param int $id
     * @param int $uid
     * @param int $status
     * @author Qinii
     * @day 10/29/21
     */
    public function setFocus(int $id, int $uid, int $status)
    {
        Db::transaction(function () use ($id, $uid, $status) {
            $make = app()->make(RelevanceRepository::class);
            $check = $make->checkHas($uid, $id, RelevanceRepository::TYPE_COMMUNITY_FANS);
            if ($status) {
                if ($check) throw new ValidateException('您已经关注过他了～');
                $make->create($uid, $id, RelevanceRepository::TYPE_COMMUNITY_FANS, true);
                app()->make(UserRepository::class)->incField($id, 'count_fans', 1);
            } else {
                if (!$check) throw new ValidateException('您还未关注他哦～');
                $make->destory($uid, $id, RelevanceRepository::TYPE_COMMUNITY_FANS);
                app()->make(UserRepository::class)->decField($id, 'count_fans', 1);
            }
        });

        return;
    }

    /**
     *  设置文章排序星际
     * @param int $id
     * @param null $self
     * @return mixed
     * @author Qinii
     * @day 10/29/21
     */
    public function form($id)
    {
        $form = Elm::createForm(Route::buildUrl('systemCommunityUpdate', ['id' => $id])->build());
        $data = $this->dao->get($id);
        if (!$data) throw new ValidateException('数据不存在');
        $formData = $data->toArray();

        return $form->setRule([
            Elm::rate('start', '排序星级：')->max(5)
        ])->setTitle('编辑星级')->formData($formData);
    }

    /**
     *  后台强制下架操作
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     */
    public function showForm($id)
    {
        $form = Elm::createForm(Route::buildUrl('systemCommunityStatus', ['id' => $id])->build());
        $data = $this->dao->get($id);
        if (!$data) throw new ValidateException('数据不存在');
        return $form->setRule([
            Elm::hidden('status', -1),
            Elm::textarea('refusal', '下架理由：', '信息存在违规')->placeholder('请输入下架理由')->required('请输入下架理由')
        ])->setTitle('强制下架');
    }

    /**
     *  给文章点赞
     * @param int $id
     * @param $userInfo
     * @param int $status
     * @return void
     * @author Qinii
     */
    public function setCommunityStart(int $id, $userInfo, int $status)
    {
        $make = app()->make(RelevanceRepository::class);
        $userRepository = app()->make(UserRepository::class);

        if ($status) {
            $res = $make->create($userInfo->uid, $id, RelevanceRepository::TYPE_COMMUNITY_START, true);
            if (!$res) throw new ValidateException('您已经点赞过了');

            $ret = $this->dao->get($id);
            $user = $userRepository->get($ret['uid']);
            $this->dao->incField($id, 'count_start', 1);
            if ($user) $userRepository->incField((int)$user->uid, 'count_start', 1);
        }
        if (!$status) {
            if (!$make->checkHas($userInfo->uid, $id, RelevanceRepository::TYPE_COMMUNITY_START))
                throw new ValidateException('您还没有点赞呢～');
            $make->destory($userInfo->uid, $id, RelevanceRepository::TYPE_COMMUNITY_START);

            $ret = $this->dao->get($id);
            $user = $userRepository->get($ret['uid']);
            $this->dao->decField($id, 'count_start', 1);
            if ($user) $userRepository->decField((int)$user->uid, 'count_start', 1);
        }
    }

    /**
     * 审核
     * @param $id
     * @param $data
     * @return void
     * @author Qinii
     */
    public function setStatus($id, $data)
    {
        $ret = $this->dao->get($id);
        event('community.status.before', compact('id', 'data'));
        Db::transaction(function () use ($ret, $id, $data) {
            $data['status_time'] = date('Y-m-d H:i;s', time());
            $this->dao->update($id, $data);
            if ($data['status'] == 1) {
                $make = app()->make(UserBrokerageRepository::class);
                $make->incMemberValue($ret['uid'], 'member_community_num', $id);
                $this->giveIntegral($ret);
            }
            event('community.status', compact('id'));
        });

    }
    /**
     * 种草完成给用户增加积分
     *
     * @param object $communityInfo 种草信息
     * @return void
     */
    public function giveIntegral(object $communityInfo)
    {
        $giveIntegralConfig = systemConfig(['integral_community_give', 'integral_community_give_limit']);
        if (!$giveIntegralConfig['integral_community_give'] || !$giveIntegralConfig['integral_community_give_limit']) {
            return false;
        }
        $uid = $communityInfo->uid;
        $createDay = date('Y-m-d', strtotime($communityInfo->create_time));
        // 计算用户在当天发布的审核通过的图文内容数量, 判断是否超过每日限制
        $communityCount = $this->getWhereCount(['uid' => $uid, 'status' => 1, ['create_time', 'like', $createDay.'%']]);
        if($communityCount <= $giveIntegralConfig['integral_community_give_limit']) {
            // 使用依赖注入的方式创建用户账单仓库实例，并增加用户的积分账单
            app()->make(UserBillRepository::class)->incBill($uid, 'integral', 'lock', [
                'link_id' => $communityInfo->community_id, // 图文内容id，关联的订单ID，用于记录积分的来源
                'status' => 0, // 积分状态，这里假设0表示积分锁定，即还未完全发放
                'title' => '种草发帖送积分', // 积分的描述，表明积分的来源是种草发帖
                'number' => $giveIntegralConfig['integral_community_give'], // 赠送的积分数量
                'mark' => $communityInfo->author->nickname.'【用户ID: '.$uid.'】于'.$createDay.', 成功发布第' . floatval($communityCount) . '篇种草, 赠送积分' . floatval($giveIntegralConfig['integral_community_give']), // 积分的备注信息
                'balance' => $communityInfo->author->integral // 用户当前的积分余额，用于记录积分的变化
            ]);
        }
    }

    /**
     * 删除社区内容
     *
     * 此函数用于处理社区内容的删除操作。它首先触发一个名为'community.delete.before'的事件，
     * 允许任何监听此事件的组件在实际删除操作之前进行干预。接下来，它从数据库中获取指定ID的内容信息，
     * 并将该内容的删除标记设置为1，执行实际的删除逻辑。随后，它减少与该内容关联的用户的内容计数，
     * 这反映了内容数量的变化。最后，它触发'community.delete'事件，允许其他组件在删除操作完成后执行额外的操作。
     *
     * @param int $id 内容的唯一标识符
     * @param null|$user 删除操作的用户信息，默认为null，表示任何用户都可以执行此操作
     */
    public function destory($id, $user = null)
    {
        // 在执行删除操作之前触发事件，允许其他组件或功能进行干预
        event('community.delete.before', compact('id', 'user'));

        // 从数据库中获取指定ID的内容信息
        $info = $this->dao->get($id);

        // 将内容的删除状态设置为已删除
        $this->dao->update($id, ['is_del' => 1]);

        // 减少与该内容关联的用户的内容计数，反映内容的删除
        // 内容数统计
        app()->make(UserRepository::class)->decField((int)$info['uid'], 'count_content');

        // 删除操作完成后触发事件，允许其他组件或功能执行后续操作
        event('community.delete', compact('id', 'user'));
    }


    /**
     * 根据SPU ID获取相关数据
     *
     * 本函数通过SPU ID查询特定的数据集，这些数据集特定于应用程序的业务逻辑。
     * 它合并了查询条件，选择了特定的字段，排序方式，并限制了返回的记录数。
     *
     * @param int $spuId 商品规格ID，用于精确查询特定SPU的数据。
     * @return array 返回一个包含社区ID、标题、图片、类型标志和创建时间的记录集数组，最多包含3条记录。
     */
    public function getDataBySpu($spuId)
    {
        // 合并查询条件，确保查询的SPU ID准确，并包含显示状态的条件
        $where = array_merge(['spu_id' => $spuId], self::IS_SHOW_WHERE);

        // 执行查询，选择特定字段，按创建时间降序排序，并限制返回结果的数量
        $result = $this->dao->getSearch($where)
            ->field('community_id,title,image,is_type,create_time')
            ->order('create_time DESC')
            ->limit(3)->select();

        // 返回查询结果
        return $result;
    }

    /**
     * 生成视频社区的二维码。
     * 根据传入的类型和用户信息，生成相应的二维码用于视频社区的访问或推广。
     *
     * @param int $id 社区ID，标识特定的视频社区。
     * @param string $type 二维码类型，区分常规二维码和小程序二维码。
     * @param object|null $user 用户信息，用于生成带有用户标识的二维码。
     * @return string|boolean 返回二维码的路径或者在生成失败时返回false。
     */
    public function qrcode($id, $type, $user)
    {
        // 查询视频社区信息，确保社区存在且状态正常
        $res = $this->dao->search(['is_type' => self::COMMUNIT_TYPE_VIDEO, 'community_id' => $id, 'status' => 1, 'is_show' => 1])->find();
        if (!$res) return false;

        // 增加视频社区的访问量
        // 增加视频播放量
        $this->dao->incField($id, 'pv');

        // 创建二维码服务实例
        $make = app()->make(QrcodeService::class);

        // 根据二维码类型生成不同的二维码内容和名称
        if ($type == 'routine') {
            // 生成小程序二维码的名称和参数
            $name = md5('rcwx' . $id . $type . ($user ? $user->uid . $user['is_promoter'] : '') . date('Ymd')) . '.jpg';
            $params = 'id=' . $id . ($user ? '&spid=' . $user['uid'] : '');
            $link = 'pages/short_video/nvueSwiper/index';
            // 生成小程序二维码并返回路径
            return $make->getRoutineQrcodePath($name, $link, $params);
        } else {
            // 生成普通二维码的名称和链接
            $name = md5('cwx' . $id . $type . ($user ? $user->uid . $user['is_promoter'] : '') . date('Ymd')) . '.jpg';
            $link = 'pages/short_video/nvueSwiper/index';
            $link .= '?id=' . $id . ($user ? '&spid=' . $user['uid'] : '');
            $key = 'com' . $type . '_' . $id . '_' . ($user['uid'] ?? 0);
            // 生成普通二维码并返回路径
            return $make->getWechatQrcodePath($name, $link, false, $key);
        }
    }
}

