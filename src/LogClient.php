<?php
/**
 * Desc: 日志基础服务
 * User: <lideqiang@yundun.com>
 * Date: 2018/9/27 9:47
 *
 * $data = ["key" => "key_0", "name" => "name_0", "title" => "title_0", "create_at" => date("Y-m-d H:i:s")];
 * $jobid = LogClient::instance('only_for_test_1')->add($data);
 */
namespace Jingwu\LogEs;

use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;

class LogClient extends Core implements LoggerInterface {

    const DEBUG    = 100;        //Logger::DEBUG
    const INFO     = 200;        //Logger::INFO
    const NOTICE   = 250;        //Logger::NOTICE
    const WARNING  = 300;        //Logger::WARNING
    const ERROR    = 400;        //Logger::ERROR
    const CRITICAL = 500;        //Logger::CRITICAL

    protected $_syskey       = '';      //系统标识
    protected $_logkey       = '';      //Logger 标识
    protected $_logfkeyDebug = '';      //有logpre的logkey
    protected $_logfkeyBiz   = '';      //有logpre的logkey
    protected $_logfile      = '';
    protected $_logfileDebug = '';
    protected $_logfileBiz   = '';
    protected $_level        = Logger::DEBUG;
    protected $_ignore       = false;
    protected $_useEs        = true;
    protected $_useFile      = false;
    protected $_useStdout    = false;
    protected $_queueFile    = '';
    static public $loggers   = [];
    static public $instances = [];

    public function __construct($logkey, $syskey = '') {
        $this->_syskey = $syskey;
        $this->_logkey = $logkey;
        $logdir = Cfg::instance()->get('logdir');
        $logpre = Cfg::instance()->get('logpre');
        $this->_logfkey = "{$logpre}{$logkey}";
        $this->_logfkeyBiz = sprintf("%s%sbiz-%s", $logpre, $syskey ? $syskey.'-' : '', $logkey);
        $this->_logfkeyDebug = sprintf("%s%sdebug-%s", $logpre, $syskey ? $syskey.'-' : '', $logkey);
        $this->_queueFile = "{$logdir}/log_queue.log";
        $this->_logfile = "{$logdir}/{$logpre}{$logkey}.log";
        $this->_logfileBiz = sprintf("%s/%s%sbiz-%s.log", $logdir, $logpre, $syskey ? $syskey.'-' : '', $logkey);
        $this->_logfileDebug = sprintf("%s/%s%sdebug-%s.log", $logdir, $logpre, $syskey ? $syskey.'-' : '', $logkey);
    }

    static public function instance($logkey = 'default') {
        $logkey = trim($logkey);
        if(empty($logkey)) throw new Exception('logkey is empty');

        if(!isset(self::$instances[$logkey])) self::$instances[$logkey] = new self($logkey);
        return self::$instances[$logkey];
    }

    public function resetLogger() {
        unset(self::$loggers[$this->_logfkeyDebug]);
        $this->logger();
    }

    public function logger() {
        if(!isset(self::$loggers[$this->_logfkeyDebug])) {
            $logger = new Logger($this->_logfkeyDebug);

            if($this->_useEs)     self::setLoggerEs    ($logger, $this->_logfkeyDebug, $this->_level);
            if($this->_useStdout) self::setLoggerStdout($logger, $this->_level);
            if($this->_useFile)   self::setLoggerFile  ($logger, $this->_logfileDebug, $this->_level);

            self::$loggers[$this->_logfkeyDebug] = $logger;
        }
        return self::$loggers[$this->_logfkeyDebug];
    }

    static public function setLogger($logkey, $logger, $level = Logger::DEBUG) {
        $esHandler = new EsHandler($logkey, $level);
        $esHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra% \n", '', true));
        $logger->pushHandler($esHandler);
        self::$loggers[$logkey] = $logger;
    }

    static public function setLoggerStdout($logger, $level = Logger::DEBUG) {
        $logfile = 'php://stdout';
        $streamHandler = new StreamHandler($logfile, $level);
        $streamHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra% \n", '', true));
        $logger->pushHandler($streamHandler);
    }

    static public function setLoggerFile($logger, $logfile, $level = Logger::DEBUG) {
        $streamHandler = new StreamHandler($logfile, $level);
        $streamHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra% \n", '', true));
        $logger->pushHandler($streamHandler);
    }

    static public function setLoggerEs($logger, $logkey, $level = Logger::DEBUG) {
        $esHandler = new EsHandler($logkey, $level);
        $esHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra% \n", '', true));
        $logger->pushHandler($esHandler);
    }

    public function setLevel($level = Logger::DEBUG) {
        $this->_level = $level;
        $this->resetLogger();
        return $this;
    }
    public function useFile($flag = false) {
        $this->_useFile = $flag ? true : false;
        $this->resetLogger();
        return $this;
    }
    public function useStdout($flag = false) {
        $this->_useStdout = $flag ? true : false;
        $this->resetLogger();
        return $this;
    }
    public function useEs($flag = true) {
        $this->_useEs = $flag ? true : false;
        $this->resetLogger();
        return $this;
    }
    public function ignore($flag = false) {
        $this->_ignore = $flag;
        return $this;
    }
    public function setUuid($uuid = '') {
        $this->_uuid = $uuid;
        return $this;
    }
    //封装message, 添加请求ID前缀
    public function wrapMessage($message) {
        return $this->_uuid ? $this->_uuid." ".$message : $message;
    }

    public function add($row) {
        if($this->_ignore) return;
        $now = date("Y-m-d H:i:s");
        if($this->_useEs) {
            $body = json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $result = LogQueue::instance('client')->usePut($this->_logfkeyBiz, $body);
            if(!$result) file_put_contents($this->_queueFile, "{$now}\t{$this->_logfkeyBiz}\t{$body}\n", FILE_APPEND);
            return $result;
        }
        if($this->_useFile) {
            $body = json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            file_put_contents($this->_logfileBiz, "{$now}\t{$this->_logfkeyBiz}\t{$body}\n", FILE_APPEND);
        }
        if($this->_useStdout) {
            $body = json_encode($row, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            print_r("{$now}\t{$this->_logfkeyBiz}\t{$body}\n");
        }
    }

    public function emergency($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->emergency($this->wrapMessage($message), $context);
    }
    public function emerg($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->emerg($this->wrapMessage($message), $context);
    }
    public function alert($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->alert($this->wrapMessage($message), $context);
    }
    public function critical($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->critical($this->wrapMessage($message), $context);
    }
    public function crit($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->crit($this->wrapMessage($message), $context);
    }
    public function error($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->error($this->wrapMessage($message), $context);
    }
    public function err($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->err($this->wrapMessage($message), $context);
    }
    public function warning($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->warning($this->wrapMessage($message), $context);
    }
    public function warn($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->warn($this->wrapMessage($message), $context);
    }
    public function notice($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->notice($this->wrapMessage($message), $context);
    }
    public function info($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->info($this->wrapMessage($message), $context);
    }
    public function debug($message, array $context = array()) {
        if($this->_ignore) return;
        $this->logger()->debug($this->wrapMessage($message), $context);
    }
    public function log($level, $message, array $context = array()) {
    }

}

