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

namespace app\common\repositories\store\parameter;

use think\exception\ValidateException;
use app\common\dao\store\parameter\ParameterValueDao;
use app\common\repositories\BaseRepository;
use think\facade\Db;

class ParameterValueRepository extends BaseRepository
{
    /**
     * @var ParameterValueDao
     */
    protected $dao;


    /**
     * ParameterRepository constructor.
     * @param ParameterValueDao $dao
     */
    public function __construct(ParameterValueDao  $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建产品参数
     *
     * 该方法用于根据提供的产品ID和参数数据集，创建产品的参数信息。
     * 参数数据集中的每个数据项都应该包含名称、值，以及可选的排序、参数ID和商家ID。
     * 如果数据项有效（即名称和值都不为空），则将其组装成参数记录并准备插入数据库。
     *
     * @param int $id 产品ID，用于关联产品参数。
     * @param array $data 参数数据集，包含多个参数项，每个参数项是一个包含名称、值等信息的数组。
     * @param int $merId 商家ID，用于指定参数所属的商家，如果数据中没有提供，则使用此默认值。
     */
    public function create($data, $productId = 0, $merId = 0)
    {
        // 检查数据集是否为空，如果为空则直接返回，不进行后续处理
        if (empty($data)) return ;
        // 遍历数据集中的每个参数项
        $toProduct = [];

        // 如果没有parameter_id，且有productId,表示是某商品新增的属性,需要先清除商品中的属性
        if(isset($productId) && !empty($productId)) {
            $this->dao->clear($productId,'product_id');
        }
        $productRepository = app()->make(ParameterProductRepository::class);
        foreach ($data as &$datum) {
            $value_ids = $this->dao->getSearch(['parameter_id' => $datum['parameter_id']])->column('parameter_value_id');
            $keepValueIds = array_filter(array_map('intval', array_column($datum['values'], 'parameter_value_id')));
            $deleteValueIds = array_values(array_diff($value_ids, $keepValueIds));
            if ($deleteValueIds) {
                $this->dao->getSearch([])->whereIn('parameter_value_id', $deleteValueIds)->delete();
                $productRepository->clear($deleteValueIds, 'parameter_value_id');
            }
            // 创建参数属性
            foreach ($datum['values'] as &$v) {
                $createData = [
                    'name' => $datum['name'],
                    'value' => $v['value'],
                    'mer_id' => $merId,
                    //如果是存在$datum['parameter_id'],则表示这个属性是模板中定义的 不需要和商品绑定
                    'product_id' => isset($datum['parameter_id']) ? 0 : $productId,
                    'parameter_id' => isset($datum['parameter_id']) ? $datum['parameter_id'] : 0,
                ];
                $create = $this->dao->findOrcreate($createData);
                $v['parameter_value_id'] = $create->parameter_value_id;

                if ($productId){
                    $toProduct[] = [
                        'product_id' => $productId,
                        'parameter_value_id' => $v['parameter_value_id']
                    ];
                }
            }
        }
        // 如果有有效的参数记录，则插入到数据库
        if($toProduct){
            $productRepository->clear(array_column($toProduct,'product_id'),'product_id');
            $productRepository->insertAll($toProduct);
        }

        return $data;
    }


    /**
     *  获取所有参数的值，并合并所关联的商品ID
     * @param $where
     * @return array
     * @author Qinii
     * @day 2023/10/21
     */
    public function getOptions($where)
    {
        $data = $this->dao->getSearch($where)->column('parameter_value_id,parameter_id,name,value');
        return $data;
    }

    public function dostory($id, $merId)
    {
        $parameterRepository = app()->make(ParameterRepository::class);
        $ids = [91];
        foreach ($id as $i) {
            $data = $parameterRepository->getWhere(['parameter_id' => $i, 'mer_id' => $merId]);
            if ($data) {$ids[] = $i;}
        }
        $parameterProductRepository = app()->make(ParameterProductRepository::class);
        if ($ids) {
            $parameter_value_id = $this->dao->getSearch(['parameter_id' => $ids])->column('parameter_value_id');
            return Db::transaction(function () use (
                $ids,
                $parameter_value_id,
                $parameterRepository,
                $parameterProductRepository
            ) {
                $parameterRepository->getSearch([])->whereIn('parameter_id',$ids)->delete();
                $parameterProductRepository->clear($parameter_value_id,'parameter_value_id');
                $res = $this->dao->getSearch(['parameter_id' => $ids])->delete();
                return $res;
            });
        }
    }

    /**
     *  根据筛选的参数，查询出商品ID
     * @param $filter_params
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 2023/11/14
     */
    public  function filter_params($filter_params)
    {
        $productId = [];
        if (!empty($filter_params)) {
            if (!is_array($filter_params)) $filter_params = json_decode($filter_params,true);
            $value = [];
            foreach ($filter_params as $k => $v) {
                $id[] = $k;
                $value = array_merge($value,$v);
            }
            if (empty($id) || empty($value)) return false;
            $productData = $this->dao->getSearch([])->alias('P')
                ->join('ParameterValue V','P.product_id = V.product_id')
                ->whereIn('P.parameter_id',$id)->whereIn('P.value',$value)
                ->whereIn('V.parameter_id',$id)->whereIn('V.value',$value)
                ->group('P.product_id')
                ->field('P.product_id')
                ->select();
            if ($productData) {
                $productData = $productData->toArray();
                $productId = array_column($productData,'product_id');
            }
        }
        return $productId;
    }

}

