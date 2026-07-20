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


namespace app\common\repositories\system\admin;


use app\common\dao\BaseDao;
use app\common\dao\system\admin\LogDao;
use app\common\repositories\BaseRepository;
use app\Request;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\Model;

/**
 * 管理员操作记录
 */
class AdminLogRepository extends BaseRepository
{
    /**
     * AdminLogRepository constructor.
     * @param LogDao $dao
     */
    public function __construct(LogDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 查询管理员操作日志列表
     *
     * 该方法用于根据给定的条件和分页参数，从数据库中检索管理员的操作日志。
     * 它首先构造一个查询，包括与商家关联的条件，并指定要包含的关联数据（如菜单）。
     * 然后，它计算满足条件的日志总数，以便于分页。
     * 最后，它构造一个包含日志列表和总数的数组，并返回该数组。
     *
     * @param string $merId 商家ID，用于限定查询的商家范围
     * @param array $where 查询条件，用于过滤日志
     * @param int $page 当前页码，用于分页查询
     * @param int $limit 每页记录数，用于分页查询
     * @return array 返回一个包含日志总数和日志列表的数组
     */
    public function lst($merId, array $where, $page, $limit)
    {
        // 构造查询，包括指定的条件和商家ID，以及要加载的关联数据（这里指菜单）
        $query = $this->dao->search($where, $merId)->with([
            'menu' => function($query){
                // 仅加载菜单的名称和路由
                $query->field('menu_name,route');
            }
        ]);

        // 计算满足条件的日志总数
        $count = $query->count($this->dao->getPk());

        // 确定查询的字段，进行分页查询，并按创建时间降序排序
        // 这里指定了要查询的字段，包括创建时间、日志ID、管理员名称、路由、方法、URL、IP和管理员ID
        $list = $query->setOption('field', [])->field(['create_time', 'log_id', 'admin_name', 'route', 'method', 'url', 'ip', 'admin_id'])
            ->page($page, $limit)->order('create_time DESC')->select();

        // 返回包含日志总数和日志列表的数组
        return compact('count', 'list');
    }

    /**
     * 添加日志记录
     *
     * 该方法用于根据传入的请求信息和商家ID添加一条日志记录。
     * 它首先解析请求数据，然后使用解析后的数据和商家ID来创建新的日志条目。
     *
     * @param Request $request 本次请求的对象，用于解析请求数据。
     * @param int $merId 商家ID，用于标识日志所属的商家。默认为0，表示系统日志。
     * @return mixed 返回添加日志的结果，具体类型取决于create方法的实现。
     */
    public function addLog(Request $request, int $merId = 0)
    {
        // 解析请求数据并创建日志条目
        return $this->create($merId, self::parse($request));
    }

    /**
     * 创建一个新的实体对象
     *
     * 本函数用于在数据库中创建一个新的实体记录。它首先将传入的商家ID添加到数据数组中，然后调用DAO层的创建方法来执行实际的数据库操作。
     * 这里的商家ID是作为外部传入的参数，它被用于指定新记录所属的商家。
     *
     * @param int $merId 商家ID，用于标识记录所属的商家。
     * @param array $data 包含新记录数据的数组，不包含商家ID。
     * @return mixed 返回DAO层创建方法的执行结果，可能是新创建记录的ID或其他数据。
     */
    public function create(int $merId, array $data)
    {
        // 将商家ID添加到数据数组中
        $data['mer_id'] = $merId;

        // 调用DAO层的创建方法，传入完整的数据数组，执行数据库插入操作
        return $this->dao->create($data);
    }

    /**
     * 解析请求信息并生成管理员操作日志的数组格式。
     *
     * 该方法旨在提取当前请求中的关键信息，如管理员ID、姓名、请求的路由、IP地址、URL和请求方法，
     * 用于构建管理员操作日志，以便后续存储和审计。通过对这些信息的提取和打包，可以方便地
     * 记录管理员在系统中的操作轨迹，增强系统的可审计性和安全性。
     *
     * @param Request $request 当前的请求对象，包含了所有的请求信息。
     * @return array 返回一个包含管理员ID、姓名、请求路由、IP地址、完整URL和请求方法的数组。
     */
    public static function parse(Request $request)
    {
        // 构建并返回包含管理员信息和请求信息的数组
        return [
            'admin_id' => $request->adminId(), // 获取管理员ID
            'admin_name' => $request->adminInfo()->real_name ?: '未定义', // 获取管理员真实姓名，如果未定义则显示为“未定义”
            'route' => $request->rule()->getName(), // 获取当前请求的路由名称
            'ip' => $request->ip(), // 获取客户端的IP地址
            'url' => $request->url(true), // 获取完整的请求URL
            'method' => $request->method() // 获取请求的方法（如GET、POST等）
        ];
    }
}
