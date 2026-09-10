<?php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preload API | MiPhant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h1>Preload API</h1>
    <p class="text-muted mb-3">JavaScript API exposed by MiPhant to renderer processes</p>

    <!-- ==================== version ==================== -->
    <div class="card">
        <h2>miphant.version(type)</h2>
        <p class="text-muted">Get version information</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>type</code></td><td>string</td><td><code>'miphant'</code>, <code>'electron'</code>, <code>'node'</code>, or <code>'chromium'</code></td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;string&gt;</code> — Version string</p>

        <h3 class="mt-2">Demo</h3>
        <div id="v-miphant">...</div>
        <div id="v-electron">...</div>
        <div id="v-node">...</div>
        <div id="v-chromium">...</div>
        <script>
            async function showVersions() {
                document.getElementById('v-miphant').innerHTML = '<strong>MiPhant:</strong> ' + await miphant.version('miphant');
                document.getElementById('v-electron').innerHTML = '<strong>Electron:</strong> ' + await miphant.version('electron');
                document.getElementById('v-node').innerHTML = '<strong>Node.js:</strong> ' + await miphant.version('node');
                document.getElementById('v-chromium').innerHTML = '<strong>Chromium:</strong> ' + await miphant.version('chromium');
            }
            showVersions();
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const version = await miphant.version('miphant');
console.log(version); // "5.0.0"</code></pre>
    </div>

    <!-- ==================== alert ==================== -->
    <div class="card">
        <h2>miphant.alert(title, msg, type, button)</h2>
        <p class="text-muted">Show an alert dialog</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>title</code></td><td>string</td><td>Dialog title</td></tr>
            <tr><td><code>msg</code></td><td>string</td><td>Message text</td></tr>
            <tr><td><code>type</code></td><td>string</td><td><code>'info'</code>, <code>'warning'</code>, <code>'error'</code>, <code>'question'</code></td></tr>
            <tr><td><code>button</code></td><td>string</td><td>Button label</td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;number&gt;</code> — Button index</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="demoAlert()">Show Alert</button>
        <script>
            function demoAlert() {
                miphant.alert('Information', 'This is an example alert!', 'info', 'OK');
            }
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>await miphant.alert('Title', 'Message', 'info', 'OK');</code></pre>
    </div>

    <!-- ==================== confirm ==================== -->
    <div class="card">
        <h2>miphant.confirm(title, msg, type, ...buttons)</h2>
        <p class="text-muted">Show a confirmation dialog with multiple buttons</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>title</code></td><td>string</td><td>Dialog title</td></tr>
            <tr><td><code>msg</code></td><td>string</td><td>Message text</td></tr>
            <tr><td><code>type</code></td><td>string</td><td><code>'info'</code>, <code>'warning'</code>, <code>'error'</code>, <code>'question'</code></td></tr>
            <tr><td><code>...buttons</code></td><td>string</td><td>Button labels</td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;number&gt;</code> — Index of clicked button (0 = first)</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="demoConfirm()">Show Confirm</button>
        <span id="confirm-result" class="ml-2"></span>
        <script>
            async function demoConfirm() {
                const result = await miphant.confirm(
                    'Confirm', 'Do you want to continue?', 'question', 'Yes', 'No'
                );
                document.getElementById('confirm-result').innerHTML =
                    result === 0
                        ? '<span class="text-success">Confirmed (Yes)</span>'
                        : '<span class="text-danger">Cancelled (No)</span>';
            }
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const result = await miphant.confirm(
    'Title', 'Are you sure?', 'question', 'Yes', 'No'
);
if (result === 0) { /* Yes */ } else { /* No */ }</code></pre>
    </div>

    <!-- ==================== newWindow ==================== -->
    <div class="card">
        <h2>miphant.newWindow(url, width, height, resizable, frame, hide, menu)</h2>
        <p class="text-muted">Open a new MiPhant window</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr>
            <tr><td><code>url</code></td><td>string</td><td>—</td><td>Page URL to load</td></tr>
            <tr><td><code>width</code></td><td>number</td><td>800</td><td>Window width</td></tr>
            <tr><td><code>height</code></td><td>number</td><td>600</td><td>Window height</td></tr>
            <tr><td><code>resizable</code></td><td>boolean</td><td>true</td><td>Allow resize</td></tr>
            <tr><td><code>frame</code></td><td>boolean</td><td>true</td><td>Show title bar</td></tr>
            <tr><td><code>hide</code></td><td>boolean</td><td>false</td><td>Start hidden</td></tr>
            <tr><td><code>menu</code></td><td>string</td><td>'menu'</td><td>Menu file name (without .json)</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="miphant.newWindow('env.php', 600, 400)">Open Env Window</button>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.newWindow('page.php', 1024, 768, true, true, false, 'menu');</code></pre>
    </div>

    <!-- ==================== openURL ==================== -->
    <div class="card">
        <h2>miphant.openURL(url)</h2>
        <p class="text-muted">Open URL in system default browser</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>url</code></td><td>string</td><td>Full URL to open</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-outline" onclick="miphant.openURL('https://github.com/profmugomes/miphant')">Open GitHub</button>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.openURL('https://example.com');</code></pre>
    </div>

    <!-- ==================== translate ==================== -->
    <div class="card">
        <h2>miphant.translate(text, ...values)</h2>
        <p class="text-muted">Translate a key using the current language</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>text</code></td><td>string</td><td>Translation key</td></tr>
            <tr><td><code>...values</code></td><td>string</td><td>Values for sprintf</td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;string&gt;</code> — Translated string</p>

        <h3 class="mt-2">Demo</h3>
        <div id="translate-demo">Loading...</div>
        <script>
            async function showTranslate() {
                const t1 = await miphant.translate('Continue');
                const t2 = await miphant.translate('Cancel');
                document.getElementById('translate-demo').innerHTML =
                    '<strong>Continue:</strong> ' + t1 + '<br>' +
                    '<strong>Cancel:</strong> ' + t2;
            }
            showTranslate();
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const text = await miphant.translate('Continue');
const msg = await miphant.translate('Unable to find file %s', 'file.php');</code></pre>
    </div>

    <!-- ==================== selectDirectory ==================== -->
    <div class="card">
        <h2>miphant.selectDirectory()</h2>
        <p class="text-muted">Open system directory picker dialog</p>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;string&gt;</code> — Selected directory path</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="demoSelectDir()">Select Directory</button>
        <span id="dir-result" class="ml-2"></span>
        <script>
            async function demoSelectDir() {
                const dir = await miphant.selectDirectory();
                if (dir) {
                    document.getElementById('dir-result').innerHTML =
                        '<span class="text-success">' + dir + '</span>';
                }
            }
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const dir = await miphant.selectDirectory();
if (dir) { console.log('Selected:', dir); }</code></pre>
    </div>

    <!-- ==================== openFile ==================== -->
    <div class="card">
        <h2>miphant.openFile(multi)</h2>
        <p class="text-muted">Open system file picker dialog</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr>
            <tr><td><code>multi</code></td><td>boolean</td><td>false</td><td>Allow multiple selection</td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;string|string[]&gt;</code> — File path(s)</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="demoOpenFile()">Open File</button>
        <button class="btn btn-outline" onclick="demoOpenFiles()">Open Multiple</button>
        <div id="file-result" class="mt-1"></div>
        <script>
            async function demoOpenFile() {
                const file = await miphant.openFile(false);
                if (file) {
                    document.getElementById('file-result').innerHTML =
                        '<span class="text-success">' + file + '</span>';
                }
            }
            async function demoOpenFiles() {
                const files = await miphant.openFile(true);
                if (files) {
                    document.getElementById('file-result').innerHTML =
                        '<span class="text-success">' + files.join('<br>') + '</span>';
                }
            }
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const file = await miphant.openFile();        // single
const files = await miphant.openFile(true);  // multiple</code></pre>
    </div>

    <!-- ==================== saveFile ==================== -->
    <div class="card">
        <h2>miphant.saveFile()</h2>
        <p class="text-muted">Open system save file dialog</p>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;string&gt;</code> — Selected save path</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-success" onclick="demoSaveFile()">Save File</button>
        <span id="save-result" class="ml-2"></span>
        <script>
            async function demoSaveFile() {
                const file = await miphant.saveFile();
                if (file) {
                    document.getElementById('save-result').innerHTML =
                        '<span class="text-success">' + file + '</span>';
                }
            }
        </script>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const path = await miphant.saveFile();
if (path) { fs.writeFileSync(path, data); }</code></pre>
    </div>

    <!-- ==================== notification ==================== -->
    <div class="card">
        <h2>miphant.notification(title, text)</h2>
        <p class="text-muted">Show a desktop notification</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>title</code></td><td>string</td><td>Notification title</td></tr>
            <tr><td><code>text</code></td><td>string</td><td>Notification body</td></tr>
        </table>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-primary" onclick="miphant.notification('MiPhant', 'Hello from notification!')">Send Notification</button>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.notification('Title', 'Body text');</code></pre>
    </div>

    <!-- ==================== tray ==================== -->
    <div class="card">
        <h2>miphant.tray(title, tooltip, icon, menus)</h2>
        <p class="text-muted">Setup system tray with context menu</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>title</code></td><td>string</td><td>Tray title</td></tr>
            <tr><td><code>tooltip</code></td><td>string</td><td>Hover tooltip</td></tr>
            <tr><td><code>icon</code></td><td>string</td><td>Icon path (empty for default)</td></tr>
            <tr><td><code>menus</code></td><td>string (JSON)</td><td>Menu items as JSON string</td></tr>
        </table>

        <h3 class="mt-2">Menu JSON Format</h3>
        <pre><code>{
    "Label": { "page": "target.php" },
    "External": { "url": "https://example.com" },
    "Action": { "script": "console.log('clicked');" }
}</code></pre>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.tray('App', 'Tooltip', '', JSON.stringify({
    "Home": { page: "index.php" },
    "Close": { script: "window.close();" }
}));</code></pre>
    </div>

    <!-- ==================== fileExists ==================== -->
    <div class="card">
        <h2>miphant.fileExists(filename)</h2>
        <p class="text-muted">Check if a file exists on the filesystem</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Description</th></tr>
            <tr><td><code>filename</code></td><td>string</td><td>Full file path</td></tr>
        </table>

        <h3 class="mt-2">Returns</h3>
        <p><code>Promise&lt;boolean&gt;</code></p>

        <h3 class="mt-2">Usage</h3>
        <pre><code>const exists = await miphant.fileExists('/path/to/file');
if (exists) { /* file found */ }</code></pre>
    </div>

    <!-- ==================== exportPDF ==================== -->
    <div class="card">
        <h2>miphant.exportPDF(filename, options)</h2>
        <p class="text-muted">Export current page as PDF</p>

        <table>
            <tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr>
            <tr><td><code>filename</code></td><td>string</td><td>—</td><td>Output file path</td></tr>
            <tr><td><code>options</code></td><td>object</td><td>{ pageSize: 'A4' }</td><td>PDF options</td></tr>
        </table>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.exportPDF('/home/user/output.pdf', { pageSize: 'A4' });</code></pre>
    </div>

    <!-- ==================== devTools ==================== -->
    <div class="card">
        <h2>miphant.devTools()</h2>
        <p class="text-muted">Open Chrome DevTools for the current window</p>

        <h3 class="mt-2">Demo</h3>
        <button class="btn btn-outline" onclick="miphant.devTools()">Open DevTools</button>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.devTools();</code></pre>
    </div>

    <!-- ==================== close ==================== -->
    <div class="card">
        <h2>miphant.close()</h2>
        <p class="text-muted">Close the MiPhant application</p>

        <h3 class="mt-2">Usage</h3>
        <pre><code>miphant.close();</code></pre>
    </div>

    <a href="index.php" class="btn btn-outline">&larr; Back</a>
</body>
</html>
