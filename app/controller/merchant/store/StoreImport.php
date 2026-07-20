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
use app\common\repositories\store\order\StoreImportDeliveryRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductCdkeyRepository;
use app\validate\merchant\ProductCdkeyValidate;
use crmeb\jobs\ImportSpreadsheetExcelJob;
use crmeb\services\ExcelService;
use crmeb\services\SpreadsheetExcelService;
use crmeb\services\UploadService;
use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\order\StoreImportRepository;

use think\facade\Queue;

class StoreImport extends BaseController
{
    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param StoreImportRepository $repository
     */
    public function __construct(App $app, StoreImportRepository $repository)
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
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['status', 'date', ['import_type', 'delivery'], 'type']);
        $where['mer_id'] = $this->request->merId();
        // 调用仓库方法获取数据
        $data = $this->repository->getList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }


    /**
     * 获取指定ID的导入配送列表
     *
     * @param int $id 导入ID
     * @return \think\response\Json
     */
    public function detail($id)
    {
        // 构造查询条件
        $where = [
            'import_id' => $id,
            'mer_id' => $this->request->merId()
        ];
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用 StoreImportDeliveryRepository 类的 getList 方法获取数据
        $data = app()->make(StoreImportDeliveryRepository::class)->getList($where, $page, $limit);
        // 返回 JSON 格式的数据
        return app('json')->success($data);
    }

    /**
     * 导出指定ID的导入配送列表
     *
     * @param int $id 导入ID
     * @return \think\response\Json
     */
    public function export($id)
    {
        // 构造查询条件
        $where = [
            'import_id' => $id,
            'mer_id' => $this->request->merId()
        ];
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用 ExcelService 类的 importDelivery 方法导出数据
        $data = app()->make(ExcelService::class)->importDelivery($where, $page, $limit);
        // 返回 JSON 格式的数据
        return app('json')->success($data);
    }


    /**
     * 导入excel信息
     * @return \think\response\Json
     * @author Qinii
     * @day 3/16/21
     */
    public function Import($type)
    {
        $file = $this->request->file('file');
        if (!$file)  return app('json')->fail('请上传EXCEL文件');
        $file = is_array($file) ? $file[0] : $file;
        validate(["file|文件" => ['fileExt' => 'xlsx,xls',]])->check(['file' => $file]);

        $upload = UploadService::create(1);
        $ret = $upload->to('excel')->move('file');
        if ($ret === false) return app('json')->fail($upload->getError());
        $res = $upload->getUploadInfo();
        $path = rtrim(public_path(),'/').$res['dir'];
        $data = [];
        $spreadsheet = SpreadsheetExcelService::instance();
        switch ($type){
             case 'delivery' :
                 // 是否需要验证表单结构
                 $spreadsheet->checkImport($path,['E3' => '物流单号']);
                 $sql = ['delivery_name' => 'D', 'delivery_id' => 'E',];
                 $where = ['order_sn' => 'B',];
                 $data['data'] = SpreadsheetExcelService::instance()->_import($path, $sql, $where, 4);

                 if(!empty($data['data'] )){
                     $res = $this->repository->create($this->request->merId(),'delivery');
                     $data['path'] = $path;
                     $data['import_id'] = $res->import_id;
                     $data['mer_id'] = $this->request->merId();
                     Queue::push(ImportSpreadsheetExcelJob::class,$data);
                     return app('json')->success('开始导入数据，请稍后在批量发货记录中查看！');
                 }
                break;
            case 'cdkey':
                $data['library_id'] = $this->request->param('library_id');
                if (!$data['library_id'])  return app('json')->fail('缺少卡密库 ID');
                $spreadsheet->checkImport($path,['B1' => '卡号','C1' => '卡密']);
                $csList = $spreadsheet->_import($path,['key' => 'B','pwd' => 'C'],[],2);
                $data['csList'] = array_column($csList,'value');
                app()->make(ProductCdkeyValidate::class)->check($data);
                app()->make(ProductCdkeyRepository::class)->save($data, $this->request->merId());
                return app('json')->success('导入成功');
                break;
            default:
                break;
        }
    }
}

