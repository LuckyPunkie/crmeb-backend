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

namespace app\controller\merchant\store\service;

use app\common\repositories\store\service\StoreServiceReplyRepository;
use app\validate\merchant\ServiceReplyValidate;
use crmeb\basic\BaseController;
use think\App;

class StoreServiceReply extends BaseController
{
    /**
     * @var StoreServiceReplyRepository
     */
    protected $repository;

    /**
     * StoreService constructor.
     * @param App $app
     * @param StoreServiceReplyRepository $repository
     */
    public function __construct(App $app, StoreServiceReplyRepository $repository)
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
        $where = $this->request->params(['keyword', 'status']);
        $where['mer_id'] = $this->request->merId();

        // 调用 repository 类的 getList 方法获取列表数据并返回 JSON 格式的响应
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建数据
     *
     * @return \think\response\Json
     */
    public function create()
    {
        // 校验参数
        $data = $this->checkParams();
        // 调用 repository 类的 create 方法创建数据并返回 JSON 格式的响应
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }


    /**
     * 更新服务回复
     *
     * @param int $id 服务回复ID
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function update($id)
    {
        // 检查参数
        $data = $this->checkParams();
        // 判断数据是否存在
        if (!$this->repository->existsWhere(['mer_id' => $data['mer_id'], 'service_reply_id' => $id])) {
            return app('json')->fail('数据不存在');
        }
        // 更新数据
        $this->repository->update($id, $data);
        // 返回成功信息
        return app('json')->success('修改成功');
    }

    /**
     * 删除服务回复
     *
     * @param int $id 服务回复ID
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function delete($id)
    {
        if (!$this->repository->existsWhere(['mer_id' => $this->request->merId(), 'service_reply_id' => $id])) {
            return app('json')->fail('数据不存在');
        }
        // 删除数据
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }


    /**
     * 修改状态
     * @param int $id 服务回复ID
     * @return \think\response\Json
     */
    public function changeStatus($id)
    {
        // 获取请求参数中的状态值
        $data = $this->request->params(['status']);
        // 判断服务回复是否存在
        if (!$this->repository->existsWhere(['mer_id' => $this->request->merId(), 'service_reply_id' => $id])) {
            return app('json')->fail('数据不存在');
        }
        // 更新服务回复状态
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 检查参数
     * @return array
     */
    public function checkParams()
    {
        // 获取请求参数中的关键字、状态、内容和类型
        $data = $this->request->params(['keyword', 'status', 'content', 'type']);
        // 校验参数
        app()->make(ServiceReplyValidate::class)->check($data);
        // 添加商家ID到参数中
        $data['mer_id'] = $this->request->merId();
        return $data;
    }


}
