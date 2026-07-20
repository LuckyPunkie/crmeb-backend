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


namespace crmeb\services\template\storage;

use app\common\repositories\system\notice\SystemNoticeConfigRepository;
use crmeb\basic\BaseMessage;
use crmeb\services\wechat\OfficialAccount;
use think\facade\Log;

class Wechat extends BaseMessage
{
    /**
     * 初始化
     * @param array $config
     * @return mixed|void
     */
    protected function initialize(array $config)
    {
        parent::initialize($config);
    }

    /**
     * @param string $templateId
     * @return mixed
     */
    public function getTempId(string $templateId)
    {
        $tempkey = app()->make(SystemNoticeConfigRepository::class)->getSearch(['const_key' => $templateId])->find();
        return $tempkey['wechat_tempid'] ?? '';
    }

    /**
     * 发送消息
     * @param string $templateId
     * @param array $data
     * @return bool|mixed
     */
    public function send(string $templateId, array $data = [])
    {
        $tempid = $this->getTempId($templateId);
        if (!$tempid || !$this->openId) {
            return;
        }

        try {
            $res = OfficialAccount::sendTemplate($this->openId, $tempid, $data, $this->toUrl, $this->color);
            $this->clear();
            return $res;
        } catch (\Exception $e) {
            Log::error('发送给openid为:[' . $this->openId . ']微信模板消息失败,模板id为:[' . $tempid . '];错误原因为:' . $e->getMessage());
            return $this->setError($e->getMessage());
        }
    }

    /**
     * 获取所有模板
     * @return mixed
     */
    public function list()
    {
        return OfficialAccount::getPrivateTemplates();
    }

    /**
     * 添加模板消息
     * @param string $shortId
     * @return mixed
     */
    public function add(string $shortId)
    {
        return OfficialAccount::addTemplateId($shortId, []);
    }

    /**
     * 删除模板消息
     * @param string $templateId
     * @return mixed
     */
    public function delete(string $templateId)
    {
        return OfficialAccount::deleleTemplate($templateId);
    }

    /**
     * 返回所有支持的行业列表
     * @return mixed
     */
    public function getIndustry()
    {
        return OfficialAccount::getIndustry();
    }
}
