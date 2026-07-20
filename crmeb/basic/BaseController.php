<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
declare (strict_types=1);

namespace crmeb\basic;

use think\App;
use think\exception\ValidateException;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \app\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * @var
     */
    protected $services;

    /**
     * 需要授权的接口地址
     * @var string[]
     */
    private $authRule = [];
    /**
     * 构造方法
     * @access public
     * @param App $app 应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = app('request');
        $this->initialize();
    }

    /**
     * 初始化方法
      */
    protected function initialize()
    {
        // 子类可以重写此方法进行自定义初始化
    }

    /**
     * 获取分页参数
     * @return array [$page, $limit]
     */
    protected function getPage()
    {
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        return [(int)$page, (int)$limit];
    }

    /**
     * 数据验证
     * @param array $data
     * @param string|array|Validate $validate
     * @param array $message
     * @param bool $batch
     * @return bool
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false): bool
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            $scene = '';
            if (is_string($validate) && str_contains($validate, '.')) {
                [$validate, $scene] = explode('.', $validate, 2);
            }

            $class = str_contains((string)$validate, '\\')
                ? $validate
                : $this->app->parseClass('validate', (string)$validate);

            $v = new $class();
            if ($scene !== '') {
                $v->scene($scene);
            }
        }

        if (!empty($message)) {
            $v->message($message);
        }

        if ($batch) {
            $v->batch(true);
        }

        if (!$v->check($data)) {
            throw new ValidateException($v->getError());
        }

        return true;
    }


}
