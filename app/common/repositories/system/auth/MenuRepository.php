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

namespace app\common\repositories\system\auth;


//附件
use app\common\dao\BaseDao;
use app\common\dao\system\menu\MenuDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigRepository;
use crmeb\traits\SpecialConfig;
use FormBuilder\Exception\FormBuilderException;
use FormBuilder\Factory\Elm;
use FormBuilder\Form;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\facade\Db;
use think\facade\Route;
use think\Model;

/**
 * 菜单
 */
class MenuRepository extends BaseRepository
{
    use SpecialConfig;

    /**
     * MenuRepository constructor.
     * @param MenuDao $dao
     */
    protected $styles = array(
        'success' => "\033[0;32m%s\033[0m",
        'error' => "\033[31;31m%s\033[0m",
        'info' => "\033[33;33m%s\033[0m"
    );

    public $prompt = 'all';

    public function __construct(MenuDao $dao)
    {
        /**
         * @var MenuDao
         */
        $this->dao = $dao;
    }

    /**
     * 获取菜单列表
     * @param array $where
     * @param int $merId
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/23
     */
    public function getList(array $where, $merId = 0)
    {
        $query = $this->dao->search($where, $merId);
        $count = $query->count();
        $list = $query->hidden(['update_time', 'path'])->select()->toArray();
        return compact('count', 'list');
    }

    /**
     * 新增菜单
     * @param array $data
     * @return BaseDao|Model
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/23
     */
    public function create(array $data)
    {
        $data['path'] = '/';
        if ($data['pid']) {
            $data['path'] = $this->getPath($data['pid'], $data['is_mer']) . $data['pid'] . '/';
        }
        return $this->dao->create($data);
    }

    /**
     * 更新菜单
     * @param int $id
     * @param array $data
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-04-09
     */
    public function update(int $id, array $data)
    {
        $menu = $this->dao->get($id);
        if ($menu->pid != $data['pid']) {
            Db::transaction(function () use ($menu, $data) {
                $data['path'] = '/';
                if ($data['pid']) {
                    $data['path'] = $this->getPath($data['pid'], $data['is_mer']) . $data['pid'] . '/';
                }
                $this->dao->updatePath($menu->path . $menu->menu_id . '/', $data['path'] . $menu->menu_id . '/');
                $menu->save($data);
            });
        } else {
            unset($data['path']);
            $this->dao->update($id, $data);
        }
    }

    /**
     * 获取菜单树形列表
     * @param bool $is_mer
     * @return array
     * @author xaboy
     * @day 2020-04-18
     */
    public function getTree($merType = 0, $isAgent = false, $circleId = 0)
    {
        if($isAgent) {
            if($circleId) {
                $adminRule = app('request')->adminRule();
                $where['ids'] = $adminRule[$circleId];
                $options = $this->dao->getAllOptions(where:$where,isAgent:$isAgent);
                return formatTree($options, 'menu_name');
            }

            $options = $this->dao->getAllOptions(isAgent:$isAgent);
            return formatTree($options, 'menu_name');
        }

        if (!$merType) {
            $options = $this->dao->getAllOptions();
        } else {
            $options = $this->dao->merchantTypeByOptions($merType);
        }

        return formatTree($options, 'menu_name');
    }

    /**
     * 添加菜单表单
     * @param int $isMer
     * @param int|null $id
     * @param array $formData
     * @return Form
     * @throws FormBuilderException
     * @author xaboy
     * @day 2020-04-16
     */
    public function menuForm(int $isMer = 0, ?int $id = null, array $formData = [], array $params = []): Form
    {
        if($isMer == 0) {
            $action = (is_null($id) ? Route::buildUrl('systemMenuCreate')->build() : Route::buildUrl('systemMenuUpdate', ['id' => $id])->build());
        }
        if($isMer == 1) {
            $action = (is_null($id) ? Route::buildUrl('systemMerchantMenuCreate')->build() : Route::buildUrl('systemMerchantMenuUpdate', ['id' => $id])->build());
        }

        $form = Elm::createForm($action);
        $form->setRule([
            Elm::cascader('pid', '父级分类：')->options(function () use ($id, $isMer, $params) {
                $menus = $this->dao->getAllOptions($isMer, true, [], 'menu_name,pid', $params['is_agent'] ?? 0);
                if ($id && isset($menus[$id])) unset($menus[$id]);
                $menus = formatCascaderData($menus, 'menu_name');
                array_unshift($menus, ['label' => '顶级分类', 'value' => 0]);
                return $menus;
            })->placeholder('请选择分级分类')->props(['props' => ['checkStrictly' => true, 'emitPath' => false]]),
            Elm::select('is_menu', '权限类型：', 1)->options([
                ['value' => 1, 'label' => '菜单'],
                ['value' => 0, 'label' => '权限'],
            ])->control([
                [
                    'value' => 0,
                    'rule' => [
                        Elm::input('menu_name', '路由名称：')->placeholder('请输入路由名称')->required('请输入路由名称'),
                        Elm::textarea('params', '参数：')->placeholder("路由参数:\r\nkey1:value1\r\nkey2:value2"),
                    ]
                ], [
                    'value' => 1,
                    'rule' => [
                        Elm::switches('is_show', '是否显示：', 1)->inactiveValue(0)->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启'),
                        Elm::frameInput('icon', '菜单图标：', '/' . config('admin.admin_prefix') . '/setting/icons?field=icon')->icon('el-icon-circle-plus-outline')->height('338px')->width('700px')->modal(['modal' => false]),
                        Elm::input('menu_name', '菜单名称：')->placeholder('请输入菜单名称')->required('请输入菜单名称'),
                    ]
                ]
            ]),
            Elm::input('route', '路由：')->placeholder('请输入路由'),
            Elm::number('sort', '排序：', 0)->precision(0)->max(99999),
            Elm::hidden('is_agent', '组织类型')->value($params['is_agent'] ?? 0),
        ]);

        return $form->setTitle(is_null($id) ? '添加菜单' : '编辑菜单')->formData($formData);
    }


    /**
     * 更新菜单表单
     * @param int $id
     * @param int $merId
     * @return Form
     * @throws DataNotFoundException
     * @throws DbException
     * @throws FormBuilderException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020-04-16
     */
    public function updateMenuForm(int $id, $merId = 0, array $params = [])
    {
        return $this->menuForm($merId, $id, $this->dao->get($id)->toArray(), $params);
    }


    /**
     * 格式化数据
     * @param string $params
     * @return array
     * @author xaboy
     * @day 2020-04-22
     */
    public function tidyParams(?string $params)
    {
        return $params ? array_reduce(explode('|', $params), function ($initial, $val) {
            $data = explode(':', $val, 2);
            if (count($data) != 2) return $initial;
            $initial[$data[0]] = $data[1];
            return $initial;
        }, []) : [];
    }

    /**
     * 检测数据
     * @param array $params
     * @param array $routeParams
     * @return bool
     * @author xaboy
     * @day 2020-04-23
     */
    public function checkParams(array $params, array $routeParams)
    {
        foreach ($routeParams as $k => $param) {
            if (isset($params[$k]) && $params[$k] != $param)
                return false;
        }
        return true;
    }

    /**
     * 格式化路径。
     * 该方法用于处理一类特定的路径格式化任务，可以通过传入参数来指定处理的类别。
     * 默认情况下，它处理的是一般路径，但可以通过设置$is_mer参数来处理特定的商户路径。
     *
     * @param int $is_mer 标识是否为商户路径，0表示一般路径，非0表示商户路径。
     */
    public function formatPath($is_mer = 0)
    {
        // 获取所有相关路径选项，根据$is_mer参数决定是一般路径还是商户路径。
        $options = $this->getAll($is_mer);

        // 对获取的路径选项进行预处理，以'menu_id'为基准进行排序或格式化。
        $options = formatCategory($options, 'menu_id');

        // 开启数据库事务，以确保路径格式化的原子性。
        Db::transaction(function () use ($options) {
            // 遍历处理后的路径选项，调用内部方法逐个格式化路径。
            foreach ($options as $option) {
                $this->_formatPath($option);
            }
        });
    }


    /**
     * 格式化菜单项的路径。
     * 该方法递归地更新菜单项的路径，确保每个菜单项都有一个正确的路径标识。
     * 路径是基于其父项的路径和自身的ID构建的，这有助于快速定位和组织菜单结构。
     *
     * @param array $parent 父菜单项的信息，包含菜单ID和子菜单项。
     * @param string $path 父菜单项的路径，默认为根路径'/'。
     */
    protected function _formatPath($parent, $path = '/')
    {
        // 更新父菜单项的路径
        $this->dao->update($parent['menu_id'], ['path' => $path]);
        // 遍历父菜单项的子菜单项
        foreach ($parent['children'] ?? [] as $item) {
            // 构建当前子菜单项的路径
            $itemPath = $path . $item['pid'] . '/';
            // 递归调用，对子菜单项进行路径格式化
            $this->_formatPath($item, $itemPath);
        }
    }

    /**
     * 根据给定的数据生成命令行菜单。
     * 该方法主要用于处理系统（sys）和商户（mer）类型的菜单数据，通过给定的菜单标识符，从数据库中获取菜单的父ID，
     * 如果不存在，则尝试创建一个新的菜单项。
     *
     * @param string $type 菜单的类型，决定是系统菜单还是商户菜单。
     * @param array $data 菜单数据，包含需要处理的菜单标识符及其对应显示名称。
     * @param string $prompt命令行提示信息，用于优化用户交互体验。
     * @return int 返回处理的菜单项数量。
     */
    public function commandMenu($type, $data, $prompt)
    {
        $res = [];
        // 根据type设置是否为菜单的标志
        // 平台
        $isMer = 0;
        $isAgent = 0;
        // 店铺
        if($type == 'mer') {
            $isMer = 1;
        }
        // 区域
        if($type == 'region') {
            $isAgent = 1;
        }
        // 商户
        if ($type == 'business') {
            $isAgent = 2;
        }

        foreach ($data as $key => $value) {
            try {
                // 尝试根据$key获取菜单的父ID，如果失败则处理特别情况
                $result = $this->dao->getMenuPid($key, $isMer, 0, $isAgent);
                if (!$result) {
                    // 处理新增菜单项的特殊情况，包括附加权限和自定义路由
                    $route = $key;
                    $isAppend = 0;
                    if (substr($key, 0, 7) === 'append_') {
                        $isAppend = 1;
                        $route = substr($key, 7);
                    }
                    // 再次尝试获取菜单的父ID，如果仍然失败且$key不是'self'，则记录未找到菜单的信息并跳过当前循环
                    $result = $this->dao->getMenuPid($route, $isMer, 1, $isAgent);
                    if (!$result && $key !== 'self') {
                        printf($this->styles['info'], '未找到菜单: ' . $key);
                        echo PHP_EOL;
                        continue;
                    } else {
                        // 创建新的菜单项
                        $result = $this->dao->create([
                            'pid' => $key == 'self' ? 0 : $result['menu_id'],
                            'path' => $key == 'self' ? '/' : $result['path'] . $result['menu_id'] . '/',
                            'menu_name' => $isAppend ? '附加权限' : '权限',
                            'route' => $key,
                            'is_mer' => $isMer,
                            'is_menu' => 0,
                            'is_agent' => $isAgent
                        ]);
                    }
                }
                // 将处理后的菜单项数据合并到结果集中
                $res = array_merge($res, $this->createSlit($isMer, $result['menu_id'], $result['path'], $value, $isAgent));
            } catch (\Exception $exception) {
                // 抛出异常，处理菜单创建过程中的错误
                throw new Exception($key);
            }
        }
        // 插入处理后的菜单项数据到数据库，并返回处理的菜单项数量
        if (!empty($res)) $this->dao->insertAll($res);
        return count($res);
    }

    /**
     * 新增权限数据整理
     * @param int $isMer
     * @param int $menuId
     * @param string $path
     * @param array $data
     * @return array
     * @author Qinii
     * @day 3/18/22
     */
    public function createSlit(int $isMer, int $menuId, string $path, array $data, int $isAgent)
    {
        $arr = [];
        try {
            foreach ($data as $k => $v) {
                $result = $this->dao->getWhere(['route' => $v['route'], 'pid' => $menuId]);
                if (!$result) {
                    $arr[] = [
                        'pid' => $menuId,
                        'path' => $path . $menuId . '/',
                        'menu_name' => $v['menu_name'],
                        'route' => $v['route'],
                        'is_mer' => $isMer,
                        'is_menu' => 0,
                        'params' => $v['params'] ?? [],
                        'is_agent' => $isAgent,
                    ];
                    if ($this->prompt == 's') {
                        printf($this->styles['success'], '新增权限: ' . $v['menu_name'] . ' [' . $v['route'] . ']');
                        echo PHP_EOL;
                    }
                }
            }
            return $arr;
        } catch (\Exception $exception) {
            halt($isMer, $menuId, $path, $data, $isAgent);
        }
    }

    /**
     * 快捷搜索
     * @param int $isMer 是否商户
     * @param string $keyWord 关键词
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * FerryZhao 2024/4/3
     */
    public function getMenusList($isMer, $keyWord)
    {
        $pre = '/' . config('admin.' . ($isMer ? 'merchant' : 'admin') . '_prefix');
        $configRepository = app()->make(ConfigRepository::class);
        $configData = $configRepository->getSearch([])
            ->where('user_type', $isMer)
            ->whereLike('config_name', '%' . $keyWord . '%')
            ->field('config_id,config_name,config_classify_id')
            ->with(['classConfig.parent'])->select()->toArray();
        $configMenus = [];
        $configMenusPath = [];
        $param = [];
        if ($configData) {
            foreach ($configData as $configDatum) {
                if ($_p = $this->valSpecial($isMer, $configDatum['config_name'])) {
                    $configMenus[$_p] = [
                        'title' => $configDatum['config_name'],
                        'path' => $_p,
                    ];
                } else if ($classConfig = $configDatum['classConfig']) {
                    $classConfigId = !empty($classConfig['parent']) ? $classConfig['parent']['config_classify_id'] : $classConfig['config_classify_id'];
                    $classifyKey = !empty($classConfig['parent']) ? $classConfig['parent']['classify_key'] : $classConfig['classify_key'];
                    $title = !empty($classConfig['parent']) ? $classConfig['parent']['classify_name'] : $classConfig['classify_name'];
                    $path = '/systemForm/Basics/' . $classifyKey;
                    $configMenus[$classConfigId] = [
                        'title' => $title,
                        'path' => $path,
                    ];
                    $param[$path] = '/' . $classConfig['config_classify_id'];
                }
            }
            $configMenus = array_values($configMenus);
            $configMenusPath = array_column($configMenus, 'path');
        }
        $menuData = $this->dao->getSearch([])
            ->where('is_mer', $isMer)
            ->where('is_menu', 1)
            ->where('is_show', 1)
            ->where(function ($query) use ($keyWord, $configMenusPath) {
                $query->whereLike('menu_name', '%' . $keyWord . '%')->whereOr('route', 'in', $configMenusPath);
            })->select()->append(['parents', 'child'])->toArray();
        $menus = [];
        foreach ($menuData as $datum) {
            if (in_array($datum['route'], $this->unsetConfigArray)) {
                continue;
            }
            if (!empty($datum['child'])) {
                continue;
            }
            $title = '';
            if ($parents = $datum['parents']) {
                $path = explode('/', trim($datum['path'], '/'));
                $parents = array_combine(array_column($parents, 'pid'), $parents);
                $menu = $parents[0]['menu_name'] ?? '';
                foreach ($path as $item) {
                    if (isset($parents[$item])) {
                        $menu .= ' - ' . $parents[$item]['menu_name'];
                    }
                }
                $menu .= ' - ' . $datum['menu_name'];
                $title = trim($menu, '-');
            }
            $menus[] = [
                'path' => $pre . $datum['route'] . ($param[$datum['route']] ?? ''),
                'title' => $title ?: $datum['menu_name'],
            ];
        }
        return compact('menus');
    }
}

