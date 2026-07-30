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


namespace app\controller\merchant\store\service;


use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\user\UserRepository;
use app\validate\merchant\StoreServiceValidate;
use crmeb\basic\BaseController;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;

/**
 * Class StoreService
 * @package app\controller\merchant\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class StoreService extends BaseController
{
    /**
     * @var StoreServiceRepository
     */
    protected $repository;
    /**
     * @var StoreServiceLogRepository
     */
    protected $logRepository;

    /**
     * StoreService constructor.
     * @param App $app
     * @param StoreServiceRepository $repository
     */
    public function __construct(App $app, StoreServiceRepository $repository, StoreServiceLogRepository $logRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->logRepository = $logRepository;
    }

    /**
     * 获取列表数据
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function lst()
    {
        // 从请求参数中获取关键字和状态
        $where = $this->request->params(['keyword', 'status']);
        // 获取分页信息
        [$page, $limit] = $this->getPage();
        // 设置商家ID
        $where['mer_id'] = $this->request->merId();
        $where['is_open'] = $where['status'];
        unset($where['status']);
        // 调用仓库的获取列表方法并返回结果
        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 创建表单数据
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function createForm()
    {
        // 调用仓库的表单方法并返回结果
        return app('json')->success(formToData($this->repository->form($this->request->merId())));
    }


    /**
     * 创建客服账号
     *
     * @param StoreServiceValidate $validate 验证器实例
     * @return \Psr\Http\Message\ResponseInterface 返回JSON格式的响应结果
     */
    public function create(StoreServiceValidate $validate)
    {
        // 获取参数并进行校验
        $data = $this->checkParams($validate);
        // 设置商家ID
        $data['mer_id'] = $this->request->merId();
        // 判断该用户是否已经绑定了客服
        if ($this->repository->issetService($data['mer_id'], $data['uid']))
            return app('json')->fail('该用户已绑定客服');
        // 判断账号是否已存在
        if ($this->repository->fieldExists('account', $data['account'])) {
            return app('json')->fail('账号已存在');
        }
        // 对密码进行加密处理
        $data['pwd'] = password_hash($data['pwd'], PASSWORD_BCRYPT);
        // 创建客服账号
        $this->repository->create($data);
        // 自动写入本店客户关联，后续按手机号选人/发帖权限不依赖手工挂客户
        $uid = (int)($data['uid'] ?? 0);
        $merId = (int)($data['mer_id'] ?? 0);
        if ($uid > 0 && $merId > 0) {
            app()->make(\app\common\repositories\circle\CircleAgentProvisionRepository::class)
                ->ensureUserMerchantLink($uid, $merId);
        }
        // 同步客服头像/昵称到 C 端用户，避免小程序「我的」仍是空头像
        if ($uid > 0) {
            $sync = [];
            $avatar = trim((string)($data['avatar'] ?? ''));
            $nickname = trim((string)($data['nickname'] ?? ''));
            if ($avatar !== '') {
                $sync['avatar'] = $avatar;
            }
            if ($nickname !== '') {
                $userNickname = (string)\think\facade\Db::name('user')->where('uid', $uid)->value('nickname');
                // 仅当用户还是默认手机号掩码昵称时，用店员昵称覆盖
                if ($userNickname === '' || preg_match('/^\d{3}\*{4}\d{4}$/', $userNickname) || preg_match('/^1\d{10}$/', $userNickname)) {
                    $sync['nickname'] = $nickname;
                }
            }
            if ($sync) {
                \think\facade\Db::name('user')->where('uid', $uid)->update($sync);
            }
        }
        // 返回操作成功的提示信息
        return app('json')->success('添加成功');
    }


    /**
     * 检查参数并返回处理后的数据
     *
     * @param StoreServiceValidate $validate 商家服务验证器实例
     * @param bool $isUpdate 是否为更新操作，默认为false
     * @return array 处理后的数据
     * @throws ValidateException 当客服密码与确认密码不一致时抛出异常
     */
    public function checkParams(StoreServiceValidate $validate, $isUpdate = false)
    {
        // 获取请求参数
        $data = $this->request->params([['uid', []], 'nickname', 'account', 'pwd', 'confirm_pwd', 'is_open', 'status', 'customer', 'is_verify', 'is_goods', 'notify', 'avatar', 'phone', ['sort', 0]]);
        // 如果是更新操作，则调用验证器的update方法
        if ($isUpdate) {
            $validate->update();
        }
        // 如果没有传入merId，则将is_verify、customer、is_goods和notify设置为0，phone设置为空字符串
        if (!$this->request->merId()) {
            $data['is_verify'] = 0;
            $data['customer'] = 0;
            $data['is_goods'] = 0;
        }
        // 对数据进行验证
        $validate->check($data);
        // 如果没有上传头像，则将其设置为uid的src属性值
        if (!$data['avatar']) $data['avatar'] = $data['uid']['src'];
        // 如果密码和确认密码不一致，则抛出异常
        if ($data['pwd'] && $data['pwd'] != $data['confirm_pwd']) {
            throw new ValidateException('客服密码与确认密码不一致');
        }
        // 将uid设置为uid的id属性值，并删除confirm_pwd属性
        $data['uid'] = $data['uid']['id'];
        unset($data['confirm_pwd']);
        // 返回处理后的数据
        return $data;
    }

    /**
     * 更新表单
     *
     * @param int $id 表单ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateForm($id)
    {
        // 判断表单是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 调用 repository 的 updateForm 方法更新表单，并将结果转换为数据格式返回
        return app('json')->success(formToData($this->repository->updateForm($id)));
    }

    /**
     * 更新客服信息
     *
     * @param int $id 客服ID
     * @param StoreServiceValidate $validate 验证器实例
     * @return \think\response\Json
     */
    public function update($id, StoreServiceValidate $validate)
    {
        // 获取验证通过的数据
        $data = $this->checkParams($validate, true);
        // 判断客服是否存在
        if (!$this->repository->merExists($merId = $this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 判断该用户是否已经绑定了客服
        if ($this->repository->issetService($merId, $data['uid'], $id))
            return app('json')->fail('该用户已绑定客服');
        // 判断账号是否已存在
        if ($this->repository->fieldExists('account', $data['account'], $id)) {
            return app('json')->fail('账号已存在');
        }
        // 对密码进行加密处理
        if ($data['pwd']) {
            $data['pwd'] = password_hash($data['pwd'], PASSWORD_BCRYPT);
        } else {
            unset($data['pwd']);
        }
        // 更新客服信息
        $this->repository->update($id, $data);
        // 同步客服头像到 C 端用户
        $uid = (int)($data['uid'] ?? 0);
        if ($uid > 0) {
            $avatar = trim((string)($data['avatar'] ?? ''));
            if ($avatar !== '') {
                \think\facade\Db::name('user')->where('uid', $uid)->update(['avatar' => $avatar]);
            }
        }
        // 返回操作结果
        return app('json')->success('修改成功');
    }

    /**
     * 修改状态
     *
     * @param int $id 商品ID
     * @return \think\response\Json
     */
    public function changeStatus($id)
    {
        // 获取请求参数中的状态值
        $status = $this->request->param('status');
        // 判断商品是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 更新商品状态
        $this->repository->update($id, ['is_open' => $status == 1 ? 1 : 0]);
        // 返回操作结果
        return app('json')->success('修改成功');
    }

    /**
     * 根据ID删除数据
     *
     * @param int $id 数据ID
     * @return \Illuminate\Http\JsonResponse 返回JSON格式的响应结果
     */
    public function delete($id)
    {
        // 判断数据是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 删除数据
        $this->repository->delete($id);
        // 返回删除成功的响应结果
        return app('json')->success('删除成功');
    }


    /**
     * 客服的全部用户
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-18
     */
    public function serviceUserList($id)
    {
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->logRepository->getServiceUserList($id, $page, $limit));
    }


    /**
     * 商户的全部用户列表
     * @return mixed
     * @author Qinii
     * @day 2020-06-19
     */
    public function merchantUserList()
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->logRepository->getMerchantUserList($this->request->merId(), $page, $limit));
    }

    /**
     * 用户与客服聊天记录
     * @param $id
     * @param $uid
     * @return mixed
     * @author Qinii
     * @day 2020-06-19
     */
    public function getUserMsnByService($id, $uid)
    {
        [$page, $limit] = $this->getPage();
        if (!$this->repository->getWhereCount(['service_id' => $id, 'mer_id' => $this->request->merId()]))
            return app('json')->fail('客服不存在');
        return app('json')->success($this->logRepository->getUserMsn($uid, $page, $limit, $this->request->merId(), $id));
    }

    /**
     * 用户与商户聊天记录
     * @param $id
     * @return mixed
     * @author Qinii
     * @day 2020-06-19
     */
    public function getUserMsnByMerchant($id)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($this->logRepository->getUserMsn($id, $page, $limit, $this->request->merId()));
    }

    /**
     * 获取用户列表
     *
     * @return \think\response\Json
     */
    public function getUserList()
    {
        // 获取分页参数
        [$page, $limit] = $this->getPage();
        // 获取查询条件
        $where = $this->request->params(['keyword']);
        $where['status'] = 1;
        // 调用 UserRepository 类的 getPulbicLst 方法获取用户列表
        $data = app()->make(UserRepository::class)->getPulbicLst($where, $page, $limit);
        // 返回 JSON 格式的数据
        return app('json')->success($data);
    }


    /**
     * 登录方法
     *
     * @param int $id 管理员ID
     * @return \think\response\Json
     */
    public function login($id)
    {
        // 判断商家和管理员是否存在
        if (!$this->repository->merExists($this->request->merId(), $id))
            return app('json')->fail('数据不存在');
        // 获取管理员信息
        $adminInfo = $this->repository->get($id);
        // 创建令牌
        $tokenInfo = $this->repository->createToken($adminInfo);
        // 构造返回数据
        $admin = $adminInfo->toArray();
        unset($admin['pwd']);
        $data = [
            'token' => $tokenInfo['token'],
            'exp' => $tokenInfo['out'],
            'admin' => $admin,
            'url' => '/' . config('admin.service_prefix')
        ];
        // 返回成功响应
        return app('json')->success($data);
    }

}
