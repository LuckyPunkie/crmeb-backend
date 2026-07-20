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
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

class BroadcastAssistant extends BaseController
{
    protected $repository;

    public function __construct(App $app, BroadcastAssistantRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 获取用户列表
     * 本函数用于根据请求参数获取特定条件下的用户列表，支持分页查询。
     * 参数:
     * - page: 当前页码，用于分页查询。
     * - limit: 每页显示的记录数，用于分页查询。
     * - username: 用户名，可选，用于按用户名查询。
     * - nickname: 昵称，可选，用于按昵称查询。
     * - mer_id: 商户ID，可选，用于按商户查询。
     * 返回值:
     * - 返回查询结果的JSON对象，包含用户列表数据及分页信息。
     */
    public function lst()
    {
        // 解构获取当前页码和每页记录数
        [$page, $limit] = $this->getPage();

        // 从请求中获取查询参数，包括用户名和昵称
        $where = $this->request->params(['username', 'nickname']);

        // 获取当前请求的商户ID，并将其加入查询条件
        $where['mer_id'] = $this->request->merId();

        // 调用repository的getList方法进行查询，并返回查询结果
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }


    /**
     * 创建表单的JSON响应
     *
     * 本函数用于生成关于表单的数据，并将其封装在一个成功的JSON响应中。
     * 它通过调用repository的form方法来获取表单数据，然后将这些数据转换为适合JSON响应的格式。
     * 最后，它使用应用容器中的'json'服务来创建并返回这个成功的JSON响应。
     *
     * @return \Illuminate\Http\JsonResponse 表单数据的JSON响应，包含成功状态码和表单数据。
     */
    public function createForm()
    {
        // 调用app('json')->success()方法返回一个成功的JSON响应，其中包含formToData()处理后的表单数据
        return app('json')->success(formToData($this->repository->form(null)));
    }

    /**
     * 根据指定ID更新表单数据。
     *
     * 本函数旨在通过提供的ID从数据库中检索表单数据，并对其进行更新。首先，它会验证请求的ID是否对应于现有数据，
     * 如果数据不存在，则返回一个错误响应。如果数据存在，它将表单数据转换为合适的格式，然后返回成功响应携带该数据。
     *
     * @param int $id 需要更新的表单数据的ID。
     * @return \Illuminate\Http\JsonResponse 成功时返回更新的表单数据，失败时返回错误信息。
     */
    public function updateForm($id)
    {
        // 检查指定ID的数据是否存在，如果不存在则返回错误信息。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 如果数据存在，获取表单数据并转换为合适的形式，然后返回成功响应携带该数据。
        return app('json')->success(formToData($this->repository->form(intval($id))));
    }

    /**
     * 创建新记录
     *
     * 本函数用于处理创建新数据的逻辑。它首先通过检查参数的合法性来确保数据的准确性和安全性，
     * 然后将包含有效参数的数据用于创建新记录。此函数特别适用于需要进行参数验证和记录创建的场景，
     * 如在商城系统中创建新的商家记录。
     *
     * @return \think\response\Json 创建成功后的响应对象，包含成功消息。
     */
    public function create()
    {
        // 检查并获取请求中的参数
        $data = $this->checkParams();

        // 获取请求中的商家ID，并将其添加到数据数组中
        $data['mer_id'] = $this->request->merId();

        // 使用仓库接口创建新记录
        $this->repository->create($data);

        // 返回成功的JSON响应
        return app('json')->success('添加成功');
    }

    /**
     * 根据指定ID更新数据。
     * 此方法首先验证请求中的ID是否存在，如果存在，则更新数据；如果不存在，则返回错误信息。
     * 使用$this->checkParams()来获取和验证请求中的参数，确保数据更新的准确性。
     *
     * @param int $id 需要更新的数据的ID。
     * @return \Illuminate\Http\JsonResponse 更新成功时返回成功的JSON响应，否则返回失败的JSON响应。
     */
    public function update($id)
    {
        // 检查当前请求所操作的数据是否存在于仓库中
        if (!$this->repository->merExists($id, $this->request->merId()))
            // 如果数据不存在，则返回一个失败的JSON响应
            return app('json')->fail('数据不存在');

        // 使用验证后的参数更新数据
        $this->repository->update($id, $this->checkParams());
        // 更新成功后，返回一个成功的JSON响应
        return app('json')->success('修改成功');
    }


    /**
     * 检查请求参数是否符合要求
     *
     * 本函数用于从请求中提取指定的参数，并确保这些参数中必须的项（如用户名和昵称）不为空。
     * 如果关键参数为空，则抛出一个验证异常，提示微信号或昵称不可为空。
     * 这样可以提前防止无效或不完整的数据进入后续的业务逻辑，保障数据的完整性和操作的准确性。
     *
     * @return array 返回包含用户名、昵称和标记的数据数组
     * @throws ValidateException 如果用户名或昵称为空，则抛出验证异常
     */
    public function checkParams()
    {
        // 从请求中提取用户名、昵称和标记参数
        $data = $this->request->params(['username', 'nickname', 'mark']);

        // 检查用户名和昵称是否为空，如果为空则抛出异常
        if (!$data['username'] || !$data['nickname']) {
            throw new ValidateException('微信号或昵称不可为空');
        }

        // 返回提取并验证过的参数数据
        return $data;
    }

    /**
     * 删除指定ID的实体。
     *
     * 此方法首先验证请求的ID是否存在，如果不存在，则返回一个错误响应。
     * 如果ID存在，它将尝试删除该实体，并返回一个成功响应。
     * 这个方法体现了业务逻辑中对实体进行删除的操作，它封装了验证和删除的细节，
     * 并通过返回标准的JSON响应来与调用者进行交互。
     *
     * @param int $id 要删除的实体的ID。
     * @return \Illuminate\Http\JsonResponse 删除成功时返回成功的JSON响应，删除失败（ID不存在）时返回失败的JSON响应。
     */
    public function delete($id)
    {
        // 检查指定ID的实体是否存在，如果不存在则返回失败响应。
        if (!$this->repository->merExists($id, $this->request->merId()))
            return app('json')->fail('数据不存在');

        // 删除指定ID的实体。
        $this->repository->delete((int)$id);

        // 删除成功，返回成功响应。
        return app('json')->success('删除成功');
    }

}
