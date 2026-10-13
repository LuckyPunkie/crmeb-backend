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

namespace app\common\repositories\store\product;

use app\common\repositories\BaseRepository;
use app\common\dao\store\product\ProductCdkeyDao as dao;
use think\exception\ValidateException;

class ProductCdkeyRepository extends BaseRepository
{

    protected $dao;

    /**
     * ProductRepository constructor.
     * @param dao $dao
     */
    public function __construct(dao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件数组$where，从数据库中检索满足条件的数据列表。
     * 它支持分页查询，每页的数据数量由$limit参数指定，查询的页码由$page参数指定。
     * 查询结果包括满足条件的数据总数$count和实际返回的数据列表$list。
     *
     * @param array $where 查询条件数组
     * @param int $page 查询的页码
     * @param int $limit 每页的数据数量
     * @return array 包含数据总数和数据列表的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 初始化查询，根据$where条件搜索，并加载关联的product信息，但只获取指定的字段
        $query = $this->dao->search($where)->with([
            'product' => function($query){
                $query->field('product_id,mer_id,store_name,image');
            }
        ])->order('cdkey_id DESC,create_time DESC');

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据$page和$limit参数进行分页查询，并获取满足条件的数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含数据总数和数据列表的数组
        return compact('count', 'list');
    }

    /**
     *  查询key是否重复; 如果重复返回key, 否则返回false
     * @param $id
     * @param $keys
     * @return false
     * @author Qinii
     */
    public function checkKey(int $id, array $keys)
    {
        $where = [
            'library_id' => $id,
            'keys' => $keys
        ];
        $data =  $this->dao->search($where)->find();
        if ($data) return $data;
        return false;
    }

    /**
     *  处理数据,批量增加
     * @param array $data
     * @param int $merId
     * @return void
     * @author Qinii
     */
    public function save(array $data, int $merId)
    {
        $saveData = $data['csList'];
        $library_id = $data['library_id'];
        $updatedArray = array_map(function($item) use($merId,$library_id){
           $item['is_type'] = 1;
           $item['mer_id'] = $merId;
           $item['library_id'] = $library_id;
           return $item;
        },$saveData);
        $this->changeTotalNum($library_id, count($saveData));
        $this->dao->insertAll($updatedArray);
    }

    /**
     *  编辑
     * @param $id
     * @param $data
     * @return mixed
     * @author Qinii
     */
    public function edit($id, $data)
    {
        $where = ['cdkey_id' => $id, 'mer_id' => $data['mer_id']];
        $res = $this->dao->search($where)->find();
        if (!$res) throw new ValidateException('数据不存在');
        if ($res['status'] != 1) throw new ValidateException('已使用无法修改');
        $key = $data['key'];
        $has = $this->checkKey($res['library_id'],[$key]);
        if ($has && $has['cdkey_id'] != $id) throw new ValidateException('卡密已存在:'.$key);
        $res->key = $key;
        $res->pwd = $data['pwd'];
        $res->save();
        return $res;
    }

    /**
     *  删除卡密信息 减掉库存
     * @param $id
     * @param $merId
     * @return array|\think\Model
     * @author Qinii
     */
    public function dostory($id, $merId)
    {
        $res = $this->dao->get($id);
        if (!$res || $res['mer_id'] != $merId) throw new ValidateException('数据不存在');
        if ($res['status'] != 1) throw new ValidateException('已使用无法删除');
        $this->dao->delete($id);
        $this->changeTotalNum($res['library_id'],-1);
        return $res;
    }

    /**
     *  批量删除
     * @param $ids
     * @param $merId
     * @return void
     * @author Qinii
     */
    public function batchDelete($ids, $merId)
    {
        /*
         * 1. 验证卡密是否属于商户
         * 2. 验证卡密是否使用
         * 3. 删除卡密
         */
        $res = $this->dao->search(['cdkey_ids' => $ids, 'mer_id' => $merId])->select()->toArray();
        if (!$res) throw new ValidateException('数据不存在');
        foreach ($res  as $item) {
            if ($item['status'] != 1) throw new ValidateException('已使用无法删除');
            $this->dao->delete($item['cdkey_id']);
            $this->changeTotalNum($item['library_id'],-1);
        }
    }


    /**
     * 修改库存数量
     *
     * 根据传入的卡密库ID和数量，增加或减少库存总数。
     * 如果传入的数量大于0，则增加库存；如果数量小于0，则减少库存。
     * 同时，更新产品属性值和产品的库存信息。
     *
     * @param int $libraryId 图书馆ID，用于定位特定的库存项
     * @param int $num 变更的库存数量，默认为1，可以为正数（增加）或负数（减少）
     */
    public function changeTotalNum($libraryId ,$num = 1)
    {
        // 实例化CDKey卡密库
        $cdkeyLibraryRepository = app()->make(CdkeyLibraryRepository::class);
        // 根据$num的正负值，决定是增加还是减少库存数量
        try{
            if ($num > 0) {
                $cdkeyLibraryRepository->incTotalNum($libraryId, $num);
            } else {
                $cdkeyLibraryRepository->decTotalNum($libraryId,-$num);
            }
        }catch (\Exception $e) {}
        // 实例化产品属性值仓库，用于获取和更新产品的库存信息
        $value = app()->make(ProductAttrValueRepository::class)->getWhere(['library_id' => $libraryId]);
        // 如果找到了对应的库存信息
        if ($value) {
            // 更新库存数量
            $value->stock = $value->stock + $num;
            $value->save();
            // 实例化产品仓库，用于获取和更新产品的整体库存信息
            $product = app()->make(ProductRepository::class)->getWhere(['product_id' => $value['product_id']]);
            // 如果找到了对应的产品信息
            if ($product) {
                // 更新产品的库存数量
                $product->stock = $product->stock + $num;
                $product->save();
            }
        }
    }
}
