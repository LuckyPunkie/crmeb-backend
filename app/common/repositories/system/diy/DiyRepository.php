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


namespace app\common\repositories\system\diy;

use app\common\dao\system\diy\DiyDao;
use app\common\model\system\merchant\Merchant;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\system\RelevanceRepository;
use crmeb\services\QrcodeService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

/**
 * Diy
 */
class DiyRepository extends BaseRepository
{
    const IS_DEFAULT_DIY = 'is_default_diy';
    const IS_DIY_DIY = 1; // 自定义页面
    const IS_DIY_MICRO  = 0; // 微页面
    const IS_DIY_PRODUCT = 2; // 商品
    const IS_DIY_FAB = 3; // 悬浮按钮
    const IS_DIY_PRODUCT_CARTGORY = 4; // 商品分类


    public function __construct(DiyDao $dao)
    {
        $this->dao = $dao;
    }

    public function getList(array $where, int $page, int $limit )
    {
        $query = $this->dao->search($where);
        $count = $query->count();
        $list = $query->page($page,$limit)->select();
        return compact('count','list');
    }


    public function getThemeVar($type)
    {
        $var = [
            'purple' => [
                'type' => 'purple',
                'theme_color' => '#905EFF',
                'assist_color' => '#FDA900',
                'theme' => '--view-theme: #905EFF;--view-assist:#FDA900;--view-priceColor:#905eff;--view-bgColor:rgba(144, 94, 255, 0.06);--view-minorColor:rgba(144, 94, 255,.1);--view-bntColor11:#FFC552;--view-bntColor12:#FDB000;--view-bntColor21:#905EFF;--view-bntColor22:#A764FF;'
            ],
            'orange' => [
                'type' => 'orange',
                'theme_color' => '#FF5C2D',
                'assist_color' => '#FDB000',
                'theme' => '--view-theme: #FF5C2D;--view-assist:#FDB000;--view-priceColor:#FF5C2D;--view-bgColor:rgba(255, 92, 45, 0.06);--view-minorColor:rgba(255, 92, 45,.1);--view-bntColor11:#FDBA00;--view-bntColor12:#FFAA00;--view-bntColor21:#FF5C2D;--view-bntColor22:#FF9445;'
            ],
            'pink' => [
                'type' => 'pink',
                'theme_color' => '#FF448F',
                'assist_color' => '#FDB000',
                'theme' => '--view-theme: #FF448F;--view-assist:#FDB000;--view-priceColor:#FF448F;--view-bgColor:rgba(255, 68, 143, 0.06);--view-minorColor:rgba(255, 68, 143,.1);--view-bntColor11:#FDBA00;--view-bntColor12:#FFAA00;--view-bntColor21:#FF67AD;--view-bntColor22:#FF448F;'
            ],
            'default' => [
                'type' => 'default',
                'theme_color' => '#E93323',
                'assist_color' => '#FF7612',
                'theme' => '--view-theme: #E93323;--view-assist:#FF7612;--view-priceColor:#E93323;--view-bgColor:rgba(233, 51, 35, 0.06);--view-minorColor:rgba(233, 51, 35,.1);--view-bntColor11:#FEA10F;--view-bntColor12:#FA8013;--view-bntColor21:#FA6514;--view-bntColor22:#E93323;'
            ],
            'green' => [
                'type' => 'green',
                'theme_color' => '#42CA4D',
                'assist_color' => '#FE960F',
                'theme' => '--view-theme: #42CA4D;--view-assist:#FE960F;--view-priceColor:#42ca4d;--view-bgColor:rgba(66, 202, 77, 0.06);--view-minorColor:rgba(66, 202, 77,.1);--view-bntColor11:#FDBA00;--view-bntColor12:#FFAA00;--view-bntColor21:#42CA4D;--view-bntColor22:#70E038;'
            ],
            'blue' => [
                'type' => 'blue',
                'theme_color' => '#1DB0FC',
                'assist_color' => '#FFB200',
                'theme' => '--view-theme: #1DB0FC;--view-assist:#FFB200;--view-priceColor:#1db0fc;--view-bgColor:rgba(29, 176, 252, 0.06);--view-minorColor:rgba(29, 176, 252,.1);--view-bntColor11:#FFD652;--view-bntColor12:#FEB60F;--view-bntColor21:#40D1F4;--view-bntColor22:#1DB0FC;'
            ],
        ];
        return $var[$type] ?? $var['default'];
    }

    /**
     * 平台后台的商户默认模板列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2023/9/4
     */
    public function getMerDefaultList(array $where,int $page, int $limit)
    {
        $field = 'is_diy,template_name,id,title,name,type,add_time,update_time,status,is_default';
        $query = $this->dao->search($where)->where('is_default',2)->whereOr(function($query) use($where){
            $query->where('type',2)->where('is_default',1)
            ->when(isset($where['name']) && $where['name'] !== '', function($query) use($where){
                $query->whereLike('name',"%{$where['name']}%");
            });
        })->order('is_default DESC, status DESC, update_time DESC,add_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select()
            ->each(function($item) use($where){
                if ($item['is_default'])  {
                    $id = merchantConfig(0, self::IS_DEFAULT_DIY) ?: 0;
                    $item['status'] = ($id == $item['id']) ? 1 : 0;
                    return $item;
                }
            });
        return compact('count','list');
    }
    /**
     * 获取DIY列表
     * @param array $where
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getSysList(array $where,int $page, int $limit)
    {
        $field = 'is_diy,template_name,id,title,name,type,add_time,update_time,status,is_default';
        $query = $this->dao->search($where)->order('is_default DESC, status DESC, update_time DESC,add_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select()
            ->each(function($item) use($where){
                if ($item['is_default'])  {
                    $id = merchantConfig(0, self::IS_DEFAULT_DIY) ?: 0;
                    $item['status'] = ($id == $item['id']) ? 1 : 0;
                    return $item;
                }
            });
        return compact('count','list');
    }

    /**
     * 商户获取diy列表
     * @param array $where
     * @param int $page
     * @param int $limit
     * @return array
     * @author Qinii
     * @day 2023/7/14
     */
    public function getMerchantList(array $where,int $page, int $limit)
    {
        $field = 'is_diy,template_name,id,title,name,type,add_time,update_time,status,is_default';
        $id = merchantConfig($where['mer_id'], self::IS_DEFAULT_DIY) ?: 0;
        $query = $this->dao->search($where)->order('is_default DESC, status DESC, update_time DESC,add_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select()
            ->each(function($item) use($id){
                $item['status'] = ($id == $item['id']) ? 1 : 0;
                return $item;
            });
        return compact('count','list');
    }

    /**
     * 商户获取自己的默认模板
     * @param array $where
     * @param int $page
     * @param int $limit
     * @author Qinii
     * @day 2023/9/4
     */
    public function getMerchantDefaultList(array $where,int $page, int $limit)
    {
        $field = 'is_diy,template_name,id,title,name,type,add_time,update_time,status,is_default';
        $query = $this->dao->search($where)->order('is_default DESC, status DESC, update_time DESC,add_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->setOption('field',[])->field($field)->select();
        return compact('count','list');
    }



    /**
     * 保存资源
     * @param int $id
     * @param array $data
     * @return int
     */
    public function saveData(int $id, array $data)
    {
        if ($id) {
            if ($data['type'] === '') {
                unset($data['type']);
            }
            $data['update_time'] = date('Y-m-d H:i:s',time());
            $this->dao->update($id, $data);
        } else {
            $data['status'] = 0;
            $data['add_time'] = date('Y-m-d H:i:s',time());
            $data['update_time'] = date('Y-m-d H:i:s',time());
            $res = $this->dao->create($data);
            if (!$res) throw new ValidateException('保存失败');
            $id = $res->id;
        }
        $where = [
            'is_diy' => 1,
            'is_del' => 0,
            'id' => $id
        ];
        ksort($where);
        $cache_unique = 'get_sys_diy_'. md5(json_encode($where));
        Cache::delete($cache_unique);
        $micro_unique = 'sys.get_sys_micro_'.$id;
        Cache::delete($micro_unique);
        return $id;
    }

    /**
     * 删除DIY模板
     * @param int $id
     */
    public function del(int $id, $merId)
    {
        $diyData = $this->dao->getWhere(['id' => $id]);
        if (!$diyData) throw new ValidateException('数据不存在');
        if ($diyData['is_default'] && $merId) throw new ValidateException('无权删除默认模板');
        if ($diyData['is_default']){
            $count = $this->dao->search(['type' => $diyData['type']])
                ->where('is_default','<>',0)
                ->where('id','<>',$id)
                ->count();
            if (!$count)throw new ValidateException('至少存在一个默认模板');
        }
        $res = $this->dao->delete($id);
        if (!$res) throw new ValidateException('删除失败，请稍后再试');
    }


    /**
     * 获取diy详细数据
     * @param int $id
     * @return array|object
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function getMicro($id)
    {
        $where = ['id' => $id,'is_diy' => 0];
        $data = [];
        $diyInfo = $this->dao->getWhere($where);
        if ($diyInfo) {
            $data = $diyInfo->toArray();
            $data['value'] = json_decode($diyInfo['value'], true);
        }
        return compact('data');
    }

    /**
     * 商城首页/商户首页/预览等调用获取diy详情
     * @param int $merId
     * @param int $id
     * @param int $isDiy
     * @return array
     * @author Qinii
     * @day 2023/7/15
     */
    public function show(int $merId, int $id,int $isDiy = 1)
    {
        $where = ['is_diy' => $isDiy, 'is_del' => 0,];
        if (!$id) {
            $id = merchantConfig($merId, self::IS_DEFAULT_DIY);
            if (!$id || ($id && !$this->dao->get($id))) {
                if ($merId) {
                    $merchant = app()->make(MerchantRepository::class)->get($merId);
                    if (empty($merchant)) throw new ValidateException('商户信息有误！');
                    $scop = [
                        'mer_id' => $merId,
                        'type_id'=> $merchant->type_id,
                        'category_id'=> $merchant->category_id,
                        'is_trader'=> $merchant->is_trader,
                    ];
                    $ids = $this->dao->withMerSearch($scop);
                    if (empty($ids)) $ids = $this->dao->search(['type' => 2])->order('is_default','desc')->column('id');
                } else {
                    $ids = $this->dao->search(['is_default' => 1,'type' => 1])->column('id');
                }
                if (empty($ids)) throw new ValidateException('模板获取失败，请联系管理员！');
                $id = $ids[array_rand($ids,1)];
            }
        }
        $where['id'] = $id;
        ksort($where);
        $cache_unique = 'get_sys_diy_' . md5(json_encode($where));
        
        $data = Cache::remember($cache_unique,function()use($merId,$id,$where){
            $diyInfo = $this->dao->getWhere($where);
            if ($diyInfo) {
                if ($diyInfo['mer_id'] != $merId && !$diyInfo['is_default']) throw new ValidateException('模板不存在或不属于您');
                $diyInfo = $diyInfo->toArray();
                $diyInfo['value'] = json_decode($diyInfo['value'], true);
            } else {
                $diyInfo = [];
            }
            return json_encode($diyInfo, JSON_UNESCAPED_UNICODE);
        }, 3600);
        $data = json_decode($data);
        return compact('data');
    }


    /**
     * 获取底部导航
     * @param string $template_name
     * @return array|mixed
     */
    public function getNavigation()
    {
        $id = merchantConfig(0, self::IS_DEFAULT_DIY);
        $diyInfo = $this->dao->getWhere(['id' => $id,'is_del' => 0],'value');
        if (!$diyInfo) {
            $where = ['is_default' =>  1,];
            $diyInfo = $this->dao->getWhere($where,'value');
        }
        $navigation = [];
        if ($diyInfo) {
            $value = json_decode($diyInfo['value'], true);
            foreach ($value as $item) {
                if (isset($item['name']) && strtolower($item['name']) === 'pagefoot') {
                    $navigation = $item;
                    break;
                }
            }
        }
        return $navigation;
    }

    /**
     * 复制数据条目
     *
     * 该方法用于根据给定的ID复制一条数据记录。主要应用于需要生成类似但不完全相同的数据的情况，
     * 比如复制一个商品、订单或其他需要保留原始信息但又需要独立标识的数据实体。
     *
     * @param int $id 需要复制的数据的主键ID
     * @param int $merId 商户ID，用于标识数据是属于哪个商户的，0表示平台复制
     * @return array 包含新复制数据的ID的信息
     * @throws ValidateException 如果原数据不存在，则抛出异常
     */
    public function copy($id, $merId)
    {
        // 根据ID获取原始数据
        $data = $this->dao->getWhere([$this->dao->getPk() => $id]);

        // 如果原始数据不存在，则抛出异常
        if (!$data) throw new ValidateException('数据不存在');

        // 将查询结果转换为数组格式
        $data = $data->toArray();

        // 生成新的数据名称，标识这是一个复制的版本
        $data['name'] = ($merId ? '商户复制-' :  '平台复制-' ).$data['name'].'-copy';

        // 设置新的添加时间和更新时间
        $data['add_time'] = date('Y-m-d H:i:s',time());
        $data['update_time'] = date('Y-m-d H:i:s',time());

        // 设置新数据的状态为0，表示未启用或待处理
        $data['status'] = 0;

        // 设置商户ID，如果$merId非0，则表示这是属于某个商户的数据
        $data['mer_id'] = $merId;

        // 设置范围类型为0，表示全局范围
        $data['scope_type'] = 0;

        // 如果是平台复制且数据类型为2，则设置为默认数据
        $data['is_default'] =  (!$merId && $data['type'] == 2) ? 1 : 0;

        // 删除原始的主键ID，因为这是复制新的数据记录
        unset($data[$this->dao->getPk()]);

        // 创建新的数据记录
        $res = $this->dao->create($data);

        // 获取新创建数据的主键ID
        $id = $res[$this->dao->getPk()];

        // 返回包含新数据ID的信息
        return compact('id');
    }


    /**
     * 设置模板的使用状态。
     * 此方法用于将指定的模板设置为使用状态，并确保操作的模板属于当前商家或为默认模板。
     * 如果模板不存在或不属于当前商家且不是默认模板，将抛出异常。
     * 使用事务处理来确保数据库操作的一致性。
     *
     * @param int $id 模板的ID。
     * @param int $merId 商家的ID。
     * @return mixed 返回事务处理的结果。
     * @throws ValidateException 如果模板不存在或模板不属于当前商家且不是默认模板，则抛出此异常。
     */
    public function setUsed(int $id, int $merId)
    {
        // 根据ID获取模板信息
        $diyInfo = $this->dao->getWhere(['id' => $id]);
        // 如果模板不存在，则抛出异常
        if (!$diyInfo) throw new ValidateException('模板不存在');

        // 如果模板的商家ID不等于当前商家ID且模板不是默认模板，则抛出异常
        if ($diyInfo['mer_id'] != $merId && !$diyInfo['is_default']) {
            throw new ValidateException('模板不属于你');
        }

        // 实例化配置值仓库类
        $make = app()->make(ConfigValueRepository::class);
        // 使用事务处理来设置模板的使用状态和默认模板状态
        return Db::transaction(function () use($id, $merId, $make){
            // 设置模板为使用状态
            $this->dao->setUsed($id, $merId);
            // 设置默认模板的ID
            return $make->setFormData([self::IS_DEFAULT_DIY => $id ], $merId);
        });
    }


    /**
     * 根据条件获取选项数据
     *
     * 本函数旨在通过特定的条件从数据库中检索选项的标签和值。这些选项通常用于下拉列表或其他形式的选项集合中。
     * 使用DAO模式，它委托了实际的数据检索操作给DAO对象，保持了业务逻辑与数据访问逻辑的分离。
     *
     * @param array $where 查询条件数组，用于筛选选项数据。
     * @return array 返回一个数组，其中每个元素包含两个键：'label' 和 'value'，分别对应选项的显示标签和实际值。
     */
    public function getOptions(array $where)
    {
        // 使用DAO对象执行查询，根据$where条件获取搜索结果，并指定返回的字段为'name'作为标签，'id'作为值。
        return $this->dao->getSearch($where)->field('name label, id value')->select();
    }

    /**
     * 获取没个模板的适用范围
     * @param $id
     * @return array|\think\Model|null
     * @author Qinii
     * @day 2023/7/15
     */
    public function getScope($id)
    {
        $res = $this->dao->getWhere(['id' => $id],'id,scope_type',['relevance']);
        $scope_value = [];
        foreach ($res['relevance'] as $item) {
            $scope_value[] = $item['right_id'];
        }
        unset($res['relevance']);
        $data['scope_type'] = is_null($res['scope_type']) ? 4 : $res['scope_type'];
        $data['scope_value'] = $scope_value;
        return  $data;
    }

    /**
     * 保存模板的适用范围
     * @param $id
     * @param $data
     * @return mixed
     * @author Qinii
     * @day 2023/7/15
     */
    public function setScope($id,$data)
    {
        $rest = $this->dao->get($id);
        if (!$rest) throw new ValidateException('数据不存在');
        if ($rest->type != 2 && !$rest->is_default) throw new ValidateException('非默认模板');
        //DIY默认模板适用范围 0. 指定店铺、1. 指定商户分类、2. 指定店铺类型、3. 指定商户类别、4.全部店铺
        $relevanceRepository = app()->make(RelevanceRepository::class);
        $oldRelevanceType = RelevanceRepository::MER_DIY_SCOPE[$rest['scope_type']] ?? '';
        $relevanceType = RelevanceRepository::MER_DIY_SCOPE[$data['scope_type']] ?? '';

        return Db::transaction(function() use($id,$data,$relevanceRepository,$rest,$relevanceType,$oldRelevanceType) {
            if ($oldRelevanceType) $relevanceRepository->clear($id,$oldRelevanceType);
            if (!empty($data['scope_value']) && $relevanceType) {
                $relevanceRepository->createMany($id,$data['scope_value'],$relevanceType);
            }
            $rest->scope_type = $data['scope_type'];
            return $rest->save();
        });
    }

    /**
     *  生成小程序预览二维码
     * @param $id
     * @param $merId
     * @return bool|int|mixed|string
     * @author Qinii
     * @day 2023/9/12
     */
    public function review($id,$merId)
    {
        $name = 'view_diy_routine_'.$id.'_'.$merId.'.jpg';
        $qrcodeService = app()->make(QrcodeService::class);
        $link = 'pages/admin/storeDiy/index';
        $params = 'diy_id='.$id .'&id='.$merId;
        return $qrcodeService->getRoutineQrcodePath($name, $link, $params,'routine/diy');
    }

    public function getProductDetail()
    {
        $diyInfo = $this->dao->getWhere(['is_diy' => self::IS_DIY_PRODUCT,'is_del' => 0,'mer_id' => 0]);
        $data['product_detail_diy'] = json_decode($diyInfo['value'], true);
        return $data;
    }

    public function saveProductDetail($data)
    {
        $data['is_diy'] =  self::IS_DIY_PRODUCT;
        $res = $this->dao->getWhere(['is_diy' => self::IS_DIY_PRODUCT,'is_del' => 0,'mer_id' => 0]);
        $data['value'] = json_encode($data['product_detail_diy'], JSON_UNESCAPED_UNICODE);
        unset($data['product_detail_diy']);
        if ($res) {
            $this->dao->update($res->id, $data);
        } else {
            $this->dao->create($data);
        }
        $key = env('APP_KEY').'_sys.get_sys_product_detail';
        Cache::delete($key);
        return true;
    }
    /**
     * DIY悬浮按钮信息
     *
     * @return void
     */
    public function fabInfo()
    {
        $data = $this->dao->getSearch(['is_diy' => self::IS_DIY_FAB, 'is_del' => 0, 'mer_id' => 0])->order('id desc')->find();
        if (!$data) {
            throw new ValidateException('数据为空');
        }

        $data['value'] = json_decode($data['value'], true);
        return $data;
    }
    /**
     * DIY商品分类信息
     *
     * @return void
     */
    public function productCategoryInfo(int $merId = 0)
    {
        $where = ['is_diy' => self::IS_DIY_PRODUCT_CARTGORY, 'is_del' => 0, 'mer_id' => $merId];

        $data = $this->dao->getSearch($where)->order('id desc')->find();
        if (!$data) {
            throw new ValidateException('数据为空');
        }

        $data['value'] = json_decode($data['value'], true);
        return $data;
    }
}
