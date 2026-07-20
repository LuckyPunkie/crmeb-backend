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


namespace app\controller\api;


use think\facade\View;
use think\facade\Cache;
use think\event\RouteLoaded;
use crmeb\basic\BaseController;

class Route extends BaseController
{
    public $type = ['admin','merchant','api','openapi','manager','pc','service'];
    public $type_name = [
        'admin'     => '平台后台',
        'merchant'  => '商户后台',
        'api'       => '用户端',
        'openapi'   => '开放接口',
        'manager'   => '员工端',
        'pc'        => 'PC端',
        'service'   => '客服端'
    ];
    public function list()
    {
        return app('json')->success('请开发者注释当前行代码，才可查看路由列表');

        $type = $this->request->param('type','');
        $type = $type == '' ? $this->type : [$type];
        foreach ($type as $route) {
            $data[$route] = $this->getRoute($route);
        }
        $table = '';
        foreach ($data as $t => $list) {
            foreach ($list as $item) {
                $table .= '<tr>';
                $table .= "<td> {$this->type_name[$t]} </td>";
                foreach ($item as $value) {
                    $table .= "<td>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</td>";
                }
                $table .= '</tr>';
            }
        }
        $html = $this->getHtml($table);
        return  view::display($html);
    }

    protected function getRoute($type)
    {
        $cache_key = $type.'_route_list';
        $res = Cache::get($cache_key);
        if ($res) return $res;
        $this->app->route->clear();
        $path = root_path(). 'route' . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR;
        $file = root_path(). 'route' . DIRECTORY_SEPARATOR . $type . '.php';
        if (is_file($file)) {
            include $file;
        }
        if (is_dir($path)) {
            $dir = scandir($path);
            foreach ($dir as $file)  {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                include $path.$file;
            }
        }
        $this->app->event->trigger(RouteLoaded::class);
        $res = $this->routeList();
        Cache::set($cache_key, $res, 60);
        return $res;
    }

    protected function reloadRoute()
    {
        $this->app->route->clear();
        $path = root_path(). 'route' . DIRECTORY_SEPARATOR;
        $dir = scandir($path);
        foreach ($dir as $file) {
            if ($file == '.' || $file == '..') {
                continue;
            }
            if (is_file($path.$file)) {
                include $path.$file;
            }
        }
        $this->app->event->trigger(RouteLoaded::class);
    }

    protected function routeList()
    {
        $routeList = $this->app->route->getRuleList();
        $rows      = [];
        foreach ($routeList as $item) {
            $item['route'] = $item['route'] instanceof \Closure ? '<Closure>' : $item['route'];
            $row = [
                $item['rule'],
                ($item['option']['prefix'] ?? '').$item['route'],
                $item['method'], $item['name'],
                $item['option']['_alias'] ?? ''
            ];
            $rows[] = $row;
        }
        $this->reloadRoute();
        return $rows;
    }

    protected function getHtml($content)
    {
        $html = <<<HTML
<!doctype html>
<html class="x-admin-sm">
    <head>
        <meta charset="UTF-8">
        <title>路由列表</title>
        <meta name="renderer" content="webkit|ie-comp|ie-stand">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
        <meta name="viewport" content="width=device-width,user-scalable=yes, minimum-scale=0.4, initial-scale=0.8,target-densitydpi=low-dpi" />
        <meta http-equiv="Cache-Control" content="no-siteapp" />
    </head>
    <body class="index">
        <table border="1" width="100%" height="200" align="left" cellpadding="10" cellspacing="0">
          <tr><th>所属端</th><th>路由</th><th>方法位置</th><th>请求方式</th><th>名称</th><th>方法注释</th></tr>
            $content
        </table>
    </body>
</html>
HTML;
        return $html;
    }
}
