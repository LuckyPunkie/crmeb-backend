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

namespace app\controller\admin\order;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\order\MerchantReconciliationRepository as repository;

/**
 * 商户对账记录-- 废弃
 */
class Reconciliation extends BaseController
{
    protected $repository;

    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    /**
     * 获取列表数据
     * 本函数用于根据用户请求的参数，获取特定条件下的列表数据。
     * 参数包括分页信息、查询条件等，通过处理这些参数，调用仓库层的方法来获取数据，并以JSON格式返回。
     *
     * @return \think\response\Json
     * 返回处理后的列表数据，以及相关的成功提示信息。
     */
    public function lst()
    {
        // 解构获取分页信息
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数
        $where = $this->request->params(['date', 'status', 'keyword', 'reconciliation_id']);

        // 调用仓库层的getList方法获取数据，并返回JSON格式的成功响应
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }



    /**
     * 创建对账单
     *
     * 本函数用于根据提供的商户ID和请求参数创建对账单。首先，它验证商户是否存在，如果不存在，则返回错误信息。
     * 接着，从请求中提取对账单相关的参数，包括日期、订单类型、退款类型等，并添加管理员ID。
     * 最后，调用repository的create方法来实际创建对账单，并返回成功信息。
     *
     * @param int $id 商户ID
     * @return json 返回操作的结果，成功时包含成功信息，失败时包含错误信息。
     */
    public function create($id)
    {
        // 检查商户是否存在，如果不存在则返回错误信息
        if (!app()->make(MerchantRepository::class)->merExists($id))
            return app('json')->fail('商户不存在');

        // 从请求中获取参数，包括订单相关和退款相关的信息
        $data = $this->request->params([
            'date',                     //时间
            'order_type',               //订单类型
            'refund_type',              //退款类型
            ['order_ids', []],           //订单ID列表
            ['order_out_ids', []],       //排除的订单ID列表
            ['refund_out_ids', []],      //排除的退款订单ID列表
            ['refund_order_ids', []]     //退款订单ID列表
        ]);

        // 添加管理员ID到数据中
        $data['adminId'] = $this->request->adminId();

        // 调用repository创建对账单
        $this->repository->create($id, $data);

        // 返回成功信息
        return app('json')->success('对账单生成成功');
    }

    /**
     * 确认打款
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-15
     */
    public function switchStatus($id)
    {
        if (!$this->repository->getWhereCountById($id))
            return app('json')->fail('数据不存在或状态错误');
        $status = $this->request->param('status') == 1 ? 1 : 0;
        $data['is_accounts'] = $status;
        if ($status == 1) $data['accounts_time'] = date('Y-m-d H:i:s', time());
        $this->repository->switchStatus($id, $data);
        return app('json')->success('修改成功');
    }


    /**
     * 标记表单
     *
     * 该方法用于处理对特定资源的标记操作。它首先验证资源是否存在，
     * 如果存在，则将表单数据处理并返回成功响应；如果资源不存在，
     * 则返回一个失败的响应。
     *
     * @param int $id 资源的唯一标识符
     * @return \Illuminate\Http\JsonResponse 成功或失败的JSON响应
     */
    public function markForm($id)
    {
        // 检查资源是否存在
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id]))
        {
            // 如果资源不存在，返回一个失败的JSON响应
            return app('json')->fail('数据不存在');
        }

        // 如果资源存在，处理表单数据并返回一个成功的JSON响应
        return app('json')->success(formToData($this->repository->adminMarkForm($id)));
    }

    /**
     * 对指定ID的数据进行标记操作
     *
     * 本函数主要用于对数据库中的特定记录进行备注更新操作。它首先验证指定ID的数据是否存在，
     * 如果存在，则从请求中获取备注信息，并更新到数据库中对应记录的备注字段。如果更新成功，
     * 则返回成功的响应信息。
     *
     * @param int $id 需要进行标记操作的数据的唯一标识ID
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface 如果数据不存在，则返回一个表示失败的JSON响应；如果标记操作成功，则返回一个表示成功的JSON响应。
     */
    public function mark($id)
    {
        // 检查指定ID的数据是否存在，如果不存在则返回失败响应
        if (!$this->repository->getWhereCount([$this->repository->getPk() => $id])) {
            return app('json')->fail('数据不存在');
        }

        // 从请求中获取管理员的标记信息
        $data = $this->request->params(['admin_mark']);

        // 使用repository更新数据的ID和获取的标记信息
        $this->repository->update($id, $data);

        // 返回成功的JSON响应
        return app('json')->success('备注成功');
    }
}
