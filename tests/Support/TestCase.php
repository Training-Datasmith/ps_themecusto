<?php

abstract class PsThemeCustoTestCase extends PHPUnit_Framework_TestCase
{
    protected static $runtimeDir;
    protected static $moduleFixturesDir;

    public static function setUpBeforeClass()
    {
        self::$runtimeDir = getenv('PS_TEST_RUNTIME');
        if (!self::$runtimeDir) {
            self::$runtimeDir = sys_get_temp_dir() . '/ps-theme-test-' . getmypid();
            putenv('PS_TEST_RUNTIME=' . self::$runtimeDir);
        }
        if (!is_dir(self::$runtimeDir)) {
            mkdir(self::$runtimeDir, 0700, true);
        }
        Db::resetInstance();
        $db = Db::getInstance();
        $db->ensureSchema();
        self::$moduleFixturesDir = rtrim(self::$runtimeDir, '/') . '/fake-modules';
        putenv('PS_TEST_FAKE_MODULES=' . self::$moduleFixturesDir);
        if (!is_dir(self::$moduleFixturesDir)) {
            mkdir(self::$moduleFixturesDir, 0700, true);
        }
        self::writeFakeModule('ps_banner', true);
        self::writeFakeModule('ps_nocontent', false);
        self::writeFakeModule('ps_other', true);
        self::writeFakeModule('ps_missing', true);
    }

    protected function setUp()
    {
        Context::reset();
        Shop::$contextShopId = 1;
        Tools::$secureMode = false;
        Media::reset();
        Module::resetInstances();
        Tab::resetStaticCache();
        Profile::reset();
        Db::resetInstance();
        $db = Db::getInstance();
        $db->truncateTestTables();
        $_GET = array();
        $_POST = array();
        $_FILES = array();
        PrestaShop\PrestaShop\Core\Addon\Theme\ThemeManager::$throwOnInstall = false;
        PrestaShop\PrestaShop\Adapter\SymfonyContainer::setRouterPath('/admin/product/catalog');
    }

    protected function tearDown()
    {
        Module::resetInstances();
    }

    protected function assertNoPhpDiagnostics($stdout, $stderr)
    {
        $combined = $stdout . "\n" . $stderr;
        if (preg_match('/(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/', $combined)) {
            $this->fail('PHP diagnostic in child output: ' . $combined);
        }
    }

    protected function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
        }
        $parent = dirname($path);
        if ($parent !== $path && !is_dir($parent)) {
            $this->ensureDirectory($parent);
        }
        mkdir($path, 0700, true);
    }

    protected function seedAdminThemesTab($id = 5, $idParent = 10, $position = 1)
    {
        Db::getInstance()->insert('tab', array(
            'id_tab' => (string) $id,
            'id_parent' => (string) $idParent,
            'position' => (string) $position,
            'module' => '',
            'class_name' => 'AdminThemes',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Db::getInstance()->insert('tab_lang', array(
            'id_tab' => (string) $id,
            'id_lang' => '1',
            'name' => 'Themes',
        ), false, true, Db::INSERT, true);
        Tab::resetStaticCache();
    }

    protected function insertModuleRow($name, $id = null)
    {
        $data = array('name' => $name, 'active' => '1');
        if ($id !== null) {
            $data['id_module'] = (string) $id;
        }
        Db::getInstance()->insert('module', $data);
        $newId = Db::getInstance()->Insert_ID();
        Db::getInstance()->insert('module_shop', array(
            'id_module' => (string) $newId,
            'id_shop' => '1',
            'enable_device' => '7',
        ));

        return (int) $newId;
    }

    protected function insertModuleShop($idModule, $idShop, $enableDevice)
    {
        Db::getInstance()->insert('module_shop', array(
            'id_module' => (string) $idModule,
            'id_shop' => (string) $idShop,
            'enable_device' => (string) $enableDevice,
        ));
    }

    protected static function writeFakeModule($name, $withGetContent)
    {
        $dir = self::$moduleFixturesDir . '/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        $body = '<?php
class ' . $name . ' extends Module
{
    public function __construct()
    {
        $this->name = \'' . $name . '\';
        $this->displayName = \'' . $name . '\';
        $this->description = \'desc\';
        parent::__construct();
        $id = Db::getInstance()->getValue(\'SELECT id_module FROM `' . _DB_PREFIX_ . 'module` WHERE name="' . pSQL($name) . '"\');
        if ($id) {
            $this->id = (int) $id;
            $this->active = 1;
        }
    }';
        if ($withGetContent) {
            $body .= '
    public function getContent()
    {
        return \'\';
    }';
        }
        $body .= '
}
';
        file_put_contents($dir . '/' . $name . '.php', $body);
    }

    protected function createConfigurationController()
    {
        $module = new ps_themecusto();

        return TestableAdminPsThemeCustoConfigurationController::withModule($module);
    }

    protected function createAdvancedController()
    {
        $module = new ps_themecusto();

        return TestableAdminPsThemeCustoAdvancedController::withModule($module);
    }
}
