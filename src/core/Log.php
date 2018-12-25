<?php
namespace xswoole\core;

use lyzy\SLog;
use xswoole\App;

class Log extends SLog
{
    //设置日志目录
    public static function init()
    {
        self::setBasePath(App::$applicationPath . DS . 'log');
    }
}
