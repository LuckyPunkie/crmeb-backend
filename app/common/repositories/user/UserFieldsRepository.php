<?php

namespace app\common\repositories\user;

use app\common\dao\user\UserFieldsDao;
use app\common\repositories\BaseRepository;
use app\validate\admin\UserValidate;
use FormBuilder\Factory\Elm;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Route;
use think\facade\Validate;

/**
 * @mixin UserFieldsDao
 */
class UserFieldsRepository extends BaseRepository
{

    /**
     * @var UserFieldsDao
     */
    protected $dao;

    /**
     * UserSignRepository constructor.
     * @param UserFieldsDao $dao
     */
    public function __construct(UserFieldsDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 验证特殊类型字段
     * @param string $type
     * @param string $field
     * @param string $title
     * @param array $data
     * @return bool
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function validateFieldFormat(string $type, string $field, string $title, array $data, $content = [])
    {
        $name = $field . '|' . $title;
        $rule = '';
        switch ($type) {
            case 'radio':
                if (!empty($content) && is_array($content)) {
                    $range = '|in:';
                    // 拼接范围
                    foreach ($content as $key => $item) {
                        $range .= $key . ',';
                    }
                    $rule = 'integer' . rtrim($range, ',');
                }
                break;
            case 'int':
                $rule = 'integer';
                break;
            case 'phone':
                $rule = 'mobile';
                break;
            case 'date':
                $rule = 'date';
                break;
            case 'id_card':
                $rule = 'idCard';
                break;
            case 'email':
                $rule = 'email';
                break;
        }
        if (!empty($rule)) {
            $validate = Validate::rule($name, $rule);
            if (!$validate->check($data)) {
                throw new ValidateException($validate->getError());
            }
        }
        return true;
    }

    /**
     * 获取用户表单字段详情
     * @param int $uid
     * @param bool $is_user 是否用户端使用
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function info(int $uid, bool $is_user = true)
    {
        $userRepository = app()->make(UserRepository::class);
        $userInfo = $userRepository->get($uid);
        if (empty($userInfo)) {
            throw new ValidateException('用户数据异常');
        }
        $fields_where = ['is_used' => 1];
        if ($is_user) {   // 用户端判断字段是否展示
            $fields_where['is_show'] = 1;
        }

        $info = $this->dao->getWhere(['uid' => $uid]);
        //获取标段字段信息
        $infoRepository = app()->make(UserInfoRepository::class);
        $fields = $infoRepository->getSearch($fields_where)->order(['sort', 'create_time' => 'ASC'])->select()->toArray();
        foreach ($fields as &$field) {
            if ($field['is_default']) {   // 默认字段在用户表获取数据
                $field['value'] = $userInfo[$field['field']] ?? '';
            } else {
                $field['value'] = $info[$field['field']] ?? '';
            }
        }

        return [
            'avatar' => $userInfo['avatar'],
            'nickname' => $userInfo['nickname'],
            'phone' => $userInfo['phone'],
            'uid' => $userInfo['uid'],
            'extend_info' => $fields,
        ];
    }

    /**
     * 获取用户扩展信息表单
     * 该方法用于根据用户ID生成用户的扩展信息表单，表单包含各种类型的字段，如整数、日期、单选按钮等。
     * @param int $uid 用户ID，用于获取特定用户的扩展信息。
     * @return mixed 返回包含表单元素的数组，用于前端展示和数据提交。
     * @throws ValidateException 当用户数据异常或表单类型异常时，抛出验证异常。
     */
    public function extendInfoForm(int $uid = 0)
    {
        $userRepository = app()->make(UserRepository::class);
        $userInfo = $userRepository->get($uid);
        if (empty($userInfo)) {
            throw new ValidateException('用户数据异常');
        }

        $userField = $this->dao->getSearch(['uid' => $uid])->find();
        if (!empty($userField)) {
            $userField = $userField->toArray();
        }
        // 获取表单数据
        $userInfoRepository = app()->make(UserInfoRepository::class);
        $extendInfos = $userInfoRepository->getSearch(['is_used' => 1])->order(['sort', 'create_time' => 'ASC'])->select()->toArray();

        $form = [];
        foreach ($extendInfos as $extendInfo) {
            $field = $extendInfo['field'];
            $placeholder = $extendInfo['title'];
            $title = $extendInfo['title'] . '：';
            // 是默认字段重新赋值
            if ($extendInfo['is_default']) {
                $userField[$field] = $userInfo[$field];
            }

            switch ($extendInfo['type']) {
                case 'int':
                    $form_item = Elm::number($field, $title);
                    break;
                case 'date':
                    $form_item = Elm::date($field, $title);
                    break;
                case 'radio':
                    $options = [];
                    foreach ($extendInfo['content'] as $value => $label) {
                        $options[] = ['label' => $label, 'value' => (int)$value];
                    }
                    $form_item = Elm::radio($field, $title, 0)->options($options);
                    break;
                case 'email':
                    $form_item = Elm::email($field, $title);
                    break;
                case 'phone':
                case 'input':
                case 'address':
                case 'id_card':
                    $form_item = Elm::input($field, $title)->placeholder('请输入' . $placeholder);
                    break;
                default:
                    throw new ValidateException('表单类型异常');
            }
            if ($extendInfo['is_require'] && $extendInfo['type'] != 'radio' && $extendInfo['type'] != 'date') {
                $form[] = $form_item->required($extendInfo['msg'] ?: '请填写此项信息');
            } else {
                $form[] = $form_item;
            }
        }
        if (empty($form)) {
            throw new ValidateException('请先去用户设置：设置用户信息');
        }
        return Elm::createForm(Route::buildUrl('systemUserInfoFieldSave', ['uid' => $uid])->build(), $form)->setTitle('信息补充')->formData($userField ?: []);
    }

    /**
     * 报错或者编辑用户表单数据
     * @param int $uid
     * @param array $data
     * @param bool $is_user 是否用户端使用
     * @return true
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function save(int $uid, array $data = [], bool $is_user = true)
    {
        $info = $this->dao->getWhere(['uid' => $uid]);
        //获取标段字段信息
        $infoRepository = app()->make(UserInfoRepository::class);

        $where = ['is_used' => 1];
        if ($is_user) {
            $where = ['is_used' => 1, 'is_show' => 1];
        }
        $fields = $infoRepository->getSearch($where)->select()->toArray();
        $user_data = [];  //用户表更新字段数据
        $fields_data = [];  //扩展字段数据
        foreach ($fields as &$field) {
            foreach ($data as $new_field) {
                if ($new_field['field'] == $field['field']) {
                    // 数据为空统一设置为null
//                    if ($new_field['value'] === "") {
//                        $new_field['value'] = null;
//                    }
                    $field['value'] = $new_field['value'];
                }
            }
            if ($is_user) {
                if ($field['is_used'] && $field['is_show'] && $field['is_require'] && (!isset($field['value']) || $field['value'] === null)) {
                    throw new ValidateException($field['title'] . '字段不能为空');
                }
            } else {
                if (!isset($field['value']) || $field['value'] === null) {
                    continue;
                }
            }
            //验证字段
            $this->validateFieldFormat($field['type'], $field['field'], $field['title'], [$field['field'] => $field['value']], $field['content']);
            if ($field['is_default']) {
                $user_data[$field['field']] = $field['value'];
            } else {
                $fields_data[$field['field']] = $field['value'];
            }
        }

//        Db::listen(function($sql){
//            var_dump(['sql' => $sql]);
//        });
//        var_dump(['info' => $info,'fields_data' => $fields_data]);
        // 更新或创建扩展信息
        if (!empty($fields_data)) {
            if (empty($info)) {
                $fields_data['uid'] = $uid;
                $this->dao->create($fields_data);
            } else {
                $this->dao->update($info['id'], $fields_data);
            }
        }
//        var_dump(['user_data' => $user_data]);
        // 更新用户信息
        if (!empty($user_data)) {
            // 验证用户信息字段
            validate(UserValidate::class)->scene('extend_info')->check($user_data);
            app()->make(UserRepository::class)->update($uid, $user_data);
        }
        unset($user_data);
        unset($fields_data);
        return true;
    }

    /**
     * 删除用户表单数据
     * @param int $uid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     *
     * @date 2023/09/26
     * @author yyw
     */
    public function delete(int $uid)
    {
        $info = $this->dao->getWhere(['uid' => $uid]);
        if (empty($info)) {
            throw new ValidateException('数据异常');
        }
        $this->dao->delete($info['id']);
        return true;
    }
}
