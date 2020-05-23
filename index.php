<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
define("DB_LOGIN", "root");
define("DB_PASS", "");

function getLastApacheVersion()
{
    $html = file_get_contents('https://www.apachelounge.com/download/VC15/');
    preg_match('/https:\/\/home.apache.org\/~steffenal\/VC15\/binaries\/httpd-([0-9.]+)-win64-VC15.zip/i', $html, $matches);
    $link = current($matches);
    $ver = end($matches);
    return [$link, $ver];
}

function getLastMariadbVersion()
{
    $html = file_get_contents('https://downloads.mariadb.org/mariadb/');
    preg_match('/\/mariadb\/([0-9.]+)/i', $html, $matches);
    $link =  'https://downloads.mariadb.org/interstitial/mariadb-'.end($matches).'/winx64-packages/mariadb-'.end($matches).'-winx64.zip/from/http%3A//ftp.utexas.edu/mariadb/';
    $ver = end($matches);
    return [$link, $ver];
}

// get page for phpinfo
function getQ($getQ)
{
    if (!empty($getQ)) {
        switch ($getQ) {
            case 'info':
                phpinfo();
                exit;
                break;
        }
    }
}

// Get PHP extensions
function getServerExtensions($server)
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
function getPhpVersion()
{
    $lastVersion = 0;
    // get last version from php.net
    $json = file_get_contents('https://www.php.net/releases/index.php?json');
    $data = current(json_decode($json, true));

    $lastVersion = $data['version'];

    // get current installed version
    $phpVersion = phpversion();
    
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
function serverInfo()
{
    $server = explode(' ', $_SERVER['SERVER_SOFTWARE']);
    $openSsl = isset($server[2]) ? $server[2] : null;
    
    return [
        'httpdVer' => $server[0],
        'openSsl'  => $openSsl,
        'phpVer'   => getPhpVersion()['currentVersion'],
        'xDebug'   => phpversion('xdebug'),
        'docRoot'  => $_SERVER['DOCUMENT_ROOT'],
    ];
}

// get SQL version
function getSQLVersion() {
    $link = mysqli_connect("localhost", DB_LOGIN, DB_PASS);
    $output =  mysqli_get_server_info($link);
    mysqli_close($link);
    return $output;
}

// PHP links
function phpDlLink($version)
{
    $changelog = 'https://www.php.net/ChangeLog-7.php#' . $version;
    $downLink = 'https://windows.php.net/downloads/releases/php-' . $version . '-Win32-VC15-x64.zip';
    
    return [
        'changeLog' => $changelog,
        'downLink'  => $downLink
    ];
}

// define sites-enabled directory
function getSiteDir()
{
    if (preg_match("/^Apache/", $_SERVER['SERVER_SOFTWARE'])) {
        return "../laragon/etc/apache2/sites-enabled";
    } else {
        return "../laragon/etc/nginx/sites-enabled";
    }
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
        $line = array_search($value, $scanDir);
        unset($scanDir[$line]);
    }
    
    return $scanDir;
}

// Render list of links
function renderLinks()
{
    ob_start();
    
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
            
        echo '
            <div class="row w800 my-2">
                <div class="col-md-5 text-truncate tr">' . $contentHttp . ' </div>
                <div class="col-2 arrows">&xlArr; &sext; &xrArr;</div>
                <div class="col-md-5 text-truncate tl">' . $contentHttps . '</div>
            </div>
            <hr>
        ';
        }
    }
    
    return ob_get_clean();
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

$phpVer = getPhpVersion();
$serverInfo = serverInfo();
$phpVer['intLastVer'] = strlen($phpVer['intLastVer']) === 3 ? $phpVer['intLastVer'] . '0' : $phpVer['intLastVer'];
?>

<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <title>Laragon WebServer Projects</title>
    <style>ul{list-style-type:none;padding:inherit;}a{color:#555;font-weight:bold;}a:hover{color:#007cff;text-decoration:none;}.banner{text-align:center;text-shadow:3px 3px 6px rgba(0,0,0,.8);}@media(max-width:767px){.arrows{display:none;}.tl{text-align:center!important;}.tr{text-align:center!important;}hr{display:block!important;}}hr{display:none}.w800{max-width:800px;margin:0 auto;}.tl{text-align:left;}.tr{text-align:right;}</style>
</head>
<body>
<div class="container">
    <div class="banner">
        <img class="" src="https://laragon.org/logo.svg" alt="Laragon" height="180px" width="180px"/>
        <h1>Laragon WebServer</h1>
    </div>
    <!--    Buttons-->
    <div class="text-center">
        <?php if ($phpVer['intCurVer'] < $phpVer['intLastVer']): ?>
            <button type="button" class="btn btn-sm btn-success" data-toggle="modal" data-target="#updateModal">
                PHP new update
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#extensionsModal">
            PHP extensions
        </button>
        <?php if (checkHttpdServer('apache')): ?>
            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#apacheExtModal">
                Apache extensions
            </button>
        <?php endif; ?>
    </div>
    <!--    Projects list-->
    <div class="row pt-3">
        <div class="container text-center">
            <h2 class="text-center text-primary pb-2">Laragon Projects</h2>
            <?= renderLinks(); ?>
        </div>
    </div>
    <!--Server informations-->
    <div class="row pt-3">
        <div class="container text-center">
            <h2 class="text-primary">Server Informations</h2>
            <div class="col-12">
                <?= $serverInfo['httpdVer']; ?> /
                <?= apache_get_version()  ?> /
                <?php $lastApacheVer = getLastApacheVersion(); ?>
                <span class="small">Apache Last update: <a href="<?= current($lastApacheVer); ?>"><?= end($lastApacheVer);?></a></span>
            </div>
            <div class="col-12">
                <?= $serverInfo['openSsl']; ?>
            </div>
            <div class="col-12">
                PHP/<strong><a href="./?q=info"><?= $serverInfo['phpVer']; ?></a></strong>
            </div>
            <div class="col-12">
                Xdebug/<strong><?= $serverInfo['xDebug']; ?></strong>
            </div>
            <div class="col-12">
                SQL/<strong><?= getSQLVersion(); ?></strong> /
                <?php $lastMariadbVer = getLastMariadbVersion(); ?>
                <span class="small">Mariadb Last update: <a href="<?= current($lastMariadbVer); ?>"><?= end($lastMariadbVer);?></a></span>
            </div>
            <div class="col-12">
                Document Root: <strong><?= $serverInfo['docRoot']; ?></strong>
            </div>
            <div class="col-12">
                <a title="Getting Started" href="https://laragon.org/docs">Getting started with Laragon</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="updateModal" tabindex="-1" role="dialog" aria-labelledby="updateModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateModalLabel">PHP Update</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <p>
                    PHP <span class="text-primary font-weight-bold"><?= $phpVer['lastVersion']; ?></span> is available
                    (<a href="<?= phpDlLink($phpVer['lastVersion'])['changeLog']; ?>" class="text-dark" target="_blank">View changelog</a>)
                </p>
                <p>Current version <strong><?= $phpVer['currentVersion'] ?></strong></p>
            </div>
            <div class="modal-footer">
                <a href="<?= phpDlLink($phpVer['lastVersion'])['downLink']; ?>" class="btn btn-sm btn-success text-light">
                    Download PHP <?= $phpVer['lastVersion']; ?>
                </a>
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="extensionsModal" tabindex="-1" role="dialog" aria-labelledby="extensionsModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="extentionsModalLabel">PHP Extensions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <?php foreach (getServerExtensions('php') as $extension): ?>
                        <div class="row">
                            <div class="col-md-6"><?= $extension[0]; ?></div>
                            <div class="col-md-6"><?= $extension[1]; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="apacheExtModal" tabindex="-1" role="dialog" aria-labelledby="apacheExtModalLabel"
     aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apacheExtModalLabel">Apache Extensions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="container-fluid">
                    <?php foreach (getServerExtensions('apache') as $apacheExt): ?>
                        <div class="row">
                            <div class="col-md-6"><?= $apacheExt[0]; ?></div>
                            <div class="col-md-6"><?= $apacheExt[1]; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
</body>
</html>

