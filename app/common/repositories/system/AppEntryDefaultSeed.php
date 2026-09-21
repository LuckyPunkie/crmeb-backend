<?php

namespace app\common\repositories\system;

/**
 * App 入口默认种子（与 app-module-switch-plan.md §5.6 一致）
 */
class AppEntryDefaultSeed
{
    public static function slots(): array
    {
        return [
            ['slot_key' => 'home_publish_note_types', 'section_title' => '', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '发布笔记', 'sort' => 10],
            [
                'slot_key' => 'service_official_pick',
                'section_title' => '官方甄选',
                'section_note' => '平台用心之选，让生活更美好',
                'section_note_color' => '#0231F9',
                'section_note_bg' => '#EBF0FF',
                'tab_label' => '服务页',
                'sort' => 20,
            ],
            [
                'slot_key' => 'service_daily_coupon',
                'section_title' => '每日好券',
                'section_note' => '外卖打车咖啡奶茶，天天发券折上折！',
                'section_note_color' => '#B03D12',
                'section_note_bg' => '#FAE8DB',
                'tab_label' => '每日好券',
                'sort' => 25,
            ],
            ['slot_key' => 'user_gift_row', 'section_title' => '', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '我的·礼物', 'sort' => 30],
            ['slot_key' => 'user_community_grid', 'section_title' => '逛逛社区', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '我的·逛逛', 'sort' => 40],
            ['slot_key' => 'user_job_seek', 'section_title' => '求职管理', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '求职管理', 'sort' => 50],
            ['slot_key' => 'user_job_recruit', 'section_title' => '招聘管理', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '招聘管理', 'sort' => 60],
            ['slot_key' => 'user_service', 'section_title' => '我的服务', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '我的服务', 'sort' => 70],
            ['slot_key' => 'user_merchant_manage', 'section_title' => '掌上经营', 'section_note' => '', 'section_note_color' => '', 'section_note_bg' => '', 'tab_label' => '掌上经营', 'sort' => 80],
        ];
    }

    /**
     * @return array{groups: array, items: array}
     */
    public static function groupsAndItems(): array
    {
        $groups = [];
        $items = [];
        $gid = 0;

        $withHideFlags = static function (array $row): array {
            $legacy = !empty($row['hide_when_review']) ? 1 : 0;
            unset($row['hide_when_review']);
            $row['hide_when_review_ios'] = isset($row['hide_when_review_ios'])
                ? (int)(!empty($row['hide_when_review_ios']))
                : $legacy;
            $row['hide_when_review_android'] = isset($row['hide_when_review_android'])
                ? (int)(!empty($row['hide_when_review_android']))
                : $legacy;
            $row['hide_when_review_routine'] = isset($row['hide_when_review_routine'])
                ? (int)(!empty($row['hide_when_review_routine']))
                : $legacy;
            $row['hide_when_review'] = (
                $row['hide_when_review_ios']
                || $row['hide_when_review_android']
                || $row['hide_when_review_routine']
            ) ? 1 : 0;
            return $row;
        };

        $flat = function (string $slot, array $rows, int $sortStart = 0) use (&$items, $withHideFlags) {
            $sort = $sortStart;
            foreach ($rows as $row) {
                $sort += 10;
                $items[] = array_merge([
                    'slot_key' => $slot,
                    'group_id' => null,
                    'sort' => $sort,
                ], $withHideFlags($row));
            }
        };

        $flat('home_publish_note_types', [
            ['item_key' => 'note_image', 'name' => '图文笔记', 'subtitle' => '分享购物心得、穿搭日常', 'icon' => '', 'tone' => '', 'url' => '/pages/plant_grass/plant_release/index?community_type=0&is_type=1', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'note_video', 'name' => '视频笔记', 'subtitle' => '发布短视频内容', 'icon' => '', 'tone' => '', 'url' => '/pages/plant_grass/plant_release/index?community_type=0&is_type=2', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'redpacket', 'name' => '红包求助', 'subtitle' => '发布悬赏求助，邀请大家帮忙', 'icon' => '', 'tone' => 'orange', 'url' => '/pages/plant_grass/plant_release/index?community_type=1&is_type=1', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 1],
            ['item_key' => 'paid_read', 'name' => '付费阅读', 'subtitle' => '发布付费内容，知识变现', 'icon' => '', 'tone' => 'blue', 'url' => '/pages/plant_grass/plant_release/index?community_type=2&is_type=1', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 1],
            ['item_key' => 'recruit', 'name' => '招聘信息', 'subtitle' => '发布招聘岗位', 'icon' => '', 'tone' => 'green', 'url' => '/pages/plant_grass/plant_release/index?community_type=3&is_type=1', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 1],
        ]);

        $flat('service_official_pick', [
            ['item_key' => 'nearby', 'name' => '附近好店', 'subtitle' => '', 'icon' => '/static/images/service/icon-nearby.svg', 'tone' => '', 'url' => '/pages/nearby/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'equity', 'name' => '我的股份', 'subtitle' => '', 'icon' => '/static/images/service/icon-equity.svg', 'tone' => '', 'url' => '/pages/equity/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'animal_rescue', 'name' => '动物救助', 'subtitle' => '', 'icon' => '/static/images/service/icon-rescue.svg', 'tone' => '', 'url' => '/pages/animal_rescue/index/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 1],
            ['item_key' => 'blindbox', 'name' => '惊喜盲盒', 'subtitle' => '', 'icon' => '/static/images/service/icon-blindbox.svg', 'tone' => '', 'url' => '/pages/blindbox/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
        ]);

        // 每日好券：act_id 来自聚推客，名称/图标可运营改；与原 DAILY_FIRST_PAGE 对齐
        $flat('service_daily_coupon', self::dailyCouponItems());

        $flat('user_gift_row', [
            ['item_key' => 'gift_received', 'name' => '我收到的礼物', 'subtitle' => '', 'icon' => '/static/images/user/icon-gift-received.svg', 'tone' => '', 'url' => '/pages/gifts/received/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'gift_sent', 'name' => '我送出的礼物', 'subtitle' => '', 'icon' => '/static/images/user/icon-gift-sent.svg', 'tone' => '', 'url' => '/pages/gifts/sent/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
        ]);

        $flat('user_community_grid', [
            ['item_key' => 'community_redpacket', 'name' => '红包求助', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-redpacket.svg', 'tone' => 'orange', 'url' => '/pages/user_more/redpacket/list/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'community_notes', 'name' => '我的笔记', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-note.svg', 'tone' => '', 'url' => '/pages/user_more/notes/list/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'community_paid', 'name' => '付费笔记', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-note-paid.svg', 'tone' => 'green', 'url' => '/pages/user_more/paid/manage/index?tab=unlocked', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'community_paid_income', 'name' => '付费收益', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-income.svg', 'tone' => 'orange', 'url' => '/pages/user_more/paid/manage/index?tab=published', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
        ]);

        $flat('user_job_seek', [
            ['item_key' => 'job_resume', 'name' => '我的简历', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-resume.svg', 'tone' => '', 'url' => '/pages/user_more/resume/index/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'job_applications', 'name' => '投递记录', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-mail.svg', 'tone' => 'green', 'url' => '/pages/user_more/recruit/applications/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'job_favorites', 'name' => '求职收藏', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-briefcase.svg', 'tone' => 'orange', 'url' => '/pages/user_more/recruit/favorites/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'job_interview', 'name' => '面试通知', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-bell.svg', 'tone' => '', 'url' => '/pages/user_more/recruit/interview/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
        ]);

        $flat('user_job_recruit', [
            ['item_key' => 'recruit_posts', 'name' => '岗位管理', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-job.svg', 'tone' => '', 'url' => '/pages/merchant/recruit/list/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'recruit_apps', 'name' => '应聘管理', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-mail.svg', 'tone' => 'orange', 'url' => '/pages/merchant/recruit/list/index?tab=applications', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'recruit_pending', 'name' => '待处理', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-bell.svg', 'tone' => 'green', 'url' => '/pages/merchant/recruit/list/index?tab=applications&status=pending', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'recruit_talent_fav', 'name' => '人选收藏', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-heart.svg', 'tone' => 'orange', 'url' => '/pages/merchant/recruit/talent-fav/index', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
        ]);

        $flat('user_merchant_manage', [
            ['item_key' => 'mer_workbench', 'name' => '工作台', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-monitor.svg', 'tone' => '', 'url' => '/pages/admin/business/index?is_sys=0', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'mer_staff_orders', 'name' => '工单管理', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-job.svg', 'tone' => 'orange', 'url' => '/pages/staff/order_list', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'mer_delivery', 'name' => '配送管理', 'subtitle' => '', 'icon' => '/static/images/user/icon-order-receive.svg', 'tone' => 'green', 'url' => '/pages/delivery/order_list', 'link_type' => 'path', 'action_key' => '', 'hide_when_review' => 0],
            ['item_key' => 'mer_platform_cs', 'name' => '平台客服', 'subtitle' => '', 'icon' => '/static/images/user/icon-menu-chat.svg', 'tone' => '', 'url' => '', 'link_type' => 'action', 'action_key' => 'platformCustomer', 'hide_when_review' => 0],
        ]);

        $serviceGroups = [
            '账户资产' => [
                ['item_key' => 'svc_balance', 'name' => '我的余额', 'icon' => '/static/images/user/icon-menu-balance.svg', 'tone' => '', 'url' => '/pages/users/user_money/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_svip', 'name' => '会员中心', 'icon' => '/static/images/user/icon-menu-vip.svg', 'tone' => '', 'url' => '', 'link_type' => 'action', 'action_key' => 'svip'],
                ['item_key' => 'svc_grade', 'name' => '我的等级', 'icon' => '/static/images/user/icon-menu-trophy.svg', 'tone' => '', 'url' => '/pages/users/user_grade/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_integral', 'name' => '积分中心', 'icon' => '/static/images/user/icon-menu-diamond.svg', 'tone' => '', 'url' => '/pages/users/user_integral/index', 'link_type' => 'path', 'action_key' => ''],
            ],
            '消费记录' => [
                ['item_key' => 'svc_collection', 'name' => '我的收藏', 'icon' => '/static/images/user/icon-menu-star.svg', 'tone' => '', 'url' => '/pages/users/user_goods_collection/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_shop_follow', 'name' => '关注店铺', 'icon' => '/static/images/user/icon-menu-shop.svg', 'tone' => 'orange', 'url' => '/pages/users/user_goods_collection/index?tab=2', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_history', 'name' => '浏览记录', 'icon' => '/static/images/user/icon-menu-eye.svg', 'tone' => 'green', 'url' => '/pages/user_more/browsingHistory/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_coupon', 'name' => '优惠券', 'icon' => '/static/images/user/icon-menu-coupon.svg', 'tone' => '', 'url' => '/pages/users/user_coupon/index', 'link_type' => 'path', 'action_key' => ''],
            ],
            '购物服务' => [
                ['item_key' => 'svc_goods_manage', 'name' => '商品管理', 'icon' => '/static/images/user/icon-menu-bag.svg', 'tone' => '', 'url' => '', 'link_type' => 'action', 'action_key' => 'goodsManage'],
                ['item_key' => 'svc_address', 'name' => '地址管理', 'icon' => '/static/images/user/icon-menu-pin.svg', 'tone' => '', 'url' => '/pages/users/user_address_list/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_invoice', 'name' => '发票管理', 'icon' => '/static/images/user/icon-menu-invoice.svg', 'tone' => '', 'url' => '/pages/users/user_invoice_list/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_customer', 'name' => '联系客服', 'icon' => '/static/images/user/icon-menu-chat.svg', 'tone' => '', 'url' => '', 'link_type' => 'action', 'action_key' => 'customer'],
            ],
            '营销推广' => [
                ['item_key' => 'svc_spread', 'name' => '分销中心', 'icon' => '/static/images/user/icon-menu-chart.svg', 'tone' => 'orange', 'url' => '/pages/user_more/user_spread_user/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_distributor', 'name' => '分销', 'icon' => '/static/images/user/icon-menu-megaphone.svg', 'tone' => '', 'url' => '/pages/user_more/distributor/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_agent_apply', 'name' => '代理申请', 'icon' => '/static/images/user/icon-menu-handshake.svg', 'tone' => 'green', 'url' => '/pages/agent/form', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_activity', 'name' => '活动', 'icon' => '/static/images/user/icon-menu-heart.svg', 'tone' => '', 'url' => '/pages/activity/registrate_activity/index', 'link_type' => 'path', 'action_key' => ''],
            ],
            '更多' => [
                ['item_key' => 'svc_sign', 'name' => '签到', 'icon' => '/static/images/user/icon-menu-calendar.svg', 'tone' => '', 'url' => '/pages/users/user_sgin/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_assist', 'name' => '助力记录', 'icon' => '/static/images/user/icon-menu-gift.svg', 'tone' => 'orange', 'url' => '/pages/activity/assist_record/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_mer_apply', 'name' => '商户入驻', 'icon' => '/static/images/user/icon-menu-shop.svg', 'tone' => 'green', 'url' => '/pages/merchant/apply/index', 'link_type' => 'path', 'action_key' => ''],
                ['item_key' => 'svc_mer_label', 'name' => '商家标签', 'icon' => '/static/images/user/icon-menu-tag.svg', 'tone' => 'blue', 'url' => '/pages/merchant/label/index', 'link_type' => 'path', 'action_key' => ''],
            ],
        ];

        $gSort = 0;
        foreach ($serviceGroups as $title => $groupItems) {
            $gSort += 10;
            $gid++;
            $groups[] = [
                'group_id' => $gid,
                'slot_key' => 'user_service',
                'title' => $title,
                'sort' => $gSort,
            ];
            $iSort = 0;
            foreach ($groupItems as $gi) {
                $iSort += 10;
                $items[] = array_merge([
                    'slot_key' => 'user_service',
                    'group_id' => $gid,
                    'subtitle' => '',
                    'hide_when_review' => 0,
                    'hide_when_review_ios' => 0,
                    'hide_when_review_android' => 0,
                    'hide_when_review_routine' => 0,
                    'sort' => $iSort,
                ], $gi);
            }
        }

        return ['groups' => $groups, 'items' => $items];
    }

    protected static $defaultIconMapCache = null;

    public static function defaultIconMap(): array
    {
        if (self::$defaultIconMapCache !== null) {
            return self::$defaultIconMapCache;
        }
        $map = [];
        $seed = self::groupsAndItems();
        foreach ($seed['items'] as $it) {
            $key = trim((string)($it['item_key'] ?? ''));
            $icon = trim((string)($it['icon'] ?? ''));
            if ($key !== '' && $icon !== '') {
                $map[$key] = $icon;
            }
        }
        self::$defaultIconMapCache = $map;
        return $map;
    }

    /**
     * 每日好券默认入口（约 3 页×8；act_id 以线上聚推客为准，运营可改名/图标）
     */
    public static function dailyCouponItems(): array
    {
        $icon = static function (string $file): string {
            return 'https://img.jutuike.com/taokeout/icon/' . $file;
        };
        $row = static function (string $key, string $name, int $actId, string $iconFile) use ($icon): array {
            return [
                'item_key' => $key,
                'name' => $name,
                'subtitle' => '',
                'icon' => $icon($iconFile),
                'tone' => '',
                'url' => '',
                'link_type' => 'jtk_act',
                'action_key' => '',
                'act_id' => $actId,
                'hide_when_review' => 0,
            ];
        };
        return [
            // 第 1 页（原 HTML / DAILY_FIRST_PAGE）
            $row('daily_eleme', '饿了么', 69, 'ele.png'),
            $row('daily_meituan', '美团外卖', 9, 'meituan.png'),
            $row('daily_luckin', '瑞幸', 33, 'luckin.png'),
            $row('daily_didi', '滴滴打车', 18, 'didi_dc_icon.png'),
            $row('daily_movie', '电影票', 76, 'tq_cinema_jtk_icon.png'),
            $row('daily_fliggy_hotel', '飞猪酒店', 113, 'feizhu_hotel_jtk.png'),
            $row('daily_fliggy_flight', '机票', 95, 'didi_dc_icon.png'),
            $row('daily_didi_drive', '滴滴代驾', 53, 'didi_daijia_jtk.png'),
            // 第 2 页
            $row('daily_kfc', '肯德基', 58, 'tqkfc.png'),
            $row('daily_pizzahut', '必胜客', 64, 'pizzahut_jtk_icon.png'),
            $row('daily_starbucks', '星巴克', 34, 'spk.png'),
            $row('daily_heytea', '喜茶', 37, 'heytea.png'),
            $row('daily_nayuki', '奈雪', 32, 'nayuki.png'),
            $row('daily_burgerking', '汉堡王', 46, 'burgerking.png'),
            $row('daily_pagoda', '百果园', 31, 'pagoda.png'),
            $row('daily_huaxiaozhu', '花小猪', 44, 'jtk_hxz_icon.png'),
            // 第 3 页
            $row('daily_jd', '京东', 23, 'jd.png'),
            $row('daily_pdd', '拼多多', 12, 'miaosha.png'),
            $row('daily_tongcheng', '同程打车', 87, 'tcyl_icon_jtk.png?v=2'),
            $row('daily_meituan_sg', '美团闪购', 8, 'shangou.png'),
            $row('daily_fliggy_car', '飞猪租车', 145, 'feizhu_zuche_icon.png'),
            $row('daily_fliggy_ticket', '飞猪门票', 148, 'feizhu_hotel_jtk.png'),
            $row('daily_meituan_hotel', '美团酒店', 65, 'hotal.png'),
            $row('daily_chwl', '美团团购', 27, 'chwl.png'),
        ];
    }

    public static function resolveIcon(?string $itemKey, ?string $icon): string
    {
        $icon = trim((string)$icon);
        if ($icon !== '') {
            return $icon;
        }
        $key = trim((string)$itemKey);
        if ($key === '') {
            return '';
        }
        return self::defaultIconMap()[$key] ?? '';
    }
}
