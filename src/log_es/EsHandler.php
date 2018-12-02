<?php

namespace Jingwu\LogEs;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\JsonFormatter;

class EsHandler extends AbstractProcessingHandler {

    protected $_logkey = '';
    protected $_queueFile = '';

    public function __construct($logkey) {
        parent::__construct();
        $this->_logkey = $logkey;

        $logdir = Cfg::instance()->get('logdir');
        $this->_queueFile = "{$logdir}/log_queue.log";
    }

    protected function write(array $record) {
        $row = $record;
        $row["create_at"] = $row["datetime"]->format('Y-m-d H:i:s');
        $row["timezone"]  = $row["datetime"]->getTimezone()->getName();
        unset($row['datetime']);
        $result = LogQueue::instance('client')->usePut($this->_logkey, json_encode($row));
        if(!$result) 
            file_put_contents($this->_queueFile, date("Y-m-d H:i:s")."\t{$this->_logkey}\t".json_encode($row)."\n", FILE_APPEND);
    }

    public function setFormatter($formatter) {
        return parent::setFormatter($formatter);
    }

}

