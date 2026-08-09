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

namespace app\controller\merchant\store\content;

use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\community\CommunityRepository;
use app\common\repositories\community\CommunityTopicRepository;
use app\common\repositories\community\CommunityReplyRepository;
use app\common\repositories\community\CommunityCategoryRepository;
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
     * @var repository
     */
    protected $repository;
    protected $user = null;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        if (!systemConfig('community_status') )
            throw  new ValidateException('未开启社区功能');
    }

    public function getUser($uid)
    {
        $where['mer_id'] = $this->request->merId();
        $where['status'] = 1;
        $merRepository = app()->make(UserMerchantRepository::class);
        $uids = $merRepository->getManagerUid($where);
        if (!in_array($uid,$uids))
            throw new ValidateException('此用户不是您的员工用户');
        $repository =  app()->make(UserRepository::class);
        $userInfo = $repository->userInfo($uid);
        $this->user = $userInfo;
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
            ['keyword',''],
            ['topic_id',''],
            ['category_id',''],
            ['status',''],
            ['is_type',''],
            ['search_type','content'],
            ['is_del',0],
        ]);
        [$page, $limit] = $this->getPage();
        $where['mer_id'] = $this->request->merId();
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    public function create()
    {
        $uid = $this->request->param('uid');
        $this->getUser($uid);
        $data = $this->checkParams();
        $data['mer_id'] = $this->request->merId();
        $this->checkUserAuth();
        $data['uid'] = $uid;
        $res = $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    public function update($id)
    {
        $res = $this->repository->get($id);
        if (!$res['mer_id'] || $res['mer_id'] != $this->request->merId())
            return app('json')->success('内容不存在或不属于您');
        $this->getUser($res['uid']);
        $data = $this->checkParams();
        $this->checkUserAuth();
        $this->repository->edit($id, $data);
        return app('json')->success('编辑成功');
    }

    public function detail($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->detail($id));
    }

    public function delete($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $res = $this->repository->get($id);
        if (!$res['mer_id'] || $res['mer_id'] != $this->request->merId())
            return app('json')->success('内容不存在或不属于您');
        $this->repository->destory($id);
        return app('json')->success('删除成功');
    }


    public function checkParams()
    {
        $data = $this->request->params(['image','topic_id','content','spu_id','order_id',['is_type',1],'video_link']);
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
        }
        if($data['content'] == '') {
            throw new ValidateException('请输入内容描述');
        }

        $data['content'] = filter_emoji($data['content']);
        MiniProgram::msgSecCheck(
            content: $data['content'],
            scene:3,
            openId: $this->user->wechat->routine_openid ?? '',
            mediaType: 3
        );
        if ($data['is_type'] == 1 && empty($data['image'])) {
            throw new ValidateException('图片不能为空');
        }
        app()->make(CommunityValidate::class)->check($data);
        $arr = explode("\n", $data['content']);
        $title = rtrim(ltrim($arr[0]));
        if (mb_strlen($title) > 40 ){
            $data['title'] = mb_substr($title,0,30,'utf-8');
        } else {
            $data['title'] = $title;
        }
        if ($data['image']) $data['image'] = implode(',',$data['image']);
        return $data;
    }

    public function checkUserAuth()
    {
        if ( systemConfig('community_auth') ) {
            if ($this->user->phone) {
                return true;
            }
            throw  new ValidateException('请员工用户先绑定的手机号');
        } else {
            return true;
        }
    }

    public function cateLst(CommunityTopicRepository $repository)
    {
        $res = $repository->getSearch(['status' => 1])->column('topic_id,topic_name, status');
        return app('json')->success($res);
    }

    public function reply($id)
    {
        $where['community_id'] = $id;
        [$page, $limit] = $this->getPage();
        $replyRepository = app()->make(CommunityReplyRepository::class);
        $res = $replyRepository->getList($where, $page,  $limit);
        return app('json')->success($res);
    }
}
