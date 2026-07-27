<?php

namespace app\controller\admin\system\merchant;

use think\App;
use think\facade\Db;
use think\facade\Route;
use FormBuilder\Factory\Elm;
use crmeb\basic\BaseController;
use app\common\repositories\system\merchant\MerchantLabelRepository;
use think\exception\ValidateException;

class MerchantLabel extends BaseController
{
    protected $repository;

    public function __construct(App $app, MerchantLabelRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['label_name']);
        return app('json')->success($this->repository->lst($where, $page, $limit));
    }

    public function create()
    {
        $data = $this->request->params([
            'label_name',
            ['has_deposit', 0],
            ['deposit_amount', 0],
            ['description', ''],
            ['show_description', 0],
            ['logo', ''],
            ['announcement_name', ''],
        ]);
        if (empty($data['label_name'])) {
            return app('json')->fail('标签名称不能为空');
        }
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    public function update($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        $data = $this->request->params([
            'label_name',
            ['has_deposit', 0],
            ['deposit_amount', 0],
            ['description', ''],
            ['show_description', 0],
            ['logo', ''],
            ['announcement_name', ''],
        ]);
        if (empty($data['label_name'])) {
            return app('json')->fail('标签名称不能为空');
        }
        $this->repository->update((int)$id, $data);
        return app('json')->success('修改成功');
    }

    public function delete($id)
    {
        if (!$id) return app('json')->fail('参数错误');
        $this->repository->delete((int)$id);
        return app('json')->success('删除成功');
    }

    public function options()
    {
        return app('json')->success($this->repository->getAll());
    }

    /**
     * 标签保证金列表（admin）
     */
    public function depositLst()
    {
        [$page, $limit] = $this->getPage();
        $keyword = $this->request->param('keyword', '');
        $is_margin = $this->request->param('is_margin', '');

        $query = \think\facade\Db::name('merchant_label_store')
            ->alias('ls')
            ->join('merchant_label l', 'ls.label_id = l.id')
            ->join('merchant m', 'ls.mer_id = m.mer_id')
            ->where('l.has_deposit', 1)
            ->field('ls.id, ls.mer_id, ls.label_id, ls.is_margin, ls.paid_deposit, ls.create_time, l.label_name, l.deposit_amount, m.mer_name, m.real_name');

        if ($keyword !== '') {
            $query->where('m.mer_name|m.real_name', 'like', '%' . $keyword . '%');
        }
        if ($is_margin !== '') {
            $query->where('ls.is_margin', (int)$is_margin);
        }

        $total = $query->count();
        $list  = $query->page($page, $limit)->order('ls.id DESC')->select()->toArray();

        return app('json')->success(compact('list', 'total'));
    }

    public function localLabelMarginForm($id)
    {
        $record = Db::name('merchant_label_store')
            ->alias('ls')
            ->join('merchant_label l', 'ls.label_id = l.id')
            ->join('merchant m', 'ls.mer_id = m.mer_id')
            ->where('ls.id', (int)$id)
            ->field('ls.id, ls.is_margin, l.label_name, l.deposit_amount, m.mer_name')
            ->find();

        if (!$record) throw new ValidateException('记录不存在');
        if ($record['is_margin'] == 10) throw new ValidateException('保证金已缴纳');

        $form = Elm::createForm(Route::buildUrl('systemMarginLabelLocalSet', ['id' => $id])->build());
        $form->setRule([
            ['type' => 'span', 'title' => '标签名称：', 'native' => false, 'children' => [(string)$record['label_name']]],
            ['type' => 'span', 'title' => '店铺名称：', 'native' => false, 'children' => [(string)$record['mer_name']]],
            ['type' => 'span', 'title' => '保证金金额：', 'native' => false, 'children' => [(string)$record['deposit_amount'] . ' 元']],
            Elm::radio('status', '操作：', 0)->options([
                ['value' => 0, 'label' => '未缴纳'],
                ['value' => 1, 'label' => '已缴纳'],
            ]),
        ]);

        return app('json')->success(formToData($form->setTitle('线下缴纳标签保证金')));
    }

    public function localLabelMarginSet($id)
    {
        $status = (int)$this->request->param('status', 0);
        if (!$status) return app('json')->success('操作成功');

        $record = Db::name('merchant_label_store')
            ->alias('ls')
            ->join('merchant_label l', 'ls.label_id = l.id')
            ->where('ls.id', (int)$id)
            ->field('ls.id, ls.is_margin, l.deposit_amount')
            ->find();

        if (!$record) throw new ValidateException('记录不存在');
        if ($record['is_margin'] == 10) throw new ValidateException('保证金已缴纳');

        Db::execute(
            "UPDATE eb_merchant_label_store SET is_margin=10, paid_deposit=? WHERE id=?",
            [$record['deposit_amount'], (int)$id]
        );

        return app('json')->success('操作成功');
    }
}
