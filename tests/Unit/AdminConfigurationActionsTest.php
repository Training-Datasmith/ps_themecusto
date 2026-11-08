<?php

class AdminConfigurationActionsTest extends PsThemeCustoTestCase
{
    private function runAjax($version, $post, $editRight = true)
    {
        $idTab = 200;
        $postExport = var_export($post, true);
        $editExport = $editRight ? "'1'" : "'0'";
        return ChildRunner::runScript('<?php
define("_PS_VERSION_", ' . var_export($version, true) . ');
$_POST = ' . $postExport . ';
require "' . dirname(__DIR__) . '/bootstrap.php";
Db::getInstance()->ensureSchema();
putenv("PS_TEST_FAKE_MODULES=" . getenv("PS_TEST_RUNTIME") . "/fake-modules");
Db::getInstance()->insert("tab", array(
    "id_tab" => "200", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
Profile::setProfileAccess(1, 200, array("edit" => ' . $editExport . '));
$controller = TestableAdminPsThemeCustoConfigurationController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessUpdateModule();
$out = ob_get_clean();
fwrite(STDOUT, $out);
');
    }

    public function testUninstallSetsInstallActionAndRemovesModule()
    {
        $id = $this->insertModuleRow('ps_banner');
        $_POST = array();
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_banner',
            'action_module' => 'uninstall',
        ));
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertContains('url_active=install', $result['stdout']);
        $this->assertFalse((bool) Db::getInstance()->getValue('SELECT id_module FROM `' . _DB_PREFIX_ . 'module` WHERE id_module = ' . (int) $id));
    }

    public function testInstallWithGetContentSetsConfigureAndInsertsRow()
    {
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_banner',
            'action_module' => 'install',
        ));
        $this->assertContains('url_active=configure', $result['stdout']);
        $this->assertNotEmpty(Db::getInstance()->getValue('SELECT id_module FROM `' . _DB_PREFIX_ . 'module` WHERE name="ps_banner"'));
    }

    public function testInstallWithoutGetContentSetsDisable()
    {
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_nocontent',
            'action_module' => 'install',
        ));
        $this->assertContains('url_active=disable', $result['stdout']);
    }

    public function testEnableInsertsShopRowAndSetsConfigure()
    {
        Db::getInstance()->insert('module', array('name' => 'ps_banner', 'active' => '1'));
        $id = Db::getInstance()->Insert_ID();
        Module::resetInstances();
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_banner',
            'action_module' => 'enable',
        ));
        $this->assertContains('url_active=configure', $result['stdout']);
        $this->assertNotEmpty(Db::getInstance()->getValue('SELECT id_shop FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id));
    }

    public function testDisableRemovesOnlyCurrentShopAndSetsEnable()
    {
        $id = $this->insertModuleRow('ps_banner');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop`');
        $this->insertModuleShop($id, 1, 7);
        $this->insertModuleShop($id, 2, 7);
        Shop::$contextShopId = 2;
        $result = ChildRunner::runScript('<?php
define("_PS_VERSION_", "1.7.8.0");
$_POST = array("module_name" => "ps_banner", "action_module" => "disable");
require "' . dirname(__DIR__) . '/bootstrap.php";
Shop::$contextShopId = 2;
Context::getContext()->shop->id = 2;
Profile::setProfileAccess(1, 200, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "200", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$controller = TestableAdminPsThemeCustoConfigurationController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessUpdateModule();
fwrite(STDOUT, ob_get_clean());
');
        $this->assertContains('url_active=enable', $result['stdout']);
        $this->assertContains('enable_mobile=0', $result['stdout']);
        $this->assertSame('7', Db::getInstance()->getValue('SELECT enable_device FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=1'));
        $this->assertFalse(Db::getInstance()->getValue('SELECT id_shop FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=2'));
    }

    public function testDisableMobileClearsMobileBitOnCurrentShopOnly()
    {
        $id = $this->insertModuleRow('ps_banner');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop`');
        $this->insertModuleShop($id, 1, 7);
        $this->insertModuleShop($id, 2, 7);
        $result = ChildRunner::runScript('<?php
define("_PS_VERSION_", "1.7.8.0");
$_POST = array("module_name" => "ps_banner", "action_module" => "disable_mobile");
require "' . dirname(__DIR__) . '/bootstrap.php";
Shop::$contextShopId = 2;
Context::getContext()->shop->id = 2;
Profile::setProfileAccess(1, 200, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "200", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$controller = TestableAdminPsThemeCustoConfigurationController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessUpdateModule();
fwrite(STDOUT, ob_get_clean());
');
        $this->assertSame('3', Db::getInstance()->getValue('SELECT enable_device FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=2'));
        $this->assertSame('7', Db::getInstance()->getValue('SELECT enable_device FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=1'));
        $this->assertContains('enable_mobile=3', $result['stdout']);
    }

    public function testEnableMobileSetsMobileBitOnCurrentShopOnly()
    {
        $id = $this->insertModuleRow('ps_banner');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop`');
        $this->insertModuleShop($id, 1, 1);
        $this->insertModuleShop($id, 2, 3);
        $result = ChildRunner::runScript('<?php
define("_PS_VERSION_", "1.7.8.0");
$_POST = array("module_name" => "ps_banner", "action_module" => "enable_mobile");
require "' . dirname(__DIR__) . '/bootstrap.php";
Shop::$contextShopId = 2;
Context::getContext()->shop->id = 2;
Profile::setProfileAccess(1, 200, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "200", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$controller = TestableAdminPsThemeCustoConfigurationController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessUpdateModule();
fwrite(STDOUT, ob_get_clean());
');
        $this->assertSame('7', Db::getInstance()->getValue('SELECT enable_device FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=2'));
        $this->assertSame('1', Db::getInstance()->getValue('SELECT enable_device FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module=' . (int) $id . ' AND id_shop=1'));
        $this->assertContains('enable_mobile=7', $result['stdout']);
    }

    public function testResetReplacesModuleId()
    {
        $oldId = $this->insertModuleRow('ps_banner');
        $this->insertModuleRow('ps_z_extra');
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_banner',
            'action_module' => 'reset',
        ));
        $newId = (int) Db::getInstance()->getValue('SELECT id_module FROM `' . _DB_PREFIX_ . 'module` WHERE name="ps_banner"');
        $this->assertNotSame($oldId, $newId);
        $this->assertContains('url_active=configure', $result['stdout']);
    }

    public function testUpdateModuleDeniedWithoutEditRight()
    {
        $this->insertModuleRow('ps_banner');
        $result = $this->runAjax('1.7.8.0', array(
            'module_name' => 'ps_banner',
            'action_module' => 'disable',
        ), false);
        $this->assertSame('You do not have permission to edit this.', trim($result['stdout']));
    }

    public function testAjaxDieUsedBefore175()
    {
        $this->insertModuleRow('ps_banner');
        $result = $this->runAjax('1.7.4.4', array(
            'module_name' => 'ps_banner',
            'action_module' => 'disable',
        ));
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertContains('TPL:', $result['stdout']);
    }
}
