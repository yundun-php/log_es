<?php

require_once dirname(__FILE__).'/vendor/autoload.php';

use \Jingwu\LogEs\Cfg;
use \Jingwu\LogEs\LogClient;

$cfg = parse_ini_file('./loges.conf', true);

Cfg::setFromConf($cfg);
$uuid = uniqid();

$ignoreKeys = [];
$ignoreKeysStr = isset($cfg['ignore']) && isset($cfg['ignore']['keys']) ? trim($cfg['ignore']['keys']) : '';
$rows = explode(',', $ignoreKeysStr);
foreach($rows as $row) {
    $row = trim($row);
    if(!$row) continue;
    $ignoreKeys[$row] = 1;
}

$logkey = 'tester';
$syskey = 'loges';
$logClient = new LogClient($logkey, $syskey);
$logClient->useEs(false);
$logClient->useFile(true);
$logClient->useStdout(true);
$logClient->setUuid($uuid);
$logClient->ignore(isset($ignoreKeys[$logkey]) ? true : false);

$logClient->setLevel(LogClient::DEBUG);
$logClient->debug('tester debug');
$logClient->info('tester info');
$logClient->warn('tester warn');
$logClient->error('tester error');
$logClient->add(['name' => 'tester', 'ttt' => 1]);
