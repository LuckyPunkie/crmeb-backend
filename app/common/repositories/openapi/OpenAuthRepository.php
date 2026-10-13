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


namespace app\common\repositories\openapi;

use app\common\dao\openapi\OpenAuthDao;
use app\common\repositories\BaseRepository;
use crmeb\exceptions\AuthException;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Route;

class OpenAuthRepository extends BaseRepository
{
    const AUTH_TYPE_PRODUCT = '1';
    const AUTH_TYPE_ORDER = '2';

    public function __construct(OpenAuthDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建
     * @param int $merId
     * @param array $data
     * @return \app\common\dao\BaseDao|\think\Model
     * @author Qinii
     * @day 2023/7/21
     */
    public function create(int $merId, array $data)
    {
        $data['access_key'] = $this->createAccessKey($merId);
        $data['secret_key'] = $this->createSecretKey($merId);
        $data['mer_id'] = $merId;
        return $this->dao->create($data);
    }

    /**
     * 列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2023/7/21
     */
    public function getList($where,$page, $limit)
    {
        $where['is_del'] = 0;
        $query = $this->dao->getSearch($where)->hidden(['secret_key','delete_time'])->order('id ASC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count','list');
    }

    /**
     * 添加编辑表单
     * @param int $merId
     * @param $id
     * @return \FormBuilder\Form
     * @author Qinii
     * @day 2023/7/21
     */
    public function form(int $merId, $id = 0)
    {
        $formData = [];
        if ($id) {
            $formData = $this->dao->getWhere(['id' => $id])->toArray();
            if (!$formData) throw new ValidateException('数据不存在');
            $form = Elm::createForm(Route::buildUrl('merchantOpenapiUpdate', ['id' => $id])->build());
        } else {
            $form = Elm::createForm(Route::buildUrl('merchantOpenapiCreate')->build());
        }
        $form->setRule([
            Elm::input('title', '标题：')->placeholder('请输入标题'),
            Elm::selectMultiple('auth', '权限：')->options([
                ['value' => self::AUTH_TYPE_PRODUCT,'label' => '商品'],
                ['value' => self::AUTH_TYPE_ORDER,'label' => '订单'],
            ]),
            Elm::input('mark', '备注')->placeholder('请输入备注'),
            Elm::switches('status', '是否开启：', 1)->activeValue(1)->inactiveValue(0)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
        ]);
        return $form->setTitle($id ? '编辑授权账户' : '添加授权账户')->formData($formData);
    }

    /**
     * 生成 AccessKey
     * @param int $merId
     * @return string
     * @author Qinii
     * @day 2023/7/21
     */
    public function createAccessKey(int $merId)
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        $sn = $msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369));
        return 'M'.$merId.'os'.$sn;
    }

    /**
     * 生成 secret_key
     * @param int $merId
     * @return string
     * @author Qinii
     * @day 2023/7/21
     */
    public function createSecretKey(int $merId)
    {
        list($msec, $sec) = explode(' ', microtime());
        $msectime = number_format((floatval($msec) + floatval($sec)) * 1000, 0, '', '');
        return $merId.'cb'.MD5($msectime . random_int(10000, max(intval($msec * 10000) + 10000, 98369)));
    }

    /**
     * 获取secret_key
     * @param $id
     * @return array
     * @author Qinii
     * @day 2023/7/21
     */
    public function getSecretKey($id)
    {
        $data = $this->dao->get($id);
        return ['secret_key' => $data['secret_key']];
    }

    /**
     * 重置secret_key
     * @param $id
     * @return int
     * @author Qinii
     * @day 2023/7/21
     */
    public function setSecretKey($id,$merId)
    {
        $data['secret_key'] = $this->createSecretKey($merId);
        $data['update_time'] = date('Y-m-d H:i:s',time());
        $this->dao->update($id,$data);
        return  ['secret_key' => $data['secret_key']];
    }

    /**
     * 检查令牌的有效性
     *
     * 本函数用于验证传入的令牌是否有效，有效意味着该令牌曾在指定时间内被使用过。
     * 它首先检查令牌是否存在于缓存中，如果不存在，则抛出一个授权异常。
     * 接着，它获取令牌的最后使用时间，并计算令牌是否已过期。如果令牌过期，则同样抛出授权异常。
     * 这个过程旨在确保每个请求的令牌都是近期有效的，从而保障系统的安全性。
     *
     * @param string $token 需要验证的令牌字符串
     * @throws AuthException 如果令牌无效或已过期，则抛出授权异常
     */
    public function checkToken(string $token)
    {
        // 检查缓存中是否存在指定的令牌
        $has = Cache::has('openapi_' . $token);
        // 如果令牌不存在于缓存中，则抛出无效令牌异常
        if (!$has)
            throw new AuthException('无效的token');

        // 获取令牌的最后使用时间
        $lastTime = Cache::get('openapi_' . $token);
        // 计算令牌的有效期，并与当前时间进行比较
        // 如果令牌已过期，则抛出令牌过期异常
        if (($lastTime + (intval(Config::get('admin.openapi_token_valid_exp', 15))) * 60) < time())
            throw new AuthException('token 已过期，请重新登录');
    }

}
