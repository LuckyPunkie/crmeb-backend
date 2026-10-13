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

namespace app\controller\api\store\product;

use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductAssistUserRepository;
use think\App;
use crmeb\basic\BaseController;

class StoreProductAssistSet extends BaseController
{
    protected $repository;
    protected $userInfo;

    /**
     * StoreProductAssistSet constructor.
     * @param App $app
     * @param ProductAssistSetRepository $repository
     */
    public function __construct(App $app, ProductAssistSetRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->userInfo = $this->request->isLogin() ? $this->request->userInfo() : null;
    }


    /**
     * 个人助力列表
     * @return mixed
     * @author Qinii
     * @day 2020-11-25
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where['uid'] = $this->request->uid();
        return app('json')->success($this->repository->getApiList($where,$page, $limit));
    }

    /**
     * 根据ID获取详细信息
     *
     * 本函数旨在根据提供的ID和类型参数，从仓库中获取特定资源的详细信息。
     * 它首先从请求中获取类型参数，并验证其是否为预期的值（1或2）。
     * 如果类型参数无效，函数将返回一个错误响应。
     * 如果类型参数有效，函数将调用仓库中的detail方法来获取信息，并返回成功响应包含获取的数据。
     *
     * @param int $id 资源的唯一标识符
     * @return \think\Response 返回一个JSON格式的响应，包含成功获取的数据或错误信息
     */
    public function detail($id)
    {
        // 从请求中获取类型参数，并设置默认值为1
        $type = $this->request->param('type/d', 1);

        // 验证类型参数是否有效（是否为1或2）
        if (!in_array($type, [1, 2])) {
            // 如果类型参数无效，返回一个错误的JSON响应
            return app('json')->fail('类型参数错误');
        }

        // 调用仓库的detail方法来获取指定ID和类型的资源信息
        $data = $this->repository->detail($id, $this->userInfo, $type);

        // 返回一个成功的JSON响应，包含获取的资源信息
        return app('json')->success($data);
    }

    /**
     * 发起助力
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-28
     */
    public function create($id)
    {
//        if($this->userInfo->user_type == 'wechat' && !$this->userInfo->subscribe){
//            return  app('json')->fail('请先关注公众号');
//        }
        $data = $this->repository->create($id,$this->request->uid());
        return  app('json')->success($data);
    }

    /**
     * 帮好友助力
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-10-28
     */
    public function set($id)
    {
        $this->repository->set($id,$this->userInfo);
        return  app('json')->success('助力成功');
    }

    /**
     * 删除辅助设置项
     *
     * 本函数用于逻辑删除一个辅助设置项。所谓逻辑删除，是指并不真正从数据库中移除记录，
     * 而是通过将记录的状态字段设置为特定值（这里为-1）来标记该记录为已删除。
     * 这种做法通常用于处理数据安全和可恢复性方面的需求。
     *
     * @param int $id 辅助设置项的唯一标识ID
     * @return json 返回一个JSON格式的响应，包含操作的结果信息
     */
    public function delete($id)
    {
        // 根据ID和当前用户的UID查询辅助设置项信息
        $res = $this->repository->getWhere(['product_assist_set_id' => $id,'uid' => $this->request->uid()]);

        // 如果查询结果为空，则返回错误信息
        if(!$res)return  app('json')->fail('信息错误');

        // 更新辅助设置项的状态为已删除（-1）
        $this->repository->update($id,['status' => -1]);

        // 返回成功信息
        return  app('json')->success('取消成功');
    }


    /**
     * 助力列表
     * @param $id
     * @param ProductAssistUserRepository $repository
     * @return mixed
     * @author Qinii
     * @day 2020-10-28
     */
    public function userList($id,ProductAssistUserRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        $where['product_assist_set_id'] = $id;
        if(!$this->repository->get($id))  return  app('json')->fail('数据丢失');
        return app('json')->success($repository->userList($where,$page, $limit));
    }

    /**
     * 分享数量增加函数
     *
     * 本函数用于处理分享数量的增加操作。它通过调用repository中的方法来实现分享计数的增加，
     * 并返回一个表示操作成功的JSON响应。
     *
     * @param int $id 共享内容的唯一标识符。这个标识符用于确定要增加分享计数的具体内容。
     * @return \Illuminate\Http\JsonResponse 返回一个表示操作成功的JSON响应。
     */
    public function shareNum($id)
    {
        // 增加分享计数。这里$repository被视为一个依赖注入的对象，通过调用其incNum方法来增加指定$id的分享计数。
        $this->repository->incNum(1,$id);

        // 返回一个表示操作成功的JSON响应。这里使用了app('json')来获取一个JSON响应助手实例，并调用其success方法返回成功消息。
        return app('json')->success('oks');
    }
}
