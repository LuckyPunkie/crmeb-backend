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
            ['logo', ''],
        ]);
        if (empty($data['label_name'])) {
            return app('json')->fail('标签名称不能为空');
        }
        $this->checkColor($data['logo']);
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
            ['logo', ''],
        ]);
        if (empty($data['label_name'])) {
            return app('json')->fail('标签名称不能为空');
        }
        $this->checkColor($data['logo']);
        $this->repository->update((int)$id, $data);
        return app('json')->success('修改成功');
    }

    private function checkColor(string $v): void
    {
        if ($v === '') return;
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
            throw new ValidateException('标签色值格式错误，应为 #RRGGBB');
        }
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

    /**
     * 标签保证金扣费表单
     */
    public function deductForm($id)
    {
        $record = Db::name('merchant_label_store')
            ->alias('ls')
            ->join('merchant_label l', 'ls.label_id = l.id')
            ->join('merchant m', 'ls.mer_id = m.mer_id')
            ->where('ls.id', (int)$id)
            ->field('ls.id, ls.mer_id, ls.paid_deposit, ls.is_margin, l.label_name, m.mer_name')
            ->find();
        if (!$record) throw new ValidateException('记录不存在');
        if ($record['is_margin'] != 10) throw new ValidateException('该标签保证金未缴纳或已退款，无法扣费');

        $form = Elm::createForm(Route::buildUrl('systemMarginLabelDeduct', ['id' => $id])->build());
        $form->setRule([
            ['type' => 'span', 'title' => '店铺：', 'native' => false, 'children' => [(string)$record['mer_name']]],
            ['type' => 'span', 'title' => '标签：', 'native' => false, 'children' => [(string)$record['label_name']]],
            ['type' => 'span', 'title' => '当前保证金：', 'native' => false, 'children' => [(string)$record['paid_deposit'] . ' 元']],
            Elm::number('extract_money', '扣费金额', 0)->min(0.01)->max((float)$record['paid_deposit'])->precision(2)->required(),
            Elm::input('mark', '扣费备注', '')->required(),
        ]);
        return app('json')->success(formToData($form->setTitle('标签保证金扣费')));
    }

    /**
     * 标签保证金扣费提交
     */
    public function deduct($id)
    {
        $data = $this->request->params([['extract_money', 0], ['mark', '']]);
        $extract = (float)$data['extract_money'];
        $mark = trim((string)$data['mark']);
        if ($extract <= 0) throw new ValidateException('扣费金额必须大于 0');
        if ($mark === '') throw new ValidateException('请填写扣费备注');

        $record = Db::name('merchant_label_store')
            ->alias('ls')
            ->join('merchant_label l', 'ls.label_id = l.id')
            ->where('ls.id', (int)$id)
            ->field('ls.id, ls.mer_id, ls.label_id, ls.paid_deposit, ls.is_margin, l.label_name')
            ->find();
        if (!$record) throw new ValidateException('记录不存在');
        if ($record['is_margin'] != 10) throw new ValidateException('该标签保证金未缴纳或已退款，无法扣费');
        if (bccomp((string)$extract, (string)$record['paid_deposit'], 2) > 0) {
            throw new ValidateException('扣费金额不能超过当前保证金余额');
        }

        $newPaid = bcsub((string)$record['paid_deposit'], (string)$extract, 2);
        Db::transaction(function () use ($id, $newPaid, $record, $extract, $mark) {
            Db::name('merchant_label_store')
                ->where('id', (int)$id)
                ->update([
                    'paid_deposit' => $newPaid,
                    'update_time'  => date('Y-m-d H:i:s'),
                ]);
            // 记账户流水便于审计（沿用 CRMEB 的 mer_margin 分类）
            app()->make(\app\common\repositories\user\UserBillRepository::class)->bill(
                0, 'mer_margin', 'label_margin_deduct', 0, [
                    'title'   => '标签保证金扣费',
                    'mer_id'  => (int)$record['mer_id'],
                    'number'  => $extract,
                    'mark'    => '标签「' . $record['label_name'] . '」扣费：' . $mark . '【操作者：' . request()->adminId() . '|' . (request()->adminInfo()->real_name ?? '') . '】',
                    'balance' => $newPaid,
                ]
            );
        });
        return app('json')->success('扣费成功');
    }
}
