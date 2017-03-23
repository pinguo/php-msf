<?php
/**
 * PGLog
 * 日志
 * @author niulingyun@camera360.com
 * @copyright Chengdu pinguo Technology Co.,Ltd.
 */

namespace PG\MSF\Server\Helpers\Log;

use Monolog\{
    Handler\BufferHandler, Logger, Formatter\LineFormatter, Handler\FilterHandler
};
use PG\MSF\Server\Helpers\Log\Handler\PGStreamHandler;

class PGLog extends Logger
{
    /**
     * 访问请求日志变量，此变量不用unset，因为每次请求initialization都会重新赋值
     * @var array
     */
    public $accessRecord = [];
    public $logId;
    public $channel;

    public $profileStackLen = 20;

    protected $_profileStacks = [];
    protected $_pushlogs = [];
    protected $_profiles = [];
    protected $_countings = [];

    /**
     * Logging levels from syslog protocol defined in RFC 5424
     *
     * @var array $levels Logging levels
     */
    protected static $levels = array(
        100 => 'debug',
        200 => 'info',
        250 => 'notice',
        300 => 'warning',
        400 => 'error',
        500 => 'critical',
        550 => 'alert',
        600 => 'emergency',
    );

    public function __construct(
        string $name,
        array $handlers = [],
        array $processors = [],
        \DateTimeZone $timezone = null
    ) {
        parent::__construct($name, $handlers, $processors, $timezone);
        $server = get_instance();
        $config = $server->config;
        foreach ($config['server.log.handlers'] as $handler) {
            $stream = new PGStreamHandler($handler['stream']);

            //格式
            if (isset($handler['format']) && isset($handler['dateFormat'])) {
                $format = new LineFormatter($handler['format'], $handler['dateFormat']);
                $stream->setFormatter($format);
            }

            //buffer
            if ($handler['buffer'] > 0) {
                $stream = new BufferHandler($stream, $handler['buffer'], Logger::DEBUG, true, true);
            }

            //过滤器
            $stream = new FilterHandler($stream, $handler['levelList']);

            $this->pushHandler($stream);
        }
    }

    /**
     * 初始化
     */
    public function init()
    {
        $this->pushLogId();
        $this->channel();
    }

    /**
     * 写入访问日志或 Task 日志
     */
    public function appendNoticeLog()
    {
        $timeUsed = sprintf("%.0f", (microtime(true) - $this->accessRecord['beginTime']) * 1000);
        $memUsed = sprintf("%.0f", memory_get_peak_usage() / (1024 * 1024));
        $profile = $this->getAllProfileInfo();
        $counting = $this->getAllCountingInfo();
        $message = "[$timeUsed(ms)]"
            . ' ' . "[$memUsed(MB)]"
            . ' ' . "[{$this->accessRecord['uri']}]"
            . ' [' . implode(' ', $this->_pushlogs) . ']'
            . ' profile[' . "$profile" . ']'
            . ' counting[' . "$counting" . ']';
        $this->_profiles = [];
        $this->_countings = [];
        $this->_pushlogs = [];
        $this->notice($message);
    }

    /**
     * Adds a log record.（解决原始版本的进程崩溃问题）
     *
     * @param  int     $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     * @return Boolean Whether the record has been processed
     */
    public function addRecord(int $level, string $message, array $context = []): bool
    {
        // check if any handler will handle this message so we can return early and save cycles
        $handlerKey = null;
        reset($this->handlers);
        while ($handler = current($this->handlers)) {
            if ($handler->isHandling(['level' => $level])) {
                $handlerKey = key($this->handlers);
                break;
            }

            next($this->handlers);
        }

        if (null === $handlerKey) {
            return false;
        }

        $levelName = static::getLevelName($level);

        $record = [
            'message' => $message,
            'context' => $context,
            'level' => $level,
            'level_name' => $levelName,
            'channel' => $this->name,
            'datetime' => date('Y/m/d H:i:s'),
            'extra' => [],
        ];

        foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }

        while ($handler = current($this->handlers)) {
            if (true === $handler->handle($record)) {
                break;
            }

            next($this->handlers);
        }

        return true;
    }


    /**
     * 日志中增加logId字段
     */
    protected function pushLogId()
    {
        $callback = function ($record) {
            $record['logId'] = $record['context']['logId'] ?? $this->logId ?? '000000';

            return $record;
        };
        $this->pushProcessor($callback);
    }

    /**
     * 日志中的 channel 字段
     */
    protected function channel()
    {
        $callback = function ($record) {
            $record['channel'] = $record['context']['channel'] ?? $this->channel ?? $record['channel'];

            return $record;
        };
        $this->pushProcessor($callback);
    }

    /**
     * 获取profile信息
     * @return string
     */
    public function getAllProfileInfo()
    {
        if (empty($this->_profiles)) {
            return '';
        }

        $arrOut = array();
        foreach ($this->_profiles as $name => $val) {
            if (!isset($val['cost'], $val['total'])) {
                continue;
            }
            $arrOut[] = "$name=" . sprintf("%.1f", $val['cost'] * 1000) . '(ms)/' . $val['total'];
        }

        return implode(',', $arrOut);
    }

    protected function getAllCountingInfo()
    {
        if (empty($this->_countings)) {
            return '';
        }
        $arrCounting = array();
        foreach ($this->_countings as $k => $v) {
            if (isset($v['hit'], $v['total']) && $v['total'] != 0) {
                $arrCounting[] = "$k=" . $v['hit'] . '/' . $v['total'];
            } elseif (isset($v['hit'])) {
                $arrCounting[] = "$k=" . $v['hit'];
            }
        }

        return implode(',', $arrCounting);
    }

    /**
     * for info level log only
     * @param string|number $key
     * @param string $val
     */
    public function pushLog($key, $val = '')
    {
        if (!(is_string($key) || is_numeric($key))) {
            return;
        }
        $key = urlencode($key);
        if (is_array($val)) {
            $this->_pushlogs[] = "$key=" . json_encode($val);
        } elseif (is_bool($val)) {
            $this->_pushlogs[] = "$key=" . var_export($val, true);
        } elseif (is_string($val) || is_numeric($val)) {
            $this->_pushlogs[] = "$key=" . urlencode($val);
        } elseif (is_null($val)) {
            $this->_pushlogs[] = "$key=";
        }
    }

    /**
     * profile开始标示
     * @param string $name
     */
    public function profileStart($name)
    {
        if (!is_string($name) || empty($name)) {
            return;
        }

        $this->_profileStacks[$name] = microtime(true);
    }

    /**
     * profile 结束标示
     * @param string $name
     */
    public function profileEnd($name)
    {
        if (!is_string($name) || empty($name) || empty($this->_profileStacks)) {
            return;
        }

        if (!empty($this->_profileStacks[$name])) {
            $this->profile($name, microtime(true) - $this->_profileStacks[$name]);
        }
    }

    public function profile($name, $cost)
    {
        if (!isset($this->_profiles[$name])) {
            $this->_profiles[$name] = ['cost' => 0, 'total' => 0];
        }
        $this->_profiles[$name]['cost'] += $cost;
        ++$this->_profiles[$name]['total'];
    }

    /**
     * 记数类， 可以记录cache命中率
     * @param string $name
     * @param int $hit
     * @param int $total
     */
    public function counting($name, $hit, $total = null)
    {
        if (!is_string($name) || empty($name)) {
            return;
        }
        if (!isset($this->_countings[$name])) {
            $this->_countings[$name] = ['hit' => 0, 'total' => 0];
        }
        $this->_countings[$name]['hit'] += intval($hit);
        if ($total !== null) {
            $this->_countings[$name]['total'] += intval($total);
        }
    }
}
