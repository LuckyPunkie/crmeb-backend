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
use crmeb\jobs\ChatModerationJob;
use crmeb\jobs\MediaCheckSubmitJob;
use crmeb\services\security\ContentSecurityService;
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
            'registered_province', 'registered_city',
            'annual_income', 'relationship_status', 'relationship_status_not', 'marital_status',
            'dating_purpose', 'car_has', 'house_has', 'total_assets', 'asset_tier',
            'want_kids', 'smoking', 'drinking', 'tattoo', 'only_child', 'accept_cat', 'accept_dog',
            'hobby',
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
        // 《内容安全阈值确定版》5.3：熟人私聊先发后审；陌生人首次对话、被举报过的会话（及开关关闭时）先审后发
        $openid = (string)($this->request->userInfo()->wechat->routine_openid ?? '');
        $isText = (int)$data['msn_type'] === 1;
        $asyncText = $isText && ContentSecurityService::chatAsyncEnabled()
            && !$this->messageRepository->requiresPreReview($myUid, $toUid);
        $syncCheck = null;
        if ($isText && !$asyncText) {
            $syncCheck = ContentSecurityService::checkText(
                $data['msn'],
                ContentSecurityService::SCENE_SOCIAL,
                'chat_msg',
                0,
                $myUid,
                $openid
            );
        }

        $data['voice_duration'] = $data['voice_duration'] ?? 0;
        $data['ref_note_id'] = $data['ref_note_id'] ?? 0;

        try {
            $result = $this->messageRepository->sendMessage($myUid, $toUid, $data);
        } catch (ValidateException $e) {
            return app('json')->fail($e->getMessage());
        }

        $messageId = (int)($result['message_id'] ?? 0);
        if ($syncCheck) {
            ContentSecurityService::bindBizId((int)($syncCheck['log_id'] ?? 0), $messageId);
        }
        try {
            if ($asyncText) {
                ContentSecurityService::pushModeration(ChatModerationJob::class, [
                    'message_id' => $messageId,
                    'openid' => $openid,
                ], ContentSecurityService::QUEUE_HIGH);
            }
            // 图片/语音：微信只能异步检测（5~30 分钟回调），一律先发后审，违规撤回
            $msnType = (int)$data['msn_type'];
            if (in_array($msnType, [3, 9], true) && $openid !== '' && ContentSecurityService::mediaCheckEnabled()) {
                ContentSecurityService::pushModeration(MediaCheckSubmitJob::class, [
                    'biz_type' => 'chat_msg',
                    'biz_id' => $messageId,
                    'uid' => $myUid,
                    'openid' => $openid,
                    'scene' => ContentSecurityService::SCENE_SOCIAL,
                    'images' => $msnType === 3 ? [$data['msn']] : [],
                    'audio' => $msnType === 9 ? [$data['msn']] : [],
                    'video' => '',
                ], ContentSecurityService::QUEUE_HIGH);
            }
        } catch (\Throwable $e) {
            // 入队失败不影响消息发送（文档 5.4：服务不可用时放行并记录日志）
            \think\facade\Log::error("私聊审核入队失败(message#{$messageId}): " . $e->getMessage());
            ContentSecurityService::recordFailure('chat_msg', $e->getMessage());
        }
        return app('json')->success($result);
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

    public function blacklist()
    {
        $myUid = $this->request->uid();
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->dialogRepository->blacklistList($myUid, $page, $limit));
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
