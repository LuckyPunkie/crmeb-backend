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

namespace crmeb\services;

use crmeb\services\wechat\OfficialAccount;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\facade\Route;

/**
 * Class WechatUserTagService
 * @package crmeb\services
 * @author xaboy
 * @day 2020-04-27
 */
class WechatUserTagService
{
    protected function client()
    {
        return OfficialAccount::instance()->application()->getClient();
    }

    protected function responseToArray($response): array
    {
        return is_object($response) && method_exists($response, 'toArray') ? $response->toArray() : (array)$response;
    }

    public function userTag()
    {
        return $this;
    }

    public function lst()
    {
        $response = $this->responseToArray($this->client()->get('cgi-bin/tags/get'));
        return $response['tags'] ?? [];
    }

    public function create($tagName)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/tags/create', [
            'tag' => ['name' => $tagName],
        ]));
    }

    public function update($id, $tagName)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/tags/update', [
            'tag' => [
                'id' => (int)$id,
                'name' => $tagName,
            ],
        ]));
    }

    public function delete($id)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/tags/delete', [
            'tag' => ['id' => (int)$id],
        ]));
    }

    public function batchTagUsers(array $openIds, int|string $tagId)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/tags/members/batchtagging', [
            'openid_list' => array_values($openIds),
            'tagid' => (int)$tagId,
        ]));
    }

    public function batchUntagUsers(array $openIds, $tagId)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/tags/members/batchuntagging', [
            'openid_list' => array_values($openIds),
            'tagid' => (int)$tagId,
        ]));
    }

    public function userTags(string $openId)
    {
        $response = $this->responseToArray($this->client()->postJson('cgi-bin/tags/getidlist', [
            'openid' => $openId,
        ]));
        return $response['tagid_list'] ?? [];
    }

    /**
     * @param null $id
     * @param string $name
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-04-27
     */
    public function form($id = null, $name = '')
    {
        return Elm::createForm($id ? Route::buildUrl('updateWechatUserTag', compact('id'))->build() : Route::buildUrl('createWechatUserTag')->build(), [
            Elm::input('tag_name', '标签名称：', $name)->placeholder('请输入标签名称')->required('请输入标签名称')
        ])->setTitle($id ? '编辑用户标签' : '添加用户标签');
    }
}
