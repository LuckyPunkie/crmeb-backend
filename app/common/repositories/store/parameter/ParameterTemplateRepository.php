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

use app\common\dao\store\parameter\ParameterTemplateDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\RelevanceRepository;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;

/**
 * 商品参数模板
 */
class ParameterTemplateRepository extends BaseRepository
{
    /**
     * @var ParameterTemplateDao
     */
    protected $dao;


    /**
     * ParameterRepository constructor.
     * @param ParameterTemplateDao $dao
     */
    public function __construct(ParameterTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件、分页和限制从数据库获取列表数据。它利用了DAO模式进行数据操作，
     * 提供了一个灵活的方式来检索包括分类和商家信息在内的复合数据。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据的数量
     * @return array 包含总数和列表数据的数组
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 初始化查询
        $query = $this->dao->getSearch($where)
            ->with([
                // 关联分类，并只获取特定字段
                'cateId' => function ($query) {
                    $query->with(['category' => function ($query) {
                        $query->field('store_category_id,cate_name');
                    }]);
                },
                // 关联商家，并只获取特定字段
                'merchant' => function ($query) {
                    $query->field('mer_id,mer_name');
                }
                // 注释掉的关联参数，可以根据需要进行恢复和调整
            ])
            ->order('sort DESC,create_time DESC'); // 按排序和创建时间降序排列

        // 计算总条数
        $count = $query->count();

        // 分页获取数据列表
        $list = $query->page($page, $limit)->select();

        // 返回包含总数和列表的数组
        return compact('count', 'list');
    }


    /**
     * 根据条件获取选择列表
     *
     * 本函数旨在根据传入的条件数组，从数据库中检索特定字段，并以适合下拉选择框的形式返回数据。
     * 这样做的目的是为了在前端界面提供一个可选择的列表，例如模板名称的列表，其中每个选项都有一个对应的唯一标识。
     *
     * @param array $where 查询条件数组
     * @return array 返回一个格式化后的数组，包含标签和值，适用于前端下拉选择框
     */
    public function getSelect(array $where)
    {
        // 通过DAO层的getSearch方法查询数据，根据$where条件进行筛选
        // 使用field方法指定只返回template_name和template_id字段，分别作为标签和值
        // 最后调用select方法执行查询并返回结果
        return $this->dao->getSearch($where)->field('template_name label,template_id value')->select();
    }

    /**
     * 根据模板ID和商家ID查询模板详情
     *
     * @param int $id 模板ID
     * @param int $merId 商家ID，可选参数，用于指定查询特定商家的模板
     * @return array 模板的详细信息
     * @throws ValidateException 如果模板不存在，则抛出异常
     */
    public function detail($id, $merId)
    {
        // 构建查询条件，指定模板ID
        $where['template_id'] = $id;
        // 如果提供了商家ID，则加入查询条件
        if ($merId) $where['mer_id'] = $merId;

        // 查询模板详情，包括分类、参数和商家信息
        // 使用with方法预加载关联数据，以减少数据库查询次数
        $data = $this->dao->getSearch($where)
            ->with([
                // 预加载模板的分类信息，包括分类ID和分类名称
                'cateId' => function ($query) {
                    $query->with(['category' => function ($query) {
                        $query->field('store_category_id,cate_name');
                    }]);
                },
                // 预加载模板的参数信息，按排序降序排列
                'parameter' => function ($query) {
                    $query ->field('parameter_id,template_id,name,required,sort')->order('sort DESC')->append(['values'], true);
                },
                // 预加载模板所属的商家信息，包括商家名称和商家ID
                'merchant' => function ($query) {
                    $query->field('mer_name,mer_id');
                }
            ])->find();
        // 如果查询结果为空，则抛出异常提示数据不存在
        if (!$data) throw new ValidateException('数据不存在');
        // 返回模板详情
        return $data;
    }

    /**
     * 根据条件展示搜索结果
     *
     * 本函数通过调用DAO层的getSearch方法，根据传入的$where条件获取搜索结果。
     * 结果数据包括主数据和关联的参数数据，其中参数数据通过嵌套查询获取，并进行排序。
     * 最后，将所有参数数据提取出来组成一个列表并返回。
     *
     * @param array $where 查询条件
     * @return array 返回包含所有参数数据的列表
     */
    public function show($where)
    {
        // 通过DAO层的getSearch方法查询数据，同时加载关联的parameter数据
        // 这里通过闭包指定了parameter表的查询字段和排序方式
        $data = $this->dao->getSearch($where)->with([
            'parameter' => function ($query) {
                $query
                    //->with([
                    //    'paramValues' => function($query) {
                    //        $query->column('parameter_value_id,value,parameter_id','parameter_value_id');
                    //    }
                    //])
                    ->field('parameter_id,template_id,name,required')->order('sort DESC')->append(['values'], true);
            }
        ])->order('mer_id ASC,create_time DESC')->select();

        // 初始化空数组用于存放最终的参数数据列表
        $list = [];
        // 遍历查询结果，如果存在参数数据，则将其添加到列表中
        foreach ($data as $datum) {
            if ($datum['parameter']) {
                foreach ($datum['parameter'] as $item) {
                    $item['template_name'] = $datum['template_name'];
                    $item['mer_id'] = $datum['mer_id'];
                    $list[] = $item;
                }
            }
        }
        // 返回参数数据列表
        return $list;
    }

    /**
     * 创建模板
     *
     * 本函数用于根据提供的商家ID和模板信息创建新的模板。它首先从传入的数据中提取必要的信息，
     * 然后在数据库事务中执行模板的创建、参数的创建或更新，以及相关类别的创建。
     * 这样做确保了数据的一致性和完整性。
     *
     * @param string $merId 商家ID，用于标识模板所属的商家。
     * @param array $data 包含模板详细信息和参数的数据数组。
     *      - params: 模板的参数。
     *      - cate_ids: 类别ID数组，用于将模板与类别相关联。
     *      - template_name: 模板的名称。
     *      - sort: 模板的排序值。
     *      - mer_id: 商家ID（冗余参数，与函数参数相同，用于内部操作）。
     */
    public function create($merId, $data)
    {
        // 提取参数信息
        $params = $data['params'];
        // 去除重复的类别ID
        $cate = array_unique($data['cate_ids']);
        // 构建模板的基础信息
        $tem = [
            'template_name' => $data['template_name'],
            'sort' => $data['sort'],
            'mer_id' => $merId
        ];
        // 实例化参数仓库和关联仓库
        $paramMake = app()->make(ParameterRepository::class);
        $releMake = app()->make(RelevanceRepository::class);
        // 使用数据库事务来确保操作的一致性
        //Db::transaction(function () use ($params, $tem, $cate, $merId, $paramMake, $releMake) {
            // 创建模板
            $temp = $this->dao->create($tem);
            // 创建或更新模板参数
            $paramMake->createOrUpdate($temp->template_id, $merId, $params);
            // 如果有类别ID，创建模板与类别的关联
            if (!empty($cate)) {
                $releMake->createMany($temp->template_id, $cate, RelevanceRepository::PRODUCT_PARAMES_CATE);
            }
        //});
    }

    /**
     * 更新产品信息。
     *
     * 该方法用于根据给定的ID和数据更新产品的详细信息。它处理产品模板名称、排序、
     * 参数和类别关联的更新。使用事务来确保数据库操作的一致性。
     *
     * @param int $id 产品的ID
     * @param array $data 包含产品更新信息的数组，如模板名称、排序、参数和类别ID
     * @param int $merId 商家ID，默认为0，用于指定操作的商家
     */
    public function update($id, $data, $merId = 0)
    {
        // 提取参数数据
        $params = $data['params'];
        // 去重处理类别ID数组
        $cate = array_unique($data['cate_ids']);
        // 构建要更新的模板名称和排序信息
        $tem = [
            'template_name' => $data['template_name'],
            'sort' => $data['sort'],
        ];
        if ($data['delete_params'] ?? []) {
            app()->make(ParameterValueRepository::class)->dostory($data['delete_params'], $merId);
        }

        // 实例化参数仓库和关联仓库
        $paramMake = app()->make(ParameterRepository::class);
        $releMake = app()->make(RelevanceRepository::class);

        // 使用事务处理数据库操作
        Db::transaction(function () use ($id, $params, $tem, $cate, $paramMake, $releMake, $merId) {
            // 更新产品的基本模板和排序信息
            $this->dao->update($id, $tem);
            $paramMake->createOrUpdate($id, $merId, $params);
            // 删除旧的类别关联，创建新的类别关联
            $releMake->batchDelete($id, RelevanceRepository::PRODUCT_PARAMES_CATE);
            if (!empty($cate)) {
                $releMake->createMany($id, $cate, RelevanceRepository::PRODUCT_PARAMES_CATE);
            }
        });
    }


    /**
     * 删除指定ID的记录及其相关参数和关联信息。
     *
     * 使用事务确保删除操作的原子性，即操作要么全部成功，要么全部失败。
     * 首先，删除指定ID的主记录，然后删除与该记录相关的所有参数记录，
     * 最后删除与产品参数相关的所有关联信息。
     *
     * @param int $id 需要删除的记录的ID。
     */
    public function delete($id)
    {
        // 创建参数仓库实例
        $paramMake = app()->make(ParameterRepository::class);
        // 创建关联仓库实例
        $releMake = app()->make(RelevanceRepository::class);

        // 使用事务处理删除操作
        Db::transaction(function () use ($id, $paramMake, $releMake) {
            // 删除主记录
            $this->dao->delete($id);
            // 删除与模板相关的所有参数
            $paramMake->getSearch(['template_id' => $id])->delete();
            // 批量删除与产品参数相关的所有关联信息
            $releMake->batchDelete($id, RelevanceRepository::PRODUCT_PARAMES_CATE);
        });
    }


    /**
     * 根据条件获取API列表。
     *
     * 本函数主要用于查询与商品分类相关的参数信息，可区分PC端和移动端的查询需求。
     * 通过传入的分类条件，首先获取对应分类的ID集合，然后根据这些分类ID查询相应的参数模板ID。
     * 最后，根据参数模板ID查询具体的参数信息，并根据需要（如PC端查询时）填充参数的值。
     *
     * @param array $where 查询条件，用于获取商品分类ID。
     * @param bool $isPc 是否为PC端查询，默认为false表示移动端。
     * @return array 返回查询到的参数信息列表。
     */
    public function getApiList($where, $isPc = false)
    {
        // 实例化商品仓库，用于获取商品分类ID
        /**
         *  通过筛选条件查询出商品的分类ID
         *  通过分类ID获取参数模板ID
         *  通过参数模板ID查询出参数
         */
        $productRepository = app()->make(ProductRepository::class);
        // 根据分类条件获取分类ID集合
        $template_id = $productRepository->getCateIdByCategory($where);
        // 根据分类ID集合查询对应的参数模板ID
        //$template_id = $this->dao->getSearch(['cate_id' => $cate_ids, 'mer_id' => 0])->column('template_id');
        // 实例化参数仓库，用于查询参数信息
        $parameterRepository = app()->make(ParameterRepository::class);
        // 根据参数模板ID查询参数信息，并按排序降序查询
        $query = $parameterRepository->getSearch(['template_id' => $template_id])
            ->field('parameter_id,template_id,name')
            ->order('sort DESC, create_time DESC,template_id DESC');
        // 执行查询并获取参数数据
        $data = $query->select();
        // 如果是PC端查询且有数据，则进一步处理参数的值
        //如果是pc端，需要同时返回参数的值
        if ($isPc && $data) {
            // 将查询结果转换为数组，并按参数名排序
            $data = $data->toArray();
            ksort($data);
            // 构建缓存键名
            $key = 'params_1' . json_encode($data);
            // 尝试从缓存中获取已处理的参数值
            $res = Cache::get($key);
            if ($res) {
                // 如果缓存中存在，则直接使用缓存数据
                $data = json_decode($res, true);
            } else {
                // 实例化参数值仓库，用于获取参数的具体值
                $parameterValueRepository = app()->make(ParameterValueRepository::class);
                // 遍历参数数据，填充参数值
                foreach ($data as &$datum) {
                    $datum['value'] = $parameterValueRepository->getOptions(['parameter_id' => $datum['parameter_id']]);
                }
                // 将处理后的参数值数据缓存起来，有效期60秒
                Cache::set($key, json_encode($data), 60);
            }
        }
        // 返回最终的参数信息列表
        return $data;
    }
}

