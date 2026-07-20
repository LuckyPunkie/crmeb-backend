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


namespace app\controller;


use crmeb\basic\BaseController;

class View extends BaseController
{
    public function mobile()
    {
        $pathinfo = trim((string)$this->request->pathinfo(), '/');
        $https = (parse_url(systemConfig('site_url'))['scheme'] ?? '') == 'https';
        $url = trim(set_http_type($this->request->url(true), $https ? 0 : 1),'/');
        if ($pathinfo === 'pages') {
            $url = preg_replace('/\/pages(?:\?.*)?$/', '/pages/index/index', $url);
        }
        $url .= (strpos($url, '?') === false ? '?' : '&');
        $url .= 'inner_frame=1';

        $siteName = systemConfig('site_name');
        $DB = DIRECTORY_SEPARATOR;
        return view(app()->getRootPath() . 'app' . $DB . 'view' . $DB . 'mobile' . $DB . 'view.html', compact('siteName', 'url'));
    }

    public function h5()
    {
        if (
            (!$this->request->isMobile()) &&
            (!$this->request->param('inner_frame')) &&
            (strpos((string)$this->request->server('HTTP_USER_AGENT'), 'MicroMessenger') === false)
        ) {
            return $this->mobile();
        }
        $DB = DIRECTORY_SEPARATOR;
        return view(app()->getRootPath() . 'public' . $DB . 'index.html');
    }
}
