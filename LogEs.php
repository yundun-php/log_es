<?php

namespace Service\LogEs;

use \Jingwu\LogEs\Cfg;
use \Jingwu\LogEs\LogClient;

class LogEs extends LogClient {

    static public $uuid = null;
    static public $instances = [];
    static public $initCfg = false;
    static public $ignoreKeys = [];

    public function __construct($logkey = 'default', $syskey = '') {
        parent::__construct($logkey, $syskey);
    }

    static public function ins($logkey = 'default') {
        //生成日志时，添加uuid
        if(self::$uuid == null) self::$uuid = uniqid();

        self::config();
        if(!isset(self::$instances[$logkey])) {
            self::$instances[$logkey] = new self($logkey, defined('SYS_KEY') ? SYS_KEY : '');
            //日志不写入beanstalk
            self::$instances[$logkey]->useEs(false);
            self::$instances[$logkey]->useFile(true);
            self::$instances[$logkey]->setUuid(self::$uuid);
            if(isset(self::$ignoreKeys[$logkey])) self::$instances[$logkey]->ignore(true);
        }
        return self::$instances[$logkey];
    }

    static public function config() {
        if(!self::$initCfg) {
            $cfg = parse_ini_file('./loges.conf', true);
            Cfg::setFromConf($cfg);
            $ignoreKeysStr = isset($cfg['ignore']) && isset($cfg['ignore']['keys']) ? trim($cfg['ignore']['keys']) : '';
            $rows = explode(',', $ignoreKeysStr);
            foreach($rows as $row) {
                $row = trim($row);
                if(!$row) continue;
                self::$ignoreKeys[$row] = 1;
            }

            self::$initCfg = true;
        }
        return true;
    }

}

