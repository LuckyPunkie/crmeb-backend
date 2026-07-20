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


namespace app\controller\admin\system\config;


use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository;
use app\validate\admin\ConfigClassifyValidate;
use crmeb\basic\BaseController;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 配置分类
 */
class ConfigClassify extends BaseController
{
    /**
     * @var ConfigClassifyRepository
     */
    private $repository;

    /**
     * ConfigClassify constructor.
     * @param App $app
     * @param ConfigClassifyRepository $repository
     */
    public function __construct(App $app, ConfigClassifyRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-03-31
     */
    public function lst()
    {
        $where = $this->request->params(['status', 'classify_name']);
        $lst = $this->repository->listConfig($where);
        $lst['list'] = formatCategory($lst['list']->toArray(), 'config_classify_id');
        return app('json')->success($lst);
    }

    /**
     * 获取子集
     * @param int $pid
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-03-27
     */
    public function children($pid)
    {
        return app('json')->success($this->repository->children($pid));
    }

    /**
     * 创建配置分类
     * @return mixed
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-03-31
     */
    public function createTable()
    {
        $form = $this->repository->createForm();
        return app('json')->success(formToData($form));
    }

    /**
     * 修改配置分类
     * @param int $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-03-31
     */
    public function updateTable($id)
    {
        if (!$this->repository->exists($id)) app('json')->fail('数据不存在');
        $form = $this->repository->updateForm($id);
        return app('json')->success(formToData($form));
    }

    /**
     * 配置分类下拉
     * @return mixed
     * @author xaboy
     * @day 2020-03-27
     */
    public function options()
    {
        return app('json')->success($this->repository->optionsConfig());
    }

    /**
     * 配置分类详情
     * @param int $id
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-03-27
     */
    public function get($id)
    {
        $data = $this->repository->get($id);
        if (!$data)
            return app('json')->fail('分类不存在');
        else
            return app('json')->success($data);

    }

    /**
     * 创建配置分类
     * @param ConfigClassifyValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020-03-27
     */
    public function create(ConfigClassifyValidate $validate)
    {
        $data = $this->request->params(['pid', 'classify_name', 'classify_key', 'info', 'status', 'icon', 'sort']);
        $validate->check($data);
        if ($this->repository->keyExists($data['classify_key']))
            return app('json')->fail('配置分类key已存在');
        if ($data['pid'] && !$this->repository->pidExists($data['pid']))
            return app('json')->fail('上级分类不存在');
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改配置分类
     * @param int $id
     * @param ConfigClassifyValidate $validate
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-27
     */
    public function update($id, ConfigClassifyValidate $validate)
    {
        $data = $this->request->params(['pid', 'classify_name', 'classify_key', 'info', 'status', 'icon', 'sort']);
        $validate->check($data);

        if (!$this->repository->exists($id))
            return app('json')->fail('分类不存在');
        if ($this->repository->keyExists($data['classify_key'], $id))
            return app('json')->fail('配置分类key已存在');
        if ($data['pid'] && !$this->repository->pidExists($data['pid'], $id))
            return app('json')->fail('上级分类不存在');
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }

    /**
     * 修改状态
     * @param int $id
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-31
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0);
        if (!$this->repository->exists($id))
            return app('json')->fail('分类不存在');
        $this->repository->switchStatus($id, $status == 1 ? 1 : 0);
        return app('json')->success('修改成功');
    }

    /**
     * 删除配置分类
     * @param int $id
     * @param ConfigRepository $configRepository
     * @return mixed
     * @throws DbException
     * @author xaboy
     * @day 2020-03-27
     */
    public function delete($id, ConfigRepository $configRepository)
    {
        if ($this->repository->existsChild($id))
            return app('json')->fail('存在子级,无法删除');
        if ($configRepository->classifyIdExists($id))
            return app('json')->fail('分类下存在配置,无法删除');
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 获取配置分类
     * @return \think\response\Json
     * @author Qinii
     */
    public function getOptions()
    {
        $options = $this->repository->getOption();
        return app('json')->success(formatCategory($options, 'config_classify_id'));
    }
}
