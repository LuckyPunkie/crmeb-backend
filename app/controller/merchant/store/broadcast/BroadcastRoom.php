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


namespace app\controller\merchant\store\broadcast;


use app\common\repositories\store\broadcast\BroadcastAssistantRepository;
use app\common\repositories\store\broadcast\BroadcastRoomGoodsRepository;
use app\common\repositories\store\broadcast\BroadcastRoomRepository;
use app\validate\merchant\BroadcastRoomValidate;
use crmeb\basic\BaseController;
use think\App;

class BroadcastRoom extends BaseController
{
    protected $repository;

    public function __construct(App $app, BroadcastRoomRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取列表信息
     *
     * 本函数用于根据当前请求中的参数，获取对应列表数据的分页信息。
     * 主要包括关键字搜索、状态筛选、展示标签等条件，支持对直播状态和房间类型的过滤。
     *
     * @return \think\response\Json 返回一个包含列表数据的JSON响应对象
     */
    public function lst()
    {
        // 解析并获取当前请求的分页信息
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数，包括关键字、状态标签等
        $where = $this->request->params(['keyword', 'status_tag', 'show_tag', 'show_type','live_status','broadcast_room_id']);

        // 调用repository中的getList方法获取列表数据，并包装在成功的JSON响应中返回
        return app('json')->success($this->repository->getList($this->request->merId(), $where, $page, $limit));
    }

    /**
     * 获取直播间的商品列表
     *
     * 本函数用于根据提供的直播房间ID，获取该直播间内的商品列表。
     * 这包括分页信息的处理和验证直播房间是否存在。
     *
     * @param BroadcastRoomGoodsRepository $repository 直播间商品仓库，用于获取商品列表。
     * @param int $id 直播房间ID，用于指定要获取商品列表的直播房间。
     * @return json 返回包含商品列表的JSON响应，如果直播房间不存在，则返回错误信息。
     */
    public function goodsList(BroadcastRoomGoodsRepository $repository, $id)
    {
        // 获取请求中的分页信息
        [$page, $limit] = $this->getPage();

        // 验证指定的直播房间是否存在
        if (!$this->repository->merExists((int)$id, $this->request->merId()))
        {
            // 如果直播房间不存在，则返回错误的JSON响应
            return app('json')->fail('直播间不存在');
        }

        // 如果直播房间存在，则获取商品列表，并返回成功的JSON响应
        return app('json')->success($repository->getGoodsList($id, $page, $limit));
    }

    /**
     * 获取详细信息
     * 此方法用于根据给定的ID获取特定资源的详细信息。它首先验证资源是否存在，
     * 然后返回该资源的详细数据。
     *
     * @param int $id 资源的唯一标识符
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function detail($id)
    {
        // 检查资源是否存在，如果不存在，则返回错误的JSON响应
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 如果资源存在，获取资源详情并返回成功的JSON响应
        return app('json')->success($this->repository->get($id)->toArray());
    }


    /**
     * 创建表单
     *
     * 本函数用于生成并返回表单数据。它通过调用repository的createForm方法来创建表单，
     * 然后将表单数据转换为合适的格式，最后以成功响应的形式返回。
     *
     * @return \Illuminate\Http\JsonResponse 表单数据的成功响应
     */
    public function createForm()
    {
        // 使用app助手函数获取JSON响应实例，并将表单数据转换为合适格式后返回
        return app('json')->success(formToData($this->repository->createForm()));
    }

    /**
     * 更新表单信息。
     *
     * 本函数用于处理特定ID的表单更新请求。它首先验证请求的数据是否存在，
     * 然后检查当前直播间是否允许修改，最后更新表单数据并返回结果。
     *
     * @param int $id 表单的唯一标识ID。
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含更新操作的结果。
     */
    public function updateForm($id)
    {
        // 验证指定ID的数据是否存在。
        if (!$this->repository->merExists($id, $this->request->merId()))
        {
            // 如果数据不存在，则返回一个失败的JSON响应。
            return app('json')->fail('数据不存在');
        }

        // 检查是否有权限修改当前直播间的状态。
        if (!$this->repository->existsWhere(['broadcast_room_id' => $id, 'status' => [-1, 0]]))
        {
            // 如果没有权限，则返回一个失败的JSON响应。
            return app('json')->fail('当前直播间不能修改');
        }

        // 更新表单数据，并将结果转换为JSON格式返回。
        return app('json')->success(formToData($this->repository->updateForm(intval($id))));
    }

    /**
     * 创建一个新的实体。
     *
     * 本函数负责调用仓库接口，以创建一个新的实体。它首先从请求对象中提取商户ID，
     * 然后校验传入的参数，最后通过仓库接口的创建方法来执行创建操作。
     *
     * @return \Illuminate\Http\JsonResponse 创建成功时返回的JSON响应。
     */
    public function create()
    {
        // 调用仓库中的创建方法，传入商户ID和校验后的参数
        $this->repository->create($this->request->merId(), $this->checkParams());

        // 返回一个表示创建成功的JSON响应
        return app('json')->success('创建成功');
    }


    /**
     * 更新直播间的相关信息。
     *
     * 本函数用于根据提供的ID更新直播间的状态和信息。首先，它会验证请求的合法性，
     * 包括检查直播间是否存在以及是否允许进行修改。如果一切检查通过，那么将调用
     * 相关方法更新直播间的信息。
     *
     * @param int $id 直播间的ID，用于定位特定的直播间进行更新。
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含更新操作的结果信息。
     */
    public function update($id)
    {
        // 检查商家是否存在指定的直播间
        if (!$this->repository->merExists($id, $this->request->merId()))
        {
            // 如果商家没有权限访问该直播间，则返回错误信息
            return app('json')->fail('数据不存在');
        }

        // 检查直播间当前状态是否允许修改
        if (!$this->repository->existsWhere(['broadcast_room_id' => $id, 'status' => [-1, 0]]))
        {
            // 如果直播间状态不允许修改，则返回错误信息
            return app('json')->fail('当前直播间不能修改');
        }

        // 更新直播间的信息，包括可能的状态和其它参数
        $this->repository->updateRoom($this->request->merId(), $id, $this->checkParams());

        // 更新成功后，返回成功的提示信息
        return app('json')->success('修改成功');
    }

    /**
     * 检查广播室相关参数的有效性。
     *
     * 本方法用于在创建或更新广播室前，验证所提供的参数是否符合要求。它通过验证参数的存在性和合法性，确保了数据的准确性和系统的稳定性。
     * 参数包括广播室名称、封面图、分享图、主播名称、主播微信、电话、开始时间、类型、屏幕类型等，涵盖了广播室的主要信息属性。
     *
     * @return array 返回验证后的参数数据，包括开始时间等经过处理的字段。
     */
    public function checkParams()
    {
        // 实例化广播室验证器
        $validate = app()->make(BroadcastRoomValidate::class);

        // 从请求中提取指定参数
        $data = $this->request->params(['name', 'cover_img', 'share_img', 'anchor_name', 'anchor_wechat', 'phone', 'start_time', 'type', 'screen_type', 'close_like', 'close_goods', 'close_comment', 'replay_status', 'close_share', 'close_kf','feeds_img','is_feeds_public']);

        // 对提取的参数进行验证
        $validate->check($data);

        // 处理开始时间参数，将其拆分为开始时间和结束时间
        [$data['start_time'], $data['end_time']] = $data['start_time'];

        // 返回处理后的参数数据
        return $data;
    }

    /**
     * 修改商户状态
     *
     * 本函数用于根据请求中的$id修改指定商户的状态。状态通过请求中的is_show参数决定，
     * 默认为0（不显示），如果is_show参数为1，则改为1（显示）。
     *
     * @param int $id 商户的ID
     * @return json 返回一个JSON格式的响应，成功时包含成功消息，失败时包含错误消息。
     */
    public function changeStatus($id)
    {
        // 根据请求中的is_show参数决定商户的显示状态，存在$ishow变量中
        $isShow = $this->request->param('is_show') == 1 ? 1 : 0;

        // 检查商户是否存在，如果不存在，则返回失败消息
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 更新商户的显示状态
        $this->repository->isShow($id, $isShow);

        // 返回成功消息
        return app('json')->success('修改成功');
    }

    /**
     * 标记方法
     *
     * 本方法用于对特定ID的对象进行标记操作。它首先尝试从请求中获取标记参数，
     * 然后验证指定ID的对象是否存在，并且操作者是否有权限对其进行标记。
     * 如果对象存在且操作者有权限，那么将执行标记操作，并返回成功的响应。
     * 如果对象不存在，则返回一个表示失败的响应。
     *
     * @param int $id 需要被标记的对象的ID
     * @return json 标记操作的结果，成功或失败的响应
     */
    public function mark($id)
    {
        // 将请求中的mark参数转换为字符串类型
        $mark = (string)$this->request->param('mark');

        // 检查指定ID的对象是否存在，并且当前操作者是否有权限对其进行标记
        if (!$this->repository->merExists($id, $this->request->merId()))
            // 如果对象不存在或无权限，则返回一个表示失败的JSON响应
            return app('json')->fail('数据不存在');

        // 对指定ID的对象执行标记操作
        $this->repository->mark($id, $mark);

        // 标记操作成功，返回一个表示成功的JSON响应
        return app('json')->success('修改成功');
    }

    /**
     * 导出直播商品
     *
     * 本函数用于处理导出指定直播间的商品请求。它从请求中获取商品ID列表和直播间ID，
     * 并调用仓库层的相应方法来执行导出操作。
     *
     * @return \think\response\Json
     * @throws \Exception
     */
    public function exportGoods()
    {
        // 从请求中提取商品ID列表和直播间ID
        [$ids, $roomId] = $this->request->params(['ids', 'room_id'], true);

        // 检查是否选择了商品，如果没有选择则返回错误信息
        if (!count($ids)) return app('json')->fail('请选择直播商品');

        // 调用仓库层导出商品方法，传入商家ID、商品ID列表和直播间ID
        $this->repository->exportGoods($this->request->merId(), (array)$ids, $roomId);

        // 导出成功，返回成功信息
        return app('json')->success('导入成功');
    }

    /**
     * 删除导出商品
     *
     * 本函数用于处理删除特定商户所导出的商品的请求。它从请求中提取商户ID、房间ID和商品ID，
     * 并调用仓库接口来执行删除操作。此功能确保了商户可以管理他们导出到特定房间的商品，
     * 提供了一种方式来清理不再需要的商品数据。
     *
     * @return \think\response\Json 删除成功的响应对象
     */
    public function rmExportGoods()
    {
        // 从请求中提取id和room_id参数，并进行类型转换确保数据安全
        [$id, $roomId] = $this->request->params(['id', 'room_id'], true);

        // 调用repository中的方法，删除指定商户、房间和商品ID的导出商品
        $this->repository->rmExportGoods($this->request->merId(), intval($roomId), intval($id));

        // 返回一个表示删除成功的JSON响应
        return app('json')->success('删除成功');
    }

    /**
     * 删除商户信息。
     *
     * 本函数用于根据给定的ID删除商户信息。首先，它会验证商户是否存在，
     * 如果不存在，则返回一个错误消息。如果商户存在，它将调用repository的删除方法来删除商户，
     * 并返回一个成功消息。
     *
     * @param int $id 商户的唯一标识符。
     * @return \Illuminate\Http\JsonResponse 删除成功时返回成功的JSON响应，删除失败时返回错误的JSON响应。
     */
    public function delete($id)
    {
        // 检查商户是否存在，如果不存在则返回错误信息。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 删除商户，调用repository的删除方法。
        $this->repository->merDelete((int)$id);

        // 删除成功，返回成功信息。
        return app('json')->success('删除成功');
    }


    /**
     * 添加助手表单
     *
     * 本函数用于处理添加助手的表单请求。它通过接收一个ID参数，来确定具体的助手表单。
     * 表单数据的生成是通过调用repository中的assistantForm方法，并将当前请求中的商家ID作为参数传入来实现的。
     * 最后，使用json工具类将表单数据封装成成功响应返回。
     *
     * @param int $id 助手表单的唯一标识ID
     * @return \Illuminate\Http\JsonResponse 成功获取表单数据后的JSON响应
     */
    public function addAssistantForm($id)
    {
        // 生成表单数据并封装成成功响应返回
        return app('json')->success(formToData($this->repository->assistantForm($id, $this->request->merId())));
    }

    /**
     * 添加助手方法
     *
     * 本方法用于在指定的商户下添加助手。首先，它会验证请求中助手ID的有效性，
     * 然后将这些助手ID与指定的商户关联起来。如果助手ID不存在，则操作失败。
     *
     * @param int $id 商户ID，用于指定要添加助手的商户。
     * @return \think\response\Json 修改成功时返回修改成功的提示，失败时返回错误信息。
     */
    public function addAssistant($id)
    {
        // 从请求中获取助手ID列表
        $data = $this->request->param('assistant_id');

        // 实例化广播助手仓库类
        $make = app()->make(BroadcastAssistantRepository::class);

        // 遍历助手ID列表，检查每个助手ID是否存在
        foreach ($data as $datum) {
            $has = $make->exists($datum);
            // 如果助手ID不存在，返回错误信息
            if (!$has)  return app('json')->fail('助手信息不存在,ID:'.$datum);
        }

        // 更新商户的助手信息
        $this->repository->editAssistant($id, $this->request->merId(), $data);

        // 返回成功信息
        return app('json')->success('修改成功');
    }

    /**
     * 推送消息给指定用户。
     *
     * 本函数旨在向指定的用户推送消息。首先，它会验证用户是否存在，如果用户不存在，则返回错误信息。
     * 如果用户存在，则调用repository中的方法推送消息，并返回成功提示。
     *
     * @param int $id 用户ID。用于指定消息的接收者。
     * @return \Illuminate\Http\JsonResponse 返回一个JSON响应，包含推送结果的状态码和消息。
     */
    public function pushMessage($id)
    {
        // 检查用户是否存在，如果不存在则返回错误信息。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 调用repository的pushMessage方法推送消息。
        $this->repository->pushMessage($id);

        // 返回成功信息，表明消息已成功推送。
        return app('json')->success('消息已发送');
    }

    /**
     * 根据请求关闭客服
     *
     * 本函数用于处理关闭客服的请求。它通过判断请求中的状态参数来确定是否需要关闭客服，
     * 然后调用仓库中的方法来执行关闭操作，并返回一个成功提示。
     *
     * @param int $id 客服的唯一标识符
     * @return json 返回一个表示操作成功的JSON响应
     */
    public function closeKf($id)
    {
        // 根据请求中的status参数决定是否关闭客服，status为1则关闭，否则打开
        $status = $this->request->param('status') == 1 ? 1 : 0;

        // 调用仓库中的方法来关闭客服，并传递客服ID、关闭类型和状态
        $this->repository->closeInfo($id,'close_kf', $status);

        // 返回一个表示操作成功的JSON响应
        return app('json')->success('修改成功');
    }

    /**
     * 禁用或启用指定ID的评论
     *
     * @param int $id 评论ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function banComment($id)
    {
        // 获取请求参数中的状态值，如果没有则默认为0
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 调用repository中的closeInfo方法，传入评论ID和操作类型，以及状态值
        $this->repository->closeInfo($id, 'close_comment', $status);
        // 返回JSON格式的操作结果，提示修改成功
        return app('json')->success('修改成功');
    }


    /**
     * 判断动态是否公开
     *
     * @param int $id 动态ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function isFeedsPublic($id)
    {
        // 获取请求参数中的状态值，如果不存在则默认为0
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 调用repository中的closeInfo方法，将指定动态的is_feeds_public字段设置为$status
        $this->repository->closeInfo($id, 'is_feeds_public', $status);
        // 返回JSON格式的操作结果，表示修改成功
        return app('json')->success('修改成功');
    }


    /**
     * 上架或下架商品
     *
     * @param int $id 商品ID
     * @return \think\response\Json 返回JSON格式的操作结果
     */
    public function onSale($id)
    {
        // 获取请求参数中的状态值，如果不存在则默认为0
        $status = $this->request->param('status') == 1 ? 1 : 0;
        // 获取请求参数中的商品ID
        $data['goods_id'] = $this->request->param('goods_id');
        // 调用仓库类的 closeInfo 方法，将商品的上架状态修改为指定的状态
        $this->repository->closeInfo($id, 'on_sale', $status, false, $data);
        // 返回 JSON 格式的操作结果，表示修改成功
        return app('json')->success('修改成功');
    }

}
