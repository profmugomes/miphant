<?php
require_once __DIR__ . '/libs/app/config.php';
require_once __DIR__ . '/libs/app/functions.php';
require_once __DIR__ . '/libs/app/path.php';
require_once __DIR__ . '/libs/langs/translate.php';
require_once __DIR__ . '/libs/system/server.php';
require_once __DIR__ . '/libs/system/env.php';
require_once __DIR__ . '/libs/system/platform.php';

use MiPhantLibs\app\config;
use MiPhantLibs\app\functions;
use MiPhantLibs\app\path;
use MiPhantLibs\langs\translate;
use MiPhantLibs\system\server;
use MiPhantLibs\system\env;
use MiPhantLibs\system\platform;

$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Libraries | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>PHP Libraries</h1>
    <p class="text-muted mb-3">MiPhantLibs API documentation and live demos</p>

    <!-- ==================== config ==================== -->
    <div class="card">
        <h2>MiPhantLibs\app\config</h2>
        <p class="text-muted">Read values from <code>app/config.json</code></p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>get(string ...$keys)</code></td><td>string|int|bool</td><td>Read nested config values</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $cfg = new config();
        ?>
        <table>
            <tr><td><code>$cfg->get('width')</code></td><td><strong><?php echo $cfg->get('width'); ?></strong></td></tr>
            <tr><td><code>$cfg->get('height')</code></td><td><strong><?php echo $cfg->get('height'); ?></strong></td></tr>
            <tr><td><code>$cfg->get('version')</code></td><td><strong><?php echo $cfg->get('version'); ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\app\config;

$cfg = new config();
$width = $cfg->get('width');       // 1200
$version = $cfg->get('version');   // "5.0.0"</code></pre>
    </div>

    <!-- ==================== env ==================== -->
    <div class="card">
        <h2>MiPhantLibs\system\env</h2>
        <p class="text-muted">Access MiPhant environment variables</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>get(string $name)</code></td><td>string</td><td>Get any env variable</td></tr>
            <tr><td><code>username()</code></td><td>string</td><td>System username</td></tr>
            <tr><td><code>lang()</code></td><td>string</td><td>System language</td></tr>
            <tr><td><code>platform()</code></td><td>string</td><td>OS platform</td></tr>
            <tr><td><code>homeDir()</code></td><td>string</td><td>Home directory</td></tr>
            <tr><td><code>argv()</code></td><td>string</td><td>Command line arguments</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $e = new env();
        ?>
        <table>
            <tr><td><code>$env->username()</code></td><td><strong><?php echo $e->username(); ?></strong></td></tr>
            <tr><td><code>$env->lang()</code></td><td><strong><?php echo $e->lang(); ?></strong></td></tr>
            <tr><td><code>$env->platform()</code></td><td><strong><?php echo $e->platform(); ?></strong></td></tr>
            <tr><td><code>$env->homeDir()</code></td><td><strong><?php echo $e->homeDir(); ?></strong></td></tr>
            <tr><td><code>$env->argv()</code></td><td><strong><?php echo $e->argv(); ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\system\env;

$env = new env();
$user = $env->username();    // "murilo"
$platform = $env->platform(); // "linux"
$lang = $env->lang();         // "pt-br"</code></pre>
    </div>

    <!-- ==================== server ==================== -->
    <div class="card">
        <h2>MiPhantLibs\system\server</h2>
        <p class="text-muted">Server information helpers</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>domain()</code></td><td>string</td><td>Full domain (https://localhost:8443)</td></tr>
            <tr><td><code>uri()</code></td><td>string</td><td>Current request URI</td></tr>
            <tr><td><code>documentroot()</code></td><td>string</td><td>App root directory</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $s = new server();
        ?>
        <table>
            <tr><td><code>$server->domain()</code></td><td><strong><?php echo $s->domain(); ?></strong></td></tr>
            <tr><td><code>$server->uri()</code></td><td><strong><?php echo $s->uri(); ?></strong></td></tr>
            <tr><td><code>$server->documentroot()</code></td><td><strong><?php echo $s->documentroot(); ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\system\server;

$server = new server();
$domain = $server->domain();         // "https://localhost:8443"
$docRoot = $server->documentroot(); // "/path/to/app"</code></pre>
    </div>

    <!-- ==================== platform ==================== -->
    <div class="card">
        <h2>MiPhantLibs\system\platform</h2>
        <p class="text-muted">Platform detection helpers</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>osLinux()</code></td><td>bool</td><td>True if Linux</td></tr>
            <tr><td><code>osWindows()</code></td><td>bool</td><td>True if Windows</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $p = new platform();
        ?>
        <table>
            <tr><td><code>$platform->osLinux()</code></td><td><strong><?php echo $p->osLinux() ? 'true' : 'false'; ?></strong></td></tr>
            <tr><td><code>$platform->osWindows()</code></td><td><strong><?php echo $p->osWindows() ? 'true' : 'false'; ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\system\platform;

$platform = new platform();
if ($platform->osLinux()) {
    // Linux-specific code
}
if ($platform->osWindows()) {
    // Windows-specific code
}</code></pre>
    </div>

    <!-- ==================== path ==================== -->
    <div class="card">
        <h2>MiPhantLibs\app\path</h2>
        <p class="text-muted">Cross-platform path builder</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>join(string ...$parts)</code></td><td>string</td><td>Join path segments with OS separator</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $pt = new path();
        ?>
        <table>
            <tr><td><code>$path->join('app', 'data', 'file.txt')</code></td><td><strong><?php echo $pt->join('app', 'data', 'file.txt'); ?></strong></td></tr>
            <tr><td><code>$path->join('/home', 'user', 'docs')</code></td><td><strong><?php echo $pt->join('/home', 'user', 'docs'); ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\app\path;

$path = new path();
$full = $path->join('app', 'data', 'file.txt');
// Linux:  "app/data/file.txt"
// Windows: "app\data\file.txt"</code></pre>
    </div>

    <!-- ==================== translate ==================== -->
    <div class="card">
        <h2>MiPhantLibs\langs\translate</h2>
        <p class="text-muted">i18n translation with fallback chain</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Returns</th><th>Description</th></tr>
            <tr><td><code>get(string $key, string ...$values)</code></td><td>string</td><td>Translate key with optional sprintf args</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <?php
        $t = new translate();
        ?>
        <table>
            <tr><td><code>$translate->get('Continue')</code></td><td><strong><?php echo $t->get('Continue'); ?></strong></td></tr>
            <tr><td><code>$translate->get('Cancel')</code></td><td><strong><?php echo $t->get('Cancel'); ?></strong></td></tr>
            <tr><td><code>$translate->get('Unable to find file %s', 'test.php')</code></td><td><strong><?php echo $t->get('Unable to find file %s', 'test.php'); ?></strong></td></tr>
        </table>

        <h3 class="mt-2">Fallback Chain</h3>
        <p><code>pt-br</code> → <code>pt.json</code> → <code>en.json</code></p>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\langs\translate;

$translate = new translate();
echo $translate->get('Continue');                        // "Continuar" (pt)
echo $translate->get('Unable to find file %s', 'x.php'); // "Não foi possível encontrar o arquivo x.php"</code></pre>
    </div>

    <!-- ==================== functions ==================== -->
    <div class="card">
        <h2>MiPhantLibs\app\functions</h2>
        <p class="text-muted">PHP helpers that generate JavaScript calls via preload</p>

        <h3 class="mt-2">Methods</h3>
        <table>
            <tr><th>Method</th><th>Description</th></tr>
            <tr><td><code>alert($title, $msg, $type)</code></td><td>Show alert dialog</td></tr>
            <tr><td><code>confirm($title, $msg, $type, $onYes, $onNo)</code></td><td>Show confirm dialog</td></tr>
            <tr><td><code>newWindow($url, $w, $h, $resizable, $frame, $hide, $menu)</code></td><td>Open new MiPhant window</td></tr>
            <tr><td><code>openURL($url)</code></td><td>Open URL in system browser</td></tr>
            <tr><td><code>notification($title, $msg)</code></td><td>Show desktop notification</td></tr>
            <tr><td><code>redirect($url, $params)</code></td><td>Navigate to page</td></tr>
            <tr><td><code>closeWindow()</code></td><td>Close current window</td></tr>
            <tr><td><code>tray($title, $tooltip, $icon, $menus)</code></td><td>Setup system tray</td></tr>
            <tr><td><code>noTag()</code></td><td>Disable auto &lt;script&gt; tags</td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>use MiPhantLibs\app\functions;

$func = new functions();

// Alert (auto-wraps in &lt;script&gt;)
$func->alert('Info', 'Hello!', 'info');

// Without &lt;script&gt; tags (for inline use)
$func->noTag()->newWindow('page.php', 800, 600);

// Redirect with params
$func->redirect('page.php', ['id' => 42, 'name' => 'test']);</code></pre>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
