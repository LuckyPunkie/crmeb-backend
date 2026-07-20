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

namespace app\controller\merchant\store;

use app\common\repositories\store\ExcelRepository;
use crmeb\exceptions\UploadException;
use crmeb\services\ExcelService;
use think\App;
use crmeb\basic\BaseController;

class Excel extends BaseController
{

    protected $repository;

    public function __construct(App $app, ExcelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表数据
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取当前管理员信息
        $admin = $this->request->adminInfo();
        if ($admin['level']) $where['admin_id'] = $this->request->adminId();
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where['type'] = $this->request->param('type', '');
        $where['mer_id'] = $this->request->merId();
        $data = $this->repository->getList($where, $page, $limit);
        // 返回JSON格式的数据
        return app('json')->success($data);
    }


    /**
     * 下载文件
     * @param $id
     * @return \think\response\File
     * @author Qinii
     * @day 2020-07-30
     */
    public function downloadExpress($type)
    {
        try{
            switch ($type) {
                case 'cdkey':
                    $file['name'] = 'cdkey_template';
                    $path = app()->getRootPath().'extend/cdkey_template.xlsx';
                    break;
                default:
                    $file['name'] = 'express';
                    $path = app()->getRootPath().'extend/express.xlsx';
                    break;
            }

            if(!$file || !file_exists($path)) return app('json')->fail('文件不存在');
            return download($path,$file['name']);
        }catch (UploadException $e){
            return app('json')->fail('下载失败');
        }
    }

    /**
     * 所有类型
     * @return \think\response\Json
     * @author Qinii
     * @day 7/2/21
     */
    public function type()
    {
        $data = $this->repository->getTypeData();
        return app('json')->success($data);
    }

}
