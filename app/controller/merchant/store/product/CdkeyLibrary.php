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
namespace app\controller\merchant\store\product;

use app\common\repositories\store\product\CdkeyLibraryRepository;
use crmeb\basic\BaseController;
use crmeb\exceptions\UploadException;
use think\App;
use think\exception\ValidateException;

/**
 * Class CdkeyLibrary
 * app\controller\merchant\store\product
 * 卡密库
 */
class CdkeyLibrary extends BaseController
{
    protected  $repository ;

    /**
     * ProductGroup constructor.
     * @param App $app
     * @param CdkeyLibraryRepository $repository
     */
    public function __construct(App $app ,CdkeyLibraryRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword','status','date','productName','name']);
        $where['mer_id'] = $this->request->merId();
        $data = $this->repository->getList($where,$page,$limit);
        return app('json')->success($data);
    }

    /**
     * 创建表单
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createForm()
    {
        // 调用 formToData 方法将表单转换为数据并返回 JSON 格式的响应
        return app('json')->success(formToData($this->repository->form()));
    }


    /**
     * 添加表单
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams();
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function updateForm($id)
    {
        $data = $this->repository->merHas($this->request->merId(),$id, null);
        if (!$data) return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->form($id)));
    }

    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->repository->merHas($this->request->merId(),$id, null);
        if (!$data) return app('json')->fail('数据不存在');
        $data = $this->checkParams();
        $this->repository->update($id,$data);
        return app('json')->success('修改成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $this->repository->destory($id, $this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     *  验证
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function checkParams()
    {
        $data = $this->request->params(['name','remark']);
        if (empty($data['name'])) {
            throw new ValidateException('请输入名称');
        }
        $data['mer_id'] = $this->request->merId();
        return $data;
    }


    /**
     * 获取选项列表
     *
     * @return \think\response\Json
     */
    public function options()
    {
        // 调用 repository 类的 getOptions 方法获取选项列表，并通过 json 组件返回成功状态和数据
        return app('json')->success($this->repository->getOptions($this->request->merId()));
    }

    /**
     * 下载快递模板文件
     *
     * @return \think\response\Json
     */
    public function downloadExpress()
    {
        try {
            // 设置文件名和路径
            $file['name'] = 'express';
            $path = app()->getRootPath() . 'extend/express.xlsx';
            // 判断文件是否存在，若不存在则返回失败状态
            if (!$file || !file_exists($path)) return app('json')->fail('文件不存在');
            // 调用 download 函数下载文件，并返回成功状态
            return download($path, $file['name']);
        } catch (UploadException $e) {
            // 捕获上传异常，返回失败状态
            return app('json')->fail('下载失败');
        }
    }

}
