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


use think\facade\Log;
use app\common\dao\wechat\RoutineQrcodeDao;
use app\common\repositories\BaseRepository;
use crmeb\services\wechat\MiniProgram;

/**
 * Class RoutineQrcodeRepository
 * @package app\common\repositories\wechat
 * @author xaboy
 * @day 2020/6/18
 * @mixin RoutineQrcodeDao
 */
class RoutineQrcodeRepository extends BaseRepository
{
    /**
     * RoutineQrcodeRepository constructor.
     * @param RoutineQrcodeDao $dao
     */
    public function __construct(RoutineQrcodeDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 获取小程序二维码
     * @param $thirdId
     * @param $thirdType
     * @param $page
     * @param $imgUrl
     * @return array|bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getShareCode($thirdId, $thirdType, $page, $imgUrl)
    {
        $res = $this->dao->routineQrCodeForever($thirdId, $thirdType, $page, $imgUrl);
        $resCode = MiniProgram::appCodeUnlimit((string)$res->routine_qrcode_id, '', 280);
        if ($resCode)
            return ['res' => $resCode, 'id' => $res->routine_qrcode_id];
        else return false;
    }

    /**
     * 获取小程序页面带参数二维码不保存数据库
     * @param string $page
     * @param string $pramam
     * @param int $width
     * @return mixed
     */
    public function getPageCode($page = '', $pramam = "", $width = 280)
    {
        try{
            return MiniProgram::appCodeUnlimit((string)$pramam, $page, $width);
        }catch (\Exception $e) {
            Log::error('小程序生成二维码失败：'.$e->getMessage());
        }
    }
}
