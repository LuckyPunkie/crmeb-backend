<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat;

use think\exception\ValidateException;
use think\File;
use think\Response;
use GuzzleHttp\Psr7\Request;
use EasyWeChat\Work\Application;
use GuzzleHttp\Psr7\UploadedFile;
use crmeb\services\wechat\config\WorkConfig;
use crmeb\services\wechat\client\work\MediaClient;
use crmeb\services\wechat\client\work\UserClient;
use crmeb\services\wechat\client\work\MomentClient;
use crmeb\services\wechat\client\work\MessageClient;
use crmeb\services\wechat\client\work\GroupChatClient;
use crmeb\services\wechat\client\work\DepartmentClient;
use crmeb\services\wechat\client\work\ContactWayClient;
use crmeb\services\wechat\client\work\ExternalContactClient;
use EasyWeChat\Kernel\Exceptions\RuntimeException;
use Symfony\Contracts\HttpClient\ResponseInterface;
use EasyWeChat\Kernel\Exceptions\BadRequestException;
use EasyWeChat\Kernel\Exceptions\BadResponseException;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * 企业微信
 * Class Work
 * @package crmeb\services\wechat
 */
class Work extends BaseApplication
{

    const BASE_WORK_URL = 'https://qyapi.weixin.qq.com';

    /**
     * @var string
     */
    protected string $name = 'work';

    /**
     * @var WorkConfig
     */
    protected WorkConfig $config;

    /**
     * @var Application[]
     */
    protected array $application = [];

    /**
     * @var string
     */
    protected string $configHandler;

    /**
     * Work constructor.
     */
    public function __construct()
    {
        $this->debug = DefaultConfig::value('logger');
    }

    /**
     * 设置获取配置
     * @param string $handler
     * @return $this
     */
    public function setConfigHandler(string $handler)
    {
        $this->configHandler = $handler;
        return $this;
    }

    /**
     * @return Work
     */
    public static function instance(): self
    {
        $instance = app()->make(static::class);
        $instance->config = app()->make(WorkConfig::class);
        return $instance;
    }

    /**
     * 获取实例化句柄
     * @param string $type
     * @return Application
     * @throws InvalidArgumentException
     */
    public function application(string $type = WorkConfig::TYPE_USER): Application
    {
        $config = $this->config->all();
        $config = array_merge($config, $this->config->setHandler($this->configHandler)->getAppConfig($type));
        if (!isset($this->application[$type])) {
            $this->application[$type] = new Application($config);
            $this->setHttpClient($this->application[$type], self::BASE_WORK_URL);
            $this->setRequest($this->application[$type]);
            $this->setCache($this->application[$type]);
        }
        return $this->application[$type];
    }

    /**
     * 服务端
     * @return Response
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public static function serve(): Response
    {
        $make = self::instance();
        $server = $make->application()->getServer();
        $server->with($make->pushMessageHandler);
        $response = $server->serve();
        return response($response->getBody());
    }

    /**
     * 获取应用配置
     * @param string $type
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/10/8
     */
    public static function getAppConfig(string $type)
    {
        $instance = self::instance();
        return $instance->config->setHandler($instance->configHandler)->getAppConfig($type);
    }

    /**
     * @param string $type
     * @return UserClient
     * @throws InvalidArgumentException
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected static function user(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new UserClient($client);
    }

    /**
     * 客户
     * @param string $type
     * @return ExternalContactClient
     * @throws InvalidArgumentException
     */
    public function externalContact(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new ExternalContactClient($client);
    }

    /**
     * 朋友圈
     * @param string $type
     * @return MomentClient
     * @throws InvalidArgumentException
     */
    public function moment(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new MomentClient($client);
    }


    /**
     * 群发消息
     * @param string $type
     * @return MessageClient
     * @throws InvalidArgumentException
     */
    public function message(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new MessageClient($client);
    }

    /**
     * 客户群聊
     * @param string $type
     * @return GroupChatClient
     * @throws InvalidArgumentException
     */
    public function groupChat(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();
        return new GroupChatClient($client);
    }

    /**
     * 联系我二维码
     * @param string $type
     * @return ContactWayClient
     * @throws InvalidArgumentException
     */
    public function contactWay(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new ContactWayClient($client);
    }

    /**
     * @param string $type
     * @return DepartmentClient
     * @throws InvalidArgumentException
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected static function department(string $type = WorkConfig::TYPE_USER_APP): DepartmentClient
    {
        $client = self::instance()->application($type)->getClient();

        return new DepartmentClient($client);
    }

	/**
	 * 获取用户授权信息
	 * @param string $code
	 * @return array
	 */
	public static function getAuthUserInfo(string $code = '')
	{
		$code = $code ?: request()->param('code');
		if (!$code) {
			throw new WechatException('缺少CODE');
		}
		try {
			$userInfo = self::user()->userFromCode($code);

			self::logger('获取用户授权信息', compact('code'), $userInfo);

			return $userInfo->toArray();
		} catch (\Throwable $e) {
			throw new WechatException($e->getMessage());
		}

	}

    /**
     * 创建联系我二维码
     * @param int $channelCodeId
     * @param array $users
     * @param bool $skipVerify
     * @return \EasyWeChat\Kernel\HttpClient\Response|ResponseInterface
     * @throws TransportExceptionInterface*@throws InvalidArgumentException
     */
    public static function createQrCode(int $channelCodeId, array $users, bool $skipVerify = true)
    {
        $config = [
            'skip_verify' => $skipVerify,
            'state' => 'channelCode-' . $channelCodeId,
            'user' => $users,
        ];

        $response = self::instance()->contactWay()->create(2, 2, $config);

        self::logger('创建联系我二维码', $config, $response);

        return $response;
    }

    /**
     * 更新联系我二维码
     * @param int $channelCodeId
     * @param array $users
     * @param string $wxConfigId
     * @param bool $skipVerify
     * @return \EasyWeChat\Kernel\HttpClient\Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function updateQrCode(int $channelCodeId, array $users, string $wxConfigId, bool $skipVerify = true)
    {
        $config = [
            'skip_verify' => $skipVerify,
            'state' => 'channelCode-' . $channelCodeId,
            'user' => $users,
        ];

        $response = self::instance()->contactWay()->update($wxConfigId, $config);

        self::logger('更新联系我二维码', compact('config', 'wxConfigId'), $response);

        return $response;
    }

    /**
     * 删除联系我二维码
     * @param string $wxConfigId
     * @return \EasyWeChat\Kernel\HttpClient\Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public static function deleteQrCode(string $wxConfigId)
    {
        $response = self::instance()->contactWay()->delete($wxConfigId);

        self::logger('删除联系我二维码', compact('wxConfigId'), $response);

        return $response;
    }


    /**
     * 添加企业群发消息模板
     * @param array $msg
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function addMsgTemplate(array $msg)
    {
        $response = self::instance()->message()->submit($msg);

        self::logger('添加企业群发消息模板', compact('msg'), $response);

        return new WechatResponse($response);
    }

    /**
     * 获取群发成员发送任务列表
     * @param string $msgId
     * @param int|null $limit
     * @param string|null $cursor
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function getGroupmsgTask(string $msgId, ?int $limit = null, ?string $cursor = null)
    {
        $response = self::instance()->message()->getGroupmsgTask($msgId, $limit, $cursor);

        self::logger('获取群发成员发送任务列表', compact('msgId', 'limit', 'cursor'), $response);

        return new WechatResponse($response);
    }

    /**
     * 获取企业群发成员执行结果
     * @param string $msgId
     * @param string $userid
     * @param int|null $limit
     * @param string|null $cursor
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function getGroupmsgSendResult(string $msgId, string $userid, ?int $limit = null, ?string $cursor = null)
    {
        $response = self::instance()->message()->getGroupmsgSendResult($msgId, $userid, $limit, $cursor);

        self::logger('获取企业群发成员执行结果', compact('msgId', 'userid', 'limit', 'cursor'), $response);

        return new WechatResponse($response);
    }

    /**
     * 创建发送朋友圈任务
     * @param array $param
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function addMomentTask(array $param)
    {
        try {
            $response = self::instance()->moment()->createTask($param);
        } catch (\Exception $e) {
            throw new ValidateException($e->getMessage());
        }

        self::logger('创建发送朋友圈任务', compact('param'), $response);

        return new WechatResponse($response);
    }

    /**
     * 获取发送朋友圈任务创建结果
     * @param string $jobId
     * @return WechatResponse
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function getMomentTask(string $jobId)
    {
        $response = self::instance()->moment()->getTask($jobId);

        self::logger('获取发送朋友圈任务创建结果', compact('jobId'), $response);

        return new WechatResponse($response);
    }


    /**
     * 获取客户朋友圈企业发表的列表
     * @param string $momentId
     * @param string $cursor
     * @param int $limit
     * @return array
     * @throws BadResponseException
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function getMomentTaskInfo(string $momentId, string $cursor = '', int $limit = 500)
    {
        $response = self::instance()->moment()->getTasks($momentId, $cursor, $limit);

        self::logger('获取客户朋友圈企业发表的列表', compact('momentId', 'cursor', 'limit'), $response);

        return $response->toArray();
    }

    /**
     * 获取客户朋友圈发表时选择的可见范围
     * @param string $momentId
     * @param string $userId
     * @param string $cursor
     * @param int $limit
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws BadResponseException
     * @throws DecodingExceptionInterface
     */
    public static function getMomentCustomerList(string $momentId, string $userId, string $cursor, int $limit = 500)
    {
        $response = self::instance()->moment()->getCustomers($momentId, $userId, $cursor, $limit);

        self::logger('获取客户朋友圈发表时选择的可见范围', compact('momentId', 'cursor', 'userId', 'limit'), $response);

        return $response->toArray();
    }

    /**
     * 发送应用消息
     * @param array $message
     * @return WechatResponse
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public static function sendMessage(array $message)
    {
        $instance = self::instance();

        if (empty($message['agentid'])) {
            $config = $instance->getAppConfig(WorkConfig::TYPE_USER_APP);

            if (empty($config['agent_id'])) {
                throw new WechatException('请先配置agent_id');
            }

            $message['agentid'] = $config['agent_id'];
        }

        $messageClient = new MessageClient($instance->application(WorkConfig::TYPE_USER_APP)->getClient());
        $response = $messageClient->send($message);

        self::logger('发送应用消息', compact('message'), $response);

        return new WechatResponse($response);
    }

    /**
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    public static function getDepartment(): array
    {
        try {

            $response = self::department()->list();

            self::logger('获取部门列表', [], $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取子部门ID列表
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/18
     */
    public static function simpleList(): array
    {
        try {

            $response = self::department()->simpleList();

            self::logger('获取子部门ID列表', [], $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取成员ID列表
     * @param int $limit
     * @param string $cursor
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2022/10/9
     */
    public static function getUserListIds(int $limit, string $cursor = ''): array
    {
        try {

            $response = self::department()->getUserListIds($limit, $cursor);

            self::logger('获取成员ID列表', [], $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取部门详细信息
     * @param int $id
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2022/10/10
     */
    public static function getDepartmentInfo(int $id)
    {
        try {

            $response = self::department()->get($id);

            self::logger('获取部门详细信息', [], $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取部门成员详细信息
     * @param int $departmentId
     * @return array
     */
    public static function getDetailedDepartmentUsers(int $departmentId)
    {
        try {

            $response = self::user()->getDetailedDepartmentUsers($departmentId, true);

            self::logger('获取部门成员详细信息', compact('departmentId'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取通讯录成员详情
     * @param string $userId
     * @return array
     */
    public static function getMemberInfo(string $userId)
    {
        try {

            $response = self::user()->get($userId);

            self::logger('获取通讯录成员详情', compact('userId'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * userid转openid
     * @param string $userId
     * @return string|null
     */
    public static function useridByOpenid(string $userId): ?string
    {
        try {

            $response = self::user()->userIdToOpenid($userId);

            self::logger('userid转openid', compact('userId'), $response);

            return $response['openid'] ?? null;
        } catch (\Throwable $e) {

            self::error($e);

            return null;
        }
    }

    /**
     * 获取某个成员下的客户信息
     * @param string $externalUserID
     * @return array
     */
    public static function getClientInfo(string $externalUserID): array
    {
        try {

            $response = self::instance()->externalContact()->get($externalUserID);

            self::logger('获取某个成员下的客户信息', compact('externalUserID'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 批量获取客户详情
     * @param array $userids
     * @param string $cursor
     * @param int $limit
     * @return array
     */
    public static function getBatchClientList(array $userids, string $cursor = '', int $limit = 100): array
    {
        if ($limit > 100) {
            $limit = 100;
        }
        try {

            $response = self::instance()->externalContact()->batchGet($userids, $cursor, $limit);

            self::logger('批量获取客户详情', compact('userids', 'cursor', 'limit'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取客户标签
     * @param array $tagIds
     * @param array $groupIds
     * @return array
     */
    public static function getCorpTags(array $tagIds = [], array $groupIds = [])
    {
        try {

            $response = self::instance()->externalContact()->getCorpTags($tagIds, $groupIds);

            self::logger('获取客户标签', compact('tagIds', 'groupIds'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 添加客户标签
     * @param string $groupName
     * @param array $tag
     * @return array
     */
    public static function addCorpTag(string $groupName, array $tag = []): array
    {
        $params = [
            "group_name" => $groupName,
            "tag" => $tag
        ];
        try {

            $response = self::instance()->externalContact()->addCorpTag($params);

            self::logger('添加客户标签', compact('groupName', 'tag'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 编辑客户标签
     * @param string $id
     * @param string $name
     * @param int $order
     * @return array
     */
    public static function updateCorpTag(string $id, string $name, int $order = 1): array
    {
        try {

            $response = self::instance()->externalContact()->updateCorpTag($id, $name, $order);

            self::logger('编辑客户标签', compact('id', 'name', 'order'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 删除客户标签
     * @param array $tagId
     * @param array $groupId
     * @return array
     */
    public static function deleteCorpTag(array $tagId, array $groupId): array
    {
        try {

            $response = self::instance()->externalContact()->deleteCorpTag($tagId, $groupId);

            self::logger('删除客户标签', compact('tagId', 'groupId'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 编辑客户标签
     * @param string $userid
     * @param string $externalUserid
     * @param array $addTag
     * @param array $removeTag
     * @return array
     */
    public static function markTags(string $userid, string $externalUserid, array $addTag = [], array $removeTag = []): array
    {
        $params = [
            "userid" => $userid,
            "external_userid" => $externalUserid,
            "add_tag" => $addTag,
            "remove_tag" => $removeTag
        ];
        try {

            $response = self::instance()->externalContact()->markTags($params);

            self::logger('编辑客户标签', compact('params'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取客户群列表
     * @param array $useridList
     * @param int $limit
     * @param string|null $offset
     * @return array
     */
    public static function getGroupChats(array $useridList = [], int $limit = 100, string $offset = null): array
    {
        $params = [
            "status_filter" => 0,
            "owner_filter" => [
                "userid_list" => $useridList,
            ],
            "limit" => $limit
        ];

        if ($offset) {
            $params['cursor'] = $offset;
        }

        try {

            $response = self::instance()->externalContact()->getGroupChats($params);

            self::logger('获取客户群列表', compact('params'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);
            throw new WechatException($e->getMessage());
        }
    }

    /**
     * 获取群详情
     * @param string $chatId
     * @return array
     */
    public static function getGroupChat(string $chatId): array
    {
        try {

            $response = self::instance()->externalContact()->getGroupChat($chatId);

            self::logger('获取群详情', compact('chatId'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取群聊数据统计
     * @param int $dayBeginTime
     * @param int $dayEndTime
     * @param array $userIds
     * @return array
     */
    public static function groupChatStatisticGroupByDay(int $dayBeginTime, int $dayEndTime, array $userIds): array
    {
        try {

            $response = self::instance()->externalContact()->groupChatStatisticGroupByDay($dayBeginTime, $dayEndTime, $userIds);

            self::logger('获取群聊数据统计', compact('dayBeginTime', 'dayEndTime', 'userIds'), $response);

            return $response->toArray();
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 发送欢迎语
     * @param string $welcomeCode
     * @param array $message
     * @return WechatResponse
     */
    public static function sendWelcome(string $welcomeCode, array $message)
    {
        $response = self::instance()->message()->sendWelcome($welcomeCode, $message);

        self::logger('发送欢迎语', compact('welcomeCode', 'message'), $response);

        return new WechatResponse($response);
    }

    /**
     * 上传临时素材
     * @param string $path
     * @param string $type
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public static function mediaUpload(string $path, string $type = 'image')
    {
        $response = self::instance()->media()->upload($type, $path);

        self::logger('上传临时素材', compact('type', 'path'), $response);

        return new WechatResponse($response);
    }

    /**
     * 上传附件资源
     * @param string $path
     * @param string $mediaType
     * @param string $attachmentType
     * @return WechatResponse
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     */
    public static function mediaUploadAttachment(string $path, string $mediaType = 'image', string $attachmentType = '1')
    {
        if (in_array($mediaType, ['video', 'file', 'voice'])) {

            $url = 'https://qyapi.weixin.qq.com/cgi-bin/media/upload_attachment';
            $url .= '?access_token=' . self::instance()->application()->getAccessToken()->getToken();
            $url .= '&media_type=' . $mediaType . '&attachment_type=' . $attachmentType;

            $pathAtt = explode('/', $path);
            $filename = $pathAtt[count($pathAtt) - 1];
            $file = new File($path);

            $request = new Request('POST', $url, ['Content-Type' => 'multipart/form-data']);
            $fileuploade = new UploadedFile($filename, $file->getSize(), $file->getOwner(), $path, $file->getMime());
            $res = $request->withBody($fileuploade->getStream());

            $response = json_decode($res->getBody()->getContents(), true);
        } else {

            $response = self::instance()->media()->uploadAttachment($path, $mediaType, $attachmentType);
        }

        self::logger('上传附件资源', compact('path', 'mediaType', 'attachmentType'), $response);

        return new WechatResponse($response);
    }

    protected static function media(string $type = WorkConfig::TYPE_USER_APP)
    {
        $client = self::instance()->application($type)->getClient();

        return new MediaClient($client);
    }

    /**
     * 获取临时素材
     * @param string $mediaId
     * @return WechatResponse
     * @throws TransportExceptionInterface
     */
    public static function getMedia(string $mediaId)
    {
        $response = self::instance()->media()->get($mediaId);

        self::logger('获取临时素材', compact('mediaId'), $response);

        return new WechatResponse($response);
    }

    /**
     * 获取jsSDK权限配置
     * @return array|object
     */
    public static function getJsSDK(string $url = '')
    {
        try {
            $application = self::instance()->application(WorkConfig::TYPE_USER_APP);

            $utils = $application->getUtils();

            return $utils->buildJsSdkConfig(
                url: $url,
                jsApiList: ['getCurExternalContact', 'getCurExternalChat', 'getContext', 'chooseImage'],
                openTagList: [],
                debug: true,
                beta: true
            );
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }
    }

    /**
     * 获取应用配置信息
     * @param string|null $url
     * @return array
     * @throws InvalidArgumentException
     */
    public static function getAgentConfig(string $url = null): array
    {

        $instance = self::instance();
        $application = $instance->application(WorkConfig::TYPE_USER_APP);

        $config = $instance->getAppConfig(WorkConfig::TYPE_USER_APP);

        if (empty($config['agent_id'])) {
            throw new WechatException('请先配置agent_id');
        }

        try {
            return $application->getUtils()->buildJsSdkAgentConfig(
                agentId: $config['agent_id'],
                url: $url,
                jsApiList: ['getCurExternalContact', 'getCurExternalChat', 'getContext', 'chooseImage'],
                openTagList: [],
                debug: true
            );
        } catch (\Throwable $e) {

            self::error($e);

            return [];
        }

    }
}
