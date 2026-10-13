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


namespace app\controller\admin\system\merchant;


use app\common\repositories\system\auth\MenuRepository;
use app\common\repositories\system\merchant\MerchantTypeRepository;
use app\validate\admin\MerchantTypeValidate;
use crmeb\basic\BaseController;
use think\App;
use think\exception\ValidateException;

/**
 * 商户类型
 */
class MerchantType extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantTypeRepository $repository)
    {
        parent::__construct($app);

        $this->repository = $repository;
    }

    /**
     *  列表
     * @return \think\response\Json
     * @author Qinii
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();

        return app('json')->success($this->repository->getList($page, $limit));
    }

    /**
     * 获取所有店铺类型
     * @return \think\response\Json
     * @author Qinii
     */
    public function options()
    {
        return app('json')->success($this->repository->getOptions());
    }

    /**
     * 创建
     * @return \think\response\Json
     * @author Qinii
     */
    public function create()
    {
        $this->repository->create($this->getValidParams());
        return app('json')->success('添加成功');
    }

    /**
     * 修改
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function update($id)
    {
        if (!$merchant_type = $this->repository->get($id)) {
            return app('json')->fail('数据不存在');
        }
        $data = $this->getValidParams();
        $this->repository->update($id, $data);

        return app('json')->success('修改成功');
    }

    /**
     * 详情
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function detail($id)
    {
        $data = $this->repository->detail($id);
        return app('json')->success($data);
    }

    /**
     * 备注表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function markForm($id)
    {
        return app('json')->success(formToData($this->repository->markForm($id)));
    }

    /**
     * 备注
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function mark($id)
    {
        $this->repository->mark($id, $this->request->params(['mark']));
        return app('json')->success('修改成功');
    }

    /**
     * 删除
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function delete($id)
    {
        if (!$this->repository->exists($id)) {
            return app('json')->fail('数据不存在');
        }
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 店铺类型 的 商户权限
     * @return \think\response\Json
     * @author Qinii
     */
    public function mer_auth()
    {
        $options = app()->make(MenuRepository::class)->getAllOptions(1);
        return app('json')->success(formatTree($options, 'menu_name'));
    }

    /**
     * 验证数据
     * @return array
     * @author Qinii
     */
    protected function getValidParams()
    {
        $data = $this->request->params(['type_name', 'type_info', 'is_margin', 'margin', 'auth', 'description', 'mark']);
        $validate = app()->make(MerchantTypeValidate::class);
        $validate->check($data);
        if ($data['is_margin'] == 1) {
            if ($data['margin'] <= 0) throw new ValidateException('保证金必须大于0');
        } else {
            $data['margin'] = 0;
        }
        return $data;
    }
}
