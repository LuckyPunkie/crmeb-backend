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


namespace app\common\dao\system\menu;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\auth\Menu;
use app\common\repositories\system\RelevanceRepository;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * Class MenuDao
 * @package app\common\dao\system\menu
 * @author xaboy
 * @day 2020-04-08
 */
class MenuDao extends BaseDao
{
    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return Menu::class;
    }

    /**
     * 根据条件搜索菜单项
     *
     * 本函数用于查询菜单数据库表中的记录，根据传入的条件进行过滤，并返回查询结果。查询条件包括：
     * - $is_mer：指定菜单是否为商家菜单，0 表示普通菜单，非0表示商家菜单。
     * - $where：一个关联数组，包含可选的搜索条件。可能的条件有：
     *   - pid：父菜单ID，用于查询指定父菜单下的子菜单。
     *   - keyword：关键词，用于查询菜单名称或路由中包含该关键词的菜单。
     *   - is_menu：菜单状态，用于查询指定状态的菜单。
     *
     * @param array $where 搜索条件数组，包含可选的 pid、keyword 和 is_menu 字段。
     * @param int $is_mer 菜单类型标志，0 表示普通菜单，非0表示商家菜单。
     * @return \think\db\Query 返回一个查询对象，该对象可用于进一步的查询操作或获取查询结果。
     */
    public function search(array $where, int $is_mer = 0)
    {
        // 初始化查询对象，指定查询条件为 'is_mer' 字段等于 $is_mer，并按 'sort' 字段降序，'menu_id' 字段升序排序
        $query = Menu::getDB()->where('is_mer', $is_mer)->order('sort DESC,menu_id ASC');

        // 如果 $where 数组中包含 'pid' 字段，添加查询条件 'pid' 等于 $where['pid']
        if (isset($where['pid'])) $query->where('pid', (int)$where['pid']);

        // 如果 $where 数组中包含 'keyword' 字段，添加查询条件，使得 'menu_name' 或 'route' 字段包含 $where['keyword']
        if (isset($where['keyword'])) $query->whereLike('menu_name|route', "%{$where['keyword']}%");

        // 如果 $where 数组中包含 'is_menu' 字段，添加查询条件 'is_menu' 等于 $where['is_menu']
        if (isset($where['is_menu'])) $query->where('is_menu', (int)$where['is_menu']);

        // 如果 $where 数组中包含 'is_agent' 字段，添加查询条件 'is_agent' 等于 $where['is_agent']
        if (isset($where['is_agent'])) $query->where('is_agent', (int)$where['is_agent']);

        // 返回查询对象
        return $query;
    }

    /**
     * 获取所有菜单项
     *
     * 本函数用于从数据库中检索所有菜单项。它可以区分商户菜单和系统菜单，
     * 默认返回所有系统菜单。通过传入参数，可以切换返回商户菜单。
     *
     * @param int $is_mer 是否为商户菜单的标志，0表示系统菜单，非0表示商户菜单。
     * @return array 返回一个包含所有菜单项的数组，每个菜单项是一个关联数组。
     */
    public function getAllMenu($is_mer = 0)
    {
        // 根据$is_mer参数值，构造查询条件，查询并返回所有符合条件的菜单项
        return Menu::getDB()->where('is_mer', $is_mer)->where('is_menu', 1)->order('sort DESC,menu_id ASC')->select()->toArray();
    }

    /**
     * 获取所有菜单项
     *
     * 本函数用于从数据库中检索所有菜单项。它可以区分商户菜单和系统菜单，
     * 默认返回所有系统菜单。通过传入参数，可以切换返回商户菜单。
     *
     * @param int $is_mer 菜单类型标志，0代表系统菜单，非0代表商户菜单。
     * @return array 返回一个包含所有菜单项的数组，每个菜单项是一个关联数组。
     */
    public function getAll($is_mer = 0)
    {
        // 使用Menu类的单例模式获取菜单实例
        $menuInstance = Menu::getInstance();
        // 根据$is_mer参数确定查询条件，排序方式为先按排序降序，再按菜单ID升序
        return $menuInstance->where('is_mer', $is_mer)
                            ->order('sort DESC,menu_id ASC')
                            ->select()
                            ->toArray();
    }

    /**
     * 检查菜单是否存在
     *
     * 本函数用于查询数据库中是否存在指定ID且状态为菜单的记录。
     * 参数$id用于指定菜单的唯一标识符。$is_mer参数用于指定是否是商家菜单，默认为0。
     * 返回值为布尔类型，存在则返回true，否则返回false。
     *
     * @param int $id 菜单的唯一标识符
     * @param int $is_mer 是否是商家菜单的标识，默认为0，表示系统菜单
     * @return bool 菜单是否存在
     */
    public function menuExists(int $id, $is_mer = 0)
    {
        // 通过菜单模型获取数据库操作对象，然后使用where方法构建查询条件，查询指定ID、状态为菜单且商家菜单标识为$is_mer的记录数量
        // 如果查询结果的数量大于0，则表示存在满足条件的菜单，返回true；否则，返回false
        return Menu::getDB()->where($this->getPk(), $id)->where('is_menu', 1)->where('is_mer', $is_mer)->count() > 0;
    }

    /**
     * 检查是否存在特定ID和商戶狀態的記錄
     *
     * 本函数用于查询数据库中是否存在指定ID且is_mer字段符合指定值的记录。
     * 主要用于菜单管理中，确定某个菜单项是否由特定商戶拥有。
     *
     * @param int $id 要查询的记录的ID
     * @param int $is_mer 商戶標誌，用于区分记录是否属于商戶。默认为0，表示普通记录。
     * @return bool 如果存在符合条件的记录，则返回true，否则返回false。
     */
    public function merExists(int $id, $is_mer = 0)
    {
        // 通过Menu类的getDB方法获取数据库操作对象，并构造查询条件，查询指定ID和商戶狀態的记录数量
        return Menu::getDB()->where($this->getPk(), $id)->where('is_mer', $is_mer)->count() > 0;
    }

    /**
     * 检查指定ID是否存在对应的权限记录。
     *
     * 该方法用于验证给定的ID是否在权限管理系统中存在有效的权限记录。
     * 它通过查询数据库中是否存在满足特定条件的记录来实现这一验证。
     * 特别地，它考虑了权限是否属于商家（is_mer参数）的维度来进行筛选。
     *
     * @param int $id 要验证的权限ID。
     * @param int $is_mer 是否为商家权限的标志，默认为0（表示非商家权限）。
     * @return bool 如果存在满足条件的权限记录，则返回true；否则返回false。
     */
    public function authExists(int $id, $is_mer = 0)
    {
        // 使用Menu类的getDB方法获取数据库操作对象，并构造查询条件
        // 查询条件包括：主键ID等于指定ID，is_menu字段等于0（表示这是一个菜单项而非操作项），以及is_mer字段等于传入的is_mer参数值
        // 最后，通过count方法统计满足条件的记录数，并检查是否大于0来确定权限是否存在
        return Menu::getDB()->where($this->getPk(), $id)->where('is_menu', 0)->where('is_mer', $is_mer)->count() > 0;
    }

    /**
     * 检查给定的路由是否存在于数据库中。
     *
     * 本函数用于确定一个特定的路由是否在系统的菜单路由数据库中存在。
     * 它通过查询数据库中是否存在对应路由记录来实现这一功能。
     * 特别地，它还可以根据是否是商户路由（$is_mer 参数）来进一步筛选。
     *
     * @param string $route 要检查的路由字符串。
     * @param int $is_mer 标识是否是商户路由，默认为0（非商户路由）。
     *                    这允许函数对普通路由和商户路由进行区分查询。
     * @return bool 如果路由存在则返回true，否则返回false。
     */
    public function routeExists(string $route, $is_mer = 0)
    {
        // 通过数据库查询确定给定路由是否存在，并且满足is_menu为0和is_mer为指定值的条件
        // 这里使用了链式调用来构建查询条件，最后通过count方法检查满足条件的记录数量是否大于0
        return Menu::getDB()->where('route', $route)->where('is_menu', 0)->where('is_mer', $is_mer)->count() > 0;
    }

    /**
     * 获取所有菜单选项
     *
     * 本函数用于从数据库中查询并返回所有设置为菜单项的选项。这些选项可能是针对所有用户的，
     * 或者是特定于商户的，这取决于参数$is_mer的值。
     *
     * @param int $is_mer 是否为商户菜单的标志，0表示所有用户可见的菜单，1表示仅商户可见的菜单。
     * @return array 返回一个数组，其中每个元素包含菜单的名称和父级ID，索引为菜单的ID。
     */
    public function getAllMenuOptions($is_mer = 0)
    {
        // 通过Menu类的静态方法getDB获取数据库操作对象
        // 然后通过链式调用where方法设置查询条件，order方法设置排序方式
        // 最后使用column方法查询并返回指定列的数据
        return Menu::getDB()->where('is_menu', 1)->where('is_mer', $is_mer)->order('sort DESC,menu_id ASC')->column('menu_name,pid', 'menu_id');
    }

    /**
     * 根据权限规则列表获取菜单规则
     * 该方法用于根据传入的权限规则数组，筛选出对应的菜单规则。主要用于权限管理中，根据用户的权限规则，
     * 获取用户可以访问的菜单列表。is_mer 参数用于区分是否是商家后台菜单。
     *
     * @param array $rule 权限规则数组，包含有效的菜单 ID
     * @param int $is_mer 是否是商家后台菜单的标志，0 表示系统后台，1 表示商家后台, 2 表示组织区域/商户
     * @return array 返回符合权限规则的菜单规则列表，包括菜单名称、路由、参数、图标、父级 ID 和菜单 ID
     */
    public function ruleByMenuList(array $rule, $is_mer = 0)
    {
        // 根据权限规则查询菜单路径
        $paths = Menu::getDB()->whereIn($this->getPk(), $rule)->column('path', 'menu_id');

        // 用于存储所有相关菜单ID和其路径中的ID
        $ids = [];
        foreach ($paths as $id => $path) {
            // 将路径中的ID和当前菜单ID合并到$ids数组中
            $ids = array_merge($ids, explode('/', trim($path, '/')));
            array_push($ids, $id);
        }

        // 根据菜单的is_menu、is_show字段筛选出显示的菜单项，并按排序和ID升序排列
        // 过滤并去重$ids后，查询对应的菜单名称、路由、参数、图标、父级ID和菜单ID
        return Menu::getDB()->where('is_menu', 1)->where('is_show', 1)->order('sort DESC,menu_id ASC')->where('is_mer', $is_mer)
            ->whereIn('menu_id', array_unique(array_filter($ids)))
            ->column('menu_name title,route path,params,icon,pid,menu_id id');
    }

    public function getRoutesByIsMer($is_mer)
    {
        // 根据权限规则查询菜单路径
        $paths = Menu::getDB()->column('path', 'menu_id');

        // 用于存储所有相关菜单ID和其路径中的ID
        $ids = [];
        foreach ($paths as $id => $path) {
            // 将路径中的ID和当前菜单ID合并到$ids数组中
            $ids = array_merge($ids, explode('/', trim($path, '/')));
            array_push($ids, $id);
        }

        // 根据菜单的is_menu、is_show字段筛选出显示的菜单项，并按排序和ID升序排列
        // 过滤并去重$ids后，查询对应的菜单名称、路由、参数、图标、父级ID和菜单ID
        return Menu::getDB()->where('is_menu', 1)->where('is_show', 1)->order('sort DESC,menu_id ASC')->where('is_mer', $is_mer)
            ->whereIn('menu_id', array_unique(array_filter($ids)))
            ->column('menu_name title,route path,params,icon,pid,menu_id id');
    }

    /**
     * 获取有效的菜单列表
     *
     * 本函数用于查询并返回系统中定义的有效菜单的列表。有效菜单是指那些被标记为可显示在菜单栏中、
     * 并且状态为启用的菜单项。查询结果按照排序值降序、菜单ID升序的方式进行排序。
     *
     * @param int $is_mer 是否为商户菜单的标志，默认为0（表示系统菜单）。当设置为1时，查询商户菜单。
     * @return array 返回一个包含有效菜单名称、路由、参数、图标、父ID和菜单ID的数组。
     */
    public function getValidMenuList($is_mer = 0, $is_agent = 0)
    {
        // 通过Menu类的getDB方法获取数据库对象，并构造查询条件
        // 查询条件包括：is_menu为1（表示是菜单项）、is_show为1（表示显示）、按照sort降序和menu_id升序排序
        // 最后，指定返回结果的字段和格式
        return Menu::getDB()->where('is_menu', 1)->where('is_show', 1)
            ->where('is_agent', $is_agent)
            ->order('sort DESC,menu_id ASC')->where('is_mer', $is_mer)
            ->column('menu_name title,route path,params,icon,pid,menu_id id');
    }

    /**
     * 根据有效的菜单列表和类型ID，获取特定类型下的菜单路径。
     * 此方法通过查询数据库，结合左右关联数据，构造出指定类型下的菜单路径列表。
     * 主要用于在商户授权中，根据类型ID获取对应的可展示菜单路径。
     *
     * @param int $typeId 类型ID，用于查询关联的菜单路径。
     * @return array 返回一个包含菜单名称、路由、参数、图标、父ID和菜单ID的数组。
     */
    public function typesByValidMenuList($typeId)
    {
        // 根据类型ID查询关联的菜单路径，只包括显示状态为1的菜单，按排序降序、菜单ID升序排列。
        $paths = Menu::getDB()->alias('A')->leftJoin('Relevance B', 'A.menu_id = B.right_id')
            ->where('is_show', 1)
            ->order('sort DESC,menu_id ASC')
            ->where('B.left_id', $typeId)
            ->where('B.type', RelevanceRepository::TYPE_MERCHANT_AUTH)
            ->column('path', 'menu_id');

        // 初始化一个空数组，用于存储菜单ID。
        $ids = [];
        // 遍历查询结果，将路径中的菜单ID和当前菜单ID合并到$ids数组中。
        foreach ($paths as $id => $path) {
            $ids = array_merge($ids, explode('/', trim($path, '/')));
            array_push($ids, $id);
        }

        // 根据$ids数组中的菜单ID查询菜单详情，只包括菜单状态为1，显示状态为1，且为商户菜单的项。
        // 排序方式与之前查询一致，返回结果包括菜单名称、路由、参数、图标、父ID和菜单ID。
        return Menu::getDB()->where('is_menu', 1)->where('is_show', 1)->order('sort DESC,menu_id ASC')->where('is_mer', 1)
            ->whereIn('menu_id', array_unique(array_filter($ids)))
            ->column('menu_name title,route path,params,icon,pid,menu_id id');
    }

    /**
     * 获取所有选项
     * 该方法用于查询菜单表中的所有选项，根据传入的参数进行过滤和排序。
     * 主要用于构建下拉列表或其他需要菜单选项的场景。
     *
     * @param int $is_mer 是否为商户端菜单。0表示管理员菜单，1表示商户端菜单。
     * @param bool $all 是否返回所有菜单。true表示返回所有菜单，包括未设置为显示的和非菜单项；false表示只返回设置为显示且为菜单项的菜单。
     * @param array $where 查询条件数组。可以包含任何有效的SQL查询条件。
     * @param string $filed 要返回的字段，以逗号分隔。默认为'menu_name,pid'，表示返回菜单名称和父ID。
     * @return array 返回一个数组，数组的键为menu_id，值为指定字段的值。
     */
    public function getAllOptions($is_mer = 0, $all = false, $where = [], $filed = 'menu_name,pid', int $isAgent = 0)
    {
        // 使用菜单模型的数据库操作对象
        return Menu::getDB()->where('is_mer', $is_mer == 2 ? $is_mer : ($is_mer ? 1 : 0))
            // 如果$where数组中包含'ids'键，并且其值不为空，那么查询菜单ID在给定数组中的菜单
            ->when(isset($where['ids']) && !empty($where['ids']), function($query) use($where) {
                $query->whereIn('menu_id', $where['ids']);
            })
            ->where('is_agent', $isAgent)
            // 如果$all参数为false，那么查询显示且为菜单项，或者非菜单项的菜单
            ->when(!$all, function ($query) {
                $query->where(function ($query) {
                    $query->where(function ($query) {
                        $query->where('is_show', 1)->where('is_menu', 1);
                    })->whereOr('is_menu', 0);
                });
            })
            // 按照排序降序，菜单ID升序排序
            ->order('sort DESC,menu_id ASC')->column($filed, 'menu_id');
    }


    /**
     * 根据选项获取商家类型
     * 该方法用于查询特定类型ID关联的商家类型菜单名称和父ID。可选地，可以查询所有商家类型或仅查询显示在菜单中的。
     *
     * @param int $typeId 类型ID，用于查询与该类型相关的商家类型。
     * @param bool $all 是否查询所有商家类型，如果为false，则只查询显示在菜单中的商家类型。
     * @return array 返回一个数组，其中包含商家类型的菜单名称和父ID。
     */
    public function merchantTypeByOptions($typeId, $all = false)
    {
        // 从菜单数据库中查询，并给表起别名
        return Menu::getDB()->alias('A')
            // 左连接关联表B，根据菜单ID和关联ID匹配
            ->leftJoin('Relevance B', 'A.menu_id = B.right_id')
            // 限制只查询商家类型
            ->where('is_mer', 1)
            // 限制只查询与给定类型ID相关的记录
            ->where('B.left_id', $typeId)
            // 限制只查询商家授权类型的关联记录
            ->where('B.type', RelevanceRepository::TYPE_MERCHANT_AUTH)
            // 如果不查询所有类型，添加额外的查询条件
            ->when(!$all, function ($query) {
                // 仅查询显示在菜单中或不作为菜单显示的商家类型
                $query->where(function ($query) {
                    $query->where(function ($query) {
                        // 查询显示在菜单中且标记为菜单的商家类型
                        $query->where('is_show', 1)->where('is_menu', 1);
                    })->whereOr('is_menu', 0); // 或者查询不作为菜单显示的商家类型
                });
            })
            // 按排序降序，菜单ID升序排序
            ->order('sort DESC,menu_id ASC')
            // 返回菜单名称和父ID的列
            ->column('menu_name,pid', 'menu_id');
    }

    /**
     * 根据ID和类型获取菜单路径
     *
     * 本函数用于从数据库中检索指定菜单ID和类型对应的路径。
     * 参数$id表示菜单的唯一标识符，$is_mer表示菜单类型，默认为0。
     * 返回值为对应菜单项的路径字符串。
     *
     * @param int $id 菜单的唯一标识符
     * @param int $is_mer 菜单的类型标志，默认为0，表示普通菜单
     * @return string 返回对应菜单项的路径
     */
    public function getPath($id, $is_mer = 0)
    {
        // 通过Menu类的静态方法getDB获取数据库实例，然后使用where方法指定查询条件，最后使用value方法获取'path'列的值
        return Menu::getDB()->where('is_mer', $is_mer)->where('menu_id', $id)->value('path');
    }

    /**
     * 根据ID获取菜单项
     *
     * 本函数用于从数据库中检索指定ID的菜单项。它允许区分商家菜单和普通菜单，
     * 通过可选参数$is_mer进行区分。此方法封装了数据库查询逻辑，使调用者能够
     * 简单地获取菜单对象。
     *
     * @param int $id 菜单项的唯一标识ID
     * @param int $is_mer 一个标志，用于区分商家菜单（1表示商家菜单，0表示普通菜单，默认为0）
     * @return array|false 返回符合条件的菜单项数据，如果找不到则返回false。
     */
    public function getMenu(int $id, $is_mer = 0)
    {
        // 使用Menu类的静态方法getDB来获取数据库操作对象，然后通过链式调用where方法设置查询条件，
        // 最后调用find方法来执行查询并返回结果。
        return Menu::getDB()->where('is_mer', $is_mer)->where('is_menu', 1)->where($this->getPk(), $id)->find();
    }

    /**
     * 根据ID获取授权信息
     *
     * 本函数用于查询特定ID对应的授权信息。它通过指定的ID，在数据库中检索满足条件的记录。
     * 主要用于在系统中进行权限验证或获取权限详情。
     *
     * @param int $id 需要查询的记录的ID。这是主键字段的值，用于唯一标识一条记录。
     * @param int $is_mer 商户标识。默认为0，表示查询所有商户的记录。如果设置为1，则只查询商户相关的记录。
     *                   这个参数用于区分记录是属于系统还是特定商户的，从而进行更精确的查询。
     * @return array 返回查询结果。如果找到符合条件的记录，则返回该记录的数组形式；如果未找到，则返回空数组。
     */
    public function getAuth(int $id, $is_mer = 0)
    {
        // 使用Menu类的getDB方法获取数据库操作对象，然后通过where方法构建查询条件，
        // 最后调用find方法执行查询并返回结果。
        return Menu::getDB()->where('is_mer', $is_mer)->where('is_menu', 0)->where($this->getPk(), $id)->find();
    }

    /**
     * 删除菜单项
     *
     * 本函数用于根据给定的ID删除菜单项。它可以区分是否是商家菜单项，通过$is_mer参数进行标识。
     * 当$is_mer设置为1时，表示删除商家菜单项；否则，删除普通菜单项。
     *
     * @param int $id 要删除的菜单项的ID
     * @param int $is_mer 标识是否是商家菜单项，0表示普通菜单项，1表示商家菜单项，默认为0
     * @return bool 删除操作的结果，成功返回true，失败返回false
     */
    public function delete(int $id, $is_mer = 0)
    {
        // 根据$is_mer的值，构造查询条件，然后执行删除操作
        return Menu::getDB()->where('is_mer', $is_mer)->delete($id);
    }

    /**
     * 检查给定的进程ID是否存在于系统中。
     *
     * 本函数通过调用fieldExists方法来检查指定的进程ID（PID）是否存在于某个数据结构中，
     * 通常用于确认一个进程是否在运行。这在管理系统进程或者进行进程间通信时非常有用。
     *
     * @param int $id 要检查的进程ID。
     * @return bool 如果指定的PID存在，则返回true；否则返回false。
     */
    public function pidExists(int $id)
    {
        // 调用fieldExists方法来检查PID是否存在
        return $this->fieldExists('pid', $id);
    }

    /**
     * 根据路由ID数组获取对应的参数和路由信息。
     *
     * 此方法用于查询数据库中，is_menu字段为0的记录，且主键（PK）存在于给定的ID数组中，
     * 返回这些记录的params和route字段。这种方法通常用于在菜单系统中，根据ID数组批量获取菜单项的参数和路由信息。
     *
     * @param array $ids 路由ID的数组。
     * @return array 返回一个包含params和route字段的数组，这些字段对应于查询到的记录。
     */
    public function idsByRoutes(array $ids)
    {
        // 使用Menu类的getDB方法获取数据库对象，然后通过where和whereIn方法构建查询条件，最后使用column方法获取指定字段的列数据。
        return Menu::getDB()->where('is_menu', 0)->whereIn($this->getPk(), $ids)->column('params,route');
    }


    /**
     * 根据类型ID和ID数组，获取路由类型的参数和路由信息。
     *
     * 本函数通过查询数据库，获取特定类型ID和ID数组相关的路由类型参数和路由信息。
     * 其中，类型ID用于指定左侧关联ID，ID数组用于指定右侧关联ID的集合。
     * 查询条件包括：is_menu为0，B.left_id与传入的类型ID匹配，B.right_id在传入的ID数组中，
     * 并且B.type为指定的关联类型常量。
     * 返回的结果包含params和route两列数据，用于后续处理或展示。
     *
     * @param int $typeId 左侧关联ID，用于指定查询的类型。
     * @param array $ids 右侧关联ID数组，用于指定查询的具体ID集合。
     * @return array 返回包含params和route信息的数组集合。
     */
    public function typesByRoutes($typeId, array $ids)
    {
        // 使用别名A查询Menu表，并通过left join与Relevance表（别名B）关联。
        // 查询条件包括：is_menu为0，B.left_id等于$typeId，B.right_id在$ids数组中，
        // 并且B.type为RelevanceRepository::TYPE_MERCHANT_AUTH所指定的类型。
        // 最终返回params和route两列数据。
        return Menu::getDB()->alias('A')->leftJoin('Relevance B', 'A.menu_id = B.right_id')->where('is_menu', 0)
            ->where('B.left_id', $typeId)->whereIn('B.right_id', $ids)->where('B.type', RelevanceRepository::TYPE_MERCHANT_AUTH)->column('params,route');
    }


    /**
     * 根据类型ID获取商戶类型的路由信息
     *
     * 本函数通过查询数据库，获取特定类型ID对应的商戶类型路由信息。
     * 具体来说，它联接了菜单表和关联表，筛选出作为菜单项、与给定类型ID关联、
     * 且类型为商戶授权的数据，最终返回这些数据的路由和参数信息。
     *
     * @param int $typeId 类型ID，用于查询与之关联的商戶类型路由信息。
     * @return array 返回一个包含路由和参数信息的数组，每个元素都是一个子数组。
     */
    public function merchantTypeByRoutes($typeId)
    {
        // 使用Menu模型的数据库实例，设置别名为A
        return Menu::getDB()->alias('A')
            // 左连接Relevance表，别名为B，关联条件为菜单ID等于关联表的右ID
            ->leftJoin('Relevance B', 'A.menu_id = B.right_id')
            // 筛选菜单项中is_menu为0（表示不是菜单）的记录
            ->where('is_menu', 0)
            // 筛选关联表中左ID为$typeId的记录
            ->where('B.left_id', $typeId)
            // 筛选关联表中类型为商戶授权的记录
            ->where('B.type', RelevanceRepository::TYPE_MERCHANT_AUTH)
            // 查询并返回params和route两列的数据
            ->column('params,menu_name,route');
    }

    /**
     * 获取管理员菜单路由信息
     *
     * 本函数用于查询数据库中特定条件下的菜单路由信息。具体来说，它查询那些
     * is_menu 字段值为 0（表示不是菜单项）且 is_show 字段值为 1（表示显示）的记录，
     * 并返回这些记录的 params 和 route 字段的值。这个函数的目的是为了在管理
     * 员界面中构建菜单结构，只包含需要显示且不是独立菜单项的路由信息。
     *
     * @return array 返回一个包含路由信息的数组，每个元素包含 params 和 route 两个字段。
     */
    public function merAdminRoutes()
    {
        // 使用 Menu 类的静态方法 getDB 来获取数据库操作对象
        // 然后通过 where 方法指定查询条件，查询 is_menu 为 0 且 is_show 为 1 的记录
        // 最后使用 column 方法来获取这些记录的 params 和 route 字段的值，返回结果作为数组
        return Menu::getDB()->where('is_menu', 0)->where('is_show', 1)->column('params,route');
    }

    /**
     * 更新菜单路径
     *
     * 当系统中的某个路径发生变化时，需要更新所有依赖于该路径的菜单项的路径。
     * 此方法通过查找以旧路径开头的菜单项，并将它们的路径替换为新路径，来实现路径的更新。
     *
     * @param string $oldPath 旧的路径
     * @param string $path 新的路径
     */
    public function updatePath(string $oldPath, string $path)
    {
        // 从数据库中查询所有路径以旧路径开头的菜单项
        Menu::getDB()->whereLike('path', $oldPath . '%')->field('menu_id,path')->select()->each(function ($val) use ($oldPath, $path) {
            // 替换菜单项的路径中的旧路径为新路径
            $newPath = str_replace($oldPath, $path, $val['path']);
            // 更新菜单项的路径
            Menu::getDB()->where('menu_id', $val['menu_id'])->update(['path' => $newPath]);
        });
    }

    /**
     * 检查指定字段的值是否存在于数据库中。
     *
     * 本函数通过查询数据库来确定给定字段的值是否已存在。它首先从当前类中获取模型对象，
     * 然后使用该模型对象来连接数据库，并构造一个查询，该查询基于提供的字段和值来检查是否存在相应的记录。
     *
     * @param string $field 要查询的字段名。
     * @param mixed $value 字段对应的值。
     * @return bool 如果找到匹配的记录，则返回true；否则返回false。
     */
    public function getFieldExists($field,$value)
    {
        // 通过模型获取数据库实例，并构造查询条件，查询是否存在匹配的记录。
        return (($this->getModel()::getDB())->where($field,$value)->find());
    }

    /**
     * 批量插入数据到数据库。
     *
     * 本方法通过调用getModel方法获取模型实例，进而获取数据库连接对象，并执行批量插入操作。
     * 它接受一个数组作为参数，数组中的每个元素代表一条待插入的数据。
     *
     * @param array $data 包含多条待插入数据的数组，每条数据以键值对形式表示。
     * @return mixed 返回批量插入操作的结果，具体类型取决于数据库操作的返回值。
     */
    public function insertAll(array $data)
    {
        // 通过模型获取数据库实例，并执行批量插入操作
        return ($this->getModel()::getDB())->insertAll($data);
    }

    /**
     * 删除命令菜单
     *
     * 本函数用于根据给定的条件从数据库中删除命令菜单的记录。
     * 它通过调用getModel方法获取模型实例，然后使用该实例的getDB方法获取数据库操作对象，
     * 最后应用where条件并执行delete操作来实现删除。
     *
     * @param string|array $where 删除条件，可以是字符串或数组形式的SQL条件。
     */
    public function deleteCommandMenu($where)
    {
        $this->getModel()::getDB()->where($where)->delete();
    }


    /**
     * 获取所有数据
     *
     * 本函数旨在通过调用对应模型的数据库获取所有记录。它不接受任何参数，
     * 并返回数据库查询的结果。此方法适用于那些需要检索整个表数据的场景，
     * 例如在生成列表或统计总记录数时。
     *
     * @return array 返回包含所有记录的数组
     */
    public function all()
    {
        // 通过模型获取数据库实例，并执行选择所有记录的操作
        return ($this->getModel()::getDB())->select();
    }


    /**
     *  根据每个路由分组获取是否存在父级
     * @Author:Qinii
     * @Date: 2020/9/8
     * @param array $data
     * @return mixed
     */
    public function getMenuPid(string $route, $isMer, $isMenu, $isAgent)
    {
        return ($this->getModel()::getDB())
            ->where('route',$route)
            ->where('is_mer',$isMer)
            ->where('is_menu',$isMenu)
            ->where('is_agent', $isAgent)
            ->order('path ASC')->find();
    }
}
