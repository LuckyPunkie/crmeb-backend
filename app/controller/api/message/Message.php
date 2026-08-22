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
        $filter = [
            'sex' => (int)$this->request->param('sex', 0),
            'gender' => (string)$this->request->param('gender', ''),
            'age_min' => (int)$this->request->param('age_min', 0),
            'age_max' => (int)$this->request->param('age_max', 0),
            'height_min' => (int)$this->request->param('height_min', 0),
            'height_max' => (int)$this->request->param('height_max', 0),
            'education' => (string)$this->request->param('education', ''),
        ];
        $result = $this->dialogRepository->dialogList($uid, $page, $limit, $type, $filter);
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
        return app('json')->success([
            'count' => count($list),
            'dialog_id' => $dialogId,
            'chat_user' => $chatUser,
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
