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

namespace app\controller\api\community;

use app\common\repositories\community\CommunityRepository;
use app\common\repositories\store\order\StoreOrderProductRepository;
use app\common\repositories\system\RelevanceRepository;
use app\common\repositories\user\UserHistoryRepository;
use app\common\repositories\user\UserRelationRepository;
use app\common\repositories\user\UserRepository;
use app\validate\api\CommunityValidate;
use crmeb\basic\BaseController;
use crmeb\services\wechat\MiniProgram;
use think\App;
use app\common\repositories\community\CommunityRepository as repository;
use think\exception\ValidateException;

/**
 * Class Community
 * app\controller\api\community
 *  逛逛社区
 */
class Community extends BaseController
{
    /**
     * @var CommunityRepository
     */
    protected $repository;
    protected $user;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->user = $this->request->isLogin() ? $this->request->userInfo() : null;
        if (!systemConfig('community_status') ) throw  new ValidateException('未开启社区功能');
    }

    /**
     *  文章列表
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function lst()
    {
        $where = $this->request->params([
            'keyword', 'topic_id', 'is_hot', 'category_id', 'spu_id', 'search_type', 'community_type',
            'order', 'is_news_bot', 'topic_name', 'topic_keywords', 'has_spu', 'city', 'skip_category',
            'sex', 'age_min', 'age_max', 'education',
            'category_topic_match', 'category_topic_or'
        ]);
        // 内容类别 Tab 用语义筛选时，不再按帖子 category_id 过滤
        if (!empty($where['skip_category'])) {
            unset($where['category_id'], $where['skip_category']);
        } elseif (!$where['category_id']) {
            unset($where['category_id']);
        } else if ($where['category_id'] == -1) {
            $where['is_type'] = $this->repository::COMMUNIT_TYPE_VIDEO;
            unset($where['category_id']);
        }
        // 话题命中类别：类型条件与话题命中取 OR 时，转成内部字段避免被 AND
        if (!empty($where['category_id']) && !empty($where['category_topic_or'])) {
            if (isset($where['community_type']) && $where['community_type'] !== '') {
                $where['_or_community_type'] = $where['community_type'];
                unset($where['community_type']);
            }
            if (isset($where['has_spu']) && $where['has_spu'] !== '') {
                $where['_or_has_spu'] = $where['has_spu'];
                unset($where['has_spu']);
            }
            $where['category_topic_match'] = 1;
        }
        $where = array_merge($where, $this->repository::IS_SHOW_WHERE);
        $order = $where['order'] ?? '';
        if ($order === 'new' || $order === 'create_time') {
            $where['order'] = 'create_time';
        } else {
            $where['order'] = 'start';
        }
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getApiList($where, $page, $limit, $this->user));
    }

    /**
     *  用户列表
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function userList()
    {
        $where = $this->request->params(['keyword', 'sex', 'age_min', 'age_max', 'education']);
        [$page, $limit] = $this->getPage();

        $userRepository = app()->make(UserRepository::class);

        return app('json')->success($userRepository->getCommunityUserList($where, $page, $limit));
    }

    /**
     *  视频列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/11/29
     */
    public  function videoShow()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->repository::IS_SHOW_WHERE;
        $where['community_id'] = $this->request->param('id','');
        return app('json')->success($this->repository->getApiVideoList($where, $page, $limit, $this->user));
    }

    /**
     *   关注的人的文章
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 11/2/21
     */
    public function focuslst(RelevanceRepository $relevanceRepository)
    {
        $where = $this->repository::IS_SHOW_WHERE;
        $where_ = [
            'left_id' => $this->user->uid ?? null ,
            'type'    => RelevanceRepository::TYPE_COMMUNITY_FANS,
        ];
        $where['uids'] = $relevanceRepository->getSearch($where_)->column('right_id');
        $where['order'] = 'start';
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type',0);
        if ($type) {
            $where['is_type'] = $this->repository::COMMUNIT_TYPE_VIDEO;
        }
        return app('json')->success($this->repository->getApiList($where, $page, $limit, $this->user));
    }

    /**
     *  某个用户的文章
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function userCommunitylst($id)
    {
        $where = [];
        if (!$this->user || $this->user->uid !=  $id) {
            $where = $this->repository::IS_SHOW_WHERE;
        }
        $where['uid'] = $id;
        $is_type = $this->request->param('is_type', '');
        if ($is_type !== '') $where['is_type'] = $is_type;
        $community_type = $this->request->param('community_type', '');
        if ($community_type !== '') $where['community_type'] = $community_type;
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->getApiList($where, $page, $limit, $this->user));
    }

    /**
     *  某个用户的视频
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function userCommunityVideolst($id)
    {
        $where = [];
        [$page, $limit] = $this->getPage();
        $is_start = $this->request->param('is_star',0);
        if ($is_start) {
            //某人赞过的视频
            $where = $this->repository::IS_SHOW_WHERE;
        } else {
            //某个人的视频
            if (!$this->user || $this->user->uid !=  $id) {
                $where =$this->repository::IS_SHOW_WHERE;
            }
            $where['uid'] = $id;
        }
        $where['is_del'] = 0;
        $where['community_id'] = $this->request->param('community_id/d','');

        $data = $this->repository->getApiVideoList($where, $page, $limit, $this->user,$is_start);
        return app('json')->success($data);
    }


    /**
     *  我赞过的文章
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserStartCommunity(RelevanceRepository $relevanceRepository)
    {
        [$page, $limit] = $this->getPage();
        $where['uid'] = $this->user->uid;
        $data = $relevanceRepository->getUserStartCommunity($where,$page, $limit);
        return app('json')->success($data);
    }

    /**
     *  文章详情
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function show($id)
    {
        return app('json')->success($this->repository->show((int)$id, $this->user));
    }

    /**
     *  已购商品
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function payList()
    {
        [$page, $limit] = $this->getPage();
        $keyword = $this->request->param('keyword');
        $data = app()->make(StoreOrderProductRepository::class)->getUserPayProduct($keyword, $this->user->uid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  收藏商品
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function relationList()
    {
        [$page, $limit] = $this->getPage();
        $keyword = $this->request->param('keyword');
        $data = app()->make(UserRelationRepository::class)->getUserProductToCommunity($keyword, $this->user->uid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  历史记录
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function historyList()
    {
        [$page, $limit] = $this->getPage();
        $where['keyword'] = $this->request->param('keyword');
        $where['uid'] = $this->request->userInfo()->uid;
        $where['type'] = 1;
        $data = app()->make(UserHistoryRepository::class)->historyLst($where, $page,$limit);
        return app('json')->success($data);
    }

    /**
     *  发布文章
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function create()
    {
        $data = $this->checkParams();
        $this->checkUserAuth();
        $data['uid'] = $this->request->uid();
        $res = $this->repository->create($data);
        return app('json')->success(['community_id' => $res]);
    }

    /**
     * 检测用户是否可以发贴
     * @return bool|\think\response\Json
     * @author Qinii
     * @day 10/30/21
     */
    public function checkUserAuth()
    {
        $user = $this->request->userInfo();
        if ( systemConfig('community_auth') ) {
            if ($user->phone) {
                return true;
            }
            throw  new ValidateException('请先绑定您的手机号');
        } else {
            return true;
        }
    }


    /**
     *  编辑
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function update($id)
    {
        $data = $this->checkParams();
        $this->checkUserAuth();
        if(!$this->repository->uidExists($id, $this->user->uid))
            return app('json')->success('内容不存在或不属于您');
        $this->repository->edit($id, $data);
        return app('json')->success(['community_id' => $id]);
    }

    /**
     *  验证数据
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function checkParams()
    {
        $data = $this->request->params(['image','topic_id','topic_names','content','spu_id','order_id',['is_type',1],'video_link','title']);
        $config = systemConfig(["community_app_switch",'community_audit','community_video_audit']);
        $data['status'] = 0;
        $data['is_show'] = 0;
        if ($data['is_type'] == 1) {
            if (!in_array($this->repository::COMMUNIT_TYPE_FONT,$config['community_app_switch']))
                throw new ValidateException('社区图文未开启');
            if ($config['community_audit']) {
                $data['status'] = 1;
                $data['is_show'] = 1;
                $data['status_time'] = date('Y-m-d H:i:s', time());
            }
        } else {
            if (!in_array($this->repository::COMMUNIT_TYPE_VIDEO,$config['community_app_switch']))
                throw new ValidateException('短视频未开启');
            if ($config['community_video_audit']) {
                $data['status'] = 1;
                $data['is_show'] = 1;
                $data['status_time'] = date('Y-m-d H:i:s', time());
            }
            if (!$data['video_link']) throw new ValidateException('请上传视频');
            // 视频未传封面时，自动截取第一帧；同时尽量保证 H.264 可播
            try {
                \crmeb\services\VideoCoverService::ensureH264((string)$data['video_link']);
            } catch (\Throwable $e) {
                // ignore
            }
            $imageEmpty = empty($data['image']) || (is_array($data['image']) && !array_filter($data['image']));
            if ($imageEmpty) {
                $cover = \crmeb\services\VideoCoverService::extractFromUrl((string)$data['video_link']);
                if ($cover) {
                    $data['image'] = [$cover];
                }
            }
        }

        $data['content'] = filter_emoji($data['content']);
        if (!empty($data['content'])) {
            MiniProgram::msgSecCheck(
                $data['content'],
                3,
                $this->request->userInfo()->wechat->routine_openid ?? '',
                0
            );
        }
        if ($data['is_type'] == 1 && empty($data['image'])) {
            throw new ValidateException('图片不能为空');
        }
        app()->make(CommunityValidate::class)->check($data);
        // 优先使用用户填写的标题；未填时再取正文首行
        $userTitle = trim(filter_emoji((string)($data['title'] ?? '')));
        if ($userTitle !== '') {
            $data['title'] = mb_strlen($userTitle) > 60
                ? mb_substr($userTitle, 0, 60, 'utf-8')
                : $userTitle;
        } else {
            $arr = explode("\n", (string)$data['content']);
            $title = trim($arr[0] ?? '');
            if (mb_strlen($title) > 40) {
                $data['title'] = mb_substr($title, 0, 30, 'utf-8');
            } else {
                $data['title'] = $title;
            }
        }
        if ($data['image']) $data['image'] = implode(',',$data['image']);
        return $data;
    }


    /**
     *  删除
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->uidExists($id, $this->user->uid))
            return app('json')->fail('内容不存在或不属于您');
        $this->repository->destory($id, $this->user);

        return app('json')->success('删除成功');
    }

    /**
     *  文章点赞/取消
     * @param $id
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function startCommunity($id)
    {
        $status = $this->request->param('status') == 1 ? 1 :0;
        if (!$this->repository->isApprove($id))
            return app('json')->fail('内容不存在或未审核通过，不能点赞');
        $this->repository->setCommunityStart($id, $this->user, $status);
        if ($status) {
            return app('json')->success('点赞成功');
        } else {
            return app('json')->success('取消点赞');
        }
    }

    /**
     * 笔记收藏 / 取消收藏
     */
    public function collectCommunity($id)
    {
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $this->repository->setCommunityCollect((int)$id, (int)$this->user->uid, $status);
        if ($status) {
            return app('json')->success('收藏成功');
        }
        return app('json')->success('取消收藏');
    }

    /**
     *  用户关注/取消
     * @param $id
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function setFocus($id)
    {
        $id  = (int)$id;
        $status  = $this->request->param('status') == 1 ? 1 :0;
        if ($this->user->uid == $id)
            return app('json')->fail('请勿关注自己');
        $make = app()->make(UserRepository::class);
        if (!$user = $make->get($id)) return app('json')->fail('未查询到该用户');

        $this->repository->setFocus($id, $this->user->uid, $status);

        if ($status) {
            return app('json')->success('关注成功');
        } else {
            return app('json')->success('取消关注');
        }
    }

    /**
     *  我的粉丝
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserFans(RelevanceRepository $relevanceRepository)
    {
        [$page, $limit] = $this->getPage();
        $fans = $relevanceRepository->getUserFans($this->user->uid, $page, $limit);
        return app('json')->success($fans);
    }

    /**
     *  我的关注
     * @param RelevanceRepository $relevanceRepository
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function getUserFocus(RelevanceRepository $relevanceRepository)
    {
        [$page, $limit] = $this->getPage();
        $start = $relevanceRepository->getUserFocus($this->user->uid, $page, $limit);
        return app('json')->success($start);
    }


    /**
     *  用户信息
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/28/21
     */
    public function userInfo($id)
    {
        $id = intval($id);
        if (!$id)  return app('json')->fail('缺少参数');
        $data = $this->repository->getUserInfo($id, $this->user);
        return app('json')->success($data);
    }

    /**
     *  根据订单获取商品
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function getSpuByOrder($id)
    {
        $data = $this->repository->getSpuByOrder($id);
        return app('json')->success($data);
    }

    /**
     *  生成分享二维码
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function qrcode($id)
    {
        $id = (int)$id;
        $type = $this->request->param('type');
        $url = $this->repository->qrcode($id, $type, $this->user);
        if (!$url) return app('json')->fail('二维码生成失败');
        return app('json')->success(compact('url'));
    }

    /**
     * 喜欢/取消喜欢某用户
     */
    public function toggleLike($id)
    {
        $id = (int)$id;
        if (!$id) return app('json')->fail('缺少参数');
        if ($id == $this->user->uid) return app('json')->fail('请勿喜欢自己');
        $status = (int)$this->request->param('status', 1);
        try {
            $this->repository->setLike($id, $this->user->uid, $status);
        } catch (\think\exception\ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($status ? '已喜欢' : '已取消喜欢');
    }

    /**
     * C 端图片加载失败上报：机器人帖删失效图；无图删帖；该机器人全部帖无图则注销
     */
    public function imageFail()
    {
        $communityId = (int)$this->request->param('community_id', 0);
        $imageUrl = (string)$this->request->param('image_url', '');
        $data = app()->make(\app\common\repositories\community\CommunityBotImageCleanRepository::class)
            ->reportFail($communityId, $imageUrl);
        return app('json')->success($data);
    }

    /**
     * 喜欢我的人列表
     */
    public function likeMeList(RelevanceRepository $relevanceRepository)
    {
        [$page, $limit] = $this->getPage();
        $data = $relevanceRepository->getUserLikeMe($this->user->uid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 我喜欢的人列表
     */
    public function iLikeList(RelevanceRepository $relevanceRepository)
    {
        [$page, $limit] = $this->getPage();
        $data = $relevanceRepository->getUserILike($this->user->uid, $page, $limit);
        return app('json')->success($data);
    }
}
