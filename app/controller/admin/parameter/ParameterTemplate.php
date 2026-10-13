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

namespace app\controller\admin\parameter;

use app\common\repositories\store\parameter\ParameterValueRepository;
use app\common\repositories\store\parameter\ParameterTemplateRepository;
use app\validate\admin\ParameterTemplateValidate;
use think\App;
use crmeb\basic\BaseController;
use think\exception\ValidateException;
use app\common\repositories\store\StoreCategoryRepository;

/**
 * 商品参数模板
 */
class ParameterTemplate extends BaseController
{
    protected $repository;

    /**
     * City constructor.
     * @param App $app
     * @param ParameterTemplateRepository $repository
     */
    public function __construct(App $app, ParameterTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  列表
     * @Author:Qinii
     * @Date: 2020/5/8
     * @Time: 14:40
     * @return mixed
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['template_name', 'cate_ids', 'mer_name', 'mer_id']);
        $where['is_mer'] = $this->request->param('is_mer', 1);
        if ($merId = $this->request->merId()) {
            $where['mer_id'] = $merId;
            unset($where['is_mer']);
        }
        // 处理分类id
        if (!empty($where['cate_ids'])) {
            $where['cate_id'] = end($where['cate_ids']);
            if(count($where['cate_ids']) !== 3) {
                $cartWhere = [
                    ['path', 'like', '%/'.$where['cate_id'].'/%'],
                    ['is_show', '=', 1]
                ];
                $categories = app()->make(StoreCategoryRepository::class)->selectWhere($cartWhere, 'store_category_id')->toArray();
                $storeCategoryIds = array_column($categories, 'store_category_id');
                $where['cate_id'] = $storeCategoryIds;
            }
            unset($where['cate_ids']);
        }
        $data = $this->repository->getList($where, $page, $limit);
        return app('json')->success($data);
    }

    /**
     *  详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id, $this->request->merId());
        return app('json')->success($data);
    }

    /**
     *  添加
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $data = $this->checkParams(1);
        $this->repository->create($this->request->merId(), $data);
        return app('json')->success('添加成功');
    }

    /**
     *  编辑
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->checkParams();
        $where['template_id'] = $id;
        if ($merId = $this->request->merId()) {
            $where['mer_id'] = $merId;
        }
        if (!$res = $this->repository->getWhere($where)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->update($id, $data, $merId);
        return app('json')->success('编辑成功');
    }

    public function deleteValue($id, ParameterValueRepository $repository)
    {
        $repository->dostory($id, $this->request->merId());
        return app('json')->success('删除成功');
    }

    /**
     *  删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        $where['template_id'] = $id;
        if ($merId = $this->request->merId()) {
            $where['mer_id'] = $merId;
        }
        if (!$this->repository->getWhere($where)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->delete($id);
        return app('json')->success('操作成功');
    }

    /**
     *  根据cate_id获取参数模板列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/11/22
     */
    public function select()
    {
        $where = $this->request->params([['cate_id', 0]]);
        $where['mer_id'] = 0;
        $data['sys'] = $this->repository->getSelect($where);

        $where = ['mer_id' => $this->request->merId()];
        $data['mer'] = $this->repository->getSelect($where);

        return app('json')->success($data);
    }

    /**
     *  根据模板id 获取参数
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     * @day 2022/11/22
     */
    public function show()
    {
        $template_ids = $this->request->param('template_ids');
        if (!$template_ids) return app('json')->success([]);
        $where['template_ids'] = $template_ids;
        $data = $this->repository->show($where);
        return app('json')->success($data);
    }

    /**
     *  验证参数
     * @param $isCreate
     * @return array
     * @author Qinii
     */
    public function checkParams($isCreate = 0)
    {
        $mer_id = $this->request->merId();
        $data = $this->request->params(['template_name', ['cate_ids', []], 'sort', 'params','delete_params']);
        app()->make(ParameterTemplateValidate::class)->check($data);
        if ($mer_id == 0 && empty($data['cate_ids'])) {
            throw new ValidateException('请选择商品分类');
        }
        if ($isCreate) {
            foreach ($data['params'] as $item) {
                foreach ($item['values'] as &$subArray) {
                    if (isset($subArray['parameter_value_id'])) {
                        unset($subArray['parameter_value_id']);
                    }
                }
                if (isset($item['parameter_id'])) unset($item['parameter_id']);
                if (isset($item['template_id'])) unset($item['template_id']);
                $params[] = $item;
            }
            $data['params'] = $params;
        }
        return $data;
    }
}
