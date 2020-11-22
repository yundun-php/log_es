<?php
namespace Jingwu\LogEs;

class Cfg extends Core {

    private $_cfg = null;
    static public $instances = [];
    static public $bodySizeMin = 64 * 1024;
    static public $bodySizeMax = 1024 * 1024;

    public function __construct() {
        //初始化
        $this->_cfg = [
            'beanstalk' => [
                'host'           => '',
                'port'           => '',
                'persistent'     => true,
                'timeout'        => 600,
                'socket_timeout' => 3600
            ],
            'flume' => [],
            'es' => [],
            'logdir' => '/tmp',
            'logpre' => 'log_',
            'limit' => [
                'limit_write' => 50000,
                'body_size_max' => self::$bodySizeMin,
            ],
            'mail'  => [
                'mails' => [],
                'interval' => 300,
            ],
            'mq_esdoc' => [],
        ];
    }

    static public function instance($key = 'default') {
        if(!isset(self::$instances[$key])) {
            self::$instances[$key] = new self();
        }
        return self::$instances[$key];
    }

    public function setBeanStalk($host, $port) {
        $this->_cfg['beanstalk'] = [
            'host'           => $host,
            'port'           => $port,
            'persistent'     => true,
            'timeout'        => 600,
            'socket_timeout' => 3600
        ];
    }

    public function setFlume($apis) {
        if(!$this->_formatStrArr($apis)) return false;
        $this->_cfg['flume'] = $apis;
    }

    public function setEs($apis) {
        if(!$this->_formatStrArr($apis)) return false;
        $this->_cfg['es'] = $apis;
    }

    public function setMails($mails) {
        if(!$this->_formatStrArr($mails)) return false;
        $this->_cfg['mail']['mails'] = $mails;
    }

    public function setMailInterval($interval) {
        $interval = intval($interval);
        $interval = $interval > 60 ? $interval : 60;
        $this->_cfg['mail']['interval'] = $interval;
    }

    public function setLogdir($logdir) {
        $this->_cfg['logdir'] = $logdir;
        $this->_cfg['logdir'] = $this->_formatPath($this->_cfg['logdir']);
    }

    public function setLogpre($logpre) {
        $this->_cfg['logpre'] = $logpre ? $logpre : $this->_cfg['logpre'];
    }

    public function setMqEsdoc($mqEsdoc) {
        $this->_cfg['mq_esdoc'] = $mqEsdoc ? $mqEsdoc : $this->_cfg['mq_esdoc'];
    }

    public function setLimitWrite($limitWrite = 50000) {
        $this->_cfg['limit']['limit_write'] = $limitWrite ? $limitWrite : $this->_cfg['limit']['limit_write'];
    }

    public function setBodySizeMax($bodySizeMax = 0) {
        $this->_cfg['limit']['body_size_max'] = $bodySizeMax <= self::$bodySizeMin ? self::$bodySizeMax : $bodySizeMax;
    }

    public function get($key) {
        $keys = explode(".", $key);
        $tmp = $this->_cfg;
        foreach($keys as $field) {
            if(!isset($tmp[$field])) return false;
            $tmp = $tmp[$field];
        }
        return $tmp;
    }

    private function _formatPath($path) {
        return substr($path, -1) == '/' ? substr($path, 0, -1) : $path;
    }

    private function _formatStrArr($rows) {
        if(!is_array($rows)) return false;
        foreach($rows as $row) if(!is_string($row)) return false;
        return $rows;
    }

    public function check() {
        if(!$this->_cfg['beanstalk']) return ['code' => 0, "error" => "beanstalk noset"];
        if(!$this->_cfg['flume'])     return ['code' => 0, "error" => "flume noset"    ];
        if(!$this->_cfg['es'])        return ['code' => 0, "error" => "es noset"       ];
        if(!$this->_cfg['mail'])     return ['code' => 0, "error" => "mail noset"     ];

        $cfgBs = $this->_cfg['beanstalk'];
        if(!$cfgBs['host'] || !$cfgBs['port']) return ['code' => 0, "error" => "beanstalk set error"];
        return ['code' => 1, "error" => "ok"];
    }

    static public function setFromConf($cfg) {
        $cfgObj = Cfg::instance();
        $flumeApis = $esApis = $mails = [];
        $cfgBs = $cfg['beanstalk'];
        $bsHost = isset($cfgBs['host']) ? trim($cfgBs['host']) : null;
        $bsPort = isset($cfgBs['port']) ? trim($cfgBs['port']) : null;
        $logdir = isset($cfg['base']) && isset($cfg['base']['logdir']) ? trim($cfg['base']['logdir']) : '';
        $logpre = isset($cfg['base']) && isset($cfg['base']['logpre']) ? trim($cfg['base']['logpre']) : '';
        $mailInterval = isset($cfg['mail']) && isset($cfg['mail']['interval']) ? intval($cfg['mail']['interval']) : 0;
        if(isset($cfg['flume'])) {
            foreach($cfg['flume'] as $key => $value) if(substr($key, 0, 3) == 'api') $flumeApis[] = $value;
        }
        if(isset($cfg['es'])) {
            foreach($cfg['es'] as $key => $value) if(substr($key, 0, 3) == 'api') $esApis[] = $value;
        }
        if(isset($cfg['mail']) && isset($cfg['mail']['mails'])) {
            $mails = explode(",", $cfg['mail']['mails']);
            foreach($mails as &$mail) $mail = trim($mail);
        }
        $mqEsdoc = [];
        $cfgEsdocMqMap = isset($cfg['esdoc_mq_map']) ? $cfg['esdoc_mq_map'] : [];
        foreach($cfgEsdocMqMap as $esdoc => $line) {
            if(!$line) continue;
            $rows = explode(',', $line);
            foreach($rows as $mq) {
                $mqEsdoc[$logpre.trim($mq)] = $esdoc;
            }
        }
        $limitWrite = isset($cfg['limit']) && isset($cfg['limit']['limit_write']) ? intval($cfg['limit']['limit_write']) : 5000;
        $bodySizeMax = isset($cfg['limit']) && isset($cfg['limit']['body_size_max']) ? intval($cfg['limit']['body_size_max']) : (64 * 1024);

        if($bsHost && $bsPort) $cfgObj->setBeanstalk($bsHost, $bsPort);
        if($esApis)       $cfgObj->setEs($esApis);
        if($mails)        $cfgObj->setMails($mails);
        if($logdir)       $cfgObj->setLogdir($logdir);
        if($logpre)       $cfgObj->setLogpre($logpre);
        if($flumeApis)    $cfgObj->setFlume($flumeApis);
        if($mailInterval) $cfgObj->setMailInterval($mailInterval);
        if($limitWrite)   $cfgObj->setLimitWrite($limitWrite);
        if($mqEsdoc)      $cfgObj->setMqEsdoc($mqEsdoc);
        if($bodySizeMax)  $cfgObj->setBodySizeMax($bodySizeMax);
    }

}

