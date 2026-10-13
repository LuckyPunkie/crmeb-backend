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


namespace app\common\repositories\wechat;


use app\common\dao\BaseDao;
use app\common\dao\wechat\WechatReplyDao;
use app\common\model\wechat\WechatReply;
use crmeb\services\wechat\OfficialAccount;
use app\common\repositories\BaseRepository;
use crmeb\services\wechat\Messages;
use crmeb\services\wechat\message\Image;
use crmeb\services\wechat\message\News;
use crmeb\services\wechat\message\Text;
use crmeb\services\wechat\message\Voice;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\Model;

/**
 * Class WechatReplyRepository
 * @package app\common\repositories\wechat
 * @author xaboy
 * @day 2020-04-24
 * @mixin WechatReplyDao
 */
class WechatReplyRepository extends BaseRepository
{
    /**
     * WechatReplyRepository constructor.
     * @param WechatReplyDao $dao
     */
    public function __construct(WechatReplyDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-27
     */
    public function getLst(array $where, int $page, int $limit)
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->setOption('field', [])->field('wechat_reply_id,key,type,status,create_time')->page($page, $limit)->select();

        return compact('list', 'count');
    }

    /**
     * 保存
     * @param string $key
     * @param string $type
     * @param array $data
     * @param int $status
     * @param int $hidden
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-24
     */
    public function save(string $key, string $type, array $data, int $status = 1, int $hidden = 0)
    {
        $method = 'tidy' . ucfirst($type);
        $reply = $this->dao->keyByReply($key);
        $data = $this->{$method}($data, $reply);
        if ($reply) {
            $reply->save(compact('status', 'data', 'hidden', 'type'));
        } else {
            $this->dao->create(compact('key', 'type', 'data', 'status', 'hidden'));
        }
    }

    /**
     * 更新
     * @param $id
     * @param array $data
     * @param int $hidden
     * @return bool
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-27
     */
    public function updateReply($id, array $data, int $hidden = 0)
    {

        $method = 'tidy' . ucfirst($data['type']);
        $reply = $this->dao->get($id);
        $data['data'] = $this->{$method}($data['data'], $reply);
        $data['hidden'] = $hidden;
        return $reply->save($data);
    }

    /**
     * 新增
     * @param array $data
     * @param int $hidden
     * @return BaseDao|Model
     * @author xaboy
     * @day 2020-04-27
     */
    public function create(array $data, int $hidden = 0)
    {
        $method = 'tidy' . ucfirst($data['type']);
        $data['data'] = $this->{$method}($data['data'], null);
        $data['hidden'] = $hidden;
        return $this->dao->create($data);
    }

    /**
     * 回复
     * @param string $key
     * @return array|News|Text|Image|Voice|void
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-27
     */
    public function reply(string $key)
    {
        $reply = $this->dao->keyByValidData($key);
        if (!$reply && $key != 'default')
            $reply = $this->dao->keyByValidData('default');
        if (!$reply) return;
        if ($reply['type'] == 'voice')
            return Messages::voiceMessage($reply['data']['media_id']);
        else if ($reply['type'] == 'image')
            return Messages::imageMessage($reply['data']['media_id']);
        else if ($reply['type'] == 'news')
            return Messages::newsMessage($reply['data']['list']);
        else if (isset($reply['data']['content'])) {
            return Messages::textMessage($reply['data']['content']);
        }
    }

    /**
     * 格式化文字
     * @param $data
     * @param WechatReply|null $reply
     * @return array
     * @author xaboy
     * @day 2020-04-24
     */
    public function tidyText($data, ?WechatReply $reply)
    {
        $res = [];
        if (!isset($data['content']) || !$data['content'])
            throw new ValidateException('请输入回复信息内容');
        $res['content'] = $data['content'];
        return $res;
    }

    /**
     * 格式化信息
     * @param $data
     * @param WechatReply|null $reply
     * @return array|mixed|null
     * @author xaboy
     * @day 2020-04-24
     */
    public function tidyImage($data, ?WechatReply $reply)
    {
        if (!isset($data['src']) || !$data['src'])
            throw new ValidateException('请上传回复的图片');
        $res = null;
        if ($reply) {
            $replyData = $reply->getAttr('data');
            if (isset($replyData['src']) && $replyData['src'] == $data['src'])
                $res = $replyData;
        }
        if (is_null($res)) {
            $res = [
                'src' => $data['src']
            ];

            if (!file_exists(app()->getRootPath() . 'public' . $data['src']))
                throw new ValidateException('图片文件不存在');

            $material = OfficialAccount::instance()->material()->uploadImage(app()->getRootPath() . 'public' . $data['src']);
            $material = method_exists($material, 'toArray') ? $material->toArray() : (array)$material;
            $res['media_id'] = $material['media_id'] ?? '';
        }
        return $res;
    }

    /**
     * 格式化语音
     * @param $data
     * @param WechatReply|null $reply
     * @return array|mixed|null
     * @author xaboy
     * @day 2020-04-24
     */
    public static function tidyVoice($data, ?WechatReply $reply)
    {
        if (!isset($data['src']) || !$data['src'])
            throw new ValidateException('请上传回复的声音');
        $res = null;
        if ($reply) {
            $replyData = $reply->getAttr('data');
            if (isset($replyData['src']) && $replyData['src'] == $data['src'])
                $res = $replyData;
        }

        if (is_null($res)) {
            $res = [
                'src' => $data['src']
            ];

            if (!file_exists(app()->getRootPath() . 'public' . $data['src']))
                throw new ValidateException('声音文件不存在');

            $material = OfficialAccount::instance()->material()->uploadVoice(app()->getRootPath() . 'public' . $data['src']);
            $material = method_exists($material, 'toArray') ? $material->toArray() : (array)$material;
            $res['media_id'] = $material['media_id'] ?? '';
        }

        return $res;
    }

    /**
     * 格式化图文
     * @param $params
     * @param WechatReply|null $reply
     * @return mixed
     * @author xaboy
     * @day 2020-04-24
     */
    public static function tidyNews($params, ?WechatReply $reply)
    {
        if (!isset($params['list']) || !count($params['list']))
            throw new ValidateException('请选择图文消息');
        $siteUrl = systemConfig('site_url');
        $data = $params['list'];
        foreach ($data as $k => $v) {
            if (empty($v['url'])) $data[$k]['url'] = rtrim($siteUrl, '/') . '/pages/news_details/index?id=' . $v['article_id'];
            if ($v['image_input']) $data[$k]['image'] = $v['image_input'];
        }
        $params['list'] = $data;
        return $params;
    }
}
