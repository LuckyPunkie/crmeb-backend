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
 * Class WechatUserGroupService
 * @package crmeb\services
 * @author xaboy
 * @day 2020-04-27
 */
class WechatUserGroupService
{
    protected function client()
    {
        return OfficialAccount::instance()->application()->getClient();
    }

    protected function responseToArray($response): array
    {
        return is_object($response) && method_exists($response, 'toArray') ? $response->toArray() : (array)$response;
    }

    public function userGroup()
    {
        return $this;
    }

    public function lst()
    {
        $response = $this->responseToArray($this->client()->get('cgi-bin/groups/get'));
        return $response['groups'] ?? [];
    }

    public function create($groupName)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/groups/create', [
            'group' => ['name' => $groupName],
        ]));
    }

    public function update($id, $groupName)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/groups/update', [
            'group' => [
                'id' => (int)$id,
                'name' => $groupName,
            ],
        ]));
    }

    public function delete($id)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/groups/delete', [
            'group' => ['id' => (int)$id],
        ]));
    }

    public function moveUser(string $openId, $groupId)
    {
        return $this->responseToArray($this->client()->postJson('cgi-bin/groups/members/update', [
            'openid' => $openId,
            'to_groupid' => (int)$groupId,
        ]));
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
        return Elm::createForm($id ? Route::buildUrl('updateWechatUserGroup', compact('id'))->build() : Route::buildUrl('createWechatUserGroup')->build(), [
            Elm::input('tag_name', '分组名称：', $name)->placeholder('请输入分组名称')->required('请输入分组名称')
        ])->setTitle($id ? '编辑用户分组' : '添加用户分组');
    }
}
