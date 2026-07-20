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

namespace app\controller\admin\wechat;

use think\App;
use crmeb\basic\BaseController;
use app\validate\admin\TemplateMessageValidate;
use app\common\repositories\wechat\TemplateMessageRepository;
use think\exception\ValidateException;

/**
 * Class TemplateMessage
 * app\controller\admin\wechat
 *  微信模板消息 - 弃用
 */
class TemplateMessage extends BaseController
{
    /**
     * @var TemplateMessageRepository
     */
    protected $repository;

    /**
     * TemplateMessage constructor.
     * @param App $app
     * @param TemplateMessageRepository $repository
     */
    public function __construct(App $app, TemplateMessageRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 模板消息列表
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'keyword']);
        $where['type'] = 1;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 小程序模板消息列表
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function minList()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['status', 'keyword']);
        $where['type'] = 0;
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建表单
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function createForm()
    {
        return app('json')->success(formToData($this->repository->form(null, 1)));
    }


    /**
     * 创建表单
     * @param $type
     * @return mixed
     * @author Qinii
     * @day 2020-06-19
     */
    public function createMinForm()
    {
        return app('json')->success(formToData($this->repository->form(null, 0)));
    }

    /**
     * 新增数据
     * @param TemplateMessageValidate $validate
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function create(TemplateMessageValidate $validate)
    {
        $data = $this->chekcParams($validate);
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * 修改表单
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function updateForm($id)
    {
        if (!$this->repository->getWhereCount(['template_id' => $id]))
            return app('json')->fail('数据不存在');
        return app('json')->success(formToData($this->repository->updateForm($id)));
    }

    /**
     * 更新
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function update($id)
    {
        $data = $this->request->params(['tempid', 'status']);
        if (!$data['tempid'])
            return app('json')->fail('请填写模板ID');
        if (!$this->repository->getWhereCount(['template_id' => $id]))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, $data);
        return app('json')->success('编辑成功');
    }


    /**
     * 删除
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function delete($id)
    {
        if (!$this->repository->getWhereCount(['template_id' => $id]))
            return app('json')->fail('数据不存在');
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }


    /**
     * 切换状态
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-19
     */
    public function switchStatus($id)
    {
        $status = $this->request->param('status', 0) == 1 ? 1 : 0;
        if (!$this->repository->getWhereCount(['template_id' => $id]))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, ['status' => $status]);
        return app('json')->success('修改成功');
    }

    /**
     * 验证参数
     * @param TemplateMessageValidate $validate
     * @return array
     * @author Qinii
     * @day 2020-06-18
     */
    public function chekcParams(TemplateMessageValidate $validate)
    {
        $data = $this->request->params(['tempkey', 'name', 'tempid', 'status', 'content', 'type']);
        $validate->check($data);
        return $data;
    }

    /**
     * 同步模板消息
     * @return \think\response\Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/25
     */
    public function sync()
    {
        $type = $this->request->param('type');
        if ($type) {
            $msg = $this->repository->syncWechatSubscribe();
        } else {
            $msg = $this->repository->syncMinSubscribe();
        }
        return app('json')->success($msg);
    }


}
