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


use app\common\dao\wechat\WechatQrcodeDao;
use app\common\repositories\BaseRepository;
use crmeb\services\wechat\OfficialAccount;
use think\exception\ValidateException;
use think\Model;

/**
 * Class WechatQrcodeRepository
 * @package app\common\repositories\wechat
 * @author xaboy
 * @day 2020-04-28
 * @mixin WechatQrcodeDao
 */
class WechatQrcodeRepository extends BaseRepository
{
    /**
     * WechatQrcodeRepository constructor.
     * @param WechatQrcodeDao $dao
     */
    public function __construct(WechatQrcodeDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建临时二维码
     *
     * 本函数用于生成微信公众号的临时二维码，并根据二维码ID（$qtcode_id）决定是更新已有的二维码记录还是创建新的二维码记录。
     * 临时二维码具有固定的有效时长，且在过期后不能再次使用。
     *
     * @param int $id 二维码关联的ID，用于指定二维码的具体内容，如商品ID等。
     * @param string $type 二维码的类型，用于区分不同种类的二维码。
     * @param int|null $qtcode_id 二维码的ID，如果为null，则表示创建新的二维码记录；否则，表示更新已有的二维码记录。
     * @return int|bool 返回更新或创建的二维码记录ID，如果操作失败，则返回false。
     */
    public function createTemporaryQrcode($id, $type, $qtcode_id = null)
    {
        // 通过项目内微信服务封装生成临时二维码
        $data = OfficialAccount::temporaryQrCode((int)$id, 30 * 24 * 3600);

        // 保存二维码的URL至$data数组
        $data['qrcode_url'] = $data['url'];

        // 计算二维码的过期时间，并保存至$data数组
        $data['expire_seconds'] = $data['expire_seconds'] + time();

        // 通过二维码的ticket获取二维码的URL，并保存至$data数组
        $data['url'] = OfficialAccount::qrCodeUrl($data['ticket']);

        // 设置二维码的状态为可用
        $data['status'] = 1;

        // 保存关联的ID和类型至$data数组
        $data['third_id'] = $id;
        $data['third_type'] = $type;

        // 如果$qtcode_id不为空，则更新二维码记录，否则创建新的二维码记录
        if ($qtcode_id) {
            return $this->dao->update($qtcode_id, $data);
        } else {
            return $this->dao->create($data);
        }
    }

    /**
     * 创建永久二维码
     *
     * 本函数用于生成微信公众号的永久二维码，并将其相关数据存储到数据库中。
     * 永久二维码的特点是，扫描后不会失效，可以用于诸如会员卡绑定、商品链接等长期有效的场景。
     *
     * @param int $id 二维码的标识ID，用于关联具体的数据，如商品ID、会员卡ID等。
     * @param string $type 二维码的类型，用于区分不同种类的二维码，如商品类型、会员卡类型等。
     * @return static 返回创建的二维码记录实例。
     */
    public function createForeverQrcode($id, $type)
    {
        // 通过项目内微信服务封装生成永久二维码
        $data = OfficialAccount::foreverQrCode((int)$id);

        // 将二维码的URL地址赋值给qrcode_url字段，便于后续展示或下载
        $data['qrcode_url'] = $data['url'];

        // 通过二维码的ticket获取实际的二维码URL，更新data中的URL字段
        $data['url'] = OfficialAccount::qrCodeUrl($data['ticket']);

        // 永久二维码的有效期设置为0，表示永久有效
        $data['expire_seconds'] = 0;

        // 设置二维码的状态为可用（1）
        $data['status'] = 1;

        // 记录二维码的第三方标识ID和类型
        $data['third_id'] = $id;
        $data['third_type'] = $type;

        // 根据数据创建新的二维码记录，并返回该记录实例
        return self::create($data);
    }

    /**
     * 获取临时二维码
     *
     * 本函数用于根据类型和ID获取临时二维码的信息。如果二维码已过期或不存在，则重新创建一个。
     * 临时二维码用于一些需要时效性的场景，比如会议签到、活动报名等。
     *
     * @param string $type 二维码类型，用于区分不同场景的二维码。
     * @param int $id 与二维码相关的ID，用于唯一标识该二维码。
     * @return array 包含二维码信息的数组，包括ticket和expire_seconds等字段。
     * @throws ValidateException 如果无法获取到有效的临时二维码信息，则抛出异常。
     */
    public function getTemporaryQrcode($type, $id)
    {
        // 从数据库尝试获取临时二维码信息
        $qrInfo = $this->dao->getTemporaryQrcode($type, $id);

        // 检查二维码信息是否有效，如果无效则重新创建
        if (!$qrInfo || (!$qrInfo['expire_seconds'] || $qrInfo['expire_seconds'] < time())) {
            $qrInfo = $this->createTemporaryQrcode($id, $type);
        }

        // 确保二维码的ticket存在且不为空，否则抛出异常
        if (!isset($qrInfo['ticket']) || !$qrInfo['ticket']) {
            throw new ValidateException('临时二维码获取错误');
        }

        // 返回有效的二维码信息
        return $qrInfo;
    }

    /**
     * 获取永久二维码信息
     *
     * 本函数用于根据类型和ID获取永久二维码的信息。如果该二维码信息不存在，则通过调用
     * createForeverQrcode函数创建新的永久二维码信息。
     *
     * @param string $type 二维码类型
     * @param int $id 与二维码相关联的ID
     * @return array 二维码信息，包含ticket等关键信息
     * @throws ValidateException 如果二维码信息获取失败，则抛出异常
     */
    public function getForeverQrcode($type, $id)
    {
        // 尝试从数据库中获取永久二维码信息
        $qrInfo = $this->dao->getForeverQrcode($type, $id);

        // 如果二维码信息不存在，则创建新的永久二维码
        if (!$qrInfo) {
            $qrInfo = $this->createForeverQrcode($id, $type);
        }

        // 检查二维码信息是否包含有效的ticket，如果没有则抛出异常
        if (!isset($qrInfo['ticket']) || !$qrInfo['ticket']) {
            throw new ValidateException('二维码获取错误');
        }

        // 返回二维码信息
        return $qrInfo;
    }

}
