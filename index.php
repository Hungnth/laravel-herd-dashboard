<?php

// Security: Disable directory listing for safety
if (!defined('ALLOWED_ACCESS')) {
    define('ALLOWED_ACCESS', true);
}

// Configuration
$config = [
    'app_name' => 'Laravel Herd Dashboard',
    'github_url' => 'https://github.com/Hungnth/laragon-dashboard',
    'excluded_folders' => ['.', '..', '.git', '.svn', '.htaccess', '.idea', '__pycache__', '.venv', 'assets'],
    'db_config' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 8039,
    ],
    'domains_subfix' => '.test',
    'herd_sites_path' => 'F:\laravel-herd\sites',
    'phpMyAdmin_url' => 'https://phpmyadmin.test'

];

function handleQueryParameter(string $param): void
{
    if ($param === 'info') {
        phpinfo();
    }
    if ($param === 'mailbox') {
        include 'mailbox.php';
    }
}

if (isset($_GET['q'])) {
    $queryParam = htmlspecialchars(filter_input(INPUT_GET, 'q', FILTER_DEFAULT)) ?: null;
    try {
        handleQueryParameter($queryParam);
    } catch (InvalidArgumentException $e) {
        echo 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Get MySQL databases
function getDatabases($config)
{
    $databases = [];
    try {
        $mysqli = new mysqli($config['host'], $config['user'], $config['password'], null, $config['port']);
        if ($mysqli->connect_error) {
            throw new Exception("Connection failed: " . $mysqli->connect_error);
        }

        // Get databases
        $result = $mysqli->query("SHOW DATABASES");
        while ($row = $result->fetch_array()) {
            if (!in_array($row[0], ['information_schema', 'mysql', 'performance_schema', 'sys'])) {
                $dbName = $row[0];
                // Get tables count for each database
                $mysqli->select_db($dbName);
                $tablesResult = $mysqli->query("SHOW TABLES");
                $databases[$dbName] = [
                    'name' => $dbName,
                    'tables' => $tablesResult->num_rows,
                    'size' => 0
                ];

                // Calculate database size
                $sizeResult = $mysqli->query("SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                    FROM information_schema.tables 
                    WHERE table_schema = '$dbName'");
                $sizeRow = $sizeResult->fetch_assoc();
                $databases[$dbName]['size'] = $sizeRow['size'] ?? 0;
            }
        }
        $mysqli->close();
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
    }
    return $databases;
}

$link = mysqli_connect($config['db_config']['host'], $config['db_config']['user'], $config['db_config']['password'], null, $config['db_config']['port']);
$sql_version = mysqli_get_server_info($link);

// Get system information
function getSystemInfo()
{
    global $sql_version;
    global $config;
    return [
        'PHP Version' => phpversion(),
        'My SQL Version' => $sql_version,
        'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'Document Root' => $config['herd_sites_path'] ?? 'Unknown',
        'Server OS' => PHP_OS,
        'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'Max Upload Size' => ini_get('upload_max_filesize'),
        'Max Post Size' => ini_get('post_max_size'),
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time') . 's',
        'PHP Modules' => count(get_loaded_extensions()) . ' loaded',
    ];
}

// Get directories with error handling
function getProjectFolders($herd_sites_path, $excluded = [])
{
    try {
        $folders = array_filter(glob($herd_sites_path . '/*'), 'is_dir');
        return array_values(array_diff($folders, $excluded));
    } catch (Exception $e) {
        error_log("Error reading directories: " . $e->getMessage());
        return [];
    }
}

function getSites($folders, $config)
{

    $sites = [];

    foreach ($folders as $folder) {

        $siteName = basename($folder);
        $site = ['name' => $siteName];

        if (file_exists("$folder/wp-admin")) {
            $site['framework'] = 'WordPress';
            $site['logo'] = '<img src="./assets/wordpress.svg" alt="wordpress" width="24" height="24">';
            $site['url'] = 'https://' . $siteName . $config['domains_subfix'] . '/wp-admin';
        } elseif (file_exists("$folder/public/index.php") && is_dir("$folder/app") && file_exists("$folder/.env")) {
            $site['framework'] = 'Laravel';
            $site['logo'] = '<img src="./assets/laravel.svg" alt="laravel" width="24" height="24">';
            $site['url'] = 'https://' . $siteName . $config['domains_subfix'];
        } elseif (file_exists("$folder/") && file_exists("$folder/app.py") && is_dir("$folder/static") && file_exists("$folder/.env")) {
            $site['framework'] = 'Python';
            $site['logo'] = '<img src="./assets/python.svg" alt="python" width="24" height="24">';
            $site['url'] = 'https://' . $siteName . $config['domains_subfix'];
        } else {
            $site['framework'] = 'Unknown';
            $site['logo'] = '<img src="./assets/website.svg" alt="website" width="24" height="24">';
            $site['url'] = 'https://' . $siteName . $config['domains_subfix'];
        }

        $sites[] = $site;
    }

    return $sites;
}


$folders = getProjectFolders($config['herd_sites_path'], $config['excluded_folders']);
$sites = getSites($folders, $config);
$databases = getDatabases($config['db_config']);
$phpMyAdminUrl = $config['phpMyAdmin_url'];
$systemInfo = getSystemInfo();

// Get PHP Extensions
$extensions = get_loaded_extensions();
sort($extensions);

function dd($var)
{
    echo "<pre>";
    print_r($var);
    exit;
}

// Get system resource usage
$systemResources = [
    'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2),
    'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2),
    'cpu_load' => function_exists('sys_getloadavg') ? sys_getloadavg() : ['N/A'],
    'disk_free' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
    'disk_total' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2)
];

// Add to systemInfo array
$systemInfo['Memory Usage'] = $systemResources['memory_usage'] . ' MB';
$systemInfo['Peak Memory'] = $systemResources['peak_memory'] . ' MB';
$systemInfo['CPU Load'] = is_array($systemResources['cpu_load']) ? implode(', ', $systemResources['cpu_load']) : $systemResources['cpu_load'];
$systemInfo['Disk Free Space'] = $systemResources['disk_free'] . ' GB';
$systemInfo['Disk Total Space'] = $systemResources['disk_total'] . ' GB';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laragon Dashboard</title>
    <!-- Có thể xóa file build.css bên trên và sử dụng bằng CDN: https://cdn.tailwindcss.com/3.4.16 -->
    <link rel="stylesheet" href="./assets/build.css">
    <!-- <link rel="stylesheet" href="https://cdn.tailwindcss.com/3.4.16"> -->
    <link rel="icon" sizes="any" type="image/png" href='./assets/icon.png' alt="favicon">

</head>

<body class="bg-gray-50">

    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <img width="48" height="60" src="/assets/IconLight@2.png" alt="Laravel Herd Logo">
                    <h1 class="text-2xl font-bold text-gray-900">Laragon Dashboard</h1>
                    <a href="<?= htmlspecialchars($config['github_url']); ?>"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500" target="_blank" rel="noopener">
                        <div class="flex justify-between items-center gap-2">
                            <img src="./assets/github.svg" alt="github">
                        </div>
                    </a>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">

            <!-- Quick Actions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="<?= $phpMyAdminUrl ?>"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <img src="https://phpmyadmin.test/favicon.ico" alt="phpMyAdmin" width="24" height="24">
                        </span>
                        <span class="font-medium text-gray-700">phpMyAdmin</span>
                    </a>

                    <!-- <a href="/mailbox.php"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <svg id="fi_18561709" enable-background="new 0 0 100 100" viewBox="0 0 100 100" width="24"
                                height="24" xmlns="http://www.w3.org/2000/svg">
                                <g fill="#00adf6">
                                    <path
                                        d="m31.7299805 37.9399414h-26.0999766l12.2199717-15.3898926c1.0300293-1.3000488 2.6000366-2.0600586 4.2600098-2.0600586h55.7800293c1.6599731 0 3.2299805.7600098 4.2600098 2.0600586l12.2199707 15.3898926h-26.0999757c-.9559097-.0038223-1.8684692.4965973-2.4200439 1.2700195-.1930084.3238945-.351265.6498756-.4399719 1.020031-1.9200135 8.5200081-10.3700257 13.8699837-18.8799744 11.9499397-5.960022-1.3399658-10.6200562-5.9899902-11.9500122-11.9499512-.1060982-.3543968-.2446785-.7065315-.4500122-1.0200195-.0599976-.0699463-.1099854-.1398926-.1699829-.1999512-.0599976-.0800781-.1300049-.1500244-.2000122-.2299805-.2871361-.25177-.6026535-.4755211-.960022-.6201172h-.0100098c-.3399658-.1398925-.6900024-.2099608-1.0599975-.2199706z">
                                    </path>
                                    <path
                                        d="m97.5 43.7800293v26.9699707c0 4.8399658-3.9199829 8.7600098-8.7600098 8.7600098h-77.4799814c-4.8400269 0-8.7600098-3.9200439-8.7600098-8.7600098v-26.9699707h27.039979c2.1600342 6.3399658 7.1400146 11.3099365 13.4800415 13.4799805 11.2999878 3.8499756 23.5799561-2.1800537 27.4400024-13.4799805z">
                                    </path>
                                </g>
                            </svg>
                        </span>
                        <span class="font-medium text-gray-700">Mailbox</span>
                    </a> -->

                    <a href="?q=info"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <img src="./assets/php.svg" alt="php info" width="24" height="24">
                        </span>
                        <span class="font-medium text-gray-700">PHP Info</span>
                    </a>


                </div>
            </div>

            <!-- Projects -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Projects</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php if (empty($sites)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No projects found</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sites as $site): ?>
                            <?php $name = $site['name']; ?>
                            <?php $url = $site['url']; ?>
                            <?php $logo = $site['logo']; ?>
                            <a href="<?= htmlspecialchars($url); ?>"
                                class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow flex items-center gap-2"
                                target="_blank">
                                <?= $logo ?>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                        <?= htmlspecialchars($name); ?>
                                    </span>
                                    <svg class="w-5 h-5 text-gray-400 group-hover:text-blue-600" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 5l7 7-7 7" />
                                    </svg>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Databases -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Databases</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php if (empty($databases)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No databases found</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($databases as $db): ?>
                            <a href="<?= $phpMyAdminUrl ?>/index.php?route=/database/structure&db=<?= urlencode($db['name']); ?>"
                                class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow"
                                target="_blank">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4 7v10c0 2 1.5 3 3.5 3h9c2 0 3.5-1 3.5-3V7c0-2-1.5-3-3.5-3h-9C5.5 4 4 5 4 7zm0 3h16M4 14h16" />
                                        </svg>
                                        <span class="font-medium text-gray-900 group-hover:text-blue-600">
                                            <?= htmlspecialchars($db['name']); ?>
                                        </span>
                                    </div>
                                    <span class="text-sm text-gray-500"><?= $db['tables']; ?> tables</span>
                                </div>
                                <div class="text-sm text-gray-500">
                                    Size: <?= number_format($db['size'], 2); ?> MB
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Information -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">System Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($systemInfo as $key => $value): ?>
                        <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                            <div class="text-sm font-medium text-gray-500"><?= htmlspecialchars($key); ?></div>
                            <div class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($value); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- PHP Extensions -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">PHP Extensions</h2>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 p-4">
                        <?php foreach ($extensions as $ext): ?>
                            <div class="text-sm text-gray-600 bg-gray-50 rounded px-3 py-1">
                                <?= htmlspecialchars($ext); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-8 text-center font-bold">
            <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <p class="text-center text-sm text-gray-500">Developed by HungNth</p>
                <p class="text-center text-sm text-gray-500">
                    Powered by Laragon - <?= date('Y'); ?>
                </p>
            </div>
        </footer>
    </div>
</body>

</html>