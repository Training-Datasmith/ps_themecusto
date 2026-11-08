<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$testsDir = dirname(__FILE__);
$moduleRoot = dirname($testsDir);

if (!getenv('PS_TEST_RUNTIME')) {
    putenv('PS_TEST_RUNTIME=' . sys_get_temp_dir());
}

$runtimeBootstrap = getenv('PS_TEST_RUNTIME');
putenv('PS_TEST_FAKE_MODULES=' . rtrim($runtimeBootstrap, '/') . '/fake-modules');

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '1.7.8.0');
}

define('_PS_MODULE_DIR_', $moduleRoot . '/');
if (!defined('__PS_BASE_URI__')) {
    define('__PS_BASE_URI__', '/');
}
define('_PS_BASE_URI_', '/');
define('_PS_ADMIN_PROFILE_', 1);
define('_DB_PREFIX_', 'ps_');
define('_THEME_NAME_', 'classic');

$runtime = getenv('PS_TEST_RUNTIME');
if ($runtime === false || $runtime === '') {
    $runtime = sys_get_temp_dir();
}

define('_PS_ROOT_DIR_', rtrim($runtime, '/') . '/ps-root/');
define('_PS_CACHE_DIR_', rtrim($runtime, '/') . '/cache/');
define('_PS_ALL_THEMES_DIR_', rtrim($runtime, '/') . '/themes/');
define('_PS_THEME_DIR_', _PS_ALL_THEMES_DIR_ . 'classic/');
define('_PS_OVERRIDE_DIR_', rtrim($runtime, '/') . '/override/');

if (!is_dir(_PS_CACHE_DIR_)) {
    mkdir(_PS_CACHE_DIR_, 0700, true);
}
if (!is_dir(_PS_CACHE_DIR_ . 'sandbox/')) {
    mkdir(_PS_CACHE_DIR_ . 'sandbox/', 0700, true);
}
ini_set('html_errors', '0');
if (!is_dir(_PS_ALL_THEMES_DIR_)) {
    mkdir(_PS_ALL_THEMES_DIR_, 0700, true);
}
if (!is_dir(_PS_THEME_DIR_)) {
    mkdir(_PS_THEME_DIR_, 0700, true);
}
if (!is_dir(_PS_ROOT_DIR_)) {
    mkdir(_PS_ROOT_DIR_, 0700, true);
}
if (!is_dir(_PS_ROOT_DIR_ . 'themes/')) {
    mkdir(_PS_ROOT_DIR_ . 'themes/', 0700, true);
}

if (!defined('UPLOAD_ERR_OK')) {
    define('UPLOAD_ERR_OK', 0);
    define('UPLOAD_ERR_INI_SIZE', 1);
    define('UPLOAD_ERR_FORM_SIZE', 2);
    define('UPLOAD_ERR_PARTIAL', 3);
    define('UPLOAD_ERR_NO_FILE', 4);
}

$finderBase = getenv('SYMFONY_FINDER_PATH');
if (($finderBase === false || $finderBase === '') && getenv('PS_TEST_RUNTIME')) {
    $finderBase = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/finder-3.4.47';
    putenv('SYMFONY_FINDER_PATH=' . $finderBase);
}
if ($finderBase !== false && $finderBase !== '' && is_dir($finderBase)) {
    spl_autoload_register(function ($class) use ($finderBase) {
        if (strpos($class, 'Symfony\\Component\\Finder\\') !== 0) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen('Symfony\\Component\\Finder\\')));
        $path = rtrim($finderBase, '/') . '/' . $relative . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    });
    $finderRoot = rtrim($finderBase, '/');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($finderRoot, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && substr($fileInfo->getFilename(), -4) === '.php'
            && strpos($fileInfo->getPathname(), '/Tests/') === false) {
            require_once $fileInfo->getPathname();
        }
    }
}

require_once $testsDir . '/Stubs/PrestaShopStubs.php';
require_once $testsDir . '/Stubs/PrestaShopAdapterStubs.php';
require_once $testsDir . '/Stubs/PrestaShopThemeStubs.php';

require_once $moduleRoot . '/classes/ThemeCustoRequests.php';
require_once $moduleRoot . '/ps_themecusto.php';
require_once $moduleRoot . '/controllers/admin/AdminPsThemeCustoConfiguration.php';
require_once $moduleRoot . '/controllers/admin/AdminPsThemeCustoAdvanced.php';
require_once $moduleRoot . '/upgrade/upgrade-1.1.0.php';
require_once $testsDir . '/Support/TestableControllers.php';
