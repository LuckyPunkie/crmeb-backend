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


namespace app\common\repositories\store;

use app\common\dao\store\StorePrinterDao;
use app\common\repositories\BaseRepository;
use crmeb\services\printer\Printer;
use FormBuilder\Factory\Elm;
use think\exception\ValidateException;
use think\facade\Route;

class StorePrinterRepository extends BaseRepository
{
    public function __construct(StorePrinterDao $dao)
    {
        $this->dao = $dao;
    }


    /**
     * 创建或修改打印机表单
     *
     * 此方法用于生成用于添加或修改打印机的表单。根据$id$的存在与否，决定是创建新打印机还是修改已有的打印机。
     * 表单包含打印机的基本信息输入字段和一个选择打印机类型的选项，根据选择的打印机类型，显示不同的输入字段。
     *
     * @param int|null $id 打印机的ID。如果提供了ID，则表示修改现有打印机；如果为null，则表示创建新打印机。
     * @return \EasyWeChat\Kernel\Messages\Form|\FormBuilder\Form
     */
    public function form(?int $id)
    {
        // 根据$id$的存在与否决定是获取现有打印机的数据还是创建空的数据数组
        if ($id) {
            $formData = $this->dao->get($id)->toArray();
            $formActionUrl = Route::buildUrl('merchantStorePrinterUpdate', ['id' => $id])->build();
        } else {
            $formData = [];
            $formActionUrl = Route::buildUrl('merchantStorePrinterCreate')->build();
        }

        // 定义易联云打印机的表单字段
        $yun = [
            Elm::input('printer_name', '打印机名称：')->placeholder('请输入打印机名称')->required('请输入打印机名称'),
            Elm::input('printer_appkey', '应用ID：')->placeholder('请输入应用ID')->required('请输入应用ID'),
            Elm::input('printer_appid', '用户ID：')->placeholder('请输入用户ID')->required('请输入用户ID'),
            Elm::input('printer_secret', '应用密匙：')->placeholder('请输入应用密匙')->required('请输入应用密匙'),
            Elm::input('printer_terminal', '终端号：')->placeholder('请输入打印机终端号')->required('请输入打印机终端号')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '易联云打印机终端号打印机型号: 易联云打印机 K4无线版',
                ]
            ]),
            Elm::number('times', '打印联数：', 1)->min(1)->placeholder('请输入打印联数')->required('请输入打印联数'),
            Elm::radio('print_type', '打印时机：', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '支付后'],
                    ['value' => 2, 'label' => '下单后'],
                ]),
            Elm::switches('status', '是否开启：', 1)->inactiveValue(0)->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')
        ];
        // 定义飞鹅云打印机的表单字段
        $fei = [
            Elm::input('printer_name', '打印机名称：')->placeholder('请输入打印机名称')->required('请输入打印机名称'),
            Elm::input('printer_appid', 'USER：')->placeholder('请输入USER')->required('请输入USER')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '飞鹅云后台注册账号',
                ]
            ]),
            Elm::input('printer_appkey', 'UKEY：')->placeholder('请输入UKEY')->required('请输入UKEY')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '飞鹅云后台注册账号后生成的UKEY 【备注：这不是填打印机的KEY】',
                ]
            ]),
            Elm::input('printer_terminal', '飞鹅云SN：')->placeholder('请输入飞鹅云打印机SN')->required('请输入飞鹅云打印机SN')->appendRule('suffix', [
                'type' => 'div',
                'style' => ['color' => '#999999'],
                'domProps' => [
                    'innerHTML' => '打印机标签上的编号，必须要在管理后台里添加打印机或调用API接口添加之后，才能调用API',
                ]
            ]),
            Elm::number('times', '打印联数：', 1)->min(1)->placeholder('请输入打印联数')->required('请输入打印联数'),
            Elm::radio('print_type', '打印时机：', 1)
                ->setOptions([
                    ['value' => 1, 'label' => '支付后'],
                    ['value' => 2, 'label' => '下单后'],
                ]),
            Elm::switches('status', '是否开启：', 1)->inactiveValue(0)->activeValue(1)->inactiveText('关闭')->className('switch-width-double')->activeText('开启')
        ];

        // 根据打印机类型设置表单规则，包括易联云和飞鹅云两种类型的字段集合
        $form = Elm::createForm($formActionUrl);
        $form->setRule([
            Elm::radio('type', '打印机类型：', 0)
                ->setOptions([
                    ['value' => 0, 'label' => '易联云打印机'],
                    ['value' => 1, 'label' => '飞鹅云打印机'],
                ])->control([
                    ['value' => 0, 'rule' => $yun],
                    ['value' => 1, 'rule' => $fei]
                ]),
        ]);

        // 设置表单标题和初始数据，返回表单对象
        return $form->setTitle($id ? '修改打印机' : '添加打印机')->formData($formData);
    }

    /**
     * 获取商家列表
     *
     * 本函数用于根据条件查询商家信息，并支持分页查询。它接收查询条件、页码和每页的数量作为参数，
     * 返回包含商家数量和商家列表的数组。
     *
     * @param array $where 查询条件，以数组形式传递，用于指定查询的过滤条件。
     * @param int $page 当前页码，用于指定要返回的页码。
     * @param int $limit 每页的数量，用于指定每页返回的商家数量。
     * @return array 返回包含 'count' 和 'list' 两个元素的数组，'count' 为商家总数，'list' 为商家列表。
     */
    public function merList(array $where, int $page, int $limit)
    {
        // 根据查询条件获取查询对象
        $query = $this->dao->getSearch($where)->order('create_time DESC');

        // 计算满足条件的商家总数
        $count = $query->count();

        // 根据当前页码和每页的数量进行分页查询，并获取商家列表
        $list = $query->page($page, $limit)->select();

        // 将商家总数和商家列表打包成数组返回
        return compact('count', 'list');
    }

    /**
     * 系统列表查询方法
     * 用于根据条件获取系统列表的数据，支持分页查询
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页数据条数
     * @return array 返回包含数据总数和数据列表的数组
     */
    public function sysList(array $where, int $page, int $limit)
    {
        // 根据查询条件构造查询对象，并指定关联查询时 merchant 表只返回 mer_id 和 mer_name 两个字段
        $query = $this->dao->getSearch($where)->with([
            'merchant' => function ($query) {
                $query->field('mer_id,mer_name');
            },
        ]);

        // 计算满足条件的数据总数
        $count = $query->count();

        // 根据当前页码和每页数据条数进行分页查询，并获取数据列表
        $list = $query->page($page, $limit)->select();

        // 将数据总数和数据列表一起返回
        return compact('count', 'list');
    }

    /**
     * 检查打印机配置
     *
     * 本函数用于验证商家的打印机配置是否完整且正确。它首先检查打印功能是否已开启，然后获取并验证相关配置参数，
     * 包括客户端ID、API密钥、合作伙伴ID和终端号码。如果任何一项配置缺失或无效，将抛出一个验证异常。
     *
     * @param int $merId 商家ID，用于获取商家的打印配置
     * @return array 返回包含打印机配置的数组，包括客户端ID、API密钥、合作伙伴ID和终端号码
     * @throws ValidateException 如果打印功能未开启或打印机配置不完整或无效，抛出此异常
     */
    public function checkPrinterConfig(int $merId)
    {
        // 检查打印功能是否已开启
        if (!merchantConfig($merId, 'printing_status'))
            throw new ValidateException('打印功能未开启');

        // 组装打印机配置数组
        $config = [
            'clientId' => merchantConfig($merId, 'printing_client_id'),
            'apiKey' => merchantConfig($merId, 'printing_api_key'),
            'partner' => merchantConfig($merId, 'develop_id'),
            'terminal' => merchantConfig($merId, 'terminal_number')
        ];

        // 验证打印机配置是否完整
        if (!$config['clientId'] || !$config['apiKey'] || !$config['partner'] || !$config['terminal'])
            throw new ValidateException('打印机配置错误');

        // 返回验证通过的打印机配置
        return $config;
    }


    /**
     * 根据商家ID获取打印机配置信息
     *
     * 本函数用于根据传入的商家ID，从数据库中查询该商家的打印机配置信息。
     * 如果商家未配置打印机或打印机配置无效，则会尝试从商家配置中获取相关信息。
     * 如果所有尝试都失败，则会抛出一个异常，提示打印功能未开启或未添加打印机。
     *
     * @param int $merId 商家ID，用于查询特定商家的打印机配置。
     * @return array 返回包含打印机配置信息的数组，每个元素代表一个打印机的配置。
     * @throws ValidateException 如果打印功能未开启或未添加打印机，则抛出此异常。
     */
    public function getPrinter(int $merId)
    {
        // 检查商家的打印功能是否开启，如果未开启则抛出异常
        if (!merchantConfig($merId, 'printing_status'))
            throw new ValidateException('打印功能未开启');

        // 从数据库中查询商家ID为$merId且状态为1的打印机配置，返回type, clientId, terminal, partner, apiKey列
        $res = $this->dao->getSearch(['mer_id' => $merId, 'status' => 1])->column('
        type,
        printer_appkey clientId,
        printer_terminal terminal,
        printer_appid partner,
        printer_secret apiKey
        ');

        // 如果查询结果为空，则尝试从商家配置中获取打印机配置信息
        if (!$res) {
            $config = [
                'clientId' => merchantConfig($merId, 'printing_client_id'),
                'apiKey' => merchantConfig($merId, 'printing_api_key'),
                'partner' => merchantConfig($merId, 'develop_id'),
                'terminal' => merchantConfig($merId, 'terminal_number')
            ];
            // 如果商家配置中存在不完整的打印机配置，则将该配置添加到查询结果中
            if (!$config['clientId'] || !$config['apiKey'] || !$config['partner'] || !$config['terminal']) {
                $res[] = $config;
            }
        }

        // 如果最终仍未能获取到有效的打印机配置，则抛出异常
        if (!$res) throw new ValidateException('请添加打印机');
        // 返回获取到的打印机配置信息
        return $res;
    }


    public function startPrint($merId, $order, $product, $print_type = 1)
    {
        $where = ['mer_id' => $merId, 'status' => 1];
        if (!is_null($print_type)) $where['print_type'] = $print_type;
        $list = $this->dao->getSearch([])->where($where)->select();
        foreach ($list as $item) {
            if(!$item['print_content']) {
                throw new ValidateException('请前往小票打印列表中，点击设置打印机小票模版内容');
                break;
            }
            $content = '';
            if ($item['type'] == 0) { //易联云
                $name = 'yi_lian_yun';
                $configData = [
                    'partner' => $item['printer_appid'],
                    'clientId' => $item['printer_appkey'],
                    'apiKey' => $item['printer_secret'],
                    'terminal' => $item['printer_terminal'],
                    'type' => 0,
                ];
                $content = $this->ylyContent(json_decode($item['print_content'], true), $order, $product, $item['times'], $print_type);
            } else { //飞鹅云
                $name = 'fei_e_yun';
                $configData = [
                    'partner' => $item['printer_appid'],
                    'clientId' => $item['printer_appkey'],
                    'terminal' => $item['printer_terminal'],
                    'type' => 1,
                ];
                $content = $this->feyContent(json_decode($item['print_content'], true), $order, $product, $print_type);
            }
            $printer = new Printer($name, $configData);
            if ($content){
                $status = $printer->setPrinterContent($content, $item['times'])->startPrinter();
                if (!$status) throw new ValidateException($printer->getError());
            }
        }
    }

    public function ylyContent($printContent, $orderInfo, $product, $times, $print_type)
    {
        $goodsStr = '<table><tr><td>商品</td><td>单价</td><td>数量</td><td>金额</td></tr>';
        foreach ($product as $item) {
            $price = $item['price'];
            $num = $item['cart_num'];
            $prices = $item['total_price'];
            $goodsStr .= '<tr><td><FW2>----------------</FW2></td></tr>';
            $goodsStr .= '<tr>';
            $goodsStr .= "<td>{$item['store_name']} | {$item['suk']}</td><td>{$price}</td><td>{$num}</td><td>{$prices}</td>";
            $goodsStr .= '</tr>';
            if (in_array(1, $printContent['goods'])) {
                $goodsStr .= '<tr>';
                $goodsStr .= "<td>规格编码：{$item['bar_code']}</td>";
                $goodsStr .= '</tr>';
            }
            unset($price, $num, $prices);
        }
        $goodsStr .= '</table>';
        $addTime = $orderInfo['create_time'];
        $payTime = $orderInfo['pay_time'] ??  '';
        $printTime = date('Y-m-d H:i:s', time());
        $content = '';
        $content .= '<MN>' . $times . '</MN>';
        if ($printContent['header']) {
            $content .= '<FS2><center>' . $orderInfo->merchant->mer_name . '</center></FS2>';
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if ($printContent['delivery']) {
            switch ($orderInfo['order_type']) {
                case 0:
                    $content .= '配送方式：快递配送/上门服务 \r';
                    break;
                case 1:
                    $content .= '配送方式：门店自提/到店服务 \r';
                    break;
                case 2:
                    $content .= '配送方式：同城配送 \r';
                    break;
                case 20:
                    $content .= '配送方式：积分订单 \r';
                    break;
                default:
                    $content .= '配送方式：快递配送 \r';
                    break;
            }
            $content .= '客户姓名: ' . $orderInfo['real_name'] . ' \r';
            $content .= '客户电话: ' . $orderInfo['user_phone'] . ' \r';
            if ($orderInfo['order_type'] != 1) $content .= '收货地址: ' . $orderInfo['user_address'] . ' \r';
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if ($printContent['buyer_remarks']) {
            $content .= '买家备注: ' . $orderInfo['mark'] . ' \r';
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if (in_array(0, $printContent['goods'])) {
            $content .= '*************商品***************';
            $content .= '      \r';
            $content .= $goodsStr;
            $content .= '********************************\r';
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
            $content .= '<RA>合计：' . $orderInfo['total_price'] . '元</RA>';
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if ($printContent['preferential'] || $printContent['freight']) {
            if ($printContent['freight']) {
                $content .= '<RA>邮费：+' . $orderInfo['pay_postage'] . '元</RA>';
            }

            if ($printContent['preferential']) {
                $discount_price = bcadd($orderInfo['coupon_price'], $orderInfo['svip_discount'], 2);
                $content .= '<RA>优惠：-' . $discount_price . '元</RA>';
                $content .= '<RA>抵扣：-' . $orderInfo['integral_price'] . '元</RA>';
            }
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if (in_array(0, $printContent['pay'])) {
            if ($orderInfo['paid']) {
                switch ($orderInfo['pay_type']) {
                    case 0:
                        $content .= '<RA>支付方式：余额支付</RA>';
                        break;
                    case 1:
                        //notbreak;
                    case 2:
                        //notbreak;
                    case 3:
                        //notbreak;
                    case 6:
                        $content .= '<RA>支付方式：微信支付</RA>';
                        break;
                    case 4:
                        //notbreak;
                    case 5:
                        $content .= '<RA>支付方式：支付宝支付</RA>';
                        break;
                    case 7:
                        $content .= '<RA>支付方式：线下支付</RA>';
                        break;
                    default:
                        $content .= '<RA>支付方式：暂无</RA>';
                        break;
                }
            } else {
                $content .= '<RA>支付方式：暂无</RA>';
            }
        }
        if (in_array(1, $printContent['pay'])) {
            $content .= '<RA>实际支付：' . $orderInfo['pay_price'] . '元</RA>';
        }
        if (count($printContent['pay'])) {
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if (in_array(0, $printContent['order'])) {
            $content .= '订单编号：' . $orderInfo['order_sn'] . '\r';
        }
        if (in_array(1, $printContent['order'])) {
            $content .= '下单时间：' . $addTime . '\r';
        }
        if (in_array(2, $printContent['order'])) {
            $content .= '支付时间：' . $payTime . '\r';
        }
        if (in_array(3, $printContent['order'])) {
            $content .= '打印时间：' . $printTime . '\r';
        }
        if (count($printContent['order'])) {
            $content .= '<FH2><FW2>----------------</FW2></FH2>';
        }
        if ($printContent['code'] && $printContent['code_url']) {
            $content .= '<QR>' . trim(systemConfig('site_url'),'/') . $printContent['code_url'] . '</QR>';
            $content .= '      \r';
        }
        if ($printContent['show_notice']) {
            $content .= '<center>' . $printContent['notice_content'] . '</center>';
            $content .= '      \r';
        }
        return $content;
    }

    public function feyContent($printContent, $orderInfo, $product, $print_type)
    {
        //halt($orderInfo->toArray());
        $printTime = date('Y-m-d H:i:s', time());
        $addTime = $orderInfo['create_time'];
        $payTime = $orderInfo['pay_time'] ?? '';
        $content = '';
        if ($printContent['header']) {
            $content .= '<CB>' . $orderInfo->merchant->mer_name . '</CB><BR>';
            $content .= '--------------------------------<BR>';
        }
        if ($printContent['delivery']) {
            switch ($orderInfo['order_type']) {
                case 0:
                    $content .= '配送方式：快递配送/上门服务<BR>';
                    break;
                case 1:
                    $content .= '配送方式：门店自提/到店服务<BR>';
                    break;
                case 2:
                    $content .= '配送方式：同城配送<BR>';
                    break;
                case 20:
                    $content .= '配送方式：积分订单<BR>';
                    break;
                default:
                    $content .= '配送方式：快递配送<BR>';
                    break;
            }
            $content .= '客户姓名: ' . $orderInfo['real_name'] . '<BR>';
            $content .= '客户电话: ' . $orderInfo['user_phone'] . '<BR>';
            if ($orderInfo['order_type'] != 1) {
                $content .= '收货地址：' . $orderInfo['user_address'] . '<BR>';
            }
            $content .= '--------------------------------<BR>';
        }
        if ($printContent['buyer_remarks']) {
            $content .= '买家备注：' . $orderInfo['mark'] . '<BR>';
            $content .= '--------------------------------<BR>';
        }

        if (in_array(0, $printContent['goods'])) {
            $content .= '<BR>';
            $content .= '**************商品**************<BR>';
            $content .= '<BR>';
            $content .= '名称           单价  数量 金额<BR>';
            foreach ($product as $item) {
                $content .= '--------------------------------<BR>';
                $name = $item['store_name'] . " | " . $item['suk'];
                $price = $item['price'];
                $num = $item['cart_num'];
                $prices = $item['total_price'];
                $kw3 = '';
                $kw1 = '';
                $kw2 = '';
                $kw4 = '';
                $str = $name;
                $blankNum = 14;//名称控制为14个字节
                $lan = mb_strlen($str, 'utf-8');
                $m = 0;
                $j = 1;
                $blankNum++;
                $result = array();
                if (strlen($price) < 6) {
                    $k1 = 6 - strlen($price);
                    for ($q = 0; $q < $k1; $q++) {
                        $kw1 .= ' ';
                    }
                    $price = $price . $kw1;
                }
                if (strlen($num) < 3) {
                    $k2 = 3 - strlen($num);
                    for ($q = 0; $q < $k2; $q++) {
                        $kw2 .= ' ';
                    }
                    $num = $num . $kw2;
                }
                if (strlen($prices) < 6) {
                    $k3 = 6 - strlen($prices);
                    for ($q = 0; $q < $k3; $q++) {
                        $kw4 .= ' ';
                    }
                    $prices = $prices . $kw4;
                }
                for ($i = 0; $i < $lan; $i++) {
                    $new = mb_substr($str, $m, $j, 'utf-8');
                    $j++;
                    if (mb_strwidth($new, 'utf-8') < $blankNum) {
                        if ($m + $j > $lan) {
                            $m = $m + $j;
                            $tail = $new;
                            $lenght = iconv("UTF-8", "GBK//IGNORE", $new);
                            $k = 14 - strlen($lenght);
                            for ($q = 0; $q < $k; $q++) {
                                $kw3 .= ' ';
                            }
                            if ($m == $j) {
                                $tail .= $kw3 . ' ' . $price . ' ' . $num . ' ' . $prices;
                            } else {
                                $tail .= $kw3 . '<BR>';
                            }
                            break;
                        } else {
                            $next_new = mb_substr($str, $m, $j, 'utf-8');
                            if (mb_strwidth($next_new, 'utf-8') < $blankNum) {
                                continue;
                            } else {
                                $m = $i + 1;
                                $result[] = $new;
                                $j = 1;
                            }
                        }
                    }
                }
                $head = '';
                foreach ($result as $key => $value) {
                    if ($key < 1) {
                        $v_lenght = iconv("UTF-8", "GBK//IGNORE", $value);
                        $v_lenght = strlen($v_lenght);
                        if ($v_lenght == 13) $value = $value . " ";
                        $head .= $value . ' ' . $price . ' ' . $num . ' ' . $prices;
                    } else {
                        $head .= $value . '<BR>';
                    }
                }
                $content .= $head . $tail;
                if (in_array(1, $printContent['goods'])) {
                    $content .= '规格编码：' . $item['bar_code'] . '<BR>';
                }
                unset($price);
            }
            $content .= '<BR>';
            $content .= '********************************<BR>';
            $content .= '<BR>';
            $content .= '--------------------------------<BR>';
            $content .= '<RIGHT>合计：' . number_format($orderInfo['total_price'], 2) . '元</RIGHT>';
            $content .= '--------------------------------<BR>';
        }
        if ($printContent['preferential'] || $printContent['freight']) {
            if ($printContent['freight']) {
                $content .= '<RIGHT>邮费：+' . number_format($orderInfo['pay_postage'], 2) . '元</RIGHT><BR>';
            }
            if ($printContent['preferential']) {
                $discount_price = bcadd($orderInfo['coupon_price'], $orderInfo['svip_discount'], 2);
                $content .= '<RIGHT>优惠：-' . number_format($discount_price, 2) . '元</RIGHT><BR>';
                $content .= '<RIGHT>抵扣：-' . number_format($orderInfo['integral_price'], 2) . '元</RIGHT>';
            }
            $content .= '--------------------------------<BR>';
        }
        if (in_array(0, $printContent['pay'])) {
            if ($orderInfo['paid']) {
                switch ($orderInfo['pay_type']) {
                    case 0:
                        $content .= '<RIGHT>支付方式：余额支付</RIGHT><BR>';
                        break;
                    case 1:
                        //notbreak;
                    case 2:
                        //notbreak;
                    case 3:
                        //notbreak;
                    case 6:
                        $content .= '<RIGHT>支付方式：微信支付</RIGHT><BR>';
                        break;
                    case 4:
                        //notbreak;
                    case 5:
                        $content .= '<RIGHT>支付方式：支付宝支付</RIGHT><BR>';
                        break;
                    case 7:
                        $content .= '<RIGHT>支付方式：线下支付</RIGHT><BR>';
                        break;
                    default:
                        $content .= '<RIGHT>支付方式：暂无</RIGHT><BR>';
                        break;
                }
            } else {
                $content .= '<RIGHT>支付方式：暂无/未支付</RIGHT><BR>';
            }
        }
        if (in_array(1, $printContent['pay'])) {
            $content .= '<RIGHT>实际支付：' . number_format($orderInfo['pay_price'], 2) . '元</RIGHT>';
        }
        if (count($printContent['pay'])) {
            $content .= '--------------------------------<BR>';
        }
        if (in_array(0, $printContent['order'])) {
            $content .= '订单编号：' . $orderInfo['order_sn'] . '<BR>';
        }
        if (in_array(1, $printContent['order'])) {
            $content .= '下单时间: ' . $addTime . '<BR>';
        }
        if (in_array(2, $printContent['order'])) {
            $content .= '付款时间: ' . $payTime . '<BR>';
        }
        if (in_array(3, $printContent['order'])) {
            $content .= '打印时间: ' . $printTime . '<BR>';
        }
        if (count($printContent['order'])) {
            $content .= '--------------------------------<BR>';
            $content .= '<BR>';
        }
        if ($printContent['code'] && $printContent['code_url']) {
            $content .= '<QR>' . trim(systemConfig('site_url'),'/') . $printContent['code_url'] . '</QR>';
        }
        if ($printContent['show_notice']) {
            $content .= '<C>' . $printContent['notice_content'] . '</C>';
        }
        return $content;
    }


}
