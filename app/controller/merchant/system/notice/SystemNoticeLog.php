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


namespace app\controller\merchant\system\notice;


use app\common\repositories\system\notice\SystemNoticeLogRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * Class SystemNotice
 * @package app\controller\merchant\system\notice
 * @author xaboy
 * @day 2020/11/6
 */
class SystemNoticeLog extends BaseController
{
    /**
     * @var SystemNoticeLogRepository
     */
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用实例
     * @param SystemNoticeLogRepository $repository 系统通知日志仓库实例
     */
    public function __construct(App $app, SystemNoticeLogRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取系统通知列表
     *
     * @return \think\response\Json
     */
    public function lst()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['is_read', 'date', 'keyword']);
        // 添加商家ID条件
        $where['mer_id'] = $this->request->merId();
        $where['status'] = 1;
        // 调用仓库方法获取系统通知列表并返回JSON格式数据
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 标记系统通知已读
     *
     * @param int $id 系统通知ID
     * @return \think\response\Json
     */
    public function read($id)
    {
        // 调用仓库方法标记系统通知已读
        $this->repository->read(intval($id), $this->request->merId());
        // 返回JSON格式数据
        return app('json')->success('标记已读成功');
    }


    /**
     * 删除指定ID的数据
     *
     * @param int $id 要删除的数据ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的成功响应
     */
    public function del($id)
    {
        // 调用repository的del方法删除指定ID的数据，并传入当前商家ID
        $this->repository->del(intval($id), $this->request->merId());
        // 返回JSON格式的成功响应
        return app('json')->success('删除成功');
    }

    /**
     * 获取未读消息数量
     *
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的成功响应，其中包含未读消息数量
     */
    public function unreadCount()
    {
        // 调用repository的unreadCount方法获取未读消息数量，并传入当前商家ID
        return app('json')->success(['count' => $this->repository->unreadCount($this->request->merId())]);
    }

    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->request->merId());
        return app('json')->success($data);
    }
}
