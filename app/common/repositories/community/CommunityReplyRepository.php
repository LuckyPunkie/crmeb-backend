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

use app\common\dao\community\CommunityReplyDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\RelevanceRepository;
use Carbon\Exceptions\InvalidDateException;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * 社区评论
 */
class CommunityReplyRepository extends BaseRepository
{
    /**
     * @var CommunityReplyDao
     */
    protected $dao;

    /**
     * CommunityReplyRepository constructor.
     * @param CommunityReplyDao $dao
     */
    public function __construct(CommunityReplyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表数据
     *
     * 根据给定的条件和分页信息，从数据库中检索满足条件的数据列表。此方法主要用于查询文章或帖子等信息，
     * 同时附带作者、社区和回复等相关信息，以丰富的数据集合形式返回。
     *
     * @param array $where 查询条件数组，用于指定查询的数据项。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据的数量，用于分页查询。
     * @return array 返回包含总数和列表数据的数组。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 默认不查询已删除的数据
        $where['is_del'] = 0;

        // 构建查询，同时加载关联信息，如社区、作者和回复等
        $query = $this->dao->search($where)->with([
            'community' => function ($query) {
                // 加载社区的基本信息，包括社区ID和标题
                $query->field('community_id,title');
            },
            'author' => function ($query) {
                // 加载作者的信息，包括用户ID、昵称和头像
                $query->field('uid,nickname,avatar');
            },
            'reply' => function ($query) {
                // 加载第一条回复的信息，包括用户ID、昵称和头像
                $query->field('uid,nickname,avatar');
            },
            'hasReply' => function ($query) {
                // 加载是否含有回复的信息，包括回复的父ID、回复ID和状态
                $query->field('pid, reply_id, status');
            },
        ]);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据数量，获取满足条件的数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表打包成数组返回
        return compact('count', 'list');
    }


    /**
     * 获取API列表
     * 根据给定的条件和分页信息，获取社区内容的列表。
     * 这包括对内容存在性的验证，以及根据用户信息和条件进行的特定查询设置。
     *
     * @param array $where 查询条件数组，包含社区ID等条件
     * @param int $page 当前页码
     * @param int $limit 每页显示的数量
     * @param object $userInfo 用户信息对象，包含用户ID等
     * @return array 返回包含所有、开始、计数和列表的数组
     * @throws ValidateException 如果内容不存在则抛出异常
     */
    public function getApiList(array $where, int $page, int $limit, $userInfo)
    {
        // 实例化社区仓库类
        $make = app()->make(CommunityRepository::class);

        // 设置显示状态的查询条件
        $where_ = CommunityRepository::IS_SHOW_WHERE;
        // 初始化查询条件中的社区ID
        $where_['community_id'] = $where['community_id'];

        // 如果用户信息存在，并且用户ID存在于指定的社区中，则修改查询条件
        if ($userInfo && $make->uidExists((int)$where['community_id'], $userInfo->uid)) {
            $where_ = ['is_del' => 0];
        }

        // 重新设置社区ID的查询条件
        $where_['community_id'] = $where['community_id'];

        // 验证内容是否存在
        if (!$make->getWhereCount($where_)) {
            throw new ValidateException('内容不存在，可能被删删除了哦～');
        }

        // 设置查询状态为已发布
        $where['status'] = 1;
        // 计算符合条件的总内容数量
        $all = $this->dao->getSearch($where)->count();
        // 计算已开始的内容数量
        $start = $this->dao->getSearch($where)->sum('count_start');
        // 设置查询父级内容的条件
        $where['pid'] = 0;

        // 设置查询顺序和隐藏字段，并加载关联数据
        $query = $this->dao->getSearch($where)
            ->order('create_time DESC')
            ->hidden(['refusal'])
            ->with([
                'author' => function ($query) {
                    $query->field('uid,nickname,avatar');
                },
                'is_start' => function ($query) use ($userInfo) {
                    $query->where('left_id', $userInfo->uid ?? null);
                },
                'children' => [
                    'author' => function ($query) {
                        $query->field('uid,nickname,avatar');
                    },
                    'reply' => function ($query) {
                        $query->field('uid,nickname,avatar')->order('create_time ASC');
                    },
                    'is_start' => function ($query) use ($userInfo) {
                        $query->where('left_id', $userInfo->uid ?? null);
                    }
                ],
            ]);

        // 计算查询结果的总数量
        $count = $query->count();
        // 获取当前页的列表数据
        $list = $query->page($page, $limit)->select();

        // 返回包含所有、开始、计数和列表的数组
        return compact('all', 'start', 'count', 'list');
    }


    /**
     * 发表评论
     * @param int $replyId
     * @param array $data
     * @author Qinii
     * @day 10/29/21
     */
    public function create(int $replyId, array $data)
    {
        $make = app()->make(CommunityRepository::class);

        if (!$make->exists($data['community_id']))
            throw  new ValidateException('内容不存在，可能已被删除了哦～');

        $data['pid'] = $replyId;
        if ($replyId) {
            $get = $this->dao->get($replyId);
            if (!$get) throw  new ValidateException('您回复的评论不存在');
            if ($get->pid) {
                $data['re_uid'] = $get->uid;
                $data['pid'] = $get->pid;
            }
        }

        $res = Db::transaction(function () use ($replyId, $data, $make) {
            $res = $this->dao->create($data);
            if ($replyId) $this->dao->incField($data['pid'], 'count_reply', 1);
            return $res;
        });

        $ret = $this->dao->getWhere(['reply_id' => $res->reply_id], '*', [
            'author' => function ($query) {
                $query->field('uid,nickname,avatar');
            },
            'reply' => function ($query) {
                $query->field('uid,nickname,avatar')->order('create_time ASC');
            },
        ]);
        return $ret;
    }

    /**
     * 删除指定ID的帖子
     *
     * 本函数通过使用事务处理来确保删除操作的完整性。它首先检查帖子是否存在父帖子，
     * 如果存在，则减少父帖子的回复计数。然后，它减少关联社区的回复计数。最后，它删除指定的帖子。
     * 使用事务的原因是为了避免在操作过程中发生的数据不一致问题。
     *
     * @param int $id 帖子的唯一标识符
     */
    public function delete($id)
    {
        // 开启事务处理
        Db::transaction(function () use ($id) {
            // 获取要删除的帖子评论
            $get = $this->dao->get($id);
            // 实例化社区仓库类
            $make = app()->make(CommunityRepository::class);

            // 如果帖子有父帖子，减少父帖子的回复计数
            if ($get->pid) $this->dao->decField($get['pid'], 'count_reply', 1);
            // 减少帖子的回复计数
            $make->decField($get['community_id'], 'count_reply', 1);

            // 删除帖子的评论
            $get->delete();
        });
    }


    /**
     * 社区评论 点赞和取消点赞 操作
     * @param int $id
     * @param int $uid
     * @param int $status
     * @return void
     * @author Qinii
     */
    public function setStart(int $id, int $uid, int $status)
    {
        $make = app()->make(RelevanceRepository::class);
        $check = $make->checkHas($uid, $id, RelevanceRepository::TYPE_COMMUNITY_REPLY_START);
        if ($status) {
            if ($check) throw new ValidateException('您已经赞过他了～');
            $make->create($uid, $id, RelevanceRepository::TYPE_COMMUNITY_REPLY_START, true);
            $this->dao->incField($id, 'count_start', 1);
        } else {
            if (!$check) throw new ValidateException('您还未赞过他哦～');
            $make->destory($uid, $id, RelevanceRepository::TYPE_COMMUNITY_REPLY_START);
            $this->dao->decField($id, 'count_start', 1);
        }
        return;
    }

    /**
     * 后台审核表单
     * @param int $id
     * @return \FormBuilder\Form
     * @author Qinii
     */
    public function statusForm(int $id)
    {
        $formData = $this->dao->get($id)->toArray();

        if ($formData['status'] !== 0) throw new ValidateException('请勿重复审核');
        $form = Elm::createForm(Route::buildUrl('systemCommunityReplyStatus', ['id' => $id])->build());

        $form->setRule([
            Elm::textarea('content', '评论内容：')->placeholder('请输入评论内容')->disabled(true),

            Elm::radio('status', '审核状态：', 1)->options([
                    ['value' => -1, 'label' => '未通过'],
                    ['value' => 1, 'label' => '通过']]
            )->control([
                ['value' => -1, 'rule' => [
                    Elm::textarea('refusal', '未通过原因', '')->required('请输入未通过原因')
                ]]
            ]),
        ]);
        $formData['status'] = 1;
        return $form->setTitle('审核评论')->formData($formData);
    }
}
