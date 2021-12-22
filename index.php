<?php
ini_set('display_errors', 0);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

function getLastApacheVersion(): array
{
    $html = file_get_contents('https://www.apachelounge.com/download/VC15/');
    preg_match(
        '/https:\/\/home.apache.org\/~steffenal\/VC15\/binaries\/httpd-([0-9.]+)-win64-VC15.zip/i',
        $html,
        $matches
    );
    $link = current($matches);
    $ver = end($matches);
    return [$link, $ver];
}

function getLastMariadbVersion(): array
{
    $html = file_get_contents('https://downloads.mariadb.org/mariadb/');
    preg_match('/\/mariadb\/([0-9.]+)/i', $html, $matches);
    $link = 'https://downloads.mariadb.org/interstitial/mariadb-'
        . end($matches) . '/winx64-packages/mariadb-'
        . end($matches) . '-winx64.zip/from/http%3A//ftp.utexas.edu/mariadb/';
    $ver = end($matches);
    return [$link, $ver];
}

// get page for phpinfo
function getQ($getQ)
{
    if (!empty($getQ) && $getQ === 'info') {
        phpinfo();
        exit;
    }
}

// Get PHP extensions
function getServerExtensions($server): array
{
    $ext = [];

    switch ($server) {
        case 'php':
            $ext = get_loaded_extensions();
            break;
        case 'apache':
            $ext = apache_get_modules();
            break;
    }

    sort($ext, SORT_STRING);
    $ext = array_chunk($ext, 2);

    return $ext;
}

// Check PHP version
/**
 * @throws JsonException
 */
function getPhpVersion(): array
{
    $lastVersion = 0;
    // get last version from php.net
    $json = file_get_contents('https://www.php.net/releases/index.php?json');
    $data = current(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

    $lastVersion = $data['version'];

    // get current installed version
    $phpVersion = PHP_VERSION;

    // Remove dot character from version ex: 1.2.3 to 123 and convert string to integer
    $intLastVersion = (int)str_replace('.', '', $lastVersion);
    $intCurVersion = (int)str_replace('.', '', $phpVersion);

    return [
        'lastVersion'    => $lastVersion,
        'currentVersion' => $phpVersion,
        'intLastVer'     => $intLastVersion,
        'intCurVer'      => $intCurVersion
    ];
}

// Httpd Versions
/**
 * @throws JsonException
 */
function serverInfo(): array
{
    $server = explode(' ', $_SERVER['SERVER_SOFTWARE']);
    $openSsl = $server[2] ?? null;

    return [
        'httpdVer' => $server[0],
        'openSsl'  => $openSsl,
        'phpVer'   => getPhpVersion()['currentVersion'],
        'xDebug'   => phpversion('xdebug'),
        'docRoot'  => $_SERVER['DOCUMENT_ROOT'],
    ];
}

// PHP links
function phpDlLink($version): array
{
    $VC = PHP_VERSION_ID < 80000 ? 'vs16' : 'vc15';
    $changeLog = PHP_VERSION_ID < 80000 ? 8 : 7;
    $changelog = 'https://www.php.net/ChangeLog-' . $changeLog . '.php#' . $version;

    $downLink = 'https://windows.php.net/downloads/releases/php-' . $version . '-Win32-' . $VC . '-x64.zip';

    return [
        'changeLog' => $changelog,
        'downLink'  => $downLink
    ];
}

// define sites-enabled directory
function getSiteDir(): string
{
    if (0 === strpos($_SERVER['SERVER_SOFTWARE'], "Apache")) {
        return "../laragon/etc/apache2/sites-enabled";
    }

    return "../laragon/etc/nginx/sites-enabled";
}

// get local sites list and remove unwanted values
function getLocalSites()
{
    // get sites-enabled directory
    $sitesDir = getSiteDir();
    // scan all files in the directory
    $scanDir = scandir($sitesDir);
    // remove unwanted files ('.', '..', '00-default.conf' by default)
    $rmItems = [
        '.',
        '..',
        '00-default.conf'
    ];

    foreach ($rmItems as $key => $value) {
        $line = array_search($value, $scanDir, true);
        unset($scanDir[$line]);
    }

    return $scanDir;
}

// Render list of links
function renderLinks(): ?string
{
    //ob_start();
    $contentHttp = null;
    $contentHttps = null;
    $linklist = null;

    foreach (getLocalSites() as $value) {
        $start = preg_split('/^auto./', $value);
        $end = preg_split('/.conf$/', $start[1]);
        unset($end[1]);

        foreach ($end as $link) {
            $contentHttp = '<a href="http://' . $link . '">';
            $contentHttp .= 'http://' . $link;
            $contentHttp .= '</a>';
            $contentHttps = '<a href="https://' . $link . '">';
            $contentHttps .= 'https://' . $link;
            $contentHttps .= '</a>';

            $linklist .= '<div class="bg-gray-100 p-2">'
                . $contentHttps . '<span> 🔒 <|> ⚠ </span>' . $contentHttp . '</div>';
        }
    }
    return $linklist;
}

// check is server is Apache/nginx
function checkHttpdServer($server)
{
    if ($server === 'apache') {
        $server = ucfirst($server);
    }

    return preg_match("/^$server/", $_SERVER['SERVER_SOFTWARE']);
}

// ----

isset($_GET['q']) ? getQ($_GET['q']) : null;

try {
    $phpVer = getPhpVersion();
} catch (JsonException $e) {
}
try {
    $serverInfo = serverInfo();
} catch (JsonException $e) {
}
$phpVer['intLastVer'] = strlen($phpVer['intLastVer']) === 3 ? $phpVer['intLastVer'] . '0' : $phpVer['intLastVer'];
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <title>Laragon WebServer Projects</title>
    <style>
        a:hover {
            color:  #3b82f6 !important;
        }
    </style>
</head>
<body>
<div class="container mx-auto">
    <div class="flex flex-col justify-center">
        <img class="self-center" src="https://laragon.org/logo.svg" alt="Laragon" height="180px" width="180px"/>
        <h1 class="text-center text-4xl">Laragon WebServer</h1>
    </div>

    <div class="container text-center p-4">
        <h2 class="text-center text-primary pb-2">Laragon Projects</h2>
        <div class="flex flex-col gap-2"><?= renderLinks(); ?></div>
    </div>


    <!--Server informations-->
    <div class="border p-4">
        <div class="container text-center">
            <h2 class="text-primary">Server Informations</h2>
            <div>
                <?php
                if (function_exists('apache_get_version')) {
                    $lastApacheVer = getLastApacheVersion();

                    echo $serverInfo['httpdVer'];
                    echo '<span class="text-sm">(Apache Last update: <a href="' . current($lastApacheVer) . '">'
                        . end($lastApacheVer) . '</a>)</span>';
                } else {
                    echo $serverInfo['httpdVer'];
                }
                ?>
            </div>
            <div>
                SSL/<strong><?= OPENSSL_VERSION_TEXT ?></strong>
            </div>
            <div>
                PHP/<strong><a href="./?q=info"><?= $serverInfo['phpVer']; ?></a></strong>
                --
                Xdebug/<strong><?= phpversion('xdebug'); ?></strong>
            </div>
            <div>
                Document Root: <strong><?= $serverInfo['docRoot']; ?></strong>
            </div>
            <div>
                <a title="Getting Started" href="https://laragon.org/docs">Getting started with Laragon</a>
            </div>
        </div>
    </div>

    <div class="mx-auto border border-gray-200" x-data="{selected:null}">
        <ul class="shadow-box mb-4">
            <li class="relative border-b border-gray-200 bg-white">
                <button type="button" class="w-full px-8 py-6 text-left shadow"
                        @click="selected !== 1 ? selected = 1 : selected = null">
                    <span class="flex items-center justify-between">
                        <span>PHP Version</span>
                    </span>
                </button>
                <div class="relative overflow-hidden transition-all max-h-0 duration-150 text-sm" style=""
                     x-ref="container1"
                     x-bind:style="selected == 1 ? 'max-height: ' + $refs.container1.scrollHeight + 'px' : ''">
                    <div class="p-6">
                        <p>
                            PHP <strong><?= $phpVer['lastVersion']; ?></strong> is available
                            (<a href="<?= phpDlLink($phpVer['lastVersion'])['changeLog']; ?>" class="text-dark"
                                target="_blank">View
                                changelog</a>)
                        </p>
                        <p>Current version <strong><?= $phpVer['currentVersion'] ?></strong></p>

                        <div>
                            <a href="<?= phpDlLink($phpVer['lastVersion'])['downLink']; ?>"
                               class="btn btn-sm btn-success text-light">
                                Download PHP <strong><?= $phpVer['lastVersion']; ?></strong>
                            </a>
                        </div>
                    </div>
                </div>
            </li>
            <li class="relative border-b border-gray-200 bg-white">
                <button type="button" class="w-full px-8 py-6 text-left shadow"
                        @click="selected !== 2 ? selected = 2 : selected = null">
                <span class="flex items-center justify-between">
                    <span>Loaded PHP Extensions</span>
                </span>
                </button>
                <div class="relative overflow-hidden transition-all max-h-0 duration-150 text-sm" style=""
                     x-ref="container2"
                     x-bind:style="selected == 2 ? 'max-height: ' + $refs.container2.scrollHeight + 'px' : ''">
                    <div class="p-6 flex flex-wrap gap-2">
                        <?php for ($i = 0, $iMax = count(get_loaded_extensions()); $i < $iMax; $i++): ?>
                            <span class="px-4 py-1 rounded-full bg-gray-200 hover:bg-gray-300 cursor-pointer">
                            <?= get_loaded_extensions()[$i] ?>
                        </span>
                        <?php endfor; ?>
                    </div>
                </div>
            </li>
            <li class="relative border-b border-gray-200 bg-white">
                <button type="button" class="w-full px-8 py-6 text-left shadow"
                        @click="selected !== 3 ? selected = 3 : selected = null">
            <span class="flex items-center justify-between">
                <span>Loaded Apache Extensions</span>
            </span>
                </button>
                <div class="relative overflow-hidden transition-all max-h-0 duration-700 text-sm" style=""
                     x-ref="container3"
                     x-bind:style="selected == 3 ? 'max-height: ' + $refs.container3.scrollHeight + 'px' : ''">
                    <div class="p-6 flex flex-wrap gap-2">
                        <?php if (function_exists('apache_get_version')): ?>
                            <?php for ($i = 0, $iMax = count(apache_get_modules()); $i < $iMax; $i++): ?>
                                <span class="px-4 py-1 rounded-full bg-gray-200 hover:bg-gray-300 cursor-pointer">
                            <?= ltrim(apache_get_modules()[$i], 'mod_'); ?>
                            </span>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</div>
</html>
