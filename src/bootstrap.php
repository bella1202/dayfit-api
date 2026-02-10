<?php

date_default_timezone_set('Asia/Seoul');

require_once __DIR__ . '/Env.php';
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Json.php';
require_once __DIR__ . '/Router.php';

Env::load(__DIR__ . '/../.env');
Db::init();
