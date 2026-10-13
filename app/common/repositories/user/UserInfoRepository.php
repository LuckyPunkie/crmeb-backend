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


namespace app\common\repositories\user;


use app\common\dao\user\UserInfoDao;
use app\common\dao\user\UserSignDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use FormBuilder\Factory\Elm;
use Swoole\Database\MysqliException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;

/**
 * @mixin UserInfoDao
 */
class UserInfoRepository extends BaseRepository
{

    protected $type = [
        'input'     => ['label' => '文本',   'type' => 'varchar(255)',  'value' => '', 'default' => ''],
        'int'       => ['label' => '数字',   'type' => 'int(11)',      'value' => 0,  'default' => 0],
        'phone'     => ['label' => '手机号', 'type' => 'varchar(11)',  'value' => '',  'default' => ''],
        'date'      => ['label' => '时间',   'type' => 'date',         'value' => null,'default' => NULL],
        'radio'     => ['label' => '单选',   'type' => 'tinyint(1)',   'value' => 0,  'default' => 0],
        'address'   => ['label' => '地址',   'type' => 'varchar(255)', 'value' => '', 'default' => ''],
        'id_card'   => ['label' => '身份证', 'type' => 'varchar(255)', 'value' => '',  'default' => ''],
        'email'     => ['label' => '邮箱',   'type' => 'varchar(255)', 'value' => '', 'default' => ''],
    ];
    const FIXED_FIELD = ['uid'];

    /**
     * @var UserInfoDao
     */
    protected $dao;

    /**
     * UserSignRepository constructor.
     * @param UserInfoDao $dao
     */
    public function __construct(UserInfoDao $dao)
    {
        $this->dao = $dao;
    }

    // 验证是否是有效的列名
    /**
     * 检查列名是否有效。
     *
     * 该方法用于验证传入的列名是否符合规定的格式。列名必须以字母开头，后续可以是字母、数字或下划线的组合。
     * 如果列名不符合这个规则，将抛出一个ValidateException异常，指出列名无效。
     *
     * @param string $columnName 待验证的列名。
     * @throws ValidateException 如果列名无效，则抛出此异常。
     */
    protected function isValidColumnName($columnName)
    {
        // 使用正则表达式检查列名是否以字母开头，且仅包含字母、数字和下划线
        // 检查是否以字母开头，只包含字母、数字和下划线
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $columnName)) {
            throw new ValidateException("无效的列名: {$columnName}");
        }
    }


    /**
     * 执行SQL语句。
     *
     * 本函数用于执行给定的SQL语句，通过传递数据库连接和SQL语句参数来完成。
     * 如果在执行SQL语句时遇到MysqliException异常，它将被捕获并重新抛出为ValidateException异常。
     * 这种异常处理机制允许上层调用者更统一地处理异常情况，尤其是数据库操作相关的异常。
     *
     * @param $db PDO 实例，用于执行SQL语句的数据库连接。
     * @param $sql string，待执行的SQL语句。
     * @param $params array，默认为空数组，包含SQL语句中需要的参数。
     * @throws ValidateException 如果执行SQL语句时发生错误，则抛出此异常。
     */
    public function executeSql($db, $sql, $params = [])
    {
        try {
            $db->execute($sql, $params);
        } catch (MysqliException $exception) {
            throw new ValidateException($exception->getMessage());
        }
    }

    /**
     * 创建字段
     * @param array $data
     * @param $type
     * @return bool
     *
     * @date 2023/09/25
     * @author yyw
     */
    protected function changeField(array $data, $type = true)
    {
        // 获取数据库连接实例
        $db = Db::connect();
        //操作的数据表
        $tableName = env('database.prefix', 'eb_') . 'user_fields';
        // 操作的字段名称
        $fieldName = $data['field'];
        // 验证字段端名是否合法
        $this->isValidColumnName($fieldName);
        //
        if ($type) {
            // 字段类型，可以根据需要进行修改
            $fieldType = $this->type[$data['type']]['type'] ?? 'varchar(255)';
            $comment = $data['title'] ?: $data['msg'];
            $default = $this->type[$data['type']]['default'];
            $sql = "ALTER TABLE `$tableName` ADD COLUMN `$fieldName` $fieldType NULL DEFAULT ".($default === NULL ? 'NULL' : "'$default'")." COMMENT '$comment'";
        } else {
            $sql = "ALTER TABLE `$tableName` DROP COLUMN `$fieldName`";

        }
        $this->executeSql($db, $sql);
        return true;
    }

    /**
     * 删除字段
     * @param string $fieldName
     * @return bool
     *
     * @date 2023/09/25
     * @author yyw
     */
    protected function deleteField(string $fieldName)
    {
        // 验证字段名称是否有效
        $this->isValidColumnName($fieldName);

        // 获取数据库连接实例
        $db = Db::connect();

        // 操作的数据表
        $tableName = env('database.prefix', 'eb_') . 'user_fields';

        // 构建 SQL 删除字段的语句
        $sql = "ALTER TABLE `$tableName` DROP COLUMN `$fieldName`";

        $this->executeSql($db, $sql);

        return true;
    }


    /**
     * 获取列表数据
     * 根据给定的条件和分页参数，从数据库中获取列表数据，并进行相关处理。
     *
     * @param array $where 查询条件，用于筛选数据。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页数据条数，用于分页查询。
     * @return array 返回包含数据总数、数据列表和默认头像信息的数组。
     */
    public function getList(array $where = [], int $page = 1, int $limit = 10)
    {
        // 根据条件获取查询对象
        $query = $this->dao->getSearch($where);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 获取排序后的数据列表
        $list = $query->order(['sort', 'create_time' => 'ASC'])->select()->each(function ($item) {
            // 给数据项的type_name赋值，来源于type数组中对应type的label值
            return $item->type_name = $this->type[$item->type]['label'];
        });

        // 获取系统配置的默认用户头像地址
        $avatar = systemConfig('user_default_avatar');

        // 返回包含数据总数、处理后的数据列表和默认头像信息的数组
        return compact('count', 'list', 'avatar');
    }


    /**
     * 创建表单用于添加字段信息
     *
     * 该方法通过Element UI的表单构建器创建一个表单，用于添加系统用户信息字段。
     * 表单包括字段类型的选择、字段的名称、键值和提示信息的输入。
     * 其中字段类型为单选框，选项通过getType方法获取，当选择字段类型为'radio'时，需要额外输入配置内容。
     *
     * @return \Encore\Admin\Widgets\Form|\FormBuilder\Form
     */
    public function createFrom()
    {
        // 构建表单提交的URL
        $action = Route::buildUrl('systemUserInfoCreate')->build();

        // 使用Elm表单构建器创建表单，并设置表单字段
        $form = Elm::createForm($action, [
            // 字段类型选择器，包括获取字段类型的选项和针对'radio'类型字段的额外配置输入
            Elm::select('type', '字段类型：')->options($this->getType())->control([
                [
                    'value' => 'radio',
                    'rule' => [
                        Elm::textarea('content', '配置内容')->placeholder('请输入配置内容')->required('请输入配置内容')
                    ]
                ]
            ])->required('请选择字段类型'),
            // 字段名称输入框
            Elm::input('title', '字段名称：')->placeholder('请输入字段名称')->required('请输入字段名称'),
            // 字段键值输入框
            Elm::input('field', '字段key：')->placeholder('请输入字段key')->required('请输入字段key'),
            // 提示信息输入框
            Elm::input('msg', '提示信息：')->placeholder('请输入提示信息')->required('请输入提示信息')
        ]);

        // 设置表单标题
        return $form->setTitle('添加字段');
    }


    /**
     * 创建字段
     *
     * 本函数用于根据传入的数据创建新的字段。它首先验证字段类型是否有效，然后确保对于单选字段类型（radio），
     * 至少提供了两个选项。如果验证失败，将抛出一个验证异常。然后，它将内容数据编码为JSON格式，并为字段设置默认的排序值。
     * 最后，它在数据库事务中执行字段的创建操作。
     *
     * @param array $data 包含字段相关信息的数组，包括字段类型、内容等。
     * @return bool|void 返回数据库事务执行结果，如果执行成功，则返回true，否则不返回任何值。
     * @throws ValidateException 如果字段类型无效或单选字段选项不足两个，将抛出此异常。
     */
    public function create(array $data)
    {
        // 验证字段类型是否在定义的类型数组中
        // 验证类型
        if (!isset($this->type[$data['type']])) {
            throw new ValidateException('字段类型异常');
        }
        // 对于单选字段类型（radio），确保至少有两个选项
        if ($data['type'] == 'radio' && (!is_array($data['content']) || count($data['content']) < 2)) {
            throw new ValidateException('请至少创建两个选项');
        }
        // 将内容数据编码为JSON格式
        $data['content'] = json_encode($data['content']);
        // 设置默认的排序值
        $data['sort'] = 999;

        // 使用数据库事务执行字段的创建操作，包括更改字段信息和实际创建字段的操作
        return Db::transaction(function () use ($data) {
            $this->changeField($data);
            $this->dao->create($data);
        });
    }

    /**
     * 保存用户信息及扩展字段配置
     *
     * 本函数主要用于处理用户信息的保存操作，其中包括用户头像信息的更新，
     * 以及用户扩展信息字段的配置更新。通过对传入数据的处理，确保了用户
     * 信息的完整性和扩展字段的正确配置。
     *
     * @param array $data 用户信息及扩展字段数据数组
     *                    包含 'avatar' 用户头像信息
     *                    包含 'user_extend_info' 用户扩展信息字段数组
     * @return bool 返回保存操作的结果，true表示保存成功
     */
    public function saveAll(array $data = [])
    {
        // 设置用户默认头像信息
        app()->make(ConfigValueRepository::class)->setFormData(['user_default_avatar' => $data['avatar']], 0);

        // 检查并更新用户扩展信息字段的配置
        // 保存用户表单
        if (!empty($data['user_extend_info'])) {
            foreach ($data['user_extend_info'] as $sort => $item) {
                // 确保必要参数存在，避免空值导致的错误
                if (!isset($item['field']) || !isset($item['is_used']) || !isset($item['is_require']) || !isset($item['is_show'])) {
                    throw new ValidateException('参数不能为空');
                }

                // 确保参数值的合法性，只允许特定的取值范围
                if (!in_array($item['is_used'], [0, 1]) || !in_array($item['is_require'], [0, 1]) || !in_array($item['is_show'], [0, 1])) {
                    throw new ValidateException('参数类性错误');
                }

                // 更新扩展字段的配置信息
                $this->dao->query([])->where('field', $item['field'])->update(['is_used' => $item['is_used'], 'is_require' => $item['is_require'], 'is_show' => $item['is_show'], 'sort' => $sort]);
            }
        }

        return true;
    }

    /**
     * 删除表单数据。
     *
     * 本函数用于根据给定的ID删除表单数据。在删除前，它会进行一系列的验证以确保数据的安全性和完整性。
     * 首先，它会检查所要删除的数据是否存在以及'field'字段是否为空，如果存在异常情况，则会抛出一个验证异常。
     * 其次，它会检查待删除的数据是否为默认数据，如果是默认数据，则同样会抛出一个验证异常，以防止删除重要的默认设置。
     * 最后，如果所有验证通过，函数将使用事务来确保删除操作的原子性，即先删除字段数据，再删除实际的表单数据。
     *
     * @param int $id 表单数据的唯一标识ID。
     * @return bool 如果删除成功，返回true。
     * @throws ValidateException 如果数据不存在、字段为空或尝试删除默认表单数据，则抛出此异常。
     */
    public function delete(int $id)
    {
        // 通过ID获取表单信息
        $info = $this->dao->get($id);
        // 检查获取的表单信息是否为空，或者字段是否为空，如果是，则抛出异常
        if (empty($info) || empty($info['field'])) {
            throw new ValidateException('表单数据异常');
        }
        // 检查是否为默认表单，如果是，则抛出异常，防止删除默认表单
        if ($info['is_default']) {
            throw new ValidateException('默认表单不能删除');
        }
        // 使用事务来确保删除操作的原子性
        return Db::transaction(function () use ($info, $id) {
            // 删除字段数据
            $this->deleteField($info['field']);
            // 删除实际的表单数据
            $this->dao->delete($id);
        });
    }



    /**
     * 获取类型信息
     *
     * 本方法旨在通过转换内部类型数组，返回一个格式化后的类型信息数组。每个类型信息包括
     * 值和描述，其中值对应于类型标识，描述则来自于类型数组中的相应元素。
     *
     * @return array 返回一个格式化后的类型信息数组，每个元素包含 'value' 和 'desc' 两个键，
     * 'value' 表示类型的标识，'desc' 是对应的类型描述。
     */
    public function getType()
    {
        // 初始化一个空数组，用于存放转换后的类型信息
        $res = [];

        // 遍历内部类型数组，对每个类型进行处理
        foreach ($this->type as $k => $v) {
            // 将类型标识作为值赋给当前类型信息
            $v['value'] = $k;
            // 移除原始类型数组中的'type'键，避免冗余信息
            unset($v['type']);
            // 将处理后的类型信息添加到结果数组中
            $res[] = $v;
        }

        // 返回转换后的类型信息数组
        return $res;
    }


    /**
     * 获取选择列表
     *
     * 本函数用于检索数据库中特定条件下的数据，这些数据主要用于生成前端的下拉选择列表。
     * 具体来说，它查询了被标记为已使用且类型不为'radio'或'date'的条目，并按排序和创建时间升序返回它们的标题和字段值。
     *
     * @return array 返回一个数组，其中每个元素包含标签（label）和值（value），用于生成选择列表。
     */
    public function getSelectList()
    {
        // 使用DAO对象进行数据库查询，设置条件以获取已使用且类型不是'radio'或'date'的记录
        // 排序方式为按排序字段和创建时间升序
        // 最后，只返回标题作为标签和字段作为值的列
        return $this->dao->getSearch(['is_used' => 1])
                         ->whereNotIn('type', ['radio', 'date'])
                         ->order(['sort', 'create_time' => 'ASC'])
                         ->column('title as label,field as value');
    }

    /**
     * 验证字段是否为默认字段
     * @param string $fields
     * @return bool
     */
    public function getFieldsIsItDefault(string $field)
    {
        return (bool)$this->getSearch(['field' => $field])->value('is_default', 0);
    }
}
