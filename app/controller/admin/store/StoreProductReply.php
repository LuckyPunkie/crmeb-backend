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


namespace app\controller\admin\store;


use crmeb\basic\BaseController;
use app\common\repositories\store\product\ProductReplyRepository;
use app\common\repositories\store\product\ProductRepository;
use app\validate\admin\StoreProductReplyValidate;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;

/**
 * 商品评论
 */
class StoreProductReply extends BaseController
{
    /**
     * @var ProductReplyRepository
     */
    protected $repository;

    /**
     * StoreProductReply constructor.
     * @param App $app
     * @param ProductReplyRepository $repository
     */
    public function __construct(App $app, ProductReplyRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/1
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['keyword', 'nickname', 'is_reply', 'date', 'product_id']);
        $where['mer_id'] = $this->request->merId() ?: '';
        return \app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     *  添加虚拟评论表单
     * @param $productId
     * @return \think\response\Json
     * @author Qinii
     */
    public function virtualForm($productId = null)
    {
        if ($productId && !app()->make(ProductRepository::class)->exists($productId)) {
            app('json')->fail('商品不存在');
        }
        return app('json')->success(formToData($this->repository->form($productId)));
    }

    /**
     * 添加虚拟评论
     * @param StoreProductReplyValidate $validate
     * @return mixed
     * @author xaboy
     * @day 2020/6/1
     */
    public function virtualReply(StoreProductReplyValidate $validate)
    {
        $data = $this->checkParams($validate);
        $_name = $data['nickname']; // mb_substr($data['nickname'], 0, 1) . '***';
        $name = (strLen($data['nickname']) > 6) ? $_name . mb_substr($data['nickname'], -1, 1) : $_name;
        $data['nickname'] = $name;
        $productId = $data['product_id'];
        unset($data['product_id']);
        $this->repository->createVirtual([$productId], $data);

        return app('json')->success('添加成功');
    }

    /**
     * 回复评论表单
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function replyForm($id)
    {
        $merId = $this->request->merId();
        if ($merId)
            if (!$this->repository->merExists($merId, $id))
                return app('json')->fail('数据不存在');;
        return app('json')->success(formToData($this->repository->replyForm($id, $merId)));
    }

    /**
     * 回复评论
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function reply($id)
    {
        $merId = $this->request->merId();
        if ($merId)
            if (!$this->repository->merExists($merId, $id))
                return app('json')->fail('数据不存在');
        $merchant_reply_content = $this->request->param('content');
        if (!$merchant_reply_content)
            return app('json')->fail('请输入回复的内容');
        $merchant_reply_time = date('Y-m-d H:i:s');
        $is_reply = 1;
        $this->repository->update($id, compact('is_reply', 'merchant_reply_content', 'merchant_reply_time'));
        return app('json')->success('回复成功');
    }

    /**
     * 删除评论
     * @param $id
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020/6/1
     */
    public function delete($id)
    {
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * 验证参数
     * @param StoreProductReplyValidate $validate
     * @return array
     * @author xaboy
     * @day 2020/6/1
     */
    public function checkParams(StoreProductReplyValidate $validate)
    {
        $data = $this->request->params([['product_id', []], 'nickname', 'comment', 'sort', 'product_score', 'service_score', 'postage_score', 'avatar', ['pics', ''],'create_time']);
        $validate->check($data);
        $data['product_id'] = $data['product_id']['id'] ?? 0;
        return $data;
    }

    /**
     * 下载评价批量导入模版
     * GET /sys/product/reply/import/template
     */
    public function importTemplate()
    {
        try {
            $path = app()->make(\app\common\repositories\store\product\ProductReplyImportRepository::class)->buildTemplateFile();
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        // Swoole 下框架 download() 走 write(8192) 分块，第二块常失败导致 xlsx 截断损坏
        return \think\swoole\helper\download($path, 'product_reply_import_template.xlsx')
            ->header(['Cache-Control' => 'no-store, no-cache, must-revalidate', 'Pragma' => 'no-cache']);
    }

    /**
     * 批量导入商品评价
     * POST /sys/product/reply/import
     */
    public function import()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return app('json')->fail('请上传 Excel 文件');
        }
        $path = $file->getRealPath() ?: $file->getPathname();
        try {
            $result = app()->make(\app\common\repositories\store\product\ProductReplyImportRepository::class)->import($path);
        } catch (\Throwable $e) {
            return app('json')->fail($e->getMessage());
        }
        return app('json')->success($result);
    }

    /**
     * 排序
     * @param $id
     * @return \think\response\Json
     * @author Qinii
     */
    public function sort($id)
    {
        $data = $this->request->params(['sort']);
        if (!$this->repository->exists($id))
            return app('json')->fail('数据不存在');
        $this->repository->update($id, $data);
        return app('json')->success('修改成功');
    }
}
