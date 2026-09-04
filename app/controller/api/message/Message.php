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

namespace app\controller\api\message;

use app\common\repositories\user\UserDialogRepository;
use app\common\repositories\user\UserMessageRepository;
use app\common\repositories\community\CommunityReportRepository;
use crmeb\basic\BaseController;
use crmeb\services\UploadService;
use think\App;
use think\exception\ValidateException;

class Message extends BaseController
{
    protected $dialogRepository;
    protected $messageRepository;

    public function __construct(App $app, UserDialogRepository $dialogRepository, UserMessageRepository $messageRepository)
    {
        parent::__construct($app);
        $this->dialogRepository = $dialogRepository;
        $this->messageRepository = $messageRepository;
    }

    public function dialogList()
    {
        [$page, $limit] = $this->getPage();
        $type = $this->request->param('type', 'all');
        $uid = $this->request->uid();
        $filter = $this->request->params([
            'sex', 'gender', 'age_min', 'age_max', 'height_min', 'height_max', 'education',
            'weight_min', 'weight_max', 'zodiac', 'school_name', 'job_title',
            'hometown_province', 'hometown_city', 'current_province', 'current_city',
            'annual_income', 'relationship_status', 'relationship_status_not', 'marital_status',
            'dating_purpose', 'car_has', 'house_has', 'total_assets', 'asset_tier',
            'want_kids', 'smoking', 'drinking', 'tattoo', 'only_child', 'accept_cat', 'accept_dog',
        ]);
        $sort = (string)$this->request->param('sort', 'latest');
        $result = $this->dialogRepository->dialogList($uid, $page, $limit, $type, $filter, $sort);
        return app('json')->success($result);
    }

    public function messageHistory($uid)
    {
        [$page, $limit] = $this->getPage();
        $myUid = $this->request->uid();
        $dialog = $this->dialogRepository->getOrCreate($myUid, intval($uid));
        $dialogId = $dialog->dialog_id;
        $list = $this->messageRepository->getHistory($dialogId, $myUid, $page, $limit);
        $chatUser = $this->dialogRepository->getChatUser($dialogId, $myUid);
        $myUser = \think\facade\Db::name('user')->field('uid,nickname,avatar')->where('uid', $myUid)->find();
        return app('json')->success([
            'count' => count($list),
            'dialog_id' => $dialogId,
            'chat_user' => $chatUser,
            'my_user' => $myUser ?: [],
            'list' => $list,
        ]);
    }

    public function sendMessage($uid)
    {
        $myUid = $this->request->uid();
        $toUid = intval($uid);
        $data = $this->request->params(['msn_type', 'msn', 'voice_duration', 'ref_note_id']);

        if (!isset($data['msn_type']) || !isset($data['msn'])) {
            return app('json')->fail('参数不完整');
        }
        if (!in_array($data['msn_type'], [1, 2, 3, 9])) {
            return app('json')->fail('不支持的消息类型');
        }
        if (!$data['msn']) {
            return app('json')->fail('消息内容不能为空');
        }
        if ($data['msn_type'] == 1) {
            $data['msn'] = trim(strip_tags($data['msn']));
        }
        if (!$data['msn'] && $data['msn_type'] == 1) {
            return app('json')->fail('内容字符无效');
        }

        $data['voice_duration'] = $data['voice_duration'] ?? 0;
        $data['ref_note_id'] = $data['ref_note_id'] ?? 0;

        try {
            $result = $this->messageRepository->sendMessage($myUid, $toUid, $data);
            return app('json')->success($result);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function recallMessage($messageId)
    {
        $myUid = $this->request->uid();
        try {
            $this->messageRepository->recallMessage(intval($messageId), $myUid);
            return app('json')->success('撤回成功');
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function markAsRead($uid)
    {
        $myUid = $this->request->uid();
        $dialog = $this->dialogRepository->getOrCreate($myUid, intval($uid));
        $this->messageRepository->markAsRead($dialog->dialog_id, $myUid);
        return app('json')->success('标记成功');
    }

    public function chatSettings($uid)
    {
        $myUid = $this->request->uid();
        $targetUid = intval($uid);
        if (!$targetUid) {
            return app('json')->fail('参数错误');
        }
        return app('json')->success($this->dialogRepository->getChatSettings($myUid, $targetUid));
    }

    public function toggleBlacklist($uid)
    {
        $myUid = $this->request->uid();
        $targetUid = intval($uid);
        $status = (int)$this->request->param('status', 0);
        try {
            $this->dialogRepository->setBlacklist($myUid, $targetUid, $status === 1);
            return app('json')->success($status === 1 ? '已加入黑名单' : '已移除黑名单');
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function clearHistory($uid)
    {
        $myUid = $this->request->uid();
        $targetUid = intval($uid);
        try {
            $this->dialogRepository->clearHistory($myUid, $targetUid);
            return app('json')->success('已清空聊天记录');
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function searchHistory($uid)
    {
        $myUid = $this->request->uid();
        $targetUid = intval($uid);
        $keyword = trim((string)$this->request->param('keyword', ''));
        [$page, $limit] = $this->getPage();
        $dialog = $this->dialogRepository->getOrCreate($myUid, $targetUid);
        try {
            $list = $this->messageRepository->searchHistory((int)$dialog->dialog_id, $myUid, $keyword, $page, $limit);
            return app('json')->success([
                'count' => count($list),
                'list' => $list,
            ]);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }
    }

    public function reportUser($uid)
    {
        $myUid = $this->request->uid();
        $targetUid = intval($uid);
        if (!$targetUid) {
            return app('json')->fail('被举报用户不存在');
        }
        $data = $this->request->params(['reason', 'images']);
        try {
            $reportRepo = app()->make(CommunityReportRepository::class);
            $report = $reportRepo->createUserReport($data, $myUid, $targetUid);
            return app('json')->success('举报已提交，等待审核', ['report_id' => $report['id']]);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        } catch (\Throwable $e) {
            return app('json')->fail('提交失败，请稍后重试');
        }
    }

    public function uploadVoice()
    {
        $file = $this->request->file('voice');
        if (!$file) {
            return app('json')->fail('请选择语音文件');
        }

        $ext = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION) ?: '');
        if (!$ext) {
            $ext = 'mp3';
        }
        if (!in_array($ext, ['amr', 'mp3', 'wav', 'aac', 'webm', 'm4a'], true)) {
            return app('json')->fail('语音文件格式不支持');
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return app('json')->fail('语音文件不能超过5MB');
        }

        try {
            $upload = UploadService::create();
            $result = $upload->to('attach')->asFile([
                'filesize' => 5 * 1024 * 1024,
                'fileExt' => ['amr', 'mp3', 'wav', 'aac', 'webm', 'm4a'],
                'fileMime' => [],
            ])->move('voice');
            if ($result === false) {
                return app('json')->fail($upload->getError());
            }
            return app('json')->success(['url' => tidy_url($upload->getFileInfo()->filePath, 0)]);
        } catch (\Exception $e) {
            return app('json')->fail('上传失败: ' . $e->getMessage());
        }
    }

    public function uploadImage()
    {
        $file = $this->request->file('image');
        if (!$file) {
            return app('json')->fail('请选择图片文件');
        }

        $ext = strtolower(pathinfo($file->getOriginalName(), PATHINFO_EXTENSION) ?: '');
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return app('json')->fail('图片格式不支持，仅支持 jpg/png/gif/webp');
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return app('json')->fail('图片文件不能超过10MB');
        }

        try {
            $upload = UploadService::create();
            $result = $upload->to('attach')->validate()->move('image');
            if ($result === false) {
                return app('json')->fail($upload->getError());
            }
            return app('json')->success(['url' => tidy_url($upload->getFileInfo()->filePath, 0)]);
        } catch (\Exception $e) {
            return app('json')->fail('上传失败: ' . $e->getMessage());
        }
    }
}
