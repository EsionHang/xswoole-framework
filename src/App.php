<?php
namespace xswoole;

use Swoole;
use xswoole\core\Config;
use xswoole\core\Log;
use xswoole\core\Route;
use xswoole\coroutine\Context;
use xswoole\coroutine\Coroutine;

class App
{

    /**
     * @var string 根目录
     */
    public static $rootPath;
    /**
     * @var string 框架目录
     */
    public static $frameworkPath;
    /**
     * @var string 程序目录
     */
    public static $applicationPath;

    /**
     * 框架入口
     */
    final public static function run()
    {
        try {
            if (!defined('DS')) {
                define('DS', DIRECTORY_SEPARATOR);
            }
            self::$rootPath = dirname(dirname(__DIR__));
            self::$frameworkPath = self::$rootPath . DS . 'framework';
            self::$applicationPath = self::$rootPath . DS . 'application';

            //先注册自动加载
            \spl_autoload_register(__CLASS__ . '::autoLoader');
            //加载配置
            Config::load();

            //日志初始化
            Log::init();

            //通过读取配置获得ip、端口等
            $http = new Swoole\Http\Server(Config::get('host'), Config::get('port'));
            $http->set([
                "worker_num" => Config::get('worker_num'),
            ]);
            $http->on('workerStart', function (\swoole_http_server $serv, int $worker_id) {
                if (function_exists('opcache_reset')) {
                    //清除opcache 缓存，swoole模式下其实可以关闭opcache
                    opcache_reset();
                }
                $mysqlConfig = Config::get('mysql');
                if (!empty($mysqlConfig)) {
                    try {
                        //配置了mysql, 初始化mysql连接池
                        pool\Mysql::getInstance($mysqlConfig);
                    } catch (\Exception $e) {
                        //初始化异常，关闭服务
                        $serv->shutdown();
                    } catch (\Throwable $throwable) {
                        //初始化异常，关闭服务
                        $serv->shutdown();
                    }
                }
            });
            $http->on('request', function (\swoole_http_request $request, \swoole_http_response $response) {
                try {
                    //初始化根协程ID
                    $coId = Coroutine::setBaseId();
                    //初始化上下文
                    $context = new Context($request, $response);
                    //存放容器pool
                    pool\Context::set($context);
                    //协程退出，自动清空
                    defer(function () use ($coId) {
                        //清空当前pool的上下文，释放资源
                        pool\Context::clear($coId);
                    });

                    //自动路由
                    $result = Route::dispatch($request->server['path_info']);
                    $response->end($result);

                } catch (\Exception $e) { //程序异常
                    Log::alert($e->getMessage(), $e->getTrace());
                    $response->end($e->getMessage());
                } catch (\Error $e) { //程序错误，如fatal error
                    Log::emergency($e->getMessage(), $e->getTrace());
                    $response->status(500);
                } catch (\Throwable $e) { //兜底
                    Log::emergency($e->getMessage(), $e->getTrace());
                    $response->status(500);
                }
            });
            $http->start();
        } catch (\Exception $e) {
            print_r($e);
        } catch (\Throwable $e) {
            print_r($e);
        }
    }

    /**
     * @param $class
     * @desc 自动加载类
     */
    final public static function autoLoader($class)
    {

        //把类转为目录，eg \a\b\c => /a/b/c.php
        $classPath = \str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';

        //约定框架类都在framework目录下, 业务类都在application下
        $findPath = [
            self::$rootPath . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR,
            self::$rootPath . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR,
        ];

        //遍历目录，查找文件
        foreach ($findPath as $path) {
            //如果找到文件，则require进来
            $realPath = $path . $classPath;
            if (is_file($realPath)) {
                require "{$realPath}";
                return;
            }
        }

    }
}
