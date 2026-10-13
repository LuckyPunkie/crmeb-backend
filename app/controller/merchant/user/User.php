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


namespace app\controller\merchant\user;

use crmeb\basic\BaseController;
use app\common\repositories\user\UserRepository;
use think\App;

class User extends BaseController
{
    protected $repository;

    /**
     * 构造函数
     *
     * @param App $app 应用程序实例
     * @param UserRepository $repository 用户仓库实例
     */
    public function __construct(App $app, UserRepository $repository)
    {
        // 调用父类构造函数
        parent::__construct($app);
        // 设置用户仓库实例
        $this->repository = $repository;
    }

    /**
     * 获取用户列表
     *
     * @return mixed
     */
    public function getUserList()
    {
        // 获取关键字参数
        $keyword = $this->request->param('keyword', '');
        // 如果没有关键字则返回错误信息
        if (!$keyword)
            return app('json')->fail('请输入关键字');
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 调用用户仓库的查询方法并返回结果
        return app('json')->success($this->repository->merList($keyword, $page, $limit));
    }

}
