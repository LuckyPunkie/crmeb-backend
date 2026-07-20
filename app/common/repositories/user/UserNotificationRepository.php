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



use app\common\dao\user\UserNotificationDao;

use app\common\repositories\BaseRepository;

use crmeb\services\SwooleTaskService;

use think\facade\Db;



/**

 * @mixin UserNotificationDao

 */

class UserNotificationRepository extends BaseRepository

{

    public function __construct(UserNotificationDao $dao)

    {

        $this->dao = $dao;

    }



    public function getList(int $uid, int $page, int $limit, ?string $type = null): array

    {

        $list = $this->dao->getList($uid, $page, $limit, $type);

        $result = [];

        foreach ($list as $item) {

            $row = $item->toArray();

            if (isset($row['from_user'])) {

                $row['from_avatar'] = $row['from_user']['avatar'] ?? '';

                $row['from_nickname'] = $row['from_user']['nickname'] ?? '';

                unset($row['from_user']);

            }

            $result[] = $row;

        }



        return [

            'count' => $this->dao->getCount($uid, $type),

            'list'  => $result,

        ];

    }



    public function markRead(int $notificationId, int $uid): void

    {

        $this->dao->markRead($notificationId, $uid);

    }



    public function getUnreadCount(int $uid): int

    {

        return $this->dao->getUnreadCount($uid);

    }



    public function createAndPush(int $uid, int $fromUid, string $type, string $title, string $content = '', string $targetType = '', int $targetId = 0): void

    {

        if ($uid == $fromUid) {

            return;

        }



        $data = [

            'uid'         => $uid,

            'from_uid'    => $fromUid,

            'type'        => $type,

            'title'       => $title,

            'content'     => $content,

            'target_type' => $targetType,

            'target_id'   => $targetId,

        ];

        $notification = $this->dao->create($data);



        $fromUser = Db::name('user')->field('uid,nickname,avatar')->where('uid', $fromUid)->find();



        SwooleTaskService::user($uid, [

            'type' => 'notification',

            'data' => [

                'notification_id' => $notification->notification_id,

                'type'            => $type,

                'title'           => $title,

                'from_uid'        => $fromUid,

                'from_nickname'   => $fromUser['nickname'] ?? '',

                'from_avatar'     => $fromUser['avatar'] ?? '',

                'create_time'     => $notification->create_time,

            ],

        ]);

    }

}


