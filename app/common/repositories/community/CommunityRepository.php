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
     * 获取与当前用户存在拉黑关系（任一方向）的对方 uid 列表
     * 用于视频/图文列表过滤：被作者拉黑或我拉黑作者，双向不可见
     */
    protected function getBlockedRelatedUids(int $viewerUid): array
    {
        if ($viewerUid <= 0) return [];
        $rows = \think\facade\Db::name('user_dialog')
            ->where(function ($q) use ($viewerUid) {
                $q->where(function ($q2) use ($viewerUid) {
                    $q2->where('uid_a', $viewerUid)->where(function ($q3) {
                        $q3->where('is_black_a', 1)->whereOr('is_black_b', 1);
                    });
                })->whereOr(function ($q2) use ($viewerUid) {
                    $q2->where('uid_b', $viewerUid)->where(function ($q3) {
                        $q3->where('is_black_a', 1)->whereOr('is_black_b', 1);
                    });
                });
            })
            ->field('uid_a,uid_b')
            ->select()
            ->toArray();
        $blocked = [];
        foreach ($rows as $r) {
            $other = (int)$r['uid_a'] === $viewerUid ? (int)$r['uid_b'] : (int)$r['uid_a'];
            if ($other > 0) $blocked[] = $other;
        }
        return array_values(array_unique($blocked));
    }

    /**
     * @param array $where
     * @param int $page
     * @param int $limit
     * @param $userInfo
     * @return array
     * @author Qinii
     */
    public function getApiList(array $where, int $page, int $limit, $userInfo, string $clientPlatform = '', string $appVersion = '')
    {
        $config = systemConfig("community_app_switch");
        if (!isset($where['is_type']) && $config) $where['is_type'] = $config;
        $where['is_del'] = 0;
        $query = $this->dao->search($where);

        if ($clientPlatform !== '') {
            app()->make(\app\common\repositories\system\AppEntryCommunityFeed::class)
                ->applyToQuery($query, $clientPlatform, $appVersion);
        }

        // 可见性过滤：自己看自己的帖不限制；看他人或公共流才过滤
        $viewerUid = isset($userInfo) && $userInfo ? (int)$userInfo->uid : 0;
        $isOwnFeed = $viewerUid && isset($where['uid']) && (int)$where['uid'] === $viewerUid;
        if (!$isOwnFeed) {
            if ($viewerUid) {
                $fansType = \app\common\repositories\system\RelevanceRepository::TYPE_COMMUNITY_FANS;
                $iFollow = \think\facade\Db::name('relevance')
                    ->where('left_id', $viewerUid)->where('type', $fansType)->column('right_id') ?: [];
                $mutualUids = $iFollow ? (\think\facade\Db::name('relevance')
                    ->where('type', $fansType)->whereIn('left_id', $iFollow)
                    ->where('right_id', $viewerUid)->column('left_id') ?: []) : [];
                $query->where(function ($q) use ($viewerUid, $mutualUids) {
                    $q->where('Community.visibility', 0);
                    if ($mutualUids) {
                        $q->whereOr(function ($q2) use ($mutualUids) {
                            $q2->where('Community.visibility', 1)->whereIn('Community.uid', $mutualUids);
                        });
                    }
                    $q->whereOr(function ($q2) use ($viewerUid) {
                        $q2->where('Community.visibility', 2)->where('Community.uid', $viewerUid);
                    });
                });

                // 拉黑过滤：过滤掉与当前用户之间存在拉黑关系（任一方向）的作者
                $blockedUids = $this->getBlockedRelatedUids($viewerUid);
                if ($blockedUids) {
                    $query->whereNotIn('Community.uid', $blockedUids);
                }
            } else {
                $query->where('Community.visibility', 0);
            }
        }

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
            ->field('community_id,title,image,topic_id,Community.count_start,count_reply,start,Community.create_time,Community.uid,Community.status,Community.pv,is_show,content,video_link,is_type,community_type,community_type_data,refusal,Community.visibility')
            ->select()->append(['time']);

        $redpacketDao = app()->make(\app\common\dao\community\CommunityRedpacketDao::class);
        $paidDao = app()->make(\app\common\dao\community\CommunityPaidDao::class);
        $recruitDao = app()->make(\app\common\dao\community\CommunityRecruitDao::class);
        foreach ($list as $item) {
            if ($item['community_type'] == 1) {
                $rp = $redpacketDao->search(['community_id' => $item['community_id']])->find();
                $item['type_data'] = $rp ? [
                    'amount_per_person' => $rp['amount_per_person'],
                    'total_count' => $rp['total_count'],
                    'taken_count' => $rp['taken_count'],
                    'deadline' => $rp['deadline'],
                    'status' => $rp['status'],
                ] : null;
            } elseif ($item['community_type'] == 2) {
                $paid = $paidDao->search(['community_id' => $item['community_id']])->find();
                if ($paid) {
                    $paidRepo = app()->make(CommunityPaidContentRepository::class);
                    $item['type_data'] = $paidRepo->buildTypeData(
                        $paid->toArray(),
                        (int)$item['uid'],
                        $userInfo
                    );
                } else {
                    $item['type_data'] = null;
                }
            } elseif ($item['community_type'] == 3) {
                $recruit = $recruitDao->search(['community_id' => $item['community_id']])->find();
                $item['type_data'] = $recruit ? [
                    'job_title' => $recruit['job_title'],
                    'salary_range' => $recruit['salary_range'],
                    'status' => $recruit['status'],
                ] : null;
            }
        }
        $list = $this->appendTopics($list);
        return compact('count', 'list');
    }

    /**
     * 社区搜索用户：按昵称 / uid / user_code 匹配
     * 返回结构兼容前端搜索结果页「用户」Tab（author + is_fans）
     */
    public function searchApiUsers(string $keyword, int $page, int $limit, $userInfo = null): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return ['count' => 0, 'list' => []];
        }

        $viewerUid = isset($userInfo) && $userInfo ? (int)$userInfo->uid : 0;
        $query = \think\facade\Db::name('user')->alias('User')
            ->whereNull('User.cancel_time')
            ->where('User.status', 1)
            ->where(function ($q) use ($keyword) {
                $q->whereLike('User.nickname', '%' . $keyword . '%')
                    ->whereOr('User.user_code', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $q->whereOr('User.uid', (int)$keyword);
                }
            });

        if ($viewerUid > 0) {
            $blockedUids = $this->getBlockedRelatedUids($viewerUid);
            if ($blockedUids) {
                $query->whereNotIn('User.uid', $blockedUids);
            }
        }

        $count = (int)$query->count('User.uid');
        $rows = $query->field('User.uid,User.avatar,User.nickname,User.count_fans,User.count_content,User.user_code')
            ->order('User.uid', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $followedMap = [];
        if ($viewerUid > 0 && $rows) {
            $uids = array_column($rows, 'uid');
            $followed = \think\facade\Db::name('relevance')
                ->where('left_id', $viewerUid)
                ->where('type', RelevanceRepository::TYPE_COMMUNITY_FANS)
                ->whereIn('right_id', $uids)
                ->column('right_id');
            $followedMap = array_flip($followed ?: []);
        }

        $list = [];
        foreach ($rows as $row) {
            $uid = (int)$row['uid'];
            $author = [
                'uid' => $uid,
                'avatar' => $row['avatar'] ?? '',
                'nickname' => $row['nickname'] ?? '',
                'count_fans' => (int)($row['count_fans'] ?? 0),
                'count_content' => (int)($row['count_content'] ?? 0),
                'user_code' => $row['user_code'] ?? '',
            ];
            $isFans = isset($followedMap[$uid]);
            $list[] = [
                'uid' => $uid,
                'author' => $author,
                'is_fans' => $isFans,
                'is_start' => $isFans,
            ];
        }

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
        // 拉黑过滤：作者与当前用户存在拉黑关系时，不返回
        $viewerUid = isset($userInfo) && $userInfo ? (int)$userInfo->uid : 0;
        if ($viewerUid > 0) {
            $blockedUids = $this->getBlockedRelatedUids($viewerUid);
            if ($blockedUids) {
                $ownerUid = (int)$this->dao->search(['community_id' => $community_id])->value('Community.uid');
                if ($ownerUid > 0 && in_array($ownerUid, $blockedUids, true)) {
                    return null;
                }
            }
        }
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
            ->field('community_id,title,image,topic_id,Community.count_start,count_reply,start,Community.create_time,Community.uid,Community.status,is_show,content,video_link,is_type,community_type,community_type_data,refusal')
            ->find();
        if ($info) {
            $info = $info->append(['time']);
            $info = $this->appendTopics($info);
            $infoArr = is_array($info) ? $info : $info->toArray();
            if ((int)($infoArr['community_type'] ?? 0) === 2) {
                $paidDao = app()->make(\app\common\dao\community\CommunityPaidDao::class);
                $paid = $paidDao->search(['community_id' => $infoArr['community_id']])->find();
                if ($paid) {
                    $paidRepo = app()->make(CommunityPaidContentRepository::class);
                    $infoArr['type_data'] = $paidRepo->buildTypeData(
                        $paid->toArray(),
                        (int)$infoArr['uid'],
                        $userInfo
                    );
                }
                if (is_object($info)) {
                    foreach ($infoArr as $k => $v) {
                        $info[$k] = $v;
                    }
                } else {
                    $info = $infoArr;
                }
            }
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
    public function getApiVideoList(array $where, int $page, int $limit, $userInfo, $type = 0, string $clientPlatform = '', string $appVersion = '')
    {
        $where['is_type'] = self::COMMUNIT_TYPE_VIDEO;
        $first = $this->getFirst($where['community_id'], $userInfo);
        // 提审隐藏类型（如付费）时，首条也不应强插进下滑列表
        if ($first && $clientPlatform !== '' && $this->isCommunityFeedBlockedItem($first, $clientPlatform, $appVersion)) {
            $first = null;
        }

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
        $data = $this->getApiList($where, $page, $limit, $userInfo, $clientPlatform, $appVersion);
        if (empty($data['list']) && isset($where['topic_id'])) {
            unset($where['topic_id']);
            $data = $this->getApiList($where, $page, $limit, $userInfo, $clientPlatform, $appVersion);
        }

        if ($first && $page == 1) {
            $firstArr = is_array($first) ? $first : (method_exists($first, 'toArray') ? $first->toArray() : (array)$first);
            array_unshift($data['list'], $firstArr);
            $data['count']++;
        }
        return $data;
    }

    /**
     * 是否属于 C1 社区信息流在当前提审端应隐藏的帖子
     */
    protected function isCommunityFeedBlockedItem($item, string $clientPlatform, string $appVersion): bool
    {
        $arr = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array)$item);
        $communityType = (int)($arr['community_type'] ?? 0);
        $isType = (int)($arr['is_type'] ?? 0);
        $ctx = app()->make(\app\common\repositories\system\AppEntryCommunityFeed::class)
            ->clientContext($clientPlatform, $appVersion);
        foreach ($ctx['hidden_type_keys'] as $typeKey) {
            switch ($typeKey) {
                case 'note_image':
                    if ($communityType === 0 && $isType === 1) return true;
                    break;
                case 'note_video':
                    if ($communityType === 0 && $isType === 2) return true;
                    break;
                case 'redpacket':
                    if ($communityType === 1) return true;
                    break;
                case 'paid':
                    if ($communityType === 2) return true;
                    break;
                case 'recruit':
                    if ($communityType === 3) return true;
                    break;
            }
        }
        return false;
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
                    $query->field('uid,real_name,status,avatar,nickname,count_start,member_level,sex');
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
            ])
            ->hidden(['is_del'])
            ->field('community_id,title,image,topic_id,count_start,count_reply,start,create_time,update_time,uid,status,pv,is_show,content,video_link,is_type,community_type,community_type_data,refusal,visibility')
            ->find();

        if (!$data) throw new ValidateException('内容不存在，可能已被删除了哦～');
        $data = $data->toArray();

        // 可见性权限检查
        if (!$is_author) {
            $vis = (int)($data['visibility'] ?? 0);
            if ($vis === 2) {
                throw new ValidateException('该内容仅作者本人可见');
            }
            if ($vis === 1) {
                $fansType = \app\common\repositories\system\RelevanceRepository::TYPE_COMMUNITY_FANS;
                $iFollow = $user ? \think\facade\Db::name('relevance')
                    ->where('left_id', $user->uid)->where('right_id', $data['uid'])->where('type', $fansType)->count() : 0;
                $theyFollow = $user ? \think\facade\Db::name('relevance')
                    ->where('left_id', $data['uid'])->where('right_id', $user->uid)->where('type', $fansType)->count() : 0;
                if (!($iFollow && $theyFollow)) {
                    throw new ValidateException('该内容仅互关好友可见');
                }
            }
        }
        $data = $this->appendTopics($data);
        $data['is_collect'] = $user ? ($this->isCollected($id, (int)$user->uid) ? 1 : 0) : 0;
        $data['count_collect'] = $this->collectCount($id);

        // 根据community_type附加类型数据
        $data['type_data'] = null;
        if ($data['community_type'] == 1) {
            $redpacketDao = app()->make(\app\common\dao\community\CommunityRedpacketDao::class);
            $rp = $redpacketDao->search(['community_id' => $id])->find();
            if ($rp) {
                $rp = $rp->toArray();
                $typeData = [
                    'amount_per_person' => $rp['amount_per_person'],
                    'total_count' => $rp['total_count'],
                    'taken_count' => $rp['taken_count'],
                    'completed_count' => $rp['completed_count'],
                    'total_amount' => $rp['total_amount'],
                    'deadline' => $rp['deadline'],
                    'status' => $rp['status'],
                ];
                if ($user) {
                    $taskDao = app()->make(\app\common\dao\community\CommunityRedpacketTaskDao::class);
                    $task = $taskDao->search(['redpacket_id' => $rp['id'], 'uid' => $user->uid])->find();
                    $typeData['my_task'] = $task ? $task->toArray() : null;
                }
                $data['type_data'] = $typeData;
            }
        } elseif ($data['community_type'] == 2) {
            $paidDao = app()->make(\app\common\dao\community\CommunityPaidDao::class);
            $paid = $paidDao->search(['community_id' => $id])->find();
            if ($paid) {
                $paid = $paid->toArray();
                $isUnlocked = false;
                if ($user && ($paid['uid'] == $user->uid)) {
                    $isUnlocked = true;
                } elseif ($user) {
                    $orderDao = app()->make(\app\common\dao\community\CommunityPaidOrderDao::class);
                    $isUnlocked = $orderDao->search([
                        'community_id' => $id, 'buyer_uid' => $user->uid, 'pay_status' => 1
                    ])->count() > 0;
                }
                $typeData = [
                    'price' => $paid['price'],
                    'buy_count' => $paid['buy_count'],
                    'trial_ratio' => $paid['trial_ratio'],
                    'free_content' => $paid['free_content'],
                    'video_paid_mode' => (int)($paid['video_paid_mode'] ?? 0),
                    'video_trial_duration' => (float)($paid['video_trial_duration'] ?? 0),
                    'video_duration' => (float)($paid['video_duration'] ?? 0),
                    'is_unlocked' => $isUnlocked,
                ];
                if ($isUnlocked) {
                    $typeData['paid_content'] = $paid['paid_content'];
                }
                $data['type_data'] = $typeData;
            }
        } elseif ($data['community_type'] == 3) {
            $recruitDao = app()->make(\app\common\dao\community\CommunityRecruitDao::class);
            $recruit = $recruitDao->search(['community_id' => $id])->find();
            if ($recruit) {
                $recruit = $recruit->toArray();
                $typeData = [
                    'recruit_id' => $recruit['id'],
                    'job_title' => $recruit['job_title'],
                    'work_city' => $recruit['work_city'],
                    'salary_range' => $recruit['salary_range'],
                    'job_desc' => $recruit['job_desc'],
                    'job_require' => $recruit['job_require'],
                    'hire_count' => $recruit['hire_count'],
                    'deadline' => $recruit['deadline'],
                    'company_intro' => $recruit['company_intro'],
                    'status' => $recruit['status'],
                    'apply_count' => $recruit['apply_count'],
                ];
                if ($user) {
                    $applyDao = app()->make(\app\common\dao\community\CommunityRecruitApplyDao::class);
                    $apply = $applyDao->search(['recruit_id' => $recruit['id'], 'uid' => $user->uid])->find();
                    $typeData['my_apply'] = $apply ? $apply->toArray() : null;
                }
                $data['type_data'] = $typeData;
            }
        }
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

        // 发帖人昵称后资料简要：出身年份·学历·身高
        if (!empty($data['author']) && !empty($data['author']['uid'])) {
            $brief = app()->make(\app\common\repositories\user\UserProfileRepository::class)
                ->getBriefByUid((int)$data['author']['uid']);
            $data['author']['profile_brief'] = $brief;
            // 兼容前端 gender 字段
            $data['author']['gender'] = (int)($data['author']['sex'] ?? 0);
        }

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

    public function create(array $data)
    {
        event('community.create.before', compact('data'));
        $this->guardDuplicateCreate($data);
        $topicRepo = app()->make(CommunityTopicRepository::class);
        $topics = $topicRepo->resolveTopicsFromPayload($data);
        unset($data['topic_names'], $data['free_content']);
        if ($topics) {
            $first = $topics[0];
            $data['topic_id'] = (int)$first['topic_id'];
            $data['category_id'] = (int)($first['category_id'] ?? 0);
        } elseif (!empty($data['topic_id'] ?? null)) {
            $getTopic = $topicRepo->get($data['topic_id']);
            if (!$getTopic || !$getTopic->status) throw new ValidateException('话题不存在或已关闭');
            $data['category_id'] = $getTopic->category_id;
            $topics = [$getTopic];
        } else {
            $data['topic_id'] = $data['topic_id'] ?? 0;
        }
        return Db::transaction(function () use ($data, $topics, $topicRepo) {
            $community = $this->dao->create($data);
            if (!empty($data['spu_id'] ?? null)) $this->joinProduct($community->community_id, $data['spu_id']);
            $topicIds = [];
            foreach ($topics as $topic) {
                $topicIds[] = (int)$topic['topic_id'];
            }
            $topicRepo->syncCommunityTopics((int)$community->community_id, $topicIds);
            event('community.create', compact('community'));
            // 内容数统计
            app()->make(UserRepository::class)->incField((int)$data['uid'], 'count_content');
            if ($data['status'] == 1) {  // 免除审核 增加经验值
                $make = app()->make(UserBrokerageRepository::class);
                $make->incMemberValue($data['uid'], 'member_community_num', $community->community_id);
                $this->giveIntegral($community);
            }
            return $community->community_id;
        });
    }

    /**
     * 防重复发布：短时内同用户同内容指纹只允许创建一次（兜底前端连点 / 双事件）
     */
    protected function guardDuplicateCreate(array $data): void
    {
        $uid = (int)($data['uid'] ?? 0);
        if ($uid <= 0) {
            return;
        }
        $image = $data['image'] ?? '';
        if (is_array($image)) {
            $image = implode(',', $image);
        }
        $fingerprint = md5(json_encode([
            $uid,
            (string)($data['title'] ?? ''),
            (string)($data['content'] ?? ''),
            (string)$image,
            (string)($data['video_link'] ?? ''),
            (int)($data['community_type'] ?? 0),
            (int)($data['is_type'] ?? 1),
        ], JSON_UNESCAPED_UNICODE));

        $contentKey = 'community:create:dup:' . $fingerprint;
        $uidKey = 'community:create:uid:' . $uid;

        if (Cache::get($contentKey) || Cache::get($uidKey)) {
            throw new ValidateException('请勿重复发布');
        }
        // 同内容 10 秒；同用户连点 3 秒
        Cache::set($contentKey, 1, 10);
        Cache::set($uidKey, 1, 3);
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
        $topicRepo = app()->make(CommunityTopicRepository::class);
        $topics = $topicRepo->resolveTopicsFromPayload($data);
        unset($data['topic_names'], $data['free_content']);
        if ($topics) {
            $first = $topics[0];
            $data['topic_id'] = (int)$first['topic_id'];
            $data['category_id'] = (int)($first['category_id'] ?? 0);
        } elseif (!empty($data['topic_id'] ?? null)) {
            $getTopic = $topicRepo->get($data['topic_id']);
            if (!$getTopic || !$getTopic->status) throw new ValidateException('话题不存在或已关闭');
            $data['category_id'] = $getTopic->category_id;
            $topics = [$getTopic];
        } else {
            $data['topic_id'] = 0;
            $topics = [];
        }

        Db::transaction(function () use ($id, $data, $topics, $topicRepo) {
            $spuId = $data['spu_id'] ?? [];
            unset($data['spu_id']);
            // 作者重新编辑后记录更新时间，详情页据此展示「编辑于」
            $data['update_time'] = date('Y-m-d H:i:s');
            $community = $this->dao->update($id, $data);
            if (!empty($spuId)) $this->joinProduct($id, $spuId);
            $topicIds = [];
            foreach ($topics as $topic) {
                $topicIds[] = (int)$topic['topic_id'];
            }
            $topicRepo->syncCommunityTopics($id, $topicIds);
            event('community.update', compact('id', 'community'));
        });
    }

    /**
     * 附加多话题字段 topics / tags（兼容旧 topic）
     */
    public function appendTopics($data)
    {
        $isList = false;
        if ($data instanceof \think\Collection) {
            $items = $data;
            $isList = true;
        } elseif (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $items = $data;
            $isList = true;
        } else {
            $items = [$data];
        }

        $ids = [];
        foreach ($items as $item) {
            $id = is_array($item) ? ($item['community_id'] ?? 0) : ($item['community_id'] ?? 0);
            if ($id) $ids[] = (int)$id;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $map = [];
        if ($ids) {
            $rows = \think\facade\Db::name('community_topic_rel')->alias('r')
                ->leftJoin('community_topic t', 't.topic_id = r.topic_id')
                ->whereIn('r.community_id', $ids)
                ->where('t.is_del', 0)
                ->field('r.community_id,t.topic_id,t.topic_name,t.pic,t.category_id,r.sort')
                ->order('r.sort ASC,r.id ASC')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $map[(int)$row['community_id']][] = [
                    'topic_id' => (int)$row['topic_id'],
                    'topic_name' => $row['topic_name'],
                    'pic' => $row['pic'] ?? '',
                    'category_id' => (int)($row['category_id'] ?? 0),
                ];
            }
        }

        $out = [];
        foreach ($items as $item) {
            $arr = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array)$item);
            $cid = (int)($arr['community_id'] ?? 0);
            $topics = $map[$cid] ?? [];
            if (!$topics && !empty($arr['topic']) && is_array($arr['topic'])) {
                $topics = [[
                    'topic_id' => (int)($arr['topic']['topic_id'] ?? 0),
                    'topic_name' => $arr['topic']['topic_name'] ?? '',
                    'pic' => $arr['topic']['pic'] ?? '',
                    'category_id' => (int)($arr['topic']['category_id'] ?? 0),
                ]];
                $topics = array_values(array_filter($topics, function ($t) {
                    return !empty($t['topic_id']) || !empty($t['topic_name']);
                }));
            }
            $arr['topics'] = $topics;
            $arr['tags'] = array_values(array_filter(array_map(function ($t) {
                return $t['topic_name'] ?? '';
            }, $topics)));
            if (empty($arr['topic']) && $topics) {
                $arr['topic'] = $topics[0];
            }
            $out[] = $arr;
        }
        return $isList ? $out : $out[0];
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

        $is_start = $is_self = $is_liked = false;
        if ($self && $self->uid == $uid) {
            $user = $self;
            $is_self = true;
        } else {
            $user = app()->make(UserRepository::class)->get($uid);
            if (!$user) return app('json')->fail('用户不存在');
            if ($self && app()->make(\app\common\repositories\user\UserDialogRepository::class)->isBlockedBetween($self->uid, $uid)) {
                return [
                    'uid' => $uid,
                    'nickname' => $user->nickname,
                    'avatar' => $user->avatar,
                    'is_blocked' => true,
                    'is_self' => false,
                ];
            }
            // 主页付费解锁：设置了 homepage_unlock_price 且当前用户未解锁 → 返回锁定态基础信息
            $homepageLockRow = \think\facade\Db::name('user_profile')
                ->where('uid', $uid)
                ->field('homepage_unlock_price')
                ->find();
            $homepagePrice = round((float)($homepageLockRow['homepage_unlock_price'] ?? 0), 2);
            if ($homepagePrice > 0) {
                $homepageUnlocked = false;
                if ($self) {
                    $homepageUnlocked = (bool)\think\facade\Db::name('user_homepage_unlock')
                        ->where('buyer_uid', $self->uid)
                        ->where('target_uid', $uid)
                        ->where('pay_status', 1)
                        ->count();
                }
                if (!$homepageUnlocked) {
                    return [
                        'uid' => $uid,
                        'nickname' => $user->nickname,
                        'avatar' => $user->avatar,
                        'is_self' => false,
                        'homepage_locked' => true,
                        'homepage_unlock_price' => $homepagePrice,
                    ];
                }
            }
            // $self 为 null 时（未登录）跳过关注状态查询
            if ($self) {
                $is_start = $relevanceRepository->checkHas($self->uid, $uid, RelevanceRepository::TYPE_COMMUNITY_FANS) > 0;
                $is_liked = $relevanceRepository->checkHas($self->uid, $uid, RelevanceRepository::TYPE_USER_LIKE) > 0;
            }
        }
        $data['start']          = $user->count_start;
        $data['uid']            = $user->uid;
        $data['user_code']      = $user->user_code ?? '';
        $data['avatar']         = $user->avatar;
        $data['nickname']       = $user->nickname;
        $data['sex']            = $user->sex ?? 0;
        $data['is_start']       = $is_start;
        $data['is_liked']       = $is_liked;
        $data['member_icon']    = systemConfig('member_status') ? ($user->member->brokerage_icon ?? '') : '';
        $data['is_self']        = $is_self;
        $data['fans']           = $user->count_fans;
        $data['mutual']         = $relevanceRepository->getMutualFollowCount($uid);
        $data['phone']          = $is_self ? ($user->phone ?: '') : '';
        $data['user_label_name'] = $user->label_id ? app()->make(\app\common\repositories\user\UserLabelRepository::class)->labels($user->label_id) : [];
        $certRepo = app()->make(\app\common\repositories\user\UserCertificationRepository::class);
        $certNames = $certRepo->getApprovedDisplayNames($uid);
        foreach ($data['user_label_name'] as $labelName) {
            $labelName = trim((string)$labelName);
            if ($labelName !== '' && !in_array($labelName, $certNames, true)) {
                $certNames[] = $labelName;
            }
        }
        $data['cert_list'] = $certNames;
        $data['profile_items']  = $this->buildProfileItems($user, $uid);

        $profileRepo = app()->make(\app\common\repositories\user\UserProfileRepository::class);
        $profile = $profileRepo->getByUid($uid);
        if (!empty($profile['hobbies']) && is_string($profile['hobbies'])) {
            $decoded = json_decode($profile['hobbies'], true);
            $profile['hobbies'] = is_array($decoded) ? $decoded : [];
        } elseif (isset($profile['hobbies']) && !is_array($profile['hobbies'])) {
            $profile['hobbies'] = [];
        } elseif (empty($profile)) {
            $profile = [];
        } else {
            $profile['hobbies'] = $profile['hobbies'] ?? [];
        }
        $data['profile'] = $profile;
        $data['profile_brief'] = $profile ? $profileRepo->formatProfileBrief($profile) : '';

        $fieldRepo = app()->make(\app\common\repositories\user\UserProfileFieldRepository::class);
        $profileFields = $fieldRepo->attachValues($fieldRepo->displayFields(), $profile ?: [], [
            'account_phone' => trim((string)($user->phone ?? '')),
        ]);
        // 非本人：隐藏敏感字段；微信号沿用解锁逻辑
        if (!$is_self) {
            foreach ($profileFields as &$pf) {
                $key = (string)($pf['field_key'] ?? '');
                if ($key === 'id_card' || $key === 'user_phone' || $key === 'contact_phone') {
                    $pf['value'] = ($pf['type'] ?? '') === 'checkbox' ? [] : '';
                }
                if ($key === 'wechat_id') {
                    $pf['value'] = '';
                }
            }
            unset($pf);
        }
        $data['profile_fields'] = $profileFields;

        $review = app()->make(\app\common\repositories\user\UserCertificationRepository::class)
            ->buildReviewDisplay(is_array($user) ? $user : $user->toArray());
        $data['review_label'] = $review['review_label'];
        $data['can_apply_urgent'] = $review['can_apply_urgent'];
        $data['profile_review_status'] = $review['profile_review_status'];
        $data['profile_review_urgent'] = $review['profile_review_urgent'];

        $wechatId = trim((string)($profile['wechat_id'] ?? ''));
        $unlockPrice = round((float)($profile['wechat_unlock_price'] ?? 0), 2);
        $wechatUnlocked = false;
        if ($self && !$is_self) {
            if ($unlockPrice <= 0) {
                $wechatUnlocked = true;
            } else {
                $wechatUnlocked = (bool)\think\facade\Db::name('user_wechat_unlock')
                    ->where('buyer_uid', $self->uid)
                    ->where('target_uid', $uid)
                    ->where('pay_status', 1)
                    ->count();
            }
        }
        $data['has_wechat'] = $wechatId !== '';
        $data['wechat_unlock_price'] = $unlockPrice;
        $data['wechat_unlocked'] = $is_self || $wechatUnlocked;
        $data['wechat_id'] = ($is_self || $wechatUnlocked || $unlockPrice <= 0) ? $wechatId : '';
        // 主页解锁状态（能走到这里说明本人 or 免费 or 已解锁；仅用于前端展示）
        $homepageUnlockPrice = round((float)($profile['homepage_unlock_price'] ?? 0), 2);
        $data['homepage_unlock_price'] = $homepageUnlockPrice;
        $data['homepage_unlocked'] = true;
        $data['homepage_locked'] = false;
        // 回填可展示的微信号到 profile_fields
        if (!empty($data['profile_fields']) && is_array($data['profile_fields'])) {
            foreach ($data['profile_fields'] as &$pf) {
                if (($pf['field_key'] ?? '') === 'wechat_id') {
                    $pf['value'] = $data['wechat_id'];
                }
            }
            unset($pf);
        }
        // 全网粉丝（用户自填）；未填则为 null，前端不展示
        $networkFans = $profile['network_fans'] ?? null;
        $data['network_fans'] = ($networkFans === null || $networkFans === '') ? null : (int)$networkFans;

        return $data;
    }

    private function buildProfileItems($user, int $uid): array
    {
        $profile = app()->make(\app\common\repositories\user\UserProfileRepository::class)->getByUid($uid);
        if (empty($profile)) return [];

        $items = [];

        // 出生年份·星座
        if ($user->birthday) {
            $year    = (int) date('Y', strtotime($user->birthday));
            $items[] = ['icon' => 'icon-ic_clock',         'text' => ($year % 100) . '年·' . $this->getZodiac($user->birthday)];
        }

        // 身高·体重
        if (!empty($profile['height']) && !empty($profile['weight'])) {
            $items[] = ['icon' => 'icon-ic_user',          'text' => $profile['height'] . 'cm·' . $profile['weight'] . 'kg'];
        } elseif (!empty($profile['height'])) {
            $items[] = ['icon' => 'icon-ic_user',          'text' => $profile['height'] . 'cm'];
        }

        // 学历·类型
        $eduMap     = [1 => '高中', 2 => '大专', 3 => '本科', 4 => '硕士', 5 => '博士'];
        $eduTypeMap = [1 => '全日制', 2 => '非全日制'];
        if (!empty($profile['education'])) {
            $edu     = $eduMap[$profile['education']] ?? '';
            $eduType = !empty($profile['education_type']) ? ($eduTypeMap[$profile['education_type']] ?? '') : '';
            $items[] = ['icon' => 'icon-ic_learn1',        'text' => $edu . ($eduType ? '·' . $eduType : '')];
        }

        // 职位
        if (!empty($profile['job_title'])) {
            $items[] = ['icon' => 'icon-ic_badge1',        'text' => $profile['job_title']];
        }

        // 籍贯·现居
        if (!empty($profile['hometown_city']) && !empty($profile['current_city'])) {
            $items[] = ['icon' => 'icon-icon_Location',    'text' => $profile['hometown_city'] . '人在' . $profile['current_city']];
        } elseif (!empty($profile['current_city'])) {
            $items[] = ['icon' => 'icon-icon_Location',    'text' => $profile['current_city']];
        }

        // 年收入
        $incomeMap = [1 => '年入10万以下', 2 => '年入10-20万', 3 => '年入20-50万', 4 => '年入50万以上'];
        if (!empty($profile['annual_income'])) {
            $items[] = ['icon' => 'icon-ic_money',         'text' => $incomeMap[$profile['annual_income']] ?? ''];
        }

        // 感情状态·交友目的
        $relMap    = [1 => '单身', 2 => '已婚', 3 => '离异', 4 => '丧偶'];
        $datingMap = [1 => '找对象', 2 => '交朋友', 3 => '不交朋友'];
        if (!empty($profile['relationship_status'])) {
            $rel    = $relMap[$profile['relationship_status']] ?? '';
            $dating = !empty($profile['dating_purpose']) ? ($datingMap[$profile['dating_purpose']] ?? '') : '';
            $items[] = ['icon' => 'icon-ic_love',          'text' => $rel . ($dating ? '·' . $dating : '')];
        }

        // 车辆·房产
        $carHouse = [];
        if (!empty($profile['car_count']))   $carHouse[] = $profile['car_count'] . '辆车';
        if (!empty($profile['house_count'])) $carHouse[] = $profile['house_count'] . '套房';
        if ($carHouse) {
            $items[] = ['icon' => 'icon-ic_home',          'text' => implode('·', $carHouse)];
        }

        // 总资产
        $assetsMap = [1 => '100万以下', 2 => '100-300万', 3 => '300万以上'];
        if (!empty($profile['total_assets'])) {
            $items[] = ['icon' => 'icon-ic_gold',          'text' => $assetsMap[$profile['total_assets']] ?? ''];
        }

        return array_values(array_filter($items));
    }

    private function getZodiac(string $birthday): string
    {
        $m = (int) date('n', strtotime($birthday));
        $d = (int) date('j', strtotime($birthday));
        // 每月星座切换日（当月 >= 该日则为下一个星座）
        $cutoff  = [20, 19, 21, 20, 21, 22, 23, 23, 23, 24, 23, 22];
        $zodiacs = ['摩羯座', '水瓶座', '双鱼座', '白羊座', '金牛座', '双子座',
                    '巨蟹座', '狮子座', '处女座', '天秤座', '天蝎座', '射手座', '摩羯座'];
        return $d >= $cutoff[$m - 1] ? $zodiacs[$m] : $zodiacs[$m - 1];
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

        $this->refreshDialogRelation($uid, $id);

        if ($status) {
            $brief = \app\common\repositories\user\UserNotificationRepository::latestNoteBrief($uid);
            $content = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
                'community_id' => $brief['community_id'],
                'title' => $brief['title'],
                'image' => $brief['image'],
            ]);
            try {
                app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                    ->createAndPush($id, $uid, 'follow', '新关注', $content, 'user', $uid);
            } catch (\Throwable $e) {
            }
        }

        return;
    }

    /**
     * 喜欢/取消喜欢某用户
     */
    public function setLike(int $likedUid, int $myUid, int $status)
    {
        $make = app()->make(RelevanceRepository::class);
        if ($status) {
            if ($make->checkHas($myUid, $likedUid, RelevanceRepository::TYPE_USER_LIKE)) {
                throw new ValidateException('您已经喜欢过他了～');
            }
            $make->create($myUid, $likedUid, RelevanceRepository::TYPE_USER_LIKE, false);
        } else {
            if (!$make->checkHas($myUid, $likedUid, RelevanceRepository::TYPE_USER_LIKE)) {
                throw new ValidateException('您还未喜欢他哦～');
            }
            $make->destory($myUid, $likedUid, RelevanceRepository::TYPE_USER_LIKE);
        }
    }

    /**
     * 关注变更后刷新双方会话 relation_type
     */
    protected function refreshDialogRelation(int $uid1, int $uid2): void
    {
        try {
            $uidA = min($uid1, $uid2);
            $uidB = max($uid1, $uid2);
            $dialog = Db::name('user_dialog')->where('uid_a', $uidA)->where('uid_b', $uidB)->find();
            if (!$dialog) {
                return;
            }
            $relationType = app()->make(\app\common\dao\user\UserDialogDao::class)
                ->calcRelationType($uidA, $uidB);
            if ((int)$dialog['relation_type'] !== $relationType) {
                Db::name('user_dialog')->where('dialog_id', $dialog['dialog_id'])
                    ->update(['relation_type' => $relationType]);
            }
        } catch (\Throwable $e) {
        }
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

            $brief = \app\common\repositories\user\UserNotificationRepository::noteBriefById($id);
            $content = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
                'community_id' => $id,
                'title' => $brief['title'],
                'image' => $brief['image'],
            ]);
            try {
                app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                    ->createAndPush((int)$ret['uid'], (int)$userInfo->uid, 'star', '笔记被点赞', $content, 'community', $id);
            } catch (\Throwable $e) {
            }
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
     * 笔记收藏 / 取消收藏
     */
    public function setCommunityCollect(int $id, int $uid, int $status): void
    {
        if (!$this->isApprove($id) && !$this->dao->uidExists($id, $uid)) {
            throw new ValidateException('内容不存在或未审核通过');
        }
        $make = app()->make(RelevanceRepository::class);
        $check = $make->checkHas($uid, $id, RelevanceRepository::TYPE_COMMUNITY_COLLECT);
        $ret = $this->dao->get($id);
        if (!$ret) {
            throw new ValidateException('内容不存在');
        }

        if ($status) {
            if ($check) {
                throw new ValidateException('您已经收藏过了');
            }
            $make->create($uid, $id, RelevanceRepository::TYPE_COMMUNITY_COLLECT, true);
            $brief = \app\common\repositories\user\UserNotificationRepository::noteBriefById($id);
            $content = \app\common\repositories\user\UserNotificationRepository::buildNoteContent([
                'community_id' => $id,
                'title' => $brief['title'],
                'image' => $brief['image'],
            ]);
            try {
                app()->make(\app\common\repositories\user\UserNotificationRepository::class)
                    ->createAndPush((int)$ret['uid'], $uid, 'bookmark', '笔记被收藏', $content, 'community', $id);
            } catch (\Throwable $e) {
            }
        } else {
            if (!$check) {
                throw new ValidateException('您还未收藏');
            }
            $make->destory($uid, $id, RelevanceRepository::TYPE_COMMUNITY_COLLECT);
        }
    }

    public function isCollected(int $communityId, int $uid): bool
    {
        if ($uid <= 0) {
            return false;
        }
        return app()->make(RelevanceRepository::class)
            ->checkHas($uid, $communityId, RelevanceRepository::TYPE_COMMUNITY_COLLECT) > 0;
    }

    public function collectCount(int $communityId): int
    {
        return (int)app()->make(RelevanceRepository::class)
            ->getFieldCount('right_id', $communityId, RelevanceRepository::TYPE_COMMUNITY_COLLECT);
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

