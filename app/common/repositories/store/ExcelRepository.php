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

use app\common\dao\store\ExcelDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\admin\AdminRepository;
use app\common\repositories\system\merchant\MerchantAdminRepository;
use crmeb\services\ExcelService;
use think\facade\Db;
use think\facade\Queue;
use crmeb\jobs\SpreadsheetExcelJob;

class ExcelRepository extends BaseRepository
{
    /**
     * @var ExcelDao
     */
    protected $dao;


    /**
     * StoreAttrTemplateRepository constructor.
     * @param ExcelDao $dao
     */
    public function __construct(ExcelDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 创建Excel并推送处理任务到队列
     *
     * 本函数用于根据提供的条件创建一个Excel文件，并将相应的处理任务推送到队列中去。
     * 这样做的目的是为了异步处理Excel相关的工作，提高系统的响应速度和处理能力。
     *
     * @param array $where 创建Excel的条件数组
     * @param int $admin_id 创建Excel的管理员ID
     * @param string $type Excel的类型，用于确定具体的处理逻辑
     * @param int $merId 商户ID，用于标识Excel的归属
     */
    public function create(array $where ,int $admin_id, string $type,int $merId)
    {
        // 通过DAO层创建Excel，指定商户ID、管理员ID和Excel类型
        $excel = $this->dao->create([
            'mer_id'    => $merId,
            'admin_id'  => $admin_id,
            'type'      => $type
        ]);

        // 构造任务数据数组，包含条件、类型和新创建的Excel的ID
        $data = ['where' => $where,'type' => $type,'excel_id' => $excel->excel_id];

        // 将Excel处理任务推送到队列中
        Queue::push(SpreadsheetExcelJob::class,$data);

        // 注释掉的代码可能是用于调试或备用的，保留注释可以提供一些历史信息或备选方案
    //    app()->make(ExcelService::class)->$type($where,1);
    }

    /**
     * 根据条件获取分页列表。
     *
     * 该方法用于根据给定的条件从数据库中检索分页数据列表。它涉及到两个不同的管理员仓库，
     * 用于处理商家管理员和系统管理员的数据。通过对查询结果进行遍历，将管理员的实名
     * 填充到结果中，以便返回的数据更加完整。
     *
     * @param array $where 查询条件数组。
     * @param int $page 当前页码。
     * @param int $limit 每页数据条数。
     * @return array 返回包含总数和列表数据的数组。
     */
    public function getList(array $where,int $page, int $limit)
    {
        // 创建商家管理员仓库实例
        $mer_make = app()->make(MerchantAdminRepository::class);
        // 创建系统管理员仓库实例
        $sys_make = app()->make(AdminRepository::class);

        // 根据条件构造查询
        $query = $this->dao->search($where);
        // 计算总条数
        $count = $query->count();

        // 进行分页查询，并对结果进行处理
        $list = $query->page($page,$limit)->select()
            ->each(function($item) use ($mer_make,$sys_make){
                // 检查是否存在管理员ID，并且不为空
                if (isset($item['admin_id']) && $item['admin_id']) {
                    // 根据商家ID判断是商家管理员还是系统管理员
                    if($item['mer_id']){
                        $admin = $mer_make->get($item['admin_id']);
                    }else{
                        $admin = $sys_make->get($item['admin_id']);
                    }
                    // 将管理员实名填充到结果中
                    return $item['admin_id'] = $admin['real_name'] ??"";
                }

            });

        // 返回分页结果
        return compact('count','list');
    }

    /**
     *  删除文件
     * @param int $id
     * @param string $path
     * @author Qinii
     * @day 2020-08-15
     */
    public function del(int $id,?string $path)
    {
        Db::transaction(function()use($id,$path){
            $this->dao->delete($id);
            if(!is_null($path)){
                $path = app()->getRootPath().'public'.$path;
                if(file_exists($path))unlink($path);
            }
        });
    }
}

