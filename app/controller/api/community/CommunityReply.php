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
use app\common\repositories\system\RelevanceRepository;
use crmeb\basic\BaseController;
use crmeb\services\security\ContentSecurityService;
use crmeb\services\security\UgcTextSecurity;
use think\App;
use app\common\repositories\community\CommunityReplyRepository as repository;
use think\exception\ValidateException;

/**
 * Class CommunityReply
 * app\controller\api\community
 *  社区评论
 */
class CommunityReply extends BaseController
{
    /**
     * @var CommunityReplyRepository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        if (!systemConfig('community_status')) throw  new ValidateException('未开启社区功能');
    }

    /**
     * 列表
     * @return mixed
     * @author Qinii
     */
    public function lst($id)
    {
        if (!systemConfig('community_reply_status'))
            return app('json')->success([
                'count' => 0,
                'all' => 0,
                'start' => 0,
                'list' => []
            ]);
        $where['community_id'] = $id;
        $where['sort'] = $this->request->param('sort', 'new');
        [$page, $limit] = $this->getPage();
        $userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
        return app('json')->success($this->repository->getApiList($where, $page, $limit, $userInfo));
    }

    /**
     *  发评论
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function create($id)
    {
        if (!systemConfig('community_reply_status'))
            return app('json')->fail('评论功能未开启');
        if (systemConfig('community_reply_auth') && !$this->request->userInfo()->phone)
            return app('json')->fail('请先绑定手机号');

        $replyId = $this->request->param('reply_id', 0);

        $data = $this->request->params(['content', ['pics', []]]);
        $data['content'] = trim((string)$data['content']);
        $pics = is_array($data['pics']) ? $data['pics'] : explode(',', (string)$data['pics']);
        $pics = array_values(array_filter(array_map(static function ($v) {
            return is_string($v) ? trim($v) : '';
        }, $pics)));
        if (count($pics) > 9) return app('json')->fail('图片最多 9 张');
        if (mb_strlen(implode(',', $pics)) > 1000) return app('json')->fail('图片地址过长');
        $data['pics'] = $pics;
        if ($data['content'] === '' && !$pics) return app('json')->fail('请输入回复内容');
        $user = $this->request->userInfo();
        $openid = (string)($user->wechat->routine_openid ?? '');
        $check = ['suggest' => 'pass', 'log_id' => 0];
        if ($data['content'] !== '') {
            $check = ContentSecurityService::checkText(
                $data['content'],
                ContentSecurityService::SCENE_COMMENT,
                'community_reply',
                0,
                $user->uid,
                $openid,
                false
            );
        }
        $data['uid'] = $user->uid;
        $data['community_id'] = $id;

        // 文本 DFA：明文存库；命中 → status=0 进后台待审；C 端非「审核通过」且命中词打星
        // 未命中敏感词仍直接 status=1；community_reply_audit 不再对全量评论待审
        $data['status'] = 1;
        $msg = '发布成功';
        if ($check['suggest'] === 'risky') {
            $data['status'] = -1;
            $data['refusal'] = '内容包含违规信息';
            $msg = '评论未通过审核，未能公开展示';
        } elseif (!empty($check['hit_sensitive'])) {
            $data['status'] = 0;
            $msg = '评论已提交，审核通过后公开展示';
        }
        $ret = $this->repository->create($replyId, $data);
        ContentSecurityService::bindBizId((int)($check['log_id'] ?? 0), (int)($ret['reply_id'] ?? 0));
        if ($data['status'] === -1) {
            ContentSecurityService::notifyRejected((int)$user->uid, '评论');
        } elseif ($pics && (int)$data['status'] !== -1) {
            // 评论配图先发后审，违规时清除对应图片
            ContentSecurityService::dispatchMediaCheck('community_reply', (int)($ret['reply_id'] ?? 0), (int)$user->uid,
                $openid, $pics, ContentSecurityService::SCENE_COMMENT);
        }
        // 刚发的评论直接插进评论区：待审/拒绝打星，人工通过后再原文展示
        if ($ret && isset($ret['content'])) {
            $replyStatus = (int)($ret['status'] ?? $data['status']);
            $ret['content'] = UgcTextSecurity::maskReplyContentForDisplay((string)$ret['content'], $replyStatus);
        }
        return app('json')->success($msg, $ret);
    }

    /**
     * 删除评论：评论作者本人，或帖子作者可删
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function delete($id)
    {
        $uid = (int)$this->request->userInfo()->uid;
        $reply = $this->repository->get($id);
        if (!$reply || (int)($reply['is_del'] ?? 0) === 1) {
            return app('json')->fail('评论不存在');
        }
        $isCommentOwner = (int)$reply['uid'] === $uid;
        $isPostOwner = app()->make(CommunityRepository::class)
            ->uidExists((int)$reply['community_id'], $uid);
        if (!$isCommentOwner && !$isPostOwner) {
            return app('json')->fail('无权删除该评论');
        }
        $this->repository->delete($id);
        return app('json')->success('评论删除');
    }

    /**
     *  评论点赞
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 10/29/21
     */
    public function start($id)
    {
        if (!systemConfig('community_reply_status'))
            return app('json')->success('评论不存在');
        $status = $this->request->param('status') == 1 ? 1 : 0;
        if (!$this->repository->exists($id))
            return app('json')->fail('评论不存在');

        $uid = $this->request->userInfo()->uid;
        $this->repository->setStart($id, $uid, $status);
        if ($status) {
            return app('json')->success('点赞成功');
        } else {
            return app('json')->success('取消点赞');
        }
    }
}
