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


namespace app\common\dao\community;


use app\common\dao\BaseDao;
use app\common\model\community\Community;
use app\common\model\system\Relevance;
use app\common\repositories\system\RelevanceRepository;

class CommunityDao extends BaseDao
{

    /**
     * @return Community
     *
     * @date 2023/10/21
     * @author yyw
     */
    protected function getModel(): string
    {
        return Community::class;
    }

    /**
     * 搜索社区帖子
     * @param array $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function search(array $where)
    {
        $query = Community::hasWhere('author', function($query) use ($where){
            $query->when(isset($where['username']) && $where['username'] !==  '', function ($query) use($where) {
                $query->whereLike('real_name|phone|nickname',"%{$where['username']}%");
            })
                ->when(isset($where['phone']) && $where['phone'] !==  '', function ($query) use($where) {
                    $query->whereLike('phone',"%{$where['phone']}%");
                })
                ->when(isset($where['real_name']) && $where['real_name'] !==  '', function ($query) use($where) {
                    $query->whereLike('real_name',"%{$where['real_name']}%");
                })
                ->when(isset($where['nickname']) && $where['nickname'] !==  '', function ($query) use($where) {
                    $query->whereLike('nickname',"%{$where['nickname']}%");
                })
                ->when(isset($where['is_news_bot']) && $where['is_news_bot'] !== '', function ($query) use ($where) {
                    // category_topic_or 时在类别条件里 OR 处理，避免这里 AND
                    if (!empty($where['category_topic_or'])) {
                        return;
                    }
                    $query->where('is_news_bot', intval($where['is_news_bot']));
                })
                ->when(isset($where['sex']) && $where['sex'] !== '' && intval($where['sex']) > 0, function ($query) use ($where) {
                    $query->where('sex', intval($where['sex']));
                })
                ->when((!empty($where['age_min']) || !empty($where['age_max'])), function ($query) use ($where) {
                    $ageMin = max(0, intval($where['age_min'] ?? 0));
                    $ageMax = max(0, intval($where['age_max'] ?? 0));
                    $birthdayStart = $ageMax > 0 ? date('Y-m-d', strtotime('-' . $ageMax . ' years')) : null;
                    $birthdayEnd = $ageMin > 0 ? date('Y-m-d', strtotime('-' . $ageMin . ' years')) : null;
                    // 无生日放行；有生日则按区间
                    $query->where(function ($q) use ($birthdayStart, $birthdayEnd) {
                        $q->where(function ($q2) {
                            $q2->whereNull('birthday')->whereOr('birthday', '')->whereOr('birthday', '0000-00-00');
                        })->whereOr(function ($q2) use ($birthdayStart, $birthdayEnd) {
                            $q2->whereNotNull('birthday')->where('birthday', '<>', '')->where('birthday', '<>', '0000-00-00');
                            if ($birthdayStart) $q2->where('birthday', '>=', $birthdayStart);
                            if ($birthdayEnd) $q2->where('birthday', '<=', $birthdayEnd);
                        });
                    });
                })
                ->when(isset($where['education']) && $where['education'] !== '', function ($query) use ($where) {
                    $educations = is_array($where['education'])
                        ? $where['education']
                        : array_filter(explode(',', (string)$where['education']));
                    if ($educations) {
                        $uids = \think\facade\Db::name('user_profile')->whereIn('education', $educations)->column('uid');
                        $query->whereIn('uid', $uids ?: [0]);
                    }
                });
            $query->where(true);
        });
        $query->when(isset($where['search_type']) && $where['search_type'] !== '', function ($query) use ($where) {
                if(isset($where['keyword']) && $where['keyword'] !==  ''){
                    if($where['search_type'] == 'all'){
                        $query->whereLike('title|content|User.nickname',"%{$where['keyword']}%");
                    }
                    if($where['search_type'] == 'content'){
                        $query->whereLike('title|content',"%{$where['keyword']}%");
                    }
                    if($where['search_type'] == 'user'){
                        $query->whereLike('User.nickname',"%{$where['keyword']}%");
                    }
                }
            },function ($query) use ($where) {   // 兼容之前逻辑
                if(isset($where['keyword']) && $where['keyword'] !==  ''){
                    $query->whereLike('title',"%{$where['keyword']}%");
                }
            })
//            ->when(isset($where['keyword']) && $where['keyword'] !==  '', function ($query) use($where) {
//                $query->whereLike('title',"%{$where['keyword']}%");
//            })
            ->when(isset($where['uid']) && $where['uid'] !==  '', function ($query) use($where) {
                $query->where('uid',$where['uid']);
            })
            ->when(isset($where['uids']) && $where['uids'] !==  '', function ($query) use($where) {
                $query->whereIn('uid',$where['uids']);
            })
            ->when(isset($where['topic_id']) && $where['topic_id'] !==  '', function ($query) use($where) {
                $tid = $where['topic_id'];
                $relIds = \think\facade\Db::name('community_topic_rel')->where('topic_id', $tid)->column('community_id') ?: [];
                $query->where(function ($q) use ($tid, $relIds) {
                    $q->where('Community.topic_id', $tid);
                    if ($relIds) {
                        $q->whereOr('Community.community_id', 'in', $relIds);
                    }
                });
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !==  '', function ($query) use($where) {
                $query->where('mer_id',$where['mer_id']);
            })
            ->when(isset($where['community_id']) && $where['community_id'] !==  '', function ($query) use($where) {
                $query->where('community_id',$where['community_id']);
            })
            ->when(isset($where['not_id']) && $where['not_id'] !==  '', function ($query) use($where) {
                $query->whereNotIn('community_id',$where['not_id']);
            })
            ->when(isset($where['in_id']) && $where['in_id'] !==  '', function ($query) use($where) {
                $query->whereOr(function($query) use($where){
                    $query->whereIn('community_id',$where['in_id']);
                });
            })
            ->when(isset($where['community_ids']) && $where['community_ids'] !==  '', function ($query) use($where) {
                $query->whereIn('community_id',$where['community_ids']);
            })
            ->when(isset($where['is_type']) && $where['is_type'] !==  '', function ($query) use($where) {
                $query->whereIn('is_type',$where['is_type']);
            })
            ->when(isset($where['community_type']) && $where['community_type'] !==  '', function ($query) use($where) {
                // category_topic_or 已在类别条件里 OR 处理
                if (!empty($where['category_topic_or'])) {
                    return;
                }
                if (is_array($where['community_type'])) {
                    $query->whereIn('community_type', $where['community_type']);
                } else {
                    $query->where('community_type', $where['community_type']);
                }
            })
            ->when(isset($where['is_show']) && $where['is_show'] !==  '', function ($query) use($where) {
                $query->where('is_show',$where['is_show']);
            })
            ->when(isset($where['status']) && $where['status'] !==  '', function ($query) use($where) {
                $query->where('status',$where['status']);
            })
            ->when(isset($where['start']) && $where['start'] !==  '', function ($query) use($where) {
                $query->where('start',$where['start']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !==  '', function ($query) use($where) {
                $query->where('Community.is_del',$where['is_del']);
            })
            ->when(isset($where['category_id']) && $where['category_id'] !==  '', function ($query) use($where) {
                // 话题命中类别标签（可多话题命中多个类别）
                if (!empty($where['category_topic_match']) || !empty($where['category_topic_or'])) {
                    $cid = (int)$where['category_id'];
                    [$topicIds, $relIds] = $this->resolveCategoryTopicHits($cid);
                    $orType = $where['_or_community_type'] ?? null;
                    $orHasSpu = isset($where['_or_has_spu']) ? intval($where['_or_has_spu']) : 0;
                    $spuIds = [];
                    if ($orHasSpu === 1) {
                        $spuIds = Relevance::where('type', RelevanceRepository::TYPE_COMMUNITY_PRODUCT)->column('left_id') ?: [];
                    }
                    $query->where(function ($q) use ($cid, $topicIds, $relIds, $orType, $orHasSpu, $spuIds, $where) {
                        // 话题命中分支
                        $q->where(function ($q2) use ($cid, $topicIds, $relIds) {
                            $q2->where('Community.category_id', $cid);
                            if ($topicIds) {
                                $q2->whereOr('Community.topic_id', 'in', $topicIds);
                            }
                            if ($relIds) {
                                $q2->whereOr('Community.community_id', 'in', $relIds);
                            }
                        });
                        // 招聘/红包/商品等：类型条件与话题命中取 OR
                        if (!empty($where['category_topic_or'])) {
                            if ($orType !== null && $orType !== '') {
                                $q->whereOr('Community.community_type', $orType);
                            }
                            if ($orHasSpu === 1) {
                                $q->whereOr('Community.community_id', 'in', $spuIds ?: [0]);
                            }
                        }
                    });
                    return;
                }
                $query->where('category_id',$where['category_id']);
            })
            ->when(isset($where['spu_id']) && $where['spu_id'] !==  '', function ($query) use($where) {
                $id = Relevance::where('right_id', $where['spu_id'])
                    ->where('type',RelevanceRepository::TYPE_COMMUNITY_PRODUCT)
                    ->column('left_id');
                $query->where('community_id','in', $id);
            })
            ->when(isset($where['has_spu']) && $where['has_spu'] !== '', function ($query) use ($where) {
                // category_topic_or 已在类别条件里 OR 处理
                if (!empty($where['category_topic_or'])) {
                    return;
                }
                if (intval($where['has_spu']) === 1) {
                    $ids = Relevance::where('type', RelevanceRepository::TYPE_COMMUNITY_PRODUCT)->column('left_id');
                    $query->whereIn('community_id', $ids ?: [0]);
                }
            })
            ->when(isset($where['topic_name']) && $where['topic_name'] !== '', function ($query) use ($where) {
                $keywords = is_array($where['topic_name']) ? $where['topic_name'] : [$where['topic_name']];
                $topicIds = \app\common\model\community\CommunityTopic::where(function ($q) use ($keywords) {
                    foreach ($keywords as $i => $kw) {
                        $kw = trim((string)$kw);
                        if ($kw === '') continue;
                        if ($i === 0) {
                            $q->whereLike('topic_name', "%{$kw}%");
                        } else {
                            $q->whereOr('topic_name', 'like', "%{$kw}%");
                        }
                    }
                })->where('is_del', 0)->column('topic_id');
                $topicIds = $topicIds ?: [0];
                $relIds = \think\facade\Db::name('community_topic_rel')->whereIn('topic_id', $topicIds)->column('community_id') ?: [];
                $query->where(function ($q) use ($topicIds, $relIds) {
                    $q->whereIn('Community.topic_id', $topicIds);
                    if ($relIds) {
                        $q->whereOr('Community.community_id', 'in', $relIds);
                    }
                });
            })
            ->when(isset($where['topic_keywords']) && $where['topic_keywords'] !== '', function ($query) use ($where) {
                $keywords = is_array($where['topic_keywords'])
                    ? $where['topic_keywords']
                    : array_filter(explode(',', (string)$where['topic_keywords']));
                if (!$keywords) return;
                $topicIds = \app\common\model\community\CommunityTopic::where(function ($q) use ($keywords) {
                    foreach ($keywords as $i => $kw) {
                        $kw = trim((string)$kw);
                        if ($kw === '') continue;
                        if ($i === 0) {
                            $q->whereLike('topic_name', "%{$kw}%");
                        } else {
                            $q->whereOr('topic_name', 'like', "%{$kw}%");
                        }
                    }
                })->where('is_del', 0)->column('topic_id');
                $topicIds = $topicIds ?: [0];
                $relIds = \think\facade\Db::name('community_topic_rel')->whereIn('topic_id', $topicIds)->column('community_id') ?: [];
                $query->where(function ($q) use ($topicIds, $relIds) {
                    $q->whereIn('Community.topic_id', $topicIds);
                    if ($relIds) {
                        $q->whereOr('Community.community_id', 'in', $relIds);
                    }
                });
            })
            ->when(isset($where['city']) && $where['city'] !== '', function ($query) use ($where) {
                $uids = \think\facade\Db::name('user_profile')
                    ->where('current_city', $where['city'])
                    ->column('uid');
                $query->whereIn('uid', $uids ?: [0]);
            });

        if (isset($where['order']) && $where['order'] == 'start') {
            $query->orderRaw('`Community`.`start` DESC, `Community`.`create_time` DESC');
        } else {
            $query->orderRaw('`Community`.`create_time` DESC');
        }
        return $query;
    }

    /**
     * 解析「话题命中某首页类别」对应的话题与笔记 ID
     * - 话题 category_id = 类别
     * - 或话题名包含类别名/别名（如 搭子 → 找搭子）
     * @return array{0:int[],1:int[]} [topicIds, communityIdsFromRel]
     */
    public function resolveCategoryTopicHits(int $categoryId): array
    {
        if ($categoryId <= 0) {
            return [[], []];
        }
        $cate = \think\facade\Db::name('community_category')->where('category_id', $categoryId)->find();
        if (!$cate) {
            return [[], []];
        }
        $keywords = [];
        $cateName = trim((string)($cate['cate_name'] ?? ''));
        if ($cateName !== '') {
            $keywords[] = $cateName;
        }
        $tabKey = (string)($cate['tab_key'] ?? '');
        $aliases = [
            'partner' => ['找搭子', '搭子'],
            'welfare' => ['公益'],
            'rescue' => ['救助'],
            'adopt' => ['领养'],
            'recruit' => ['招聘'],
            'rp_task' => ['红包任务', '红包'],
            'rp_help' => ['红包求助', '红包'],
            'goods' => ['商品'],
            'news' => ['资讯'],
        ];
        if (!empty($aliases[$tabKey])) {
            $keywords = array_merge($keywords, $aliases[$tabKey]);
        }
        $keywords = array_values(array_unique(array_filter(array_map(function ($kw) {
            return trim((string)$kw);
        }, $keywords))));

        $topicIds = \app\common\model\community\CommunityTopic::where('is_del', 0)
            ->where(function ($q) use ($categoryId, $keywords) {
                $q->where('category_id', $categoryId);
                foreach ($keywords as $kw) {
                    if ($kw === '') continue;
                    $q->whereOr('topic_name', 'like', "%{$kw}%");
                }
            })
            ->column('topic_id');
        $topicIds = array_values(array_unique(array_filter(array_map('intval', $topicIds ?: []))));
        $relIds = [];
        if ($topicIds) {
            $relIds = \think\facade\Db::name('community_topic_rel')
                ->whereIn('topic_id', $topicIds)
                ->column('community_id') ?: [];
            $relIds = array_values(array_unique(array_filter(array_map('intval', $relIds))));
        }
        return [$topicIds, $relIds];
    }

    /**
     * 查询用户是否发过帖子
     * @param int $id
     * @param int $uid
     * @return bool
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function uidExists(int $id, int $uid)
    {
        return $this->getModel()::getDb()->where('uid',$uid)->where($this->getPk(),$id)->count() > 0;
    }

    /**
     * id查询帖子是否存在
     * @param int $id
     * @return bool
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function exists(int $id)
    {
        return $this->getModel()::getDb()->where('is_del',0)->where($this->getPk(),$id)->count() > 0;
    }

    /**
     * 删除某个用户的帖子
     * @param $uid
     * @return int
     * @throws \think\db\exception\DbException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function destoryByUid($uid)
    {
        return $this->getModel()::getDb()->where('uid' ,$uid)->update(['is_del' =>  1]);
    }

    /**
     * 关联用户
     * @param $where
     * @return \think\db\BaseQuery
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    public function joinUser($where)
    {
        return Community::hasWhere('relevanceRight',function($query) use($where){
            $query->where('type',RelevanceRepository::TYPE_COMMUNITY_START)->where('left_id',$where['uid']);
        })
            ->when(isset($where['is_type']) && $where['is_type'] !==  '', function ($query) use($where) {
                $query->whereIn('Community.is_type',$where['is_type']);
            })
            ->when(isset($where['is_show']) && $where['is_show'] !==  '', function ($query) use($where) {
                $query->where('Community.is_show',$where['is_show']);
            })
            ->when(isset($where['status']) && $where['status'] !==  '', function ($query) use($where) {
                $query->where('Community.status',$where['status']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !==  '', function ($query) use($where) {
                $query->where('Community.is_del',$where['is_del']);
            });
    }

    /**
     * 统计每个用户的帖子数量
     * @return mixed
     *
     * @date 2023/10/21
     * @author yyw
     */
    public function getCountByGroupUid()
    {
        return $this->getModel()::getDb()->where('is_del', 0)->field('uid,count(community_id) as count')->group('uid')->select()->toArray();
    }

    public function isApprove(int $id)
    {
        return $this->getModel()::getDb()->where('is_del',0)->where('status',1)->where($this->getPk(),$id)->count() > 0;
    }
}
