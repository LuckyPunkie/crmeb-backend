<?php

namespace app\controller\api\user;

use app\common\repositories\user\UserImpressionRepository;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 印象墙 API
 */
class Impression extends BaseController
{
    /** @var UserImpressionRepository */
    protected $repository;

    public function __construct(App $app, UserImpressionRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 头信息（墙主 + 是否开启 + 总数）
     * GET /api/user/impression/summary/:owner_uid
     */
    public function summary($owner_uid)
    {
        $ownerUid = (int) $owner_uid;
        if ($ownerUid <= 0) return app('json')->fail('参数错误');
        $data = $this->repository->summary($ownerUid);
        $viewerUid = $this->request->isLogin() ? (int) $this->request->userInfo()->uid : 0;
        $data['is_owner_view'] = $viewerUid > 0 && $viewerUid == $ownerUid;
        return app('json')->success($data);
    }

    /**
     * 印象列表
     * GET /api/user/impression/lst/:owner_uid?page=1&limit=10
     */
    public function lst($owner_uid)
    {
        $ownerUid = (int) $owner_uid;
        if ($ownerUid <= 0) return app('json')->fail('参数错误');
        $viewerUid = $this->request->isLogin() ? (int) $this->request->userInfo()->uid : 0;
        // 关闭态：仅墙主自己可见
        if (!$this->repository->isWallEnabled($ownerUid) && $viewerUid !== $ownerUid) {
            return app('json')->success(['list' => [], 'count' => 0]);
        }
        [$page, $limit] = $this->getPage();
        $data = $this->repository->listImpressions($ownerUid, $viewerUid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 发布印象
     * POST /api/user/impression/create/:owner_uid
     * body: content, image
     */
    public function create($owner_uid)
    {
        $ownerUid = (int) $owner_uid;
        if ($ownerUid <= 0) return app('json')->fail('参数错误');
        $fromUid = (int) $this->request->userInfo()->uid;
        $params = $this->request->params(['content', 'image']);
        $content = trim((string) ($params['content'] ?? ''));
        $image = (string) ($params['image'] ?? '');
        $data = $this->repository->createImpression($ownerUid, $fromUid, $content, $image);
        return app('json')->success('发布成功', $data);
    }

    /**
     * 删除印象
     * POST /api/user/impression/delete/:impression_id
     */
    public function delete($impression_id)
    {
        $uid = (int) $this->request->userInfo()->uid;
        $this->repository->deleteImpression((int) $impression_id, $uid);
        return app('json')->success('已删除');
    }

    /**
     * 评论列表
     * GET /api/user/impression/comment/:impression_id?page=1&limit=20
     */
    public function comments($impression_id)
    {
        $iid = (int) $impression_id;
        if ($iid <= 0) return app('json')->fail('参数错误');
        $viewerUid = $this->request->isLogin() ? (int) $this->request->userInfo()->uid : 0;
        [$page, $limit] = $this->getPage();
        $data = $this->repository->listComments($iid, $viewerUid, $page, $limit);
        return app('json')->success($data);
    }

    /**
     * 展开某一级评论下的全部楼中楼
     * GET /api/user/impression/comment/replies/:parent_id
     */
    public function commentReplies($parent_id)
    {
        $pid = (int) $parent_id;
        if ($pid <= 0) return app('json')->fail('参数错误');
        $viewerUid = $this->request->isLogin() ? (int) $this->request->userInfo()->uid : 0;
        return app('json')->success($this->repository->listRepliesOfParent($pid, $viewerUid));
    }

    /**
     * 发布评论/回复
     * POST /api/user/impression/comment/create/:impression_id
     * body: content, images[], parent_id, reply_to_uid, reply_to_comment_id
     */
    public function commentCreate($impression_id)
    {
        $iid = (int) $impression_id;
        $fromUid = (int) $this->request->userInfo()->uid;
        $params = $this->request->params(['content', 'images', 'parent_id', 'reply_to_uid', 'reply_to_comment_id']);
        $data = $this->repository->createComment($iid, $fromUid, $params);
        return app('json')->success('评论成功', $data);
    }

    /**
     * 删除评论
     * POST /api/user/impression/comment/delete/:comment_id
     */
    public function commentDelete($comment_id)
    {
        $uid = (int) $this->request->userInfo()->uid;
        $this->repository->deleteComment((int) $comment_id, $uid);
        return app('json')->success('已删除');
    }

    /**
     * 点赞/取消（toggle）
     * POST /api/user/impression/like  body: target_type=impression|comment, target_id
     */
    public function like()
    {
        $uid = (int) $this->request->userInfo()->uid;
        $params = $this->request->params(['target_type', 'target_id']);
        $type = (string) ($params['target_type'] ?? '');
        $id = (int) ($params['target_id'] ?? 0);
        if ($id <= 0) return app('json')->fail('参数错误');
        $res = $this->repository->toggleLike($uid, $type, $id);
        return app('json')->success($res);
    }

    /**
     * 印象墙开关
     * POST /api/user/impression/wall/toggle  body: enabled=0|1
     */
    public function wallToggle()
    {
        $uid = (int) $this->request->userInfo()->uid;
        $enabled = (int) $this->request->param('enabled', 0);
        $this->repository->toggleWall($uid, $enabled);
        return app('json')->success(['enabled' => $enabled ? 1 : 0]);
    }
}
