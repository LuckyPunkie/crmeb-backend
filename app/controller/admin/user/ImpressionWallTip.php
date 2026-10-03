<?php

namespace app\controller\admin\user;

use app\common\repositories\user\UserImpressionRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 印象墙开关确认弹窗文案（App 个人资料页打开 / 关闭印象墙开关时弹出）
 */
class ImpressionWallTip extends BaseController
{
    protected $repository;

    public function __construct(App $app, UserImpressionRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 返回当前生效文案 + 默认文案（后台据此区分「自定义」与「默认」）
     */
    public function detail()
    {
        $defaults = [];
        foreach (UserImpressionRepository::WALL_TIP_DEFAULTS as $type => [$title, $content]) {
            $defaults[$type] = compact('title', 'content');
        }
        return app('json')->success($this->repository->getWallTips() + ['defaults' => $defaults]);
    }

    public function save()
    {
        $data = $this->request->params([
            ['open_title', ''], ['open_content', ''],
            ['close_title', ''], ['close_content', ''],
        ]);
        $this->repository->saveWallTips([
            'open'  => ['title' => $data['open_title'], 'content' => $data['open_content']],
            'close' => ['title' => $data['close_title'], 'content' => $data['close_content']],
        ]);
        return app('json')->success('保存成功');
    }
}
