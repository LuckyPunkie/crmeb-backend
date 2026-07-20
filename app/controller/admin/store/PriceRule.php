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
namespace app\controller\admin\store;

use app\common\repositories\store\PriceRuleRepository;
use app\validate\admin\PriceRuleValidate;
use crmeb\basic\BaseController;
use think\App;

/**
 * 价格说明
 */
class PriceRule extends BaseController
{

    protected $repository;

    /**
     * Product constructor.
     * @param App $app
     * @param PriceRuleRepository $repository
     */
    public function __construct(App $app, PriceRuleRepository $repository)
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
        $where = $this->request->params(['cate_id', 'keyword', 'is_show']);
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->repository->lst($where, $page, $limit));
    }

    /**
     *  编辑
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->getParams();
        if (!$this->repository->exists((int)$id)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->updateRule((int)$id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 添加
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->getParams();
        $this->repository->createRule($data);
        return app('json')->success('添加成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->exists((int)$id)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }

    /**
     * 获取参数
     * @return array|mixed|string|string[]
     * @author Qinii
     */
    public function getParams()
    {
        $data = $this->request->params(['rule_name', 'cate_id', 'sort', 'is_show', 'content']);
        app()->make(PriceRuleValidate::class)->check($data);
        return $data;
    }

    /**
     *  修改状态
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('is_show') == 1 ?: 0;
        if (!$this->repository->exists((int)$id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['is_show' => $status]);
        return app('json')->success('修改成功');
    }


}
