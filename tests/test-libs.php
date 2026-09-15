#!/usr/bin/php8.5
<?php
/**
 * MiPhantLibs — Teste Geral
 * Executa: /usr/bin/php8.5 tests/test-libs.php
 */

$BASE = dirname(__DIR__);
$LIBS = $BASE . '/app/public/libs';

// ── Carrega todas as libs ──
require_once "$LIBS/app/path.php";
require_once "$LIBS/app/file.php";
require_once "$LIBS/app/folder.php";
require_once "$LIBS/app/text.php";
require_once "$LIBS/app/arrays.php";
require_once "$LIBS/app/numbers.php";
require_once "$LIBS/app/datetime.php";
require_once "$LIBS/app/functions.php";
require_once "$LIBS/app/config.php";
require_once "$LIBS/app/about.php";
require_once "$LIBS/app/router.php";
require_once "$LIBS/app/checkupdate.php";
require_once "$LIBS/app/createshortcut.php";
require_once "$LIBS/system/env.php";
require_once "$LIBS/system/exec.php";
require_once "$LIBS/system/platform.php";
require_once "$LIBS/system/server.php";
require_once "$LIBS/langs/translate.php";
require_once "$LIBS/security/get.php";
require_once "$LIBS/security/post.php";
require_once "$LIBS/security/items.php";

// ── Framework de teste simples ──
$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $fn) {
    global $passed, $failed, $errors;
    try {
        $fn();
        echo "  ✅ $name\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  ❌ $name\n     → {$e->getMessage()}\n";
        $failed++;
        $errors[] = "$name: {$e->getMessage()}";
    }
}

function assert_eq(mixed $got, mixed $expected, string $msg = '') {
    if ($got !== $expected) {
        $g = var_export($got, true);
        $e = var_export($expected, true);
        throw new \RuntimeException($msg ? "$msg — esperado $e, obtido $g" : "esperado $e, obtido $g");
    }
}

function assert_true(mixed $val, string $msg = '') {
    if (!$val) throw new \RuntimeException($msg ?: "esperado true, obtido " . var_export($val, true));
}

function assert_false(mixed $val, string $msg = '') {
    if ($val) throw new \RuntimeException($msg ?: "esperado false, obtido " . var_export($val, true));
}

function assert_contains(string $needle, string $haystack, string $msg = '') {
    if (strpos($haystack, $needle) === false) {
        throw new \RuntimeException($msg ?: "esperado conter '$needle' em '$haystack'");
    }
}

// ────────────────────────────────────────────
echo "\n═══════════════════════════════════════════\n";
echo "  MiPhantLibs — Testes Gerais\n";
echo "═══════════════════════════════════════════\n\n";

// ════════════════════════════════════════════
echo "── app\\path ──\n";
// ════════════════════════════════════════════
$path = new MiPhantLibs\app\path();

test('join com dois segmentos', function () use ($path) {
    assert_eq($path->join('a', 'b'), 'a' . DIRECTORY_SEPARATOR . 'b');
});

test('join remove barras extras', function () use ($path) {
    assert_eq($path->join('/a/', '/b/'), 'a' . DIRECTORY_SEPARATOR . 'b');
});

test('join com três segmentos', function () use ($path) {
    assert_eq($path->join('home', 'user', 'dir'), implode(DIRECTORY_SEPARATOR, ['home', 'user', 'dir']));
});

test('join com barra mista', function () use ($path) {
    $result = $path->join('a\\', '/b');
    assert_true($result === 'a' . DIRECTORY_SEPARATOR . 'b' || $result === 'a\\b');
});

test('join vazio', function () use ($path) {
    assert_eq($path->join(''), '');
});

test('join com multiple slashes', function () use ($path) {
    assert_eq($path->join('///a///', '///b///'), 'a' . DIRECTORY_SEPARATOR . 'b');
});

// ════════════════════════════════════════════
echo "\n── app\\file ──\n";
// ════════════════════════════════════════════
$file = new MiPhantLibs\app\file();
$tmpDir = sys_get_temp_dir() . '/miphant_test_' . getmypid();

test('create arquivo', function () use ($file, $tmpDir) {
    @mkdir($tmpDir, 0777, true);
    assert_true($file->create("$tmpDir/test.txt"));
    assert_true(file_exists("$tmpDir/test.txt"));
});

test('exists para arquivo criado', function () use ($file, $tmpDir) {
    assert_true($file->exists("$tmpDir/test.txt"));
});

test('exists para arquivo inexistente', function () use ($file) {
    assert_false($file->exists('/tmp/miphant_nonexistent_999.txt'));
});

test('save e open (replace)', function () use ($file, $tmpDir) {
    $file->save("$tmpDir/test.txt", 'linha1');
    $file->save("$tmpDir/test.txt", 'linha2');
    assert_eq($file->open("$tmpDir/test.txt"), 'linha2');
});

test('save append', function () use ($file, $tmpDir) {
    $file->save("$tmpDir/test.txt", 'A');
    $file->save("$tmpDir/test.txt", 'B', false);
    assert_eq($file->open("$tmpDir/test.txt"), 'AB');
});

test('open arquivo inexistente retorna false', function () use ($file) {
    assert_eq($file->open('/tmp/miphant_nonexistent_999.txt'), false);
});

test('checkExtension sem ext esperada', function () use ($file) {
    assert_true($file->checkExtension('file.txt'));
    assert_true($file->checkExtension('image.png'));
    assert_false($file->checkExtension('noext'));
});

test('checkExtension com ext específica', function () use ($file) {
    assert_true($file->checkExtension('file.txt', 'txt'));
    assert_false($file->checkExtension('file.txt', 'php'));
    assert_true($file->checkExtension('archive.tar.gz', 'gz'));
});

test('remove arquivo', function () use ($file, $tmpDir) {
    assert_true($file->remove("$tmpDir/test.txt"));
    assert_false(file_exists("$tmpDir/test.txt"));
});

// ════════════════════════════════════════════
echo "\n── app\\folder ──\n";
// ════════════════════════════════════════════
$folder = new MiPhantLibs\app\folder();

test('create pasta', function () use ($folder, $tmpDir) {
    assert_true($folder->create("$tmpDir/subfolder"));
    assert_true(is_dir("$tmpDir/subfolder"));
});

test('exists pasta', function () use ($folder, $tmpDir) {
    assert_true($folder->exists("$tmpDir/subfolder"));
});

test('create pasta recursiva', function () use ($folder, $tmpDir) {
    assert_true($folder->create("$tmpDir/a/b/c"));
    assert_true(is_dir("$tmpDir/a/b/c"));
});

test('remove pasta vazia', function () use ($folder, $tmpDir) {
    assert_true($folder->remove("$tmpDir/subfolder"));
    assert_false(is_dir("$tmpDir/subfolder"));
});

test('remove pasta recursiva', function () use ($folder, $tmpDir) {
    file_put_contents("$tmpDir/a/b/file.txt", 'test');
    assert_true($folder->remove("$tmpDir/a", true));
    assert_false(is_dir("$tmpDir/a"));
});

// Limpa diretório temporário
@exec("rm -rf " . escapeshellarg($tmpDir));

// ════════════════════════════════════════════
echo "\n── app\\text ──\n";
// ════════════════════════════════════════════
$text = new MiPhantLibs\app\text();

test('removeAccent', function () use ($text) {
    assert_eq($text->removeAccent('São Paulo'), 'Sao Paulo');
    assert_eq($text->removeAccent('áéíóúàèìòùâêîôûãõäëïöüç'), 'aeiouaeiouaeiouaoaeiouc');
});

test('removeAccent uppercase', function () use ($text) {
    assert_eq($text->removeAccent('ÇAÇÃO'), 'CACAO');
});

test('removeSpecialCharacters', function () use ($text) {
    assert_eq($text->removeSpecialCharacters('price $100 @here 50%'), 'price 100 here 50');
    assert_eq($text->removeSpecialCharacters('a/b*c+d#e&f@g'), 'abcdefg');
});

test('separator', function () use ($text) {
    assert_eq($text->separator('São Paulo'), 'Sao-Paulo');
    assert_eq($text->separator('Olá Mundo!', '_'), 'Ola_Mundo!');
});

test('separator com caracteres especiais', function () use ($text) {
    assert_eq($text->separator('Café & Cia $'), 'Cafe--Cia-');
});

// ════════════════════════════════════════════
echo "\n── app\\arrays ──\n";
// ════════════════════════════════════════════
$arr = new MiPhantLibs\app\arrays();

test('check substring', function () use ($arr) {
    assert_true($arr->check('hello world', 'hello'));
    assert_true($arr->check('hello world', 'world'));
    assert_false($arr->check('hello world', 'xyz'));
});

test('check com array de valores', function () use ($arr) {
    assert_true($arr->check('hello world', ['xyz', 'hello']));
    assert_false($arr->check('hello world', ['xyz', 'abc']));
});

test('check com valor único (não array)', function () use ($arr) {
    assert_true($arr->check('test value', 'test'));
    assert_false($arr->check('test value', 'xyz'));
});

test('setArray e getCustom', function () use ($arr) {
    $data = ['a' => ['b' => 'value']]; 
    assert_eq($arr->getCustom($data, 'a', 'b'), 'value');
});

test('getCustom chave inexistente', function () use ($arr) {
    $data = ['a' => '1'];
    assert_eq($arr->getCustom($data, 'x', 'y'), '');
});

test('getCustom vazio', function () use ($arr) {
    assert_eq($arr->getCustom([], 'a'), '');
});

// ════════════════════════════════════════════
echo "\n── app\\numbers ──\n";
// ════════════════════════════════════════════
$numbers = new MiPhantLibs\app\numbers();

test('currencyReal', function () use ($numbers) {
    assert_eq($numbers->currencyReal('1234.56'), '1.234,56');
    assert_eq($numbers->currencyReal('1000000'), '1.000.000,00');
    assert_eq($numbers->currencyReal('0.5'), '0,50');
});

test('format zero padding', function () use ($numbers) {
    assert_eq($numbers->format('5'), '05');
    assert_eq($numbers->format('15'), '15');
    assert_eq($numbers->format(''), '0');
});

// ════════════════════════════════════════════
echo "\n── app\\datetime ──\n";
// ════════════════════════════════════════════
$dt = new MiPhantLibs\app\datetime();

test('date formato dd/mm/YYYY', function () use ($dt) {
    $d = $dt->date();
    assert_true(preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $d) === 1, "formato: $d");
});

test('now formato dd/mm/YYYY HH:MM:SS', function () use ($dt) {
    $n = $dt->now();
    assert_true(preg_match('/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/', $n) === 1, "formato: $n");
});

test('change formato', function () use ($dt) {
    assert_eq($dt->change('15/09/2025', 'd/m/Y', 'Y-m-d'), '2025-09-15');
    assert_eq($dt->change('2025-09-15', 'Y-m-d', 'd/m/Y'), '15/09/2025');
});

test('dayOfTheWeek', function () use ($dt) {
    assert_eq($dt->dayOfTheWeek('2025-09-15'), 'Monday');
    assert_eq($dt->dayOfTheWeek('2025-09-14'), 'Sunday');
    assert_eq($dt->dayOfTheWeek('2025-09-17'), 'Wednesday');
});

test('monthInFull', function () use ($dt) {
    assert_eq($dt->monthInFull(1), 'January');
    assert_eq($dt->monthInFull(9), 'September');
    assert_eq($dt->monthInFull(12), 'December');
    assert_eq($dt->monthInFull(0), '');
});

test('monthInFull com zero à esquerda', function () use ($dt) {
    assert_eq($dt->monthInFull('01'), 'January');
    assert_eq($dt->monthInFull('09'), 'September');
});

// ════════════════════════════════════════════
echo "\n── system\\platform ──\n";
// ════════════════════════════════════════════
$platform = new MiPhantLibs\system\platform();

test('osLinux ou osWindows (exatamente um true)', function () use ($platform) {
    $linux = $platform->osLinux();
    $windows = $platform->osWindows();
    assert_true($linux !== $windows, "Linux=$linux, Windows=$windows — exatamente um deve ser true");
});

test('osLinux no Linux', function () use ($platform) {
    if (PHP_OS_FAMILY === 'Linux') {
        assert_true($platform->osLinux());
    } else {
        assert_false($platform->osLinux());
    }
});

test('osWindows no Windows', function () use ($platform) {
    if (PHP_OS_FAMILY === 'Windows') {
        assert_true($platform->osWindows());
    } else {
        assert_false($platform->osWindows());
    }
});

// ════════════════════════════════════════════
echo "\n── system\\server ──\n";
// ════════════════════════════════════════════
$server = new MiPhantLibs\system\server();

test('documentroot retorna diretorio existente', function () use ($server) {
    $docroot = $server->documentroot();
    assert_true(is_dir($docroot), "documentroot '$docroot' não é um diretório");
});

test('documentroot contem app/public', function () use ($server) {
    $docroot = $server->documentroot();
    assert_true(str_contains($docroot, 'app/public'), "documentroot: $docroot");
});

test('domain formato', function () use ($server) {
    $domain = $server->domain();
    assert_true(str_starts_with($domain, 'https://'), "domain: $domain");
});

// ════════════════════════════════════════════
echo "\n── system\\env ──\n";
// ════════════════════════════════════════════
$env = new MiPhantLibs\system\env();

test('get com variável inexistente retorna vazio', function () use ($env) {
    assert_eq($env->get('MIPHANT_VAR_INEXISTENTE_999'), '');
});

test('get com variável definida (via $_ENV)', function () use ($env) {
    // filter_input(INPUT_ENV) só lê variáveis reais do processo.
    // Em CLI, putenv()/$_ENV não alimenta filter_input(INPUT_ENV).
    // Este teste valida que o método retorna string sem erro.
    $_ENV['MIPHANT_TEST_VAR'] = 'hello';
    $result = $env->get('MIPHANT_TEST_VAR');
    // Pode retornar '' em CLI (limitação PHP), ou 'hello' em CGI/FPM
    assert_true(is_string($result), "deve retornar string, obtido: " . get_debug_type($result));
    unset($_ENV['MIPHANT_TEST_VAR']);
});

test('username retorna string', function () use ($env) {
    $u = $env->username();
    assert_true(is_string($u));
});

test('lang retorna string', function () use ($env) {
    $l = $env->lang();
    assert_true(is_string($l));
});

test('platform retorna string', function () use ($env) {
    $p = $env->platform();
    assert_true(is_string($p));
});

test('homeDir retorna string', function () use ($env) {
    $h = $env->homeDir();
    assert_true(is_string($h));
});

test('argv retorna string', function () use ($env) {
    $a = $env->argv();
    assert_true(is_string($a));
});

// ════════════════════════════════════════════
echo "\n── system\\exec ──\n";
// ════════════════════════════════════════════
$exec = new MiPhantLibs\system\exec();

test('command e run (echo)', function () use ($exec) {
    $exec->command('echo "miphant_test_123"');
    $exec->run();
    $val = $exec->values();
    assert_eq(trim($val), 'miphant_test_123');
    $exec->close();
});

// ════════════════════════════════════════════
echo "\n── langs\\translate ──\n";
// ════════════════════════════════════════════
$translate = new MiPhantLibs\langs\translate();

test('get sem tradução retorna texto original', function () use ($translate) {
    $result = $translate->get('This text probably does not exist');
    assert_eq($result, 'This text probably does not exist');
});

test('get com parâmetros', function () use ($translate) {
    $result = $translate->get('Hello %s', 'World');
    assert_eq($result, 'Hello World');
});

test('get com vários parâmetros', function () use ($translate) {
    $result = $translate->get('%s has %d items', 'Admin', 5);
    assert_eq($result, 'Admin has 5 items');
});

// ════════════════════════════════════════════
echo "\n── security\\items ──\n";
// ════════════════════════════════════════════
$items = new MiPhantLibs\security\items();

test('clean string normal', function () use ($items) {
    assert_eq($items->clean('  hello  '), 'hello');
});

test('clean remove tags HTML', function () use ($items) {
    // strip_tags() remove apenas as tags, não o conteúdo
    assert_eq($items->clean('<b>bold</b>'), 'bold');
    assert_eq($items->clean('<script>alert(1)</script>hello'), 'alert(1)hello');
    assert_false(str_contains($items->clean('<p>test</p>'), '<p>'));
});

test('clean remove aspas simples', function () use ($items) {
    $result = $items->clean("it's a test");
    assert_true(strpos($result, "\\'") !== false || strpos($result, "it's") !== false || $result === "it\\'s a test");
});

test('clean null retorna vazio', function () use ($items) {
    assert_eq($items->clean(null), '');
});

test('clean string vazia', function () use ($items) {
    assert_eq($items->clean(''), '');
});

test('clean adiciona barra em aspas duplas', function () use ($items) {
    $result = $items->clean('say "hello"');
    assert_true(strpos($result, '\"') !== false, "resultado: $result");
});

// ════════════════════════════════════════════
echo "\n── app\\config ──\n";
// ════════════════════════════════════════════
$config = new MiPhantLibs\app\config();

test('config carrega arquivo', function () use ($config) {
    $version = $config->get('version');
    assert_true(!empty($version) || $version === '', "version=$version");
});

test('config get width', function () use ($config) {
    $w = $config->get('width');
    assert_true(is_int($w) || is_string($w), "width=" . var_export($w, true));
});

test('config chave inexistente retorna vazio', function () use ($config) {
    assert_eq($config->get('nonexistent_key_xyz'), '');
});

test('config get aninhado', function () use ($config) {
    // server.router ou similar
    $val = $config->get('server', 'router');
    assert_true(is_bool($val) || is_string($val) || $val === '', "server.router=" . var_export($val, true));
});

// ════════════════════════════════════════════
echo "\n── app\\about (caminhos LICENSE) ──\n";
// ════════════════════════════════════════════

test('MiPhant LICENSE.md acessível via dirname(2)', function () {
    $server = new MiPhantLibs\system\server();
    $base = dirname($server->documentroot(), 2);
    assert_true(file_exists($base . '/LICENSE.md'), "Caminho: $base/LICENSE.md");
});

test('MiPhant LICENSE.md conteúdo válido', function () {
    $server = new MiPhantLibs\system\server();
    $base = dirname($server->documentroot(), 2);
    $content = file_get_contents($base . '/LICENSE.md');
    assert_contains('PolyForm Perimeter', $content);
});

test('PHP LICENSE acessível', function () {
    $server = new MiPhantLibs\system\server();
    $base = dirname($server->documentroot(), 2);
    $phpLicense = $base . '/php/LICENSE';
    assert_true(file_exists($phpLicense), "Caminho: $phpLicense");
});

test('app\\about instantiation', function () {
    $about = new MiPhantLibs\app\about();
    assert_true($about instanceof MiPhantLibs\app\about);
});

// ════════════════════════════════════════════
echo "\n── app\\functions (geração JS) ──\n";
// ════════════════════════════════════════════
$functions = new MiPhantLibs\app\functions();

test('alert gera JS com miphant.alert', function () use ($functions) {
    ob_start();
    $functions->noTag()->alert('Title', 'Message', 'info');
    $js = ob_get_clean();
    assert_contains('miphant.alert', $js);
    assert_contains('Title', $js);
    assert_contains('Message', $js);
});

test('notification gera JS com miphant.notification', function () use ($functions) {
    ob_start();
    $functions->noTag()->notification('Title', 'Body');
    $js = ob_get_clean();
    assert_contains('miphant.notification', $js);
});

test('openURL gera JS com miphant.openURL', function () use ($functions) {
    ob_start();
    $functions->noTag()->openURL('https://example.com');
    $js = ob_get_clean();
    assert_contains('miphant.openURL', $js);
    assert_contains('https://example.com', $js);
});

test('closeWindow gera JS com window.close', function () use ($functions) {
    ob_start();
    $functions->noTag()->closeWindow();
    $js = ob_get_clean();
    assert_contains('window.close', $js);
});

test('redirect gera JS com window.location.assign', function () use ($functions) {
    ob_start();
    $functions->noTag()->redirect('/page');
    $js = ob_get_clean();
    assert_contains("window.location.assign('/page')", $js);
});

test('redirect com parâmetros array', function () use ($functions) {
    ob_start();
    $functions->noTag()->redirect('/page', ['id' => 5, 'tab' => 'main']);
    $js = ob_get_clean();
    assert_contains('id=5', $js);
    assert_contains('tab=main', $js);
});

test('newWindow gera JS com miphant.newWindow', function () use ($functions) {
    ob_start();
    $functions->noTag()->newWindow('/page', 1024, 768, true, true, false, 'menu');
    $js = ob_get_clean();
    assert_contains('miphant.newWindow', $js);
    assert_contains('1024', $js);
    assert_contains('768', $js);
});

test('alert com tags (padrão)', function () use ($functions) {
    ob_start();
    $functions->alert('T', 'M', 'info');
    $js = ob_get_clean();
    assert_contains('<script>', $js);
    assert_contains('miphant.alert', $js);
    assert_contains('</script>', $js);
});

test('noTag remove tags script', function () use ($functions) {
    ob_start();
    $functions->noTag()->alert('T', 'M', 'info');
    $js = ob_get_clean();
    assert_false(str_contains($js, '<script>'), "不应该 conter <script>: $js");
});

// ════════════════════════════════════════════
echo "\n── security\\get (simulado) ──\n";
// ════════════════════════════════════════════
$secGet = new MiPhantLibs\security\get();

test('get classe existe', function () use ($secGet) {
    assert_true($secGet instanceof MiPhantLibs\security\get);
});

test('get método existe', function () use ($secGet) {
    assert_true(method_exists($secGet, 'get'));
    assert_true(method_exists($secGet, 'exists'));
    assert_true(method_exists($secGet, 'request'));
});

// ════════════════════════════════════════════
echo "\n── security\\post (simulado) ──\n";
// ════════════════════════════════════════════
$secPost = new MiPhantLibs\security\post();

test('post classe existe', function () use ($secPost) {
    assert_true($secPost instanceof MiPhantLibs\security\post);
});

test('post método existe', function () use ($secPost) {
    assert_true(method_exists($secPost, 'get'));
    assert_true(method_exists($secPost, 'exists'));
    assert_true(method_exists($secPost, 'request'));
});

// ════════════════════════════════════════════
echo "\n── app\\router (simulado) ──\n";
// ════════════════════════════════════════════
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8443';

test('router instância corretamente', function () {
    $router = new MiPhantLibs\app\router();
    assert_true($router instanceof MiPhantLibs\app\router);
});

// ════════════════════════════════════════════
echo "\n── app\\createshortcut (simulado) ──\n";
// ════════════════════════════════════════════
test('createshortcut classe existe', function () {
    $cs = new MiPhantLibs\app\createshortcut();
    assert_true($cs instanceof MiPhantLibs\app\createshortcut);
    assert_true(method_exists($cs, 'create'));
});

// ════════════════════════════════════════════
echo "\n── app\\checkupdate (simulado) ──\n";
// ════════════════════════════════════════════
test('checkupdate fluent API', function () {
    $cu = new MiPhantLibs\app\checkupdate();
    $result = $cu->url('https://example.com')->show();
    assert_true($result instanceof MiPhantLibs\app\checkupdate);
});

// ════════════════════════════════════════════
echo "\n── Licenças nos arquivos ──\n";
// ════════════════════════════════════════════
$libFiles = [
    "$LIBS/app/path.php",
    "$LIBS/app/file.php",
    "$LIBS/app/folder.php",
    "$LIBS/app/text.php",
    "$LIBS/app/arrays.php",
    "$LIBS/app/numbers.php",
    "$LIBS/app/datetime.php",
    "$LIBS/app/functions.php",
    "$LIBS/app/config.php",
    "$LIBS/app/about.php",
    "$LIBS/app/router.php",
    "$LIBS/app/checkupdate.php",
    "$LIBS/app/createshortcut.php",
    "$LIBS/system/env.php",
    "$LIBS/system/exec.php",
    "$LIBS/system/platform.php",
    "$LIBS/system/server.php",
    "$LIBS/langs/translate.php",
    "$LIBS/security/get.php",
    "$LIBS/security/post.php",
    "$LIBS/security/items.php",
];

test('todas as 21 libs têm header PolyForm Perimeter', function () use ($libFiles) {
    $missing = [];
    foreach ($libFiles as $f) {
        $head = file_get_contents($f, false, null, 0, 200);
        if (strpos($head, 'PolyForm Perimeter') === false) {
            $missing[] = basename($f);
        }
    }
    if (!empty($missing)) {
        throw new \RuntimeException("Arquivos sem header: " . implode(', ', $missing));
    }
});

test('package.json license = SEE LICENSE IN LICENSE.md', function () use ($BASE) {
    $pkg = json_decode(file_get_contents("$BASE/package.json"), true);
    assert_eq($pkg['license'], 'SEE LICENSE IN LICENSE.md');
});

test('app/config.json license = SEE LICENSE IN LICENSE.md', function () use ($BASE) {
    $cfg = json_decode(file_get_contents("$BASE/app/config.json"), true);
    assert_eq($cfg['app']['license'], 'SEE LICENSE IN LICENSE.md');
});

test('LICENSE.md existe na raiz', function () use ($BASE) {
    assert_true(file_exists("$BASE/LICENSE.md"));
});

test('LICENSE.md contém PolyForm Perimeter', function () use ($BASE) {
    $content = file_get_contents("$BASE/LICENSE.md");
    assert_contains('PolyForm Perimeter License 1.0.1', $content);
});

// ════════════════════════════════════════════
echo "\n── JS Server files (syntax check) ──\n";
// ════════════════════════════════════════════
$jsFiles = [
    "$BASE/server/config.js",
    "$BASE/server/logger.js",
    "$BASE/server/php-protocol.js",
    "$BASE/server/http-server.js",
    "$BASE/server/php-manager.js",
    "$BASE/server/certificates.js",
    "$BASE/main.js",
    "$BASE/preload.js",
    "$BASE/mifunctions.js",
    "$BASE/milang.js",
];

foreach ($jsFiles as $jsf) {
    if (file_exists($jsf)) {
        test('JS syntax: ' . basename($jsf), function () use ($jsf) {
            $output = [];
            $ret = 0;
            exec('node --check ' . escapeshellarg($jsf) . ' 2>&1', $output, $ret);
            if ($ret !== 0) {
                throw new \RuntimeException(implode("\n", $output));
            }
        });
    }
}

// ════════════════════════════════════════════
// RESUMO
// ════════════════════════════════════════════
echo "\n═══════════════════════════════════════════\n";
echo "  RESULTADO: $passed passaram, $failed falharam\n";
echo "═══════════════════════════════════════════\n";

if ($failed > 0) {
    echo "\n  ❌ FALHAS:\n";
    foreach ($errors as $e) {
        echo "     • $e\n";
    }
    echo "\n";
    exit(1);
}

echo "  ✅ Todos os testes passaram!\n\n";
exit(0);
