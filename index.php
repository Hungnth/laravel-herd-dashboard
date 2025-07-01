<?php

/**
 * Laravel Herd Dashboard
 * 
 * A dashboard to manage and visualize Laravel and WordPress sites
 * organized by technology with separate sections for each framework.
 * 
 * Features:
 * - WordPress sites from F:\laravel-herd\sites
 * - Laravel sites from F:\Laravel\laravel12
 * - Database management via phpMyAdmin
 * - System information display
 * - Quick access to common tools
 */

// Security: Disable directory listing for safety
if (!defined('ALLOWED_ACCESS')) {
    define('ALLOWED_ACCESS', true);
}

// Configuration
$config = [
    'app_name' => 'Laravel Herd Dashboard',
    'github_url' => 'https://github.com/Hungnth/laravel-herd-dashboard',
    'excluded_folders' => ['.', '..', '.git', '.svn', '.htaccess', '.idea', '__pycache__', '.venv', 'assets'],
    'db_config' => [
        'host' => 'localhost',
        'user' => 'root',
        'password' => '',
        'port' => 3366,
    ],
    'domains_subfix' => '.test',
    'paths' => [
        'wordpress' => 'F:\laravel-herd\sites',
        'laravel' => 'F:\Laravel\laravel12'
    ],
    'phpMyAdmin_url' => 'https://phpmyadmin.test'
];


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
        'WordPress Path' => $config['paths']['wordpress'] ?? 'Unknown',
        'Laravel Path' => $config['paths']['laravel'] ?? 'Unknown',
        'Server OS' => PHP_OS,
        'Server IP' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
        'Max Upload Size' => ini_get('upload_max_filesize'),
        'Max Post Size' => ini_get('post_max_size'),
        'Memory Limit' => ini_get('memory_limit'),
        'Max Execution Time' => ini_get('max_execution_time') . 's',
        'PHP Modules' => count(get_loaded_extensions()) . ' loaded',
    ];
}

// Helper function to get directories from a path
function getDirectoriesFromPath($path, $excluded = [])
{
    try {
        if (!is_dir($path)) {
            return [];
        }
        $folders = array_filter(glob($path . '/*'), 'is_dir');
        return array_values(array_diff($folders, $excluded));
    } catch (Exception $e) {
        error_log("Error reading directories from $path: " . $e->getMessage());
        return [];
    }
}

// Get WordPress sites
function getWordPressSites($wordpressPath, $config)
{
    $sites = [];

    if (!is_dir($wordpressPath)) {
        error_log("WordPress path does not exist: $wordpressPath");
        return $sites;
    }

    $folders = getDirectoriesFromPath($wordpressPath, $config['excluded_folders']);

    foreach ($folders as $folder) {
        $siteName = basename($folder);

        // Check if it's a WordPress site (multiple checks for better detection)
        if (
            file_exists("$folder/wp-admin") ||
            file_exists("$folder/wp-config.php") ||
            file_exists("$folder/wp-content")
        ) {
            error_log("Checking wp-config.php at: $folder/wp-config.php");
            if (file_exists("$folder/wp-config.php")) {
                error_log("wp-config.php exists. Reading content...");
                $dbName = getWordPressDatabaseInfo("$folder/wp-config.php");
                error_log("Extracted database name: " . ($dbName ?? 'Not Found'));
            } else {
                error_log("wp-config.php does not exist at: $folder/wp-config.php");
            }

            $dbName = getWordPressDatabaseInfo("$folder/wp-config.php");

            $sites[] = [
                'name' => $siteName,
                'framework' => 'WordPress',
                'logo' => '<img src="./assets/wordpress.svg" alt="wordpress" width="24" height="24">',
                'url' => 'http://' . $siteName . $config['domains_subfix'],
                'admin_url' => 'http://' . $siteName . $config['domains_subfix'] . '/wp-admin',
                'path' => $folder,
                'database' => $dbName
            ];
        }
    }

    return $sites;
}

// Get Laravel sites
function getLaravelSites($laravelPath, $config)
{
    $sites = [];

    if (!is_dir($laravelPath)) {
        error_log("Laravel path does not exist: $laravelPath");
        return $sites;
    }

    $folders = getDirectoriesFromPath($laravelPath, $config['excluded_folders']);

    foreach ($folders as $folder) {
        $siteName = basename($folder);

        // Check if it's a Laravel site (multiple checks for better detection)
        if (
            file_exists("$folder/artisan") ||
            (file_exists("$folder/public/index.php") && is_dir("$folder/app")) ||
            (file_exists("$folder/composer.json") && is_dir("$folder/app"))
        ) {
            $dbName = getLaravelDatabaseInfo("$folder/.env");

            $sites[] = [
                'name' => $siteName,
                'framework' => 'Laravel',
                'logo' => '<img src="./assets/laravel.svg" alt="laravel" width="24" height="24">',
                'url' => 'http://' . $siteName . $config['domains_subfix'],
                'path' => $folder,
                'database' => $dbName
            ];
        }
    }

    return $sites;
}

// Helper function to get site statistics
function getSiteStatistics($wordpressSites, $laravelSites)
{
    return [
        'total_sites' => count($wordpressSites) + count($laravelSites),
        'wordpress_count' => count($wordpressSites),
        'laravel_count' => count($laravelSites)
    ];
}

// Get sites from both directories
$wordpressSites = getWordPressSites($config['paths']['wordpress'], $config);
$laravelSites = getLaravelSites($config['paths']['laravel'], $config);
$siteStats = getSiteStatistics($wordpressSites, $laravelSites);
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

// Extract database information from Laravel .env file
function getLaravelDatabaseInfo($envFilePath)
{
    if (!file_exists($envFilePath)) {
        return null;
    }

    $envContent = file_get_contents($envFilePath);
    preg_match('/DB_DATABASE=(.+)/', $envContent, $matches);

    return $matches[1] ?? null;
}

// Extract database information from WordPress wp-config.php file
function getWordPressDatabaseInfo($configFilePath)
{
    if (!file_exists($configFilePath)) {
        return null;
    }

    $configContent = file_get_contents($configFilePath);
    // preg_match('/define\(\s*["\"]DB_NAME["\"]\s*,\s*["\"](.+)["\"]\s*\)/', $configContent, $matches);
    preg_match('/define.*DB_NAME.*\'(.*)\'/', $configContent, $matches);

    return $matches[1] ?? null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $config['app_name'] ?></title>
    <link rel="stylesheet" href="./assets/css/styles.css">
    <link rel="icon" sizes="any" type="image/png" href='./assets/icon.png' alt="favicon">

</head>

<body class="bg-gray-50">

    <div class="min-h-screen">

        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center">
                    <img width="48" height="60" src="/assets/IconLight@2.png" alt="Laravel Herd Logo">
                    <h1 class="text-2xl font-bold text-gray-900"><?= $config['app_name'] ?></h1>
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

            <!-- Statistics Overview -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9v-9m0-9v9"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= $siteStats['total_sites']; ?></div>
                                <div class="text-sm text-gray-500">Total Sites</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-red-100 rounded-lg">
                                <img src="./assets/laravel.svg" alt="laravel" width="24" height="24">
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= $siteStats['laravel_count']; ?></div>
                                <div class="text-sm text-gray-500">Laravel Sites</div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg shadow-sm p-4 border border-gray-200">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-blue-100 rounded-lg">
                                <img src="./assets/wordpress.svg" alt="wordpress" width="24" height="24">
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-gray-900"><?= $siteStats['wordpress_count']; ?></div>
                                <div class="text-sm text-gray-500">WordPress Sites</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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

                    <a href="http://localhost:8025"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <img src="http://localhost:8025/favicon.svg" alt="mailbox" width="24" height="24">
                        </span>
                        <span class="font-medium text-gray-700">Mailbox</span>
                    </a>

                    <a href="/info.php"
                        class="flex items-center justify-center px-4 py-3 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200 gap-2"
                        target="_blank">
                        <span class="">
                            <img src="./assets/php.svg" alt="php info" width="24" height="24">
                        </span>
                        <span class="font-medium text-gray-700">PHP Info</span>
                    </a>


                </div>
            </div>

            <!-- Laravel Projects -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Laravel Projects</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                    <?php if (empty($laravelSites)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No Laravel projects found in <?= htmlspecialchars($config['paths']['laravel']); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($laravelSites as $site): ?>
                            <div class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-3">
                                        <?= $site['logo'] ?>
                                        <span class="font-medium text-gray-900">
                                            <?= htmlspecialchars($site['name']); ?>
                                        </span>
                                    </div>
                                    <a href="<?= htmlspecialchars($site['url']); ?>" target="_blank" class="text-gray-400 hover:text-blue-600" title="Visit Site">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                        </svg>
                                    </a>
                                </div>
                                <div class="text-sm text-gray-500 flex items-center justify-between">
                                    <span>Database: <?= htmlspecialchars($site['database'] ?? 'Not Found'); ?></span>
                                    <?php if ($site['database']): ?>
                                        <a href="<?= $phpMyAdminUrl ?>/index.php?route=/database/structure&db=<?= urlencode($site['database']); ?>"
                                            target="_blank" class="text-xs text-blue-600 hover:underline ml-2">
                                            PHPMyAdmin
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- WordPress Projects -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">WordPress Projects</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                    <?php if (empty($wordpressSites)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No WordPress projects found in <?= htmlspecialchars($config['paths']['wordpress']); ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($wordpressSites as $site): ?>
                            <div class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow">
                                <div class="flex items-center gap-3 mb-2">
                                    <?= $site['logo'] ?>
                                    <span class="font-medium text-gray-900">
                                        <?= htmlspecialchars($site['name']); ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 flex items-center justify-between mb-3">
                                    <span>Database: <?= htmlspecialchars($site['database'] ?? 'Not Found'); ?></span>
                                    <?php if ($site['database']): ?>
                                        <a href="<?= $phpMyAdminUrl ?>/index.php?route=/database/structure&db=<?= urlencode($site['database']); ?>"
                                            target="_blank" class="text-xs text-blue-600 hover:underline ml-2">
                                            PHPMyAdmin
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2">
                                    <a href="<?= htmlspecialchars($site['url']); ?>"
                                        class="flex-1 text-center px-3 py-1 bg-blue-50 text-blue-600 rounded text-sm hover:bg-blue-100 transition-colors"
                                        target="_blank">
                                        Visit Site
                                    </a>
                                    <a href="<?= htmlspecialchars($site['admin_url']); ?>"
                                        class="flex-1 text-center px-3 py-1 bg-gray-50 text-gray-600 rounded text-sm hover:bg-gray-100 transition-colors"
                                        target="_blank">
                                        Admin
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Databases -->
            <div class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Databases</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 gap-4">
                    <?php if (empty($databases)): ?>
                        <div class="col-span-full">
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                <div class="text-yellow-700">No databases found</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($databases as $db): ?>
                            <a href="<?= $phpMyAdminUrl ?>/index.php?route=/database/structure&db=<?= urlencode($db['name']); ?>"
                                class="group bg-white rounded-lg shadow-sm p-4 border border-gray-200 hover:shadow-md transition-shadow overflow-x-auto"
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
                                </div>
                                <div class="text-sm text-gray-500 flex justify-between">
                                    <span class="text-sm text-gray-500"><?= $db['tables']; ?> tables</span>
                                    <span>Size: <?= number_format($db['size'], 2); ?> MB</span>
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
                    Powered by Laravel Herd - <?= date('Y'); ?>
                </p>
            </div>
        </footer>
    </div>
</body>

</html>