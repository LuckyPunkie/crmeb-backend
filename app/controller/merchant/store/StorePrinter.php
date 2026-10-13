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
use app\common\repositories\store\StorePrinterRepository;
use crmeb\exceptions\UploadException;
use crmeb\services\ExcelService;
use think\App;
use crmeb\basic\BaseController;

class StorePrinter extends BaseController
{

    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param StorePrinterRepository $repository 打印机仓库实例
     */
    public function __construct(App $app, StorePrinterRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 初始化仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取打印机列表
     *
     * @return mixed
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'keyword','type']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        $data = $this->repository->merList($where, $page, $limit);
        // 返回成功响应
        return app('json')->success($data);
    }

    /**
     * 创建打印机表单
     *
     * @return mixed
     */
    public function createForm()
    {
        // 调用仓库方法获取表单数据并转换为数组
        return app('json')->success(formToData($this->repository->form(null)));
    }


    /**
     * 创建打印机信息
     *
     * @return \think\response\Json
     */
    public function create()
    {
        // 获取请求参数
        $params = $this->request->params([
            'type',
            'printer_name',
            'printer_appkey',
            'printer_appid',
            'printer_secret',
            'printer_terminal',
            'status',
            'times',
            'print_type',
        ]);

        // 判断参数是否完整
        if (!$params['printer_name'] ||
            !$params['printer_appid'] ||
            !$params['printer_appkey'] ||
            !$params['printer_terminal']
        ) {
            return app('json')->fail('信息不完整');
        }
        if (!is_int($params['times'])) return app('json')->fail('打印联数必须为整数');
        // 设置商家ID
        $params['mer_id'] = $this->request->merId();
        // 调用仓库的创建方法
        $this->repository->create($params);
        // 返回成功信息
        return app('json')->success('添加成功');
    }

    /**
     * 获取打印机表单数据
     *
     * @param int $id 打印机ID
     * @return \think\response\Json
     */
    public function updateForm($id)
    {
        // 调用仓库的表单方法，并转换为数据格式
        return app('json')->success(formToData($this->repository->form($id)));
    }


    /**
     * 更新打印机信息
     * @param int $id 打印机ID
     * @return \think\response\Json
     */
    public function update($id)
    {
        // 获取请求参数
        $params = $this->request->params([
            'type',
            'printer_name',
            'printer_appkey',
            'printer_appid',
            'printer_secret',
            'printer_terminal',
            'status',
            'times',
            'print_type',
        ]);

        // 判断参数是否完整
        if (!$params['printer_name'] ||
            !$params['printer_appid'] ||
            !$params['printer_appkey'] ||
            !$params['printer_terminal']
        ) {
            return app('json')->fail('信息不完整');
        }
        if (!is_int($params['times'])) return app('json')->fail('打印联数必须为整数');
        // 查询打印机信息
        $res = $this->repository->getWhere(['printer_id' => $id, 'mer_id' => $this->request->merId()]);
        if (!$res) return app('json')->fail('打印机信息不存在');
        // 更新打印机信息
        $this->repository->update($id, $params);
        return app('json')->success('编辑成功');
    }

    /**
     * 删除打印机信息
     * @param int $id 打印机ID
     * @return \think\response\Json
     */
    public function delete($id)
    {
        // 查询打印机信息
        $res = $this->repository->getWhere(['printer_id' => $id, 'mer_id' => $this->request->merId()]);
        if (!$res) return app('json')->fail('打印机信息不存在');
        // 删除打印机信息
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 切换打印机状态
     * @param int $id 打印机ID
     * @return \think\response\Json
     */
    public function switchWithStatus($id)
    {
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 更新打印机状态
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     * 根据打印机ID获取内容信息
     *
     * 本函数通过调用repository的getWhere方法，根据提供的打印机ID（$id）和商户ID（mer_id），
     * 获取对应的记录如果找不到匹配的记录，则返回错误信息提示打印机信息不存在
     * 找到记录后，将记录的内容（content）从JSON字符串格式解码为PHP关联数组，
     * 并将内容信息返回成功响应
     *
     * @param int $id 打印机ID，用于查询特定的打印机信息
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的成功或失败响应
     */
    public function getContent($id)
    {
        // 根据打印机ID和商户ID获取记录
        $res = $this->repository->getWhere(['printer_id' => $id, 'mer_id' => $this->request->merId()]);
        // 如果没有找到记录，返回失败响应
        if (!$res) return app('json')->fail('打印机信息不存在');

        // 将记录的内容从JSON字符串解码为关联数组
        $print_content = json_decode($res->print_content, true) == null ? [] : json_decode($res->print_content, true);

        // 返回成功响应，包含解码后的内容信息
        return app('json')->success(compact('print_content'));
    }

    /**
     * 设置内容
     *
     * 此方法用于更新特定项的内容信息它从请求参数中获取内容信息，
     * 然后更新到存储库中的指定项上，最后返回操作结果
     *
     * @param mixed $id 项的唯一标识符，用于定位需要更新的项
     * @return mixed 操作结果，通常为JSON格式的成功响应
     */
    public function setContent($id)
    {
        // 从请求中获取需要设置的内容
        $content = $this->request->param('print_content');

        // 将获取到的内容转换为JSON格式并存储到指定项的内容字段中
        $this->repository->update($id, ['print_content' => json_encode($content, true)]);

        // 返回成功消息，表示内容设置成功
        return app('json')->success('修改成功');
    }

}
