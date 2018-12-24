<?php
namespace xswoole\Core;

use xswoole\App;
use lyzy\SLog;

class Log extends SLog
{
    //设置日志目录
    public static function init()
    {
        self::setBasePath(App::$applicationPath . DS . 'log');
    }
}
