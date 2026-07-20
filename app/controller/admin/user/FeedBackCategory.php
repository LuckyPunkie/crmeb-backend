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

namespace app\controller\admin\user;


use crmeb\basic\BaseController;
use think\App;
use app\common\repositories\user\FeedBackCategoryRepository as repository;

/**
 * 用户反馈分类
 * app\controller\admin\user
 * FeedBackCategory
 */
class FeedBackCategory extends BaseController
{
    /**
     * @var repository
     */
    protected $repository;

    /**
     * User constructor.
     * @param App $app
     * @param  $repository
     */
    public function __construct(App $app, repository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @author Qinii
     */
    public function lst()
    {
        return app('json')->success($this->repository->getFormatList(0));
    }

    /**
     * 创建表单
     * @return mixed
     * @author Qinii
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(0)));
    }

    /**
     * 编辑表单
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function updateForm($id)
    {
        if (!$this->repository->merExists(0, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->updateForm(0, $id)));
    }

    /**
     * 创建
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function create()
    {
        $data = $this->request->params(['pid', 'cate_name', 'sort', 'pic', 'is_show']);
        if(is_array($data['pid']) && empty($data['pid']))
            return app('json')->fail('请选择上级分类');
        if (empty($data['cate_name']))
            return app('json')->fail('分类名不可为空');
        if (strlen($data['cate_name']) > 60)
            return app('json')->fail('分类名不得超过20个汉字');
        if ($data['pid'] && !$this->repository->merExists(0, $data['pid']))
            return app('json')->fail('上级分类不存在');
        if ($data['pid'] && !$this->repository->checkLevel($data['pid']))
            return app('json')->fail('不可添加更低阶分类');
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function update($id)
    {
        $data = $this->request->params(['pid', 'cate_name', 'sort', 'pic', 'is_show']);
        if (!$this->repository->merExists(0, $id))
            return app('json')->fail('数据不存在');
        if ($data['pid'] && !$this->repository->merExists(0, $data['pid']))
            return app('json')->fail('上级分类不存在');
        if ($data['pid'] && !$this->repository->checkLevel($data['pid']))
            return app('json')->fail('不可添加更低阶分类');
        if (!$this->repository->checkChangeToChild($id, $data['pid']))
            return app('json')->fail('无法修改到当前分类到子集，请先修改子类');
        //todo 待优化
//        if (!$this->repository->checkChildLevel($id,$data['pid']))
//            return app('json')->fail('子类超过最低限制，请先修改子类');
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }

    /**
     * 修改状态
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status/d', 0) == 1 ? 1 : 0;
        if (!$this->repository->merExists(0, $id))
            return app('json')->fail('数据不存在');

        $this->repository->switchStatus($id, $status);
        return app('json')->success('修改成功');
    }

    /**
     * 删除
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->merExists(0, $id))
            return app('json')->fail('数据不存在');
        if ($this->repository->hasChild($id))
            return app('json')->fail('该分类存在子集，请先处理子集');

        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 详情
     * @param $id
     * @return mixed
     * @author Qinii
     */
    public function detail($id)
    {
        if (!$this->repository->merExists(0, $id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->get($id));
    }

}
