<?php
namespace xswoole\pool;

use xswoole\coroutine\Coroutine;

/**
 * Class Context
 * @package xswoole\Coroutine
 * @desc 保持嵌套协程的context传递
 */
class Context
{
    /**
     * @var array context pool
     */
    public static $pool = [];

    /**
     * @return \xswoole\coroutine\Context
     * @desc 可以任意协程获取到context
     */
    public static function getContext()
    {
        $id = Coroutine::getPid();
        if (isset(self::$pool[$id])) {
            return self::$pool[$id];
        }

        return null;
    }

    /**
     * @desc 清除context
     */
    public static function clear()
    {
        $id = Coroutine::getPid();
        if (isset(self::$pool[$id])) {
            unset(self::$pool[$id]);
        }
    }

    /**
     * @param $context
     * @desc 设置context
     */
    public static function set($context)
    {
        $id = Coroutine::getPid();
        self::$pool[$id] = $context;
    }
}
