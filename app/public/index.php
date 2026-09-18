<?php

use MiPhantLibs\app\router;
use MiPhantLibs\security\items;
use MiPhantLibs\system\server;

require_once(__DIR__ . '/vendor/autoload.php');

$server = new server();
$item = new items();
$rt = new router();

$uri = parse_url($server->uri(), PHP_URL_PATH);

$rt->get('/', function() {
    require_once(__DIR__ . '/home.php');
});

$rt->get('/about', function() {
    require_once(__DIR__ . '/about.php');
});

$rt->get('/args', function() {
    require_once(__DIR__ . '/args.php');
});

$rt->get('/cookies', function() {
    require_once(__DIR__ . '/cookies.php');
});

$rt->get('/extramenu', function() {
    require_once(__DIR__ . '/extramenu.php');
});

$rt->get('/formget', function() {
    require_once(__DIR__ . '/formget.php');
});

$rt->get('/formpost', function() {
    require_once(__DIR__ . '/formpost.php');
});

$rt->get('/libs', function() {
    require_once(__DIR__ . '/libs.php');
});

$rt->get('/message', function() {
    require_once(__DIR__ . '/message.php');
});

$rt->get('/notification', function() {
    require_once(__DIR__ . '/notification.php');
});

$rt->get('/env', function() {
    require_once(__DIR__ . '/env.php');
});

$rt->get('/openfile', function() {
    require_once(__DIR__ . '/openfile.php');
});

$rt->get('/openfiles', function() {
    require_once(__DIR__ . '/openfiles.php');
});

$rt->get('/phpinfo', function() {
    require_once(__DIR__ . '/phpinfo.php');
});

$rt->get('/preload-doc', function() {
    require_once(__DIR__ . '/preload-doc.php');
});

$rt->get('/savefile', function() {
    require_once(__DIR__ . '/savefile.php');
});

$rt->get('/selectdirectory', function() {
    require_once(__DIR__ . '/selectdirectory.php');
});

$rt->get('/pdf', function() {
    require_once(__DIR__ . '/pdf.php');
});

$rt->get('/session', function() {
    require_once(__DIR__ . '/session.php');
});

$rt->get('/cadastro/listedit', function() {
    require_once(__DIR__ . '/sqlite.php');
});

$rt->get('/timezone', function() {
    require_once(__DIR__ . '/timezone.php');
});

$rt->get('/translate', function() {
    require_once(__DIR__ . '/translate.php');
});

if ($rt->noPHP()) {
    return false;
}


// if (substr($uri, -1) == '/') {
//     if ($uri == 'home')
// } elseif (substr($uri, -4) == '.php') {
//     require_once(__DIR__ . $item->clean($uri));
// } else {
//     return false;
// }