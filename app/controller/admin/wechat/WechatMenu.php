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


namespace app\controller\admin\wechat;


use crmeb\basic\BaseController;
use crmeb\services\wechat\OfficialAccount;
use app\common\repositories\system\CacheRepository;
use Exception;
use think\db\exception\DbException;

/**
 * Class WechatMenu
 * @package app\controller\admin\wechat
 * @author xaboy
 * @day 2020-04-24
 *  微信公众号菜单
 */
class WechatMenu extends BaseController
{
    /**
     * 获取菜单
     * @param CacheRepository $repository
     * @return mixed
     * @author xaboy
     * @day 2020-04-24
     */
    public function info(CacheRepository $repository)
    {
        $data['wechat_menus'] = $repository->getResultByKey('wechat_menus') ?? [];
        return app('json')->success($data);
    }

    /**
     * 设置菜单
     * @param CacheRepository $repository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-04-24
     */
    public function save(CacheRepository $repository)
    {
        $buttons = (array)$this->request->param('button', []);
        if (!count($buttons)) return app('json')->fail('请添加至少一个按钮');

        try {
            OfficialAccount::instance()->user()->menuCreate($buttons);
        } catch (Exception $e) {
            return app('json')->fail('设置失败:' . $e->getMessage());
        }
        $repository->save('wechat_menus', $buttons);
        return app('json')->success('设置成功');
    }
}
