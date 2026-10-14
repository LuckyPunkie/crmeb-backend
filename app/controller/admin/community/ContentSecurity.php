<?php

namespace app\controller\admin\community;

use app\common\repositories\user\UserMessageRepository;
use crmeb\basic\BaseController;
use crmeb\services\security\AliyunBaselinePolicy;
use crmeb\services\security\ContentSecurityService;
use crmeb\services\security\UgcModerationTicket;
use crmeb\services\security\WechatMediaPolicy;
use think\App;
use think\exception\ValidateException;
use think\facade\Db;

class ContentSecurity extends BaseController
{
    public function wechatPolicyList()
    {
        return app('json')->success([
            'labels' => WechatMediaPolicy::labels(),
            'labelTitles' => WechatMediaPolicy::labelTitles(),
            'suggests' => WechatMediaPolicy::suggests(),
            'rows' => WechatMediaPolicy::allRows(),
        ]);
    }

    public function wechatPolicySave()
    {
        $cells = $this->request->param('cells', []);
        if (!is_array($cells)) {
            throw new ValidateException('参数错误');
        }
        WechatMediaPolicy::saveGrid($cells);
        return app('json')->success('保存成功');
    }

    public function aliyunLabelList()
    {
        return app('json')->success(['rows' => AliyunBaselinePolicy::allRows()]);
    }

    public function aliyunLabelSave()
    {
        $rows = $this->request->param('rows', []);
        if (!is_array($rows)) {
            throw new ValidateException('参数错误');
        }
        AliyunBaselinePolicy::saveRows($rows);
        return app('json')->success('保存成功');
    }

    public function ticketList()
    {
        [$page, $limit] = $this->getPage();
        $where = [
            'status' => $this->request->param('status', 'all'),
            'media_type' => $this->request->param('media_type', 'all'),
            'scene' => $this->request->param('scene', 'all'),
        ];
        return app('json')->success(UgcModerationTicket::adminList($where, $page, $limit));
    }

    public function ticketApprove($id)
    {
        $ticket = Db::name('ugc_moderation_ticket')->where('id', (int)$id)->find();
        if (!$ticket) {
            return app('json')->fail('记录不存在');
        }
        if ((int)$ticket['status'] !== UgcModerationTicket::STATUS_PENDING) {
            return app('json')->fail('已处理');
        }
        $adminId = (int)$this->request->adminId();
        UgcModerationTicket::resolve((int)$id, UgcModerationTicket::STATUS_APPROVED, $adminId);
        return app('json')->success('已通过');
    }

    public function ticketReject($id)
    {
        $ticket = Db::name('ugc_moderation_ticket')->where('id', (int)$id)->find();
        if (!$ticket) {
            return app('json')->fail('记录不存在');
        }
        if ((int)$ticket['status'] !== UgcModerationTicket::STATUS_PENDING) {
            return app('json')->fail('已处理');
        }
        $adminId = (int)$this->request->adminId();
        UgcModerationTicket::resolve((int)$id, UgcModerationTicket::STATUS_REJECTED, $adminId);
        $this->applyTicketReject($ticket);
        return app('json')->success('已驳回');
    }

    protected function applyTicketReject(array $ticket): void
    {
        $entityType = (string)($ticket['entity_type'] ?? '');
        $entityId = (int)($ticket['entity_id'] ?? 0);
        if ($entityType === 'chat_msg' && $entityId > 0) {
            $fromUid = (int)Db::name('user_message')->where('message_id', $entityId)->value('from_uid');
            if (app()->make(UserMessageRepository::class)->systemRecallMessage($entityId) && $fromUid) {
                ContentSecurityService::notifyRecalled($fromUid);
            }
        }
    }
}