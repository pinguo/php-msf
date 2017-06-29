<?php
/**
 * InotifyProcess
 *
 * @author camera360_server@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Process;

class Inotify
{
    const RELOAD_SIG = 'reload_sig';
    public $monitorDir;
    public $inotifyFd;
    public $managePid;
    public $server;

    public function __construct($server)
    {
        $notice = 'Enable Inotify Auto Reload: ';
        $this->server     = $server;
        $this->monitorDir = realpath(ROOT_PATH . '/');
        if (!extension_loaded('inotify')) {
            $notice .= "Failed(未安装inotify扩展)\n";
        } else {
            $this->useInotify();
            $notice .= "Success\n";
        }

        echo $notice;
    }

    public function useInotify()
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
                    echo "[RELOAD]  " . $file . " update\n";
                    unset($monitorFiles[$ev['wd']]);

                    $wd = inotify_add_watch($inotifyFd, $file, IN_MODIFY);
                    $monitorFiles[$wd] = $file;
                }
                $this->server->reload();
            }
        }, null, SWOOLE_EVENT_READ);
    }
}
