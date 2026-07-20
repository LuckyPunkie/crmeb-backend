<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\client\miniprogram;


use crmeb\services\wechat\client\BaseClient;
use crmeb\services\wechat\WechatException;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SubscribeClient extends BaseClient
{

    /**
     * @var array
     */
    protected array $message = [
        'touser' => '',
        'template_id' => '',
        'page' => '',
        'data' => [],
        'miniprogram_state' => 'formal',
    ];

    /**
     * @var string[]
     */
    protected array $required = ['touser', 'template_id', 'data'];

    /**
     * 添加订阅消息模版
     * @param string $tid
     * @param array $kidList
     * @param string|null $sceneDesc
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function addTemplate(string $tid, array $kidList, string $sceneDesc = null): ResponseInterface|Response
    {
        $sceneDesc = $sceneDesc ?? '';
        $data = compact('tid', 'kidList', 'sceneDesc');

        return $this->api->post('wxaapi/newtmpl/addtemplate', $data);
    }

    /**
     * 删除模板消息
     * @param string $id
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function deleteTemplate(string $id): ResponseInterface|Response
    {
        return $this->api->post('wxaapi/newtmpl/deltemplate', ['priTmplId' => $id]);
    }

    /**
     * 获取模版标题的关键词列表
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     * @param string $tid
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     */
    public function getTemplateKeywords(string $tid): ResponseInterface|Response
    {
        return $this->api->get('wxaapi/newtmpl/getpubtemplatekeywords', compact('tid'));
    }

    /**
     * 发送消息
     * @param array $data
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function send(array $data = []): ResponseInterface|Response
    {
        $params = $this->formatMessage($data);

        $this->restoreMessage();

        return $this->api->postJson('cgi-bin/message/subscribe/send', $params);
    }

    /**
     * @param array $data
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function formatMessage(array $data = []): array
    {
        $params = array_merge($this->message, $data);

        foreach ($params as $key => $value) {
            if (in_array($key, $this->required, true) && empty($value) && empty($this->message[$key])) {
                throw new WechatException(sprintf('Attribute "%s" can not be empty!', $key));
            }

            $params[$key] = empty($value) ? $this->message[$key] : $value;
        }

        foreach ($params['data'] as $key => $value) {
            if (is_array($value)) {
                if (\array_key_exists('value', $value)) {
                    $params['data'][$key] = ['value' => $value['value']];

                    continue;
                }

                if (count($value) >= 1) {
                    $value = [
                        'value' => $value[0],
                    ];
                }
            } else {
                $value = [
                    'value' => strval($value),
                ];
            }

            $params['data'][$key] = $value;
        }

        return $params;
    }

    /**
     * Restore message.
     */
    protected function restoreMessage()
    {
        $this->message = (new \ReflectionClass(static::class))->getDefaultProperties()['message'];
    }
}
