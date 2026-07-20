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


use app\common\repositories\store\ExcelRepository;
use app\common\repositories\user\UserInfoRepository;
use crmeb\basic\BaseController;
use app\common\repositories\user\UserBillRepository;
use crmeb\services\ExcelService;
use think\App;

/**
 * Class UserInfo
 * app\controller\admin\user
 * 用户扩展字段设置
 */
class UserInfo extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserInfoRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     *  列表
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/9/24
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['date']);
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }


    /**
     *  添加属性
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/9/24
     */
    public function create()
    {
        $params = $this->request->params(['field', 'title', 'is_used', 'is_require', 'is_show', 'type', 'msg', ['content', []], 'sort']);
        if (!$params['field']) return app('json')->fail('请输入字段');
        $params['field'] = strtolower($params['field']);
        $hasField = $this->repository->getSearch(['field' => $params['field']])->count();
        if ($hasField) return app('json')->fail('该字段名已存在');
        $this->repository->create($params);
        return app('json')->success('创建成功');
    }

    /**
     *  添加属性
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/9/24
     */
    public function createFrom()
    {
        return app('json')->success(formToData($this->repository->createFrom()));
    }

    /**
     *  保存
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/9/24
     */
    public function saveAll()
    {
        $data = $this->request->params([['avatar', ''], ['user_extend_info', []]]);
        $this->repository->saveAll($data);
        return app('json')->success('保存成功');
    }


    /**
     * 删除属性
     * @param $id
     * @return \think\response\Json
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }

    /**
     *  获取属性的类型
     * @return \think\response\Json
     * @author Qinii
     * @day 2023/9/24'
     */
    public function getType()
    {
        $data = $this->repository->getType();
        return app('json')->success($data);
    }

    /**
     * 下啦筛选
     * @return \think\response\Json
     * @author Qinii
     */
    public function getSelectList()
    {
        return app('json')->success($this->repository->getSelectList());
    }

}
