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


namespace app\common\dao\wechat;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\wechat\WechatQrcode;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class WechatQrcodeDao
 * @package app\common\dao\wechat
 * @author xaboy
 * @day 2020-04-28
 */
class WechatQrcodeDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return WechatQrcode::class;
    }

    /**
     * 通过二维码票号查询微信二维码信息。
     *
     * 本函数旨在通过微信二维码的票号，从数据库中检索对应的二维码信息。
     * 这对于需要根据二维码票号进行特定操作的场景非常有用，比如验证入场券的有效性。
     *
     * @param string $ticket 二维码票号，用于查询数据库。
     * @return array|false 返回匹配的二维码信息数组，如果未找到则返回false。
     */
    public function ticketByQrcode($ticket)
    {
        // 使用WechatQrcode类的数据库访问对象，根据ticket查询数据库，返回找到的第一条记录。
        return WechatQrcode::getDB()->where('ticket', $ticket)->find();
    }

    /**
     * 获取永久二维码信息
     *
     * 本函数用于查询微信公众号永久二维码的相关信息。通过传入类型和标识符，定位到特定的二维码记录。
     * 永久二维码的特点是其有效期为0，意味着一旦生成，将一直有效。
     *
     * @param string $type 二维码类型标识，用于区分不同类型的二维码。
     * @param int $id 与二维码相关联的标识符，用于唯一标识某个特定的二维码。
     * @return object 返回符合查询条件的二维码信息对象，如果未找到则返回null。
     */
    public function getForeverQrcode($type, $id)
    {
        // 构建查询条件，查询third_id为$id，third_type为$type，且expire_seconds为0（表示永久有效）的二维码记录
        return WechatQrcode::getDB()->where('third_id', $id)->where('third_type', $type)->where('expire_seconds', 0)->find();
    }

    /**
     * 根据类型和ID获取临时二维码信息
     *
     * 本函数用于从数据库中检索指定类型和ID对应的临时二维码信息。
     * 临时二维码是具有过期时间的，本函数只会返回未过期的二维码记录。
     *
     * @param string $type 二维码类型标识
     * @param int $id 与二维码相关的ID，用于标识特定的二维码
     * @return object|null 返回符合查询条件的临时二维码对象，如果未找到则返回null
     */
    public function getTemporaryQrcode($type, $id)
    {
        // 通过WechatQrcode的数据库访问对象执行查询
        // 查询条件为third_id为$id，third_type为$type，且expire_seconds大于0（表示未过期）
        return WechatQrcode::getDB()->where('third_id', $id)->where('third_type', $type)->where('expire_seconds', '>', 0)->find();
    }
}
