<?php
/**
 * 文件监控进程
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

use Noodlehaus\Config as Conf;
use PG\MSF\MSFServer;
use PG\MSF\Macro;

/**
 * Class Inotify
 * @package PG\MSF\Process
 */
class Inotify extends ProcessBase
{
    /**
     * @var bool|string 监控目录
     */
    public $monitorDir;

    /**
     * @var int inotify fd
     */
    public $inotifyFd;

    /**
     * Inotify constructor.
     *
     * @param Conf $config 配置对象
     * @param MSFServer $MSFServer Server运行实例
     */
    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        parent::__construct($config, $MSFServer);
        $this->MSFServer->processType = Macro::PROCESS_RELOAD;
        $notice = 'Inotify  Reload: ';
        $this->monitorDir = realpath(ROOT_PATH . '/');
        if (!extension_loaded('inotify')) {
            $notice .= "Failed(未安装inotify扩展)";
        } else {
            $this->inotify();
            $notice .= "Enabled";
        }

        writeln($notice);
    }

    /**
     * 监控目录
     */
    public function inotify()
    {
        $this->inotifyFd = inotify_init();

        stream_set_blocking($this->inotifyFd, 0);
        $dirIterator  = new \RecursiveDirectoryIterator($this->monitorDir);
        $iterator     = new \RecursiveIteratorIterator($dirIterator);
        $monitorFiles = [];
        $tempFiles    = [];

        foreach ($iterator as $file) {
            $fileInfo = pathinfo($file);

            if (!isset($fileInfo['extension']) || $fileInfo['extension'] != 'php') {
                continue;
            }

            //改为监听目录
            $dirPath = $fileInfo['dirname'];
            if (!isset($tempFiles[$dirPath])) {
                $wd = inotify_add_watch($this->inotifyFd, $fileInfo['dirname'], IN_MODIFY | IN_CREATE | IN_IGNORED | IN_DELETE);
                $tempFiles[$dirPath] = $wd;
                $monitorFiles[$wd] = $dirPath;
            }
        }

        $tempFiles = null;

        swoole_event_add($this->inotifyFd, function ($inotifyFd) use (&$monitorFiles) {
            $events = inotify_read($inotifyFd);
            $flag = true;
            foreach ($events as $ev) {
                if (pathinfo($ev['name'], PATHINFO_EXTENSION) != 'php') {
                    //创建目录添加监听
                    if ($ev['mask'] == 1073742080) {
                        $path = $monitorFiles[$ev['wd']] .'/'. $ev['name'];

                        $wd = inotify_add_watch($inotifyFd, $path, IN_MODIFY | IN_CREATE | IN_IGNORED | IN_DELETE);
                        $monitorFiles[$wd] = $path;
                    }
                    $flag = false;
                    continue;
                }
                writeln('RELOAD ' . $monitorFiles[$ev['wd']] .'/'. $ev['name'] . ' update');
            }
            if ($flag == true) {
                $this->MSFServer->server->reload();
            }
        }, null, SWOOLE_EVENT_READ);
    }
}
