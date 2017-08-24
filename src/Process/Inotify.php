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
     * @param Conf $config
     * @param MSFServer $MSFServer
     */
    public function __construct(Conf $config, MSFServer $MSFServer)
    {
        parent::__construct($config, $MSFServer);
        $notice = 'Inotify Auto Reload Process: ';
        $this->monitorDir = realpath(ROOT_PATH . '/');
        if (!extension_loaded('inotify')) {
            $notice .= "Failed(未安装inotify扩展)";
        } else {
            $this->inotify();
            $notice .= "Enabled";
        }

        writeln('App', $notice);
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

        foreach ($iterator as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                continue;
            }
            $wd = inotify_add_watch($this->inotifyFd, $file, IN_MODIFY);
            $monitorFiles[$wd] = $file;
        }

        swoole_event_add($this->inotifyFd, function ($inotifyFd) use ($monitorFiles) {
            $events = inotify_read($inotifyFd);
            if ($events) {
                foreach ($events as $ev) {
                    $file = $monitorFiles[$ev['wd']];
                    writeln('App', 'RELOAD ' . $file . ' update');
                    unset($monitorFiles[$ev['wd']]);

                    $wd = inotify_add_watch($inotifyFd, $file, IN_MODIFY);
                    $monitorFiles[$wd] = $file;
                }
                $this->MSFServer->server->reload();
            }
        }, null, SWOOLE_EVENT_READ);
    }
}
