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
namespace app\common\repositories\store;

use app\common\dao\store\GuaranteeTemplateDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

class GuaranteeTemplateRepository extends BaseRepository
{
    /**
     * @var GuaranteeTemplateDao
     */
    protected $dao;


    /**
     * GuaranteeRepository constructor.
     * @param GuaranteeTemplateDao $dao
     */
    public function __construct(GuaranteeTemplateDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 平台列表
     * @param $where
     * @param $page
     * @param $limit
     * @return array
     * @author Qinii
     * @day 5/17/21
     */
    public function getList($where,$page, $limit)
    {
        $query = $this->dao->getSearch($where)->with(['template_value.value'])->order('sort DESC');
        $count = $query->count();
        $list = $query->page($page,$limit)->select();
        return compact('count','list');
    }

    /**
     * 创建
     * @param array $data
     * @author Qinii
     * @day 5/17/21
     */
    public function create(array $data)
    {
        Db::transaction(function() use($data){
            $template = [
                'template_name' => $data['template_name'],
                'mer_id' => $data['mer_id'],
                'status' => $data['status'],
                'sort' => $data['sort']
            ];
            $guaranteeData = $this->dao->create($template);
            $make = app()->make(GuaranteeRepository::class);

            foreach ($data['template_value'] as $datum){

                $where = [ 'status' => 1,'is_del' => 0,'guarantee_id' => $datum];
                $ret = $make->getWhere($where);
                if(!$ret) throw new ValidateException('ID['.$datum.']不存在');
                $value[] = [
                    'guarantee_id' => $datum ,
                    'guarantee_template_id' => $guaranteeData->guarantee_template_id,
                    'mer_id' => $data['mer_id']
                ];
            }
            app()->make(GuaranteeValueRepository::class)->insertAll($value);
        });
    }

    /**
     * 编辑
     * @param int $id
     * @param array $data
     * @author Qinii
     * @day 5/17/21
     */
    public function edit(int $id,array $data)
    {
        Db::transaction(function() use($id,$data){
            $template = [
                'template_name' => $data['template_name'],
                'status' => $data['status'],
                'sort' => $data['sort']
            ];
            $make = app()->make(GuaranteeRepository::class);
            $makeValue = app()->make(GuaranteeValueRepository::class);
            foreach ($data['template_value'] as $datum){
                $where = [ 'status' => 1,'is_del' => 0,'guarantee_id' => $datum];
                $ret = $make->getWhere($where);
                if(!$ret) throw new ValidateException('ID['.$datum.']不存在');
                $value[] = [
                    'guarantee_id' => $datum ,
                    'guarantee_template_id' => $id,
                    'mer_id' => $data['mer_id']
                ];
            }
            $this->dao->update($id,$template);
            $makeValue->clear($id);
            $makeValue->insertAll($value);
        });
    }

    /**
     * 详情
     * @param int $id
     * @param int $merId
     * @return array|\think\Model|null
     * @author Qinii
     * @day 5/17/21
     */
    public function detail(int $id,int $merId)
    {
        $where = [
            'mer_id' => $merId,
            'guarantee_template_id' => $id,
        ];
        $ret = $this->dao->getSearch($where)->find();
        $ret->append(['template_value']);
        if(!$ret) throw new ValidateException('数据不存在');
        return $ret;
    }

    /**
     * 删除保证模板方法
     *
     * 本方法用于删除指定的保证模板。在删除之前，它会检查是否有产品正在使用该模板。
     * 如果有产品正在使用该模板，它将抛出一个验证异常，阻止模板的删除。
     * 如果没有产品使用该模板，它将安全地删除模板，并清理相关的保证值数据。
     *
     * @param int $id 保证模板的ID
     * @throws ValidateException 如果有产品正在使用该模板，则抛出此异常
     */
    public function delete($id)
    {
        // 查询使用该保证模板的所有产品的ID
        $productId = app()->make(ProductRepository::class)->getSearch(['guarantee_template_id' => $id])->column('product_id');

        // 如果有产品正在使用该模板，抛出异常
        if($productId) throw new ValidateException('有商品正在使用此模板,商品ID：'.implode(',',$productId));

        // 使用事务处理来确保删除操作和清理操作的原子性
        Db::transaction(function() use($id){
            // 删除保证模板
            $this->dao->delete($id);
            // 清理与该模板相关的保证值数据
            app()->make(GuaranteeValueRepository::class)->clear($id);
        });
    }

    /**
     * 根据商家ID列出相关数据
     *
     * 本函数用于查询特定商家（由$merId指定）的所有有效且未被删除的数据。
     * 它首先构造一个查询条件数组，然后调用dao层的getSearch方法进行查询。
     * 查询结果按照'sort'字段的降序排列，并最终将查询结果转换为数组形式返回。
     *
     * @param int $merId 商家ID，用于指定查询哪个商家的数据
     * @return array 返回查询结果的数组形式，每个元素代表一条数据
     */
    public function list($merId)
    {
        // 定义查询条件，包括数据状态、是否被删除以及商家ID
        $where = [
            'status' => 1, // 只查询状态为有效的数据
            'is_del' => 0, // 只查询未被删除的数据
            'mer_id' => $merId // 指定查询的商家ID
        ];

        // 调用dao层的getSearch方法进行查询，并指定排序方式为'sort'字段的降序
        // 最后将查询结果转换为数组形式返回
        return $this->dao->getSearch($where)
                         ->order('sort DESC')
                         ->select()
                         ->toArray();
    }

}
