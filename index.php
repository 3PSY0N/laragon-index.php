<?php
/**
 * Laragon Index
 * A simple index script to display information about your Laragon sites and display if .
 */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

class Laragon
{
    /**
     * A simple script to display information on servers managed by laragon
     * This script also displays updates for the various servers used.
     * Made by 3PSY0N https://github.com/3PSY0N/
     *
     *
     *
     * Please configure the constants below to match your setup.
     */

    // Minimum PHP version required to run this page
    private const MIN_PHP_VERSION = '8.0.0';
    // Set the path to your Laragon install directory
    private const LARAGON_DIR = 'D:/laragon';
    // Set the ports for HTTP and HTTPS (Refer to your laragon configuration (Services & Ports))
    private const HTTP_PORT = 80;
    private const HTTPS_PORT = 443;
    /**
     * Show or hide https links (set true only if https is enabled on your Laragon server).
     * Once HTTPS is enabled in the Laragon configuration, you can enable or disable HTTPS links in the index.
     */
    private const SHOW_HTTPS_LINKS = true;

    // SQL Configuration (for Version Check)
    private const SQL_HOST = 'localhost';
    private const SQL_USER = 'root';


    // Show or hide sections
    public const SHOW_INFOS_SECTION = true;
    public const SHOW_PHP_SECTION = true;
    public const SHOW_HTTP_SECTION = true;
    /**
     * Show or hide the SQL section if SQL Server is enabled or disabled.
     * (Enable SQL server in Laragon configuration)
     */
    public const SHOW_SQL_SECTION = true;
    /**
     * End of configuration
     */


    private string|false $currentPhpVersion;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->currentPhpVersion = phpversion();

        $this->checkRequirements();
    }

    /**
     * @throws Exception
     */
    private function checkRequirements(): void
    {
        $this->checkLaragonDir();
        $this->checkPhpVersion();
    }

    /**
     * @throws Exception
     */
    private function checkLaragonDir(): void
    {
        if (empty(self::LARAGON_DIR)) {
            http_response_code(500);
            throw new Exception('Please set the LARAGON_DIR constant.');
        }
    }

    /**
     * @throws Exception
     */
    private function checkPhpVersion(): void
    {
        $phpVersion = phpversion();

        $minPhpVersion = self::MIN_PHP_VERSION;
        $currentPhpVersion = PHP_VERSION_ID;

        $minPhpVersionInt = $this->convertVersion($minPhpVersion);

        if ($currentPhpVersion < $minPhpVersionInt) {
            throw new Exception("The minimum PHP version required is $minPhpVersion. Your current PHP version is $phpVersion.");
        }
    }

    public function convertVersion(string $version): int
    {
        $part = explode('.', $version);
        return (intval($part[0]) * 10000) + (intval($part[1]) * 100) + intval($part[2]);
    }

    public function getCurrentPhpVersion(): false|string
    {
        return $this->currentPhpVersion;
    }

    private function getSiteDir(): string
    {
        if (str_contains($_SERVER['SERVER_SOFTWARE'], "Apache")) {
            return self::LARAGON_DIR . "/etc/apache2/sites-enabled";
        }

        return self::LARAGON_DIR . "/etc/nginx/sites-enabled";
    }

    private function getLocalSites(): false|array
    {
        // get sites-enabled directory
        $sitesDir = $this->getSiteDir();

        // scan all files in the directory
        $scanDir = scandir($sitesDir);

        // remove unwanted files ('.', '..', '00-default.conf' by default)
        $rmItems = [
            '.',
            '..',
            '00-default.conf'
        ];

        foreach ($rmItems as $value) {
            $line = array_search($value, $scanDir, true);
            if ($line !== false && array_key_exists($line, $scanDir)) {
                unset($scanDir[$line]);
            }
        }

        return $scanDir;
    }

    public function renderLinks(): ?string
    {
        $linkList = '';

        foreach ($this->getLocalSites() as $value) {
            // Extract the base link from the value
            $baseLink = $this->extractBaseLink($value);

            if ($baseLink) {
                $linkList .= $this->generateLinkBlock($baseLink);
            }
        }

        return $linkList ?: null;
    }

    private function extractBaseLink(string $value): ?string
    {
        $start = preg_split('/^auto\./', $value);
        if (isset($start[1])) {
            $end = preg_split('/\.conf$/', $start[1]);
            if (isset($end[0])) {
                return $end[0];
            }
        }

        return null;
    }

    private function generateLinkBlock(string $link): string
    {
        $linkBlock = '<div class="grid-cols-2">';
        $linkBlock .= $this->generateLink('http', $link, self::HTTP_PORT, 'HTTP');

        if (self::SHOW_HTTPS_LINKS) {
            $linkBlock .= $this->generateLink('https', $link, self::HTTPS_PORT, 'HTTPS');
        }

        $linkBlock .= '</div>';
        return $linkBlock;
    }

    private function generateLink(string $protocol, string $link, int $port, string $badge): string
    {
        $url = sprintf('%s://%s:%d', $protocol, $link, $port);
        return sprintf(
            '<p><span class="badge %s">%s</span><a href="%s">%s</a></p>',
            strtolower($badge),
            $badge,
            $url,
            $url
        );
    }

    // Show phpinfo() if the query parameter is set to 'info'
    public function getQ($getQ): void
    {
        if (!empty($getQ) && $getQ === 'info') {
            phpinfo();
            exit;
        }
    }

    public function getServerInfo(): array
    {
        $server = explode(' ', $_SERVER['SERVER_SOFTWARE']);
        $openSsl = $server[2] ?? null;

        $httpd = null;
        $httpName = 'null';

        if (str_contains($server[0], 'Apache')) {
            $parts = explode('/', $server[0]);
            $httpd = $parts[1];
            $httpName = 'Apache';
        }

        if (str_contains($server[0], 'nginx')) {
            $parts = explode('/', $server[0]);
            $httpd = $parts[1];
            $httpName = 'nginx';
        }

        return [
            'httpd'    => $httpName,
            'httpdVer' => $httpd,
            'openSsl'  => $openSsl,
            'php'      => $this->currentPhpVersion,
            'xDebug'   => phpversion('xdebug'),
            'docRoot'  => $_SERVER['DOCUMENT_ROOT'],
        ];
    }


    /**
     * @throws Exception
     */
    public function getLastApacheVersion(): ?array
    {
        // Scraping Apache Lounge for the latest Apache version
        $url = 'https://www.apachelounge.com/download/';
        $html = file_get_contents($url);

        if ($html === false) {
            throw new Exception('Error fetching the content of Apache Lounge.');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        // Find the links that contain the Apache version and are for Windows 64-bit with VS17 compiler in ZIP format
        $query = '//a[contains(@href, "/download/VS17/binaries/httpd-") and contains(@href, "-win64-VS17") and contains(@href, ".zip")]';
        $nodes = $xpath->query($query);

        if ($nodes !== false && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $href = $node->getAttribute('href');

                if (preg_match('/httpd-(\d+\.\d+\.\d+)-\d+-win64-VS17\.zip/', $href, $matches)) {
                    $latestVersion = $matches[1];
                    $fullUrl = 'https://www.apachelounge.com' . $href;

                    return [
                        'downloadLink'   => $fullUrl,
                        'latestVersion'  => $latestVersion,
                        'currentVersion' => $this->getServerInfo()['httpdVer']
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function getLastNginxVersion(): ?array
    {
        // Scraping the Nginx website for the latest Nginx version
        $url = 'https://nginx.org/en/download.html';
        $html = file_get_contents($url);

        // Vérifier si le contenu a été récupéré avec succès
        if ($html === false) {
            throw new Exception('Error fetching the content of nginx.');
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        // Find the links that contain the Nginx version and are in ZIP format
        $query = '//a[contains(@href, "/download/nginx") and contains(@href, ".zip")]';
        $nodes = $xpath->query($query);

        if ($nodes !== false && $nodes->length > 0) {
            foreach ($nodes as $node) {
                $href = $node->getAttribute('href');

                if (preg_match('/\/download\/nginx-([0-9.]+)\.zip/', $href, $matches)) {
                    $fullUrl = 'https://nginx.org' . $href;
                    $nginxVersion = $matches[1];

                    return [
                        'downloadLink'   => $fullUrl,
                        'latestVersion'  => $nginxVersion,
                        'currentVersion' => $this->getServerInfo()['httpdVer']
                    ];
                }
                break;
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function fetchMariaDbApi(string $endpoint, string $versionType = 'major')
    {
        $curlRequest = curl_init($endpoint);
        curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlRequest);

        if ($response === false) {
            throw new Exception('Error while fetching MariaDB version information: ' . curl_error($curlRequest));
        }

        curl_close($curlRequest);
        $data = json_decode($response, true);

        if (!$data) {
            throw new Exception('Error no version information available for MariaDB ' . $versionType . ' versions.');
        }

        return $data;
    }

    /**
     * @throws Exception
     */
    public function getCurrentSqlVersion(): ?string
    {
        $host = self::SQL_HOST;
        $username = self::SQL_USER;

        try {
            $pdo = new PDO("mysql:host=$host", $username);
            return $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (PDOException $e) {
            throw new Exception('Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function getLastMariaDBVersion(): array
    {
        /**
         * Major version
         */
        $majorVersionEndpoint = "https://downloads.mariadb.org/rest-api/mariadb/";

        // Fetch the major version data
        $majorVersionData = $this->fetchMariaDbApi($majorVersionEndpoint, 'major');

        // Remove non-stable releases
        $filteredMajorReleases = array_filter($majorVersionData['major_releases'], function ($release) {
            return $release['release_status'] === 'Stable';
        });

        $filteredMajorReleases = array_values($filteredMajorReleases);
        $latestMajorRelease = current($filteredMajorReleases)['release_id'];

        /**
         * Minor version
         */
        $minorVersionEndpoint = "https://downloads.mariadb.org/rest-api/mariadb/$latestMajorRelease";

        // Fetch the minor version data
        $minorVersionData = $this->fetchMariaDbApi($minorVersionEndpoint, 'minor');

        // Remove ALPHA and RC releases
        $latestStableRelease = array_filter($minorVersionData['releases'], function ($release) {
            return
                !str_contains($release['release_name'], 'Alpha')
                && !str_contains($release['release_name'], 'RC');
        });
        $latestStableRelease = current($latestStableRelease);

        // Keep only ZIP files
        $filteredMinorReleases = array_filter($latestStableRelease['files'], function ($file) {
            return $file['package_type'] === 'ZIP file';
        });

        $filteredMinorReleases = array_values($filteredMinorReleases);
        // Match the file name pattern
        $pattern = '/mariadb-\d+\.\d+\.\d+-winx64\.zip/';
        // filter the files
        $filteredFiles = preg_grep($pattern, array_column($filteredMinorReleases, 'file_name'));
        // Get the keys of the filtered files
        $keys = array_keys($filteredFiles);
        // Get the data of the first file
        $filteredData = current(array_intersect_key($filteredMinorReleases, array_flip($keys)));

        return [
            'downloadLink' => $filteredData['file_download_url'],
            'version'      => $latestStableRelease['release_id']
        ];
    }

    /**
     * @throws Exception
     */
    private function checkLatestPhpVersion(): string
    {
        $curlRequest = curl_init('https://www.php.net/releases/index.php?json&version=8');
        curl_setopt($curlRequest, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlRequest);

        if ($response === false) {
            die('Error while fetching PHP version information: ' . curl_error($curlRequest));
        }

        curl_close($curlRequest);

        $data = json_decode($response, true);

        if (!$data) {
            throw new Exception('Error no version information available for PHP Release');
        }

        return $data['version'];
    }

    /**
     * @throws Exception
     */
    public function getLastPhpVersion(?string $customVersion = null): array
    {
        $version = $customVersion;

        if (empty($customVersion)) {
            $version = $this->checkLatestPhpVersion();
        }

        $downloadLink = sprintf(
            'https://windows.php.net/downloads/releases/php-%s-Win32-vs16-x64.zip',
            $version
        );

        $changeLog = sprintf(
            'https://www.php.net/ChangeLog-8.php#%s',
            $version
        );

        return [
            'currentVersion' => $this->currentPhpVersion,
            'latestVersion'  => $version,
            'changeLog'      => $changeLog,
            'downloadLink'   => $downloadLink
        ];
    }

    /**
     * May be unstable due to the website structure
     */
    function getLastMysqlVersion(): ?array
    {
        $baseUrl = "https://dev.mysql.com/downloads/mysql/";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $baseUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        // Extract the latest version from title
        if (preg_match('/MySQL Community Server (\d+\.\d+\.\d+)/', $response, $matches)) {
            $latestVersion = $matches[1];
            $versionParts = explode('.', $latestVersion);
            $majorMinor = "{$versionParts[0]}.{$versionParts[1]}";
            // Build the download link
            $downloadLink = "https://dev.mysql.com/get/Downloads/MySQL-{$majorMinor}/mysql-{$latestVersion}-winx64.zip";

            return [
                'version'      => $latestVersion,
                'downloadLink' => $downloadLink
            ];
        }

        return null;
    }
}

// Initialize the Laragon class
try {
    $laragon = new Laragon();
} catch (Exception $e) {
    echo $e->getMessage();
    exit;
}

// Check if the query parameter is set to q
isset($_GET['q']) ? $laragon->getQ($_GET['q']) : null;
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Index</title>
    <style>
        *,::before,::after{box-sizing:border-box;}body, p{margin:0;background-color:#f3f4f6;}html{font-family:ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";}.badge{display:inline-flex;justify-content:center;padding:0.1rem 0.3rem;font-size:0.55rem;width:2rem;margin-right:0.5rem;border-radius:0.25rem;&.http{background-color:#fca5a5;}&.https{background-color:#86efac;}}.bg-gray{background-color:#f3f4f6;}.badge-ext{display:inline-block;justify-content:center;padding:0.1rem 0.3rem;font-size:0.85rem;border-radius:0.25rem;background-color:#e2e8f0;cursor:default;font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;&:hover{background-color:#cbd5e1;}}.container{margin:auto;max-width:768px;}.grid-cols-2{display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));}.grid{display:grid;gap:0.5rem;}section{padding:1rem;}a{color:inherit;text-decoration:inherit;}.cursor-pointer{cursor:pointer;}.mt-1{margin-top:1rem;}code{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;font-size:1em;}.border{margin-top:1rem;border:3px dashed #e2e8f0;border-radius:0.25rem;h2{margin-top:0;}}.new-version{display:inline-block;padding:0.5rem;border-radius:0.5rem;background-color:#A7F3D0;margin-top:1rem;}.bold{font-weight:bold;}.center{display:flex;flex-direction:column;justify-content:center;align-items:center;}
    </style>
</head>
<body>
<div class="container center">
    <img height="180px" width="180px"
         src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4NCjwhLS0gR2VuZXJhdG9yOiBBZG9iZSBJbGx1c3RyYXRvciAxNi4wLjIsIFNWRyBFeHBvcnQgUGx1Zy1JbiAuIFNWRyBWZXJzaW9uOiA2LjAwIEJ1aWxkIDApICAtLT4NCjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+DQo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB3aWR0aD0iMjRweCIgaGVpZ2h0PSIyNHB4IiB2aWV3Qm94PSIwIDAgMjQgMjQiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDI0IDI0IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxsaW5lYXJHcmFkaWVudCBpZD0iU1ZHSURfMV8iIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIiB4MT0iMTIuMDgzNSIgeTE9IjEuNzE4OCIgeDI9IjEyLjA4MzUiIHkyPSIyMi41Ij4NCgkJPHN0b3AgIG9mZnNldD0iMC4wMTgxIiBzdHlsZT0ic3RvcC1jb2xvcjojM0JCNkZGIi8+DQoJCTxzdG9wICBvZmZzZXQ9IjAuMzAyMyIgc3R5bGU9InN0b3AtY29sb3I6IzM5QUZGRiIvPg0KCQk8c3RvcCAgb2Zmc2V0PSIwLjU1MTkiIHN0eWxlPSJzdG9wLWNvbG9yOiMzNkEzRkYiLz4NCgkJPHN0b3AgIG9mZnNldD0iMC43MTczIiBzdHlsZT0ic3RvcC1jb2xvcjojMzU5RkZGIi8+DQoJCTxzdG9wICBvZmZzZXQ9IjAuODMxNiIgc3R5bGU9InN0b3AtY29sb3I6IzMzOThGRiIvPg0KCQk8c3RvcCAgb2Zmc2V0PSIwLjk2MzkiIHN0eWxlPSJzdG9wLWNvbG9yOiMzMjk3RkYiLz4NCgk8L2xpbmVhckdyYWRpZW50Pg0KCTxwYXRoIGZpbGw9InVybCgjU1ZHSURfMV8pIiBkPSJNMC44MzgsOC42MzFjMC4wNDEtMC4xMjMsMS43NjktNi4wNSw4LjYxMy02LjEzM2MwLDAsMi44Ny0zLjI0Niw2Ljc4LDANCgkJYzAsMCwxLjA2MSwwLjg3MiwxLjY0MywyLjY4MmMwLDAsNS4xMzQsMC43NzksNS45NjUsNS4wMjJjMCwwLDEuNzMyLDYuOTg3LTQuMTMzLDExLjg5NmMwLDAtMC44MjYsMC42NjEtMS4zNzYsMC45NjgNCgkJYzAsMC0xLjIyLDAuMDAyLTEuNDcyLDBjLTAuNTM3LTAuMDA0LTAuODc2LDAtMS4zNjQsMGMwLDAtMC43NS0wLjI2OC0wLjc4MS0xLjEyNWMwLDAtMC4wNjMtMi45OC0wLjA0Ni0zLjQ5NQ0KCQljMCwwLDAuMDE1LTAuNDgyLTAuNjg3LTAuNDUyYzAsMC0wLjY3LTAuMDc3LTAuNzY1LDAuNDk5YzAsMC0wLjAxNiwzLjA3NC0wLjAzMSwzLjYxOWMwLDAtMC4wNDcsMC45MDctMS4wNjEsMC45NTENCgkJYzAsMC0zLjYzNSwwLjExLTQuMTE4LTAuMDYyYzAsMC0wLjg0Mi0wLjE1Ni0wLjkwNS0wLjk1MmMwLDAtMC42ODctNC4wNTYtMC44MTEtNS4zMThjMCwwLTIuMzA5LTEuMjgtMi43NzctMS42MjMNCgkJYzAsMCwwLjE1Niw0LjEzMywxLjU5MSw1Ljg4MWMwLDAsMC4yNSwwLjIxOS0wLjI1LDAuNTNjMCwwLTAuMTg3LDAuMTU2LTAuMzc1LDAuMDY0YzAsMC02LjE1NS0zLjQzOC0zLjg4OC0xMi4yMTMiLz4NCgk8cGF0aCBmaWxsPSIjMDA2Njk5IiBkPSJNNy43MjgsMTQuMjg1YzAsMCw1LjM3LDMuMDYxLDguNjE5LTEuODUzYzAsMCwyLjYzMS0zLjQzNiwxLjU4My03LjEwMWMwLDAsMS44NDUsMy4wOC0xLjcyNSw3Ljg1OQ0KCQlDMTYuMjA2LDEzLjE5MSwxMy4xNzgsMTcuNDUxLDcuNzI4LDE0LjI4NXoiLz4NCgk8cGF0aCBmaWxsPSIjQ0VFNkZGIiBkPSJNNS42MDMsMTMuNTYzYzAsMCwwLjM4MywxLjc3My0wLjc5NSwyLjMzMWMwLDAtMi42OC0xLjEwNC0yLjQwOS0zLjE0M2MwLDAsMC4wODQtMC41MDksMC41ODMtMC4xOTcNCgkJYzAsMCwxLjE4NiwwLjY0NSwyLjEyMiwwLjgzMUM1LjEwMywxMy4zODUsNS41NzksMTMuNDI5LDUuNjAzLDEzLjU2M3oiLz4NCgk8cGF0aCBmaWxsPSIjMDA2Njk5IiBkPSJNNC44NTksMTAuNTY2YzAsMCwwLjQwNC0xLjcyNywxLjkyOS0xLjYxOGMwLDAsMS4yOTYsMC4wMzUsMS4zNDIsMS44MTcNCgkJQzguMTMsMTAuNzY2LDcuMTA5LDguMSw0Ljg1OSwxMC41NjZ6Ii8+DQo8L2c+DQo8L3N2Zz4NCg=="
         alt="Laragon"/>
    <h1>Laragon Panel</h1>
</div>


<main class="container">
    <section class="border container bg-gray">
        <h2>Available sites</h2>
        <div class="container grid">
            <?= $laragon->renderLinks(); ?>
        </div>
    </section>

    <?php
    if ($laragon::SHOW_INFOS_SECTION || $laragon::SHOW_HTTP_SECTION || $laragon::SHOW_PHP_SECTION || $laragon::SHOW_SQL_SECTION) : ?>
        <h2>Servers</h2>
    <?php
    endif; ?>

    <?php
    if ($laragon::SHOW_INFOS_SECTION): ?>
        <section class="border">
            <h2>Informations</h2>

            <div class="grid-cols-2">
                <div>
                    <span class="bold">HTTPd:</span>
                    <?= $laragon->getServerInfo()['httpd'] ?>
                </div>

                <div>
                    <span class="bold">HTTPd ver.:</span>
                    <?= $laragon->getServerInfo()['httpdVer'] ?>
                </div>

                <div>
                    <span class="bold">PHP ver.:</span>
                    <?= $laragon->getServerInfo()['php'] ?>
                </div>

                <div>
                    <span class="bold">OpenSSL:</span>
                    <?= $laragon->getServerInfo()['openSsl'] ?? 'N/A' ?>
                </div>
                <div>
                    <span class="bold">xDebug:</span>
                    <?= $laragon->getServerInfo()['xDebug'] ?? 'N/A' ?>
                </div>
                <div>
                    <span class="bold">Projects directory:</span>
                    <?= $laragon->getServerInfo()['docRoot'] ?>
                </div>
            </div>
        </section>
    <?php
    endif; ?>

    <?php
    if ($laragon::SHOW_PHP_SECTION): ?>
        <section class="border">
            <?php
            $phpVersionInfo = $laragon->getLastPhpVersion();
            $currentPhpVersion = $laragon->convertVersion($phpVersionInfo['currentVersion']);
            $latestPhpVersion = $laragon->convertVersion($phpVersionInfo['latestVersion']);
            ?>

            <h2>PHP</h2>

            <div class="mt-1">
                <p>Current PHP version: <strong><?= $phpVersionInfo['currentVersion'] ?></strong> (<a href="<?= $laragon->getLastPhpVersion($laragon->getCurrentPhpVersion())['changeLog'] ?>">Changelog</a>)</p>
                <p>Latest PHP version: <strong><?= $phpVersionInfo['latestVersion'] ?></strong> (<a href="<?= $phpVersionInfo['changeLog'] ?>">Changelog</a>)</p>


                <?php
                if ($currentPhpVersion < $latestPhpVersion) : ?>
                    <p class="new-version">
                        🚀 New version available: <a href="<?= $phpVersionInfo['downloadLink'] ?>" class="bold cursor-pointer">Download PHP <?= $phpVersionInfo['latestVersion'] ?></a>
                    </p>
                <?php
                endif; ?>

            </div>

            <h3>Loaded extension <a href="?q=info" class="badge-ext cursor-pointer">phpinfo()</a></h3>

            <?php
            foreach (get_loaded_extensions() as $ext) : ?>
                <span class="badge-ext">
                <?= $ext ?>
            </span>
            <?php
            endforeach; ?>
        </section>
    <?php
    endif; ?>

    <?php
    if ($laragon::SHOW_HTTP_SECTION):
        if (function_exists('apache_get_version')) : ?>
            <section class="border">
                <h2>Apache</h2>

                <?php
                $apacheVersionInfo = $laragon->getLastApacheVersion();
                $currentApacheVersion = $laragon->convertVersion($apacheVersionInfo['currentVersion']);
                $latestApacheVersion = $laragon->convertVersion($apacheVersionInfo['latestVersion']);
                ?>

                <p>Current Apache version: <strong><?= $laragon->getLastApacheVersion()['currentVersion'] ?></strong></p>

                <?php
                if ($currentApacheVersion < $latestApacheVersion) : ?>
                    <p class="new-version">
                        🚀 New version available: <a href="<?= $apacheVersionInfo['downloadLink'] ?>" class="bold cursor-pointer">Download Apache <?= $apacheVersionInfo['latestVersion'] ?></a>
                    </p>
                <?php
                endif; ?>

                <h3>Loaded Apache Extensions</h3>
                <?php
                foreach (apache_get_modules() as $ext) : ?>
                    <span class="badge-ext">
                    <?= "mod_$ext"; ?>
                </span>
                <?php
                endforeach; ?>
            </section>
        <?php
        else: ?>
            <section class="border">
                <h2>nginx</h2>

                <?php
                $nginxVersionInfo = $laragon->getLastNginxVersion();
                $currentNginxVersion = $laragon->convertVersion($nginxVersionInfo['currentVersion']);
                $latestNginxVersion = $laragon->convertVersion($nginxVersionInfo['latestVersion']);
                ?>

                <p> Current nginx version: <strong><?= $laragon->getServerInfo()['httpdVer'] ?></strong></p>

                <?php
                if ($currentNginxVersion < $latestNginxVersion) : ?>
                    <p class="new-version">
                        🚀 New version available: <a href="<?= $nginxVersionInfo['downloadLink'] ?>" class="bold cursor-pointer">Download nginx <?= $nginxVersionInfo['latestVersion'] ?></a>
                    </p>
                <?php
                endif; ?>
            </section>
        <?php
        endif; endif; ?>

    <?php
    if ($laragon::SHOW_SQL_SECTION) {
        $latestMariaDBVersion = $laragon->getLastMariaDBVersion()['version'];
        $latestMysqlVersion = $laragon->getLastMysqlVersion()['version'];

        try {
            if (str_contains($laragon->getCurrentSqlVersion(), 'MariaDB')):
                $mariaDbVersion = current(explode('-', $laragon->getCurrentSqlVersion()));
                ?>

                <section class="border">
                    <h2>MariaDB</h2>
                    <p>Current MariaDB version: <strong><?= $mariaDbVersion ?></strong></p>

                    <?php
                    if ($laragon->convertVersion($latestMariaDBVersion) > $laragon->convertVersion($mariaDbVersion)): ?>
                        <p class="new-version">🚀 New version available: <a href="<?= $laragon->getLastMariaDBVersion()['downloadLink'] ?>" class="bold cursor-pointer">Download MariaDB <?= $latestMariaDBVersion ?></a></p>
                    <?php
                    endif; ?>
                </section>
            <?php
            else:
                $mysqlVersion = $laragon->getCurrentSqlVersion();
                ?>

                <section class="border">
                    <h2>MySQL</h2>
                    <p>Current MySQL version: <strong><?= $mysqlVersion ?></strong></p>

                    <?php
                    if ($laragon->convertVersion($latestMysqlVersion) > $laragon->convertVersion($mysqlVersion)): ?>
                        <p class="new-version">🚀 New version available: <a href="<?= $laragon->getLastMysqlVersion()['downloadLink'] ?>" class="bold cursor-pointer">Download MySQL <?= $latestMysqlVersion ?></a></p>
                    <?php
                    endif; ?>
                </section>
            <?php
            endif;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
    ?>
</main>
</body>
</html>
