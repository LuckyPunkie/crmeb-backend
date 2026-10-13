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

namespace crmeb\services\wechat\client\official;


use crmeb\services\wechat\client\BaseClient;
use crmeb\services\wechat\WechatException;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * 模板消息
 * Class TemplateClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/18
 * @package crmeb\services\wechat\client\official
 */
class TemplateClient extends BaseClient
{

    /**
     * @var array
     */
    protected array $message = [
        'touser' => '',
        'template_id' => '',
        'url' => '',
        'data' => [],
        'miniprogram' => '',
    ];

    /**
     * @var array|string[]
     */
    protected array $required = ['touser', 'template_id'];

    /**
     * 设置行业
     * @param string $industryOne
     * @param string $industryTwo
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function setIndustry(string $industryOne, string $industryTwo): ResponseInterface|Response
    {
        $params = [
            'industry_id1' => $industryOne,
            'industry_id2' => $industryTwo,
        ];

        return $this->api->postJson('cgi-bin/template/api_set_industry', $params);
    }

    /**
     * 添加模板消息
     * @param int $shortId
     * @param array $keywordList
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function addTemplate($shortId, $keywordList): ResponseInterface|Response
    {
        $params = ['template_id_short' => $shortId, 'keyword_name_list' => $keywordList];

        return $this->api->postJson('cgi-bin/template/api_add_template', $params);
    }

    /**
     * 获取模板消息
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function getPrivateTemplates(): ResponseInterface|Response
    {
        return $this->api->postJson('cgi-bin/template/get_all_private_template');
    }

    /**
     * 删除模板
     * @param string $templateId
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function deletePrivateTemplate(string $templateId): ResponseInterface|Response
    {
        $params = ['template_id' => $templateId];

        return $this->api->postJson('cgi-bin/template/del_private_template', $params);
    }

    /**
     * 获取行业
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function getIndustry()
    {
        return $this->api->postJson('cgi-bin/template/get_industry');
    }

    /**
     * 发送模板消息
     * @param array $data
     * @return Response|ResponseInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function send(array $data = [])
    {
        $params = $this->formatMessage($data);

        $this->restoreMessage();

        return $this->api->postJson('cgi-bin/message/template/send', $params);
    }

    protected function restoreMessage()
    {
        $this->message = (new \ReflectionClass(static::class))->getDefaultProperties()['message'];
    }

    /**
     * 获取
     * @param array $data
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    protected function formatMessage(array $data = [])
    {
        $params = array_merge($this->message, $data);

        foreach ($params as $key => $value) {
            if (in_array($key, $this->required, true) && empty($value) && empty($this->message[$key])) {
                throw new WechatException(sprintf('Attribute "%s" can not be empty!', $key));
            }

            $params[$key] = empty($value) ? $this->message[$key] : $value;
        }

        $params['data'] = $this->formatData($params['data'] ?? []);

        return $params;
    }

    /**
     * 获取data
     * @param array $data
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    protected function formatData(array $data)
    {
        $formatted = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (\array_key_exists('value', $value)) {
                    $formatted[$key] = $value;

                    continue;
                }

                if (count($value) >= 2) {
                    $value = [
                        'value' => $value[0],
                        'color' => $value[1],
                    ];
                }
            } else {
                $value = [
                    'value' => strval($value),
                ];
            }

            $formatted[$key] = $value;
        }

        return $formatted;
    }
}
