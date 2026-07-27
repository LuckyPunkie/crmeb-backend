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

use app\common\dao\community\CommunityTopicDao;
use app\common\dao\community\CommunityTopicRelDao;
use app\common\model\community\CommunityTopic;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * 社区话题
 */
class CommunityTopicRepository extends BaseRepository
{
    /**
     * @var CommunityTopicDao
     */
    protected $dao;

    /**
     * CommunityTopicRepository constructor.
     * @param CommunityTopicDao $dao
     */
    public function __construct(CommunityTopicDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建或编辑社区话题表单
     *
     * 根据传入的$id$判断是创建新话题还是编辑已有的话题。如果$id$为空，则创建新话题；
     * 否则，根据$id$查询已有的话题信息，并进行编辑。
     *
     * @param int|null $id 话题ID，如果为null则表示创建新话题，否则表示编辑已有的话题。
     * @return \Encore\Admin\Widgets\Form 表单实例，包含表单的规则和数据。
     */
    public function form(?int $id)
    {
        // 初始化表单数据数组
        $formData = [];

        // 判断$id$是否为空，如果为空则创建新话题，否则编辑已有的话题
        if (!$id) {
            // 创建新话题的表单，表单提交地址为系统生成的创建话题的URL
            $form = Elm::createForm(Route::buildUrl('systemCommunityTopicCreate')->build());
        } else {
            // 根据$id$查询已有的话题信息，并转换为数组格式
            $formData = $this->dao->get($id)->toArray();
            // 创建编辑话题的表单，表单提交地址为系统生成的更新话题的URL，包含话题ID
            $form = Elm::createForm(Route::buildUrl('systemCommunityTopicUpdate', ['id' => $id])->build());
        }

        // 配置表单的规则和字段
        $form->setRule([
            // 社区分类选择字段，动态获取分类选项，必选
            Elm::select('category_id', '社区分类：')->options(function () {
                return app()->make(CommunityCategoryRepository::class)->options();
            })->placeholder('请选择社区分类')->requiredNum('请选择社区分类'),
            // 图标选择字段，使用iframe嵌入式选择图片，必选
            Elm::frameImage('pic', '图标：', '/' . config('admin.admin_prefix') . '/setting/uploadPicture?field=pic&type=1')
                ->modal(['modal' => false])
                ->icon('el-icon-camera')
                ->width('896px')
                ->height('480px'),
            // 话题名称输入字段，必填
            Elm::input('topic_name', '社区话题：')->placeholder('请输入社区话题')->required('请输入社区话题'),
            // 是否显示开关，默认开启，可切换
            Elm::switches('status', '是否显示：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 是否推荐开关，默认开启，可切换
            Elm::switches('is_hot', '是否推荐：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            // 排序数字输入字段，最大值99999，精度为0
            Elm::number('sort', '排序：')->precision(0)->max(99999),
        ]);

        // 设置表单标题，根据$id$是否为空决定是添加话题还是编辑话题
        // 设置表单数据，如果$id$不为空，则使用查询到的话题数据填充表单
        return $form->setTitle(is_null($id) ? '添加话题' : '编辑话题')->formData($formData);
    }

    /**
     * 删除指定ID的社区帖子
     *
     * 此函数用于删除社区帖子，但首先它会检查该帖子下是否还有显示中的数据。
     * 如果存在显示中的数据，则抛出一个验证异常，防止删除操作继续进行。
     * 这种做法是为了确保不会意外删除那些仍然有活跃数据的帖子，从而影响用户体验或数据完整性。
     *
     * @param int $id 要删除的帖子的ID
     * @throws ValidateException 如果帖子下存在显示中的数据，则抛出此异常
     */
    public function delete(int $id)
    {
        // 通过依赖注入获取CommunityRepository实例
        $make = app()->make(CommunityRepository::class);
        // 检查帖子下是否存在显示中的数据
        if ( $make->getWhereCount(CommunityRepository::IS_SHOW_WHERE) ) {
            throw new ValidateException('该话题下存在数据');
        }
        // 如果没有显示中的数据，执行删除操作
        $this->dao->delete($id);
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件、分页和限制从数据库获取列表数据。它首先应用条件查询，其中包括一个固定的条件（is_del = 0），
     * 然后按照排序规则获取数据的总数和实际数据。最后，它以数组的形式返回数据总数和数据列表。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据的数量
     * @return array 包含数据总数和数据列表的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 将删除标记设置为0，确保只获取未被删除的数据
        $where['is_del'] = 0;

        // 初始化查询，应用条件、关联加载和排序规则
        $query = $this->dao->getSearch($where)->with(['category'])
            ->order('sort DESC,create_time DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据数量进行分页查询，并获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count','list');
    }

    /**
     *  获取推荐的话题
     * @return array
     * @author Qinii
     * @day 10/27/21
     */
    public function getHotList()
    {
        $list = $this->dao->getSearch([
            'is_hot' => 1,
            'status' => 1,
            'is_del' => 0
        ])
            ->setOption('field',[])->field('category_id,topic_name,topic_id,pic,count_view,count_use')
            ->order('create_time DESC')->select();

        return compact('list');
    }

    /**
     * 统计话题被使用数量
     * @param int|null $id
     * @author Qinii
     * @day 11/3/21
     */
    public function sumCountUse(?int $id)
    {
        if (!$id) {
            $id = $this->dao->getSearch(['status' => 1, 'is_del' => 0])->column('topic_id');
        } else {
            $id = [$id];
        }
        $relDao = app()->make(CommunityTopicRelDao::class);
        $communityRepo = app()->make(CommunityRepository::class);
        foreach ($id as $item) {
            $relCommunityIds = $relDao->communityIdsByTopicIds([(int)$item]);
            $count = $communityRepo->getSearch(CommunityRepository::IS_SHOW_WHERE)
                ->where(function ($q) use ($item, $relCommunityIds) {
                    $q->where('topic_id', $item);
                    if ($relCommunityIds) {
                        $q->whereOr('community_id', 'in', $relCommunityIds);
                    }
                })
                ->count();
            $this->dao->update($item, ['count_use' => $count]);
        }
    }

    /**
     * 规范化话题名
     */
    public function normalizeName(string $name): string
    {
        $name = trim($name);
        $name = ltrim($name, "#＃");
        $name = preg_replace('/\s+/u', '', $name);
        $name = mb_substr((string)$name, 0, 20, 'utf-8');
        return trim((string)$name);
    }

    /**
     * 从正文解析话题名（#话题）
     * @return string[]
     */
    public function parseNamesFromContent(string $content): array
    {
        if ($content === '') {
            return [];
        }
        if (!preg_match_all('/[#＃]([^\s#＃]{1,20})/u', $content, $matches)) {
            return [];
        }
        $names = [];
        foreach ($matches[1] as $raw) {
            $name = $this->normalizeName($raw);
            if ($name !== '') {
                $names[] = $name;
            }
        }
        return array_values(array_unique($names));
    }

    /**
     * 按文字查找或创建话题（同名复用）
     * 若话题名命中首页类别标签，自动绑定对应 category_id
     */
    public function findOrCreateByName(string $name): CommunityTopic
    {
        $name = $this->normalizeName($name);
        if ($name === '') {
            throw new ValidateException('话题名称不能为空');
        }

        $matchedCategoryId = $this->matchCategoryIdByTopicName($name);

        $topic = CommunityTopic::getDB()
            ->where('topic_name', $name)
            ->where('is_del', 0)
            ->find();
        if ($topic) {
            // 历史自由话题补绑类别
            if ($matchedCategoryId > 0 && (int)$topic['category_id'] <= 0) {
                $this->dao->update((int)$topic['topic_id'], ['category_id' => $matchedCategoryId]);
                $topic['category_id'] = $matchedCategoryId;
            }
            return $topic;
        }

        $deleted = CommunityTopic::getDB()
            ->where('topic_name', $name)
            ->where('is_del', 1)
            ->find();
        if ($deleted) {
            $this->dao->update((int)$deleted['topic_id'], [
                'is_del' => 0,
                'status' => 1,
                'category_id' => $matchedCategoryId > 0 ? $matchedCategoryId : (int)$deleted['category_id'],
            ]);
            return $this->dao->get((int)$deleted['topic_id']);
        }

        return $this->dao->create([
            'topic_name' => $name,
            'category_id' => $matchedCategoryId,
            'status' => 1,
            'is_hot' => 0,
            'is_del' => 0,
            'pic' => '',
            'count_use' => 0,
            'count_view' => 0,
            'sort' => 0,
        ]);
    }

    /**
     * 话题名命中首页类别标签时返回 category_id（精确优先，其次包含）
     */
    public function matchCategoryIdByTopicName(string $name): int
    {
        $name = $this->normalizeName($name);
        if ($name === '') {
            return 0;
        }
        $list = Db::name('community_category')
            ->where('is_show', 1)
            ->field('category_id,cate_name,tab_key')
            ->order('sort DESC,category_id ASC')
            ->select()
            ->toArray();
        if (!$list) {
            return 0;
        }
        // 1) 精确等于类别名
        foreach ($list as $cate) {
            $cateName = trim((string)($cate['cate_name'] ?? ''));
            if ($cateName !== '' && $cateName === $name) {
                return (int)$cate['category_id'];
            }
        }
        // 2) 别名精确命中
        $aliasMap = [
            '找搭子' => 'partner',
            '搭子' => 'partner',
            '红包' => 'rp_help',
        ];
        if (isset($aliasMap[$name])) {
            foreach ($list as $cate) {
                if (($cate['tab_key'] ?? '') === $aliasMap[$name]) {
                    return (int)$cate['category_id'];
                }
            }
        }
        // 3) 话题名包含类别名（如 #公益活动 → 公益）
        foreach ($list as $cate) {
            $cateName = trim((string)($cate['cate_name'] ?? ''));
            if ($cateName !== '' && mb_strpos($name, $cateName) !== false) {
                return (int)$cate['category_id'];
            }
        }
        return 0;
    }

    /**
     * 批量 findOrCreate，最多 10 个
     * @param array $names
     * @return CommunityTopic[]
     */
    public function findOrCreateByNames(array $names): array
    {
        $result = [];
        $seen = [];
        foreach ($names as $name) {
            $normalized = $this->normalizeName((string)$name);
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $result[] = $this->findOrCreateByName($normalized);
            if (count($result) >= 10) {
                break;
            }
        }
        return $result;
    }

    /**
     * 从请求数据解析话题列表（topic_names / content / topic_id）
     * @return CommunityTopic[]
     */
    public function resolveTopicsFromPayload(array $data): array
    {
        $names = [];
        if (!empty($data['topic_names'])) {
            $raw = $data['topic_names'];
            if (is_string($raw)) {
                $raw = array_filter(explode(',', $raw));
            }
            if (is_array($raw)) {
                $names = array_merge($names, $raw);
            }
        }
        if (!empty($data['content'])) {
            $names = array_merge($names, $this->parseNamesFromContent((string)$data['content']));
        }
        if (!empty($data['free_content'])) {
            $names = array_merge($names, $this->parseNamesFromContent((string)$data['free_content']));
        }
        if (!empty($data['topic_id'])) {
            $legacy = $this->dao->get((int)$data['topic_id']);
            if ($legacy && !(int)$legacy['is_del']) {
                $names[] = $legacy['topic_name'];
            }
        }
        return $this->findOrCreateByNames($names);
    }

    /**
     * 同步笔记话题关联，并刷新 count_use
     */
    public function syncCommunityTopics(int $communityId, array $topicIds): void
    {
        $relDao = app()->make(CommunityTopicRelDao::class);
        $oldIds = Db::name('community_topic_rel')->where('community_id', $communityId)->column('topic_id') ?: [];
        $relDao->sync($communityId, $topicIds);
        foreach (array_unique(array_merge($oldIds, $topicIds)) as $tid) {
            if ($tid) {
                $this->sumCountUse((int)$tid);
            }
        }
    }

    /**
     * 获取可选选项
     * 此方法用于查询数据库中符合条件的记录，并将其转换为特定格式的数据，以供前端选择组件使用。
     * 查询条件包括状态为激活且未被删除的记录，排序依据是排序值降序和创建时间降序。
     * 返回的数据格式为键值对，其中键为topic_id，值为topic_name。
     *
     * @return array 返回查询结果转换后的数组，如果无数据则返回空数组。
     */
    public function options()
    {
        // 查询数据库，获取状态为1，且is_del为0的记录，按sort和create_time降序排列
        // 选择字段为topic_id和topic_name，重命名为value和label
        $data =  $this->dao->getSearch(['status' => 1,'is_del' =>0])->order('sort DESC,create_time DESC')
            ->field('topic_id as value,topic_name as label')->select();

        // 如果查询到数据，将其转换为数组形式
        if ($data) $res = $data->toArray();

        // 返回转换后的数据数组
        return $res;
    }

}

