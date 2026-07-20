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


namespace app\common\repositories\system\attachment;


//附件
use app\common\dao\BaseDao;
use crmeb\exceptions\AdminException;
use app\common\dao\system\attachment\AttachmentDao;
use app\common\repositories\BaseRepository;
use FormBuilder\Factory\Elm;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Route;
use think\Model;


/**
 * 附件
 */
class AttachmentRepository extends BaseRepository
{
    /**
     * @var AttachmentCategoryRepository
     */
    private $attachmentCategoryRepository;

    /**
     * AttachmentRepository constructor.
     * @param AttachmentDao $dao
     * @param AttachmentCategoryRepository $attachmentCategoryRepository
     */
    public function __construct(AttachmentDao $dao, AttachmentCategoryRepository $attachmentCategoryRepository)
    {
        /**
         * @var AttachmentDao
         */
        $this->dao = $dao;
        $this->attachmentCategoryRepository = $attachmentCategoryRepository;
    }

    const TYPE_IMAGE = 0;
    const TYPE_VIDEO = 1;
    /**
     * 根据条件获取列表数据
     *
     * 本函数用于根据给定的条件查询数据库，并返回分页后的数据列表以及总条数。
     * 主要用于数据查询和分页处理，适用于各种数据展示场景。
     *
     * @param array $where 查询条件，以数组形式传递，键值对表示字段和值。
     * @param int $page 当前页码，用于分页查询。
     * @param int $limit 每页的数据条数，用于分页查询。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 表示总条数，'list' 表示分页后的数据列表。
     */
    public function getList(array $where, int $page, int $limit)
    {
        // 根据条件进行查询
        $query = $this->dao->search($where);

        // 计算满足条件的数据总条数
        $count = $query->count($this->dao->getPk());

        // 进行分页查询，并隐藏某些字段
        $list = $query->page($page, $limit)->hidden(['user_type', 'user_id'])
            ->select();

        $list = getThumbWaterImage($list,['attachment_src'],'mid','','src');
        // 返回查询结果的总条数和分页后的数据列表
        return compact('count', 'list');
    }

    /**
     * 创建新记录。
     *
     * 该方法用于根据给定的参数创建一个新的数据记录。它首先将上传类型、用户类型和用户ID添加到数据数组中，
     * 然后调用DAO层的create方法来实际执行数据插入操作。
     *
     * @param int $uploadType 上传类型，表示数据的来源或上传方式。
     * @param int $userType 用户类型，用于区分不同类型的用户。
     * @param int $userId 用户ID，标识数据的创建者。
     * @param array $data 数据数组，包含需要创建的记录的具体数据。
     * @return mixed 返回DAO层create方法的执行结果，通常是一个自增的ID或影响行数。
     */
    public function create(int $uploadType, int $userType, int $userId, array $data)
    {
        // 将上传相关和用户相关信息加入到数据数组中
        $data['upload_type'] = $uploadType;
        $data['user_type'] = $userType;
        $data['user_id'] = $userId;

        // 调用DAO层的create方法，实际执行数据插入操作，并返回执行结果
        return $this->dao->create($data);
    }

    /**
     * 批量更改分类
     *
     * 本函数用于批量将指定附件ID的分类更改为新的分类ID。它可以用于管理附件的分类，无需逐个手动更改。
     *
     * @param array $ids 附件ID的数组，表示需要更改分类的附件。
     * @param int $categoryId 新的分类ID，指定附件将被更改到的分类。
     * @param int $merId 商家ID，可选参数，默认为0。用于指定操作的商家，如果系统支持多商家，则此参数可能有用。
     * @return mixed 返回执行结果，具体类型取决于DAO层的实现。
     */
    public function batchChangeCategory(array $ids, int $categoryId, $merId = 0)
    {
        // 调用DAO层的方法，执行批量更改分类的操作。
        return $this->dao->batchChange($ids, ['attachment_category_id' => $categoryId], $merId);
    }

    /**
     * 根据给定的ID和商家ID生成表单
     * 该方法用于创建一个编辑配置的表单，表单数据来源于指定ID的数据项。
     * 如果商家ID存在，则表单提交的地址是针对商家附件更新的路由；
     * 否则，表单提交的地址是针对系统附件更新的路由。
     *
     * @param int $id 配置项的ID，用于获取当前配置项的数据。
     * @param int $merId 商家ID，用于确定表单提交的地址是商家附件更新还是系统附件更新。
     * @return mixed 返回生成的表单对象，包含了表单的结构和数据。
     */
    public function form(int $id, int $merId)
    {
        // 根据$merId的存在与否确定表单提交的行动动词
        if ($merId) {
            $action = 'merchantAttachmentUpdate';
        } else {
            $action = 'systemAttachmentUpdate';
        }

        // 通过ID获取配置项的数据，并转换为数组格式
        $formData = $this->dao->get($id)->toArray();

        // 创建表单对象，并设置表单的提交URL
        $form = Elm::createForm(Route::buildUrl($action, is_null($id) ? [] : ['id' => $id])->build());

        // 设置表单的验证规则
        $form->setRule([
            Elm::input('attachment_name', '名称：')->placeholder('请输入名称')->required('请输入名称'),
        ]);

        // 设置表单的标题，并加载获取的配置项数据
        return $form->setTitle('编辑配置')->formData($formData);
    }

    /**
     * 视频分片上传
     * @param $data
     * @param $file
     * @return mixed
     */
    public function chunkVideo($data, $file)
    {
        $pathinfo = pathinfo($data['filename']);
        if (isset($pathinfo['extension']) && !in_array($pathinfo['extension'], ['avi', 'mp4', 'wmv', 'rm', 'mpg', 'mpeg', 'mov', 'flv', 'swf'])) {
            throw new AdminException(400558);
        }
        $data['chunkNumber'] = (int)$data['chunkNumber'];
        $public_dir = app()->getRootPath() . 'public';
        $dir = '/uploads/attach/' . date('Y') . DIRECTORY_SEPARATOR . date('m') . DIRECTORY_SEPARATOR . date('d');
        $all_dir = $public_dir . $dir;
        if (!is_dir($all_dir)) mkdir($all_dir, 0777, true);
        $filename = $all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'];
        move_uploaded_file($file['tmp_name'], $filename);
        $res['code'] = 0;
        $res['msg'] = 'error';
        $res['dir'] = '';
        if ($data['chunkNumber'] == $data['totalChunks']) {
            $blob = '';
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                $blob .= file_get_contents($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            file_put_contents($all_dir . '/' . $data['filename'], $blob);
            for ($i = 1; $i <= $data['totalChunks']; $i++) {
                @unlink($all_dir . '/' . $data['filename'] . '__' . $i);
            }
            if (file_exists($all_dir . '/' . $data['filename'])) {
                $res['code'] = 2;
                $res['msg'] = 'success';
                $res['dir'] = systemConfig('site_url') . $dir . '/' . $data['filename'];
            }
        } else {
            if (file_exists($all_dir . '/' . $data['filename'] . '__' . $data['chunkNumber'])) {
                $res['code'] = 1;
                $res['msg'] = 'waiting';
                $res['dir'] = '';
            }
        }
        return $res;
    }
}
