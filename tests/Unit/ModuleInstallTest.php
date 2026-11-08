<?php

class ModuleInstallTest extends PsThemeCustoTestCase
{
    public function testConstructorRejectsNonContext()
    {
        $result = ChildRunner::runScript('<?php
define("PS_TEST_NULL_CONTEXT", true);
require "' . dirname(__DIR__) . '/bootstrap.php";
Context::reset();
try {
    new ps_themecusto();
    fwrite(STDOUT, "NO_EXCEPTION");
} catch (PrestaShopException $e) {
    fwrite(STDOUT, $e->getMessage());
}
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertContains('Undefined context', $result['stdout']);
    }

    public function testConstructorSetsIdentityPathsAndCompliancy()
    {
        $module = new ps_themecusto();
        $this->assertSame('ps_themecusto', $module->name);
        $this->assertSame('1.2.5', $module->version);
        $this->assertSame('front_office_features', $module->tab);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertTrue($module->bootstrap);
        $this->assertSame('1.7', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
        $this->assertSame('/modules/ps_themecusto/views/js/', $module->js_path);
        $this->assertContains('homepage', $module->description);
    }

    public function testConstructorUsesHttpsShopDomainWhenSecure()
    {
        Tools::$secureMode = true;
        $module = new ps_themecusto();
        $this->assertSame('https://shop.test/', $module->ps_uri);
    }

    public function testConstructorSkipsAdminLinksDuringInstallation()
    {
        $result = ChildRunner::runScript('<?php
define("PS_INSTALLATION_IN_PROGRESS", true);
require "' . dirname(__DIR__) . '/bootstrap.php";
$m = new ps_themecusto();
fwrite(STDOUT, json_encode($m->front_controller));
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('[]', trim($result['stdout']));
    }

    public function testConstructorAdminLinksFollowControllerOrder()
    {
        $module = new ps_themecusto();
        $this->assertContains('AdminPsThemeCustoAdvanced', $module->front_controller[0]);
        $this->assertContains('AdminPsThemeCustoConfiguration', $module->front_controller[1]);
    }

    public function testConfigXmlMatchesModuleNameAndVersion()
    {
        $xml = simplexml_load_file(_PS_MODULE_DIR_ . 'config.xml');
        $this->assertSame('ps_themecusto', (string) $xml->name);
        $this->assertSame('1.2.5', (string) $xml->version);
    }

    public function testHasEditRightFalseWhenProfileAccessMissing()
    {
        $module = new ps_themecusto();
        Db::getInstance()->insert('tab', array(
            'id_tab' => '90',
            'id_parent' => '0',
            'position' => '1',
            'module' => 'ps_themecusto',
            'class_name' => 'AdminPsThemeCustoAdvanced',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Tab::resetStaticCache();
        $this->assertFalse($module->hasEditRight());
    }

    public function testHasEditRightFalseWhenEditFlagIsZero()
    {
        $module = new ps_themecusto();
        $idTab = 90;
        Db::getInstance()->insert('tab', array(
            'id_tab' => (string) $idTab,
            'id_parent' => '0',
            'position' => '1',
            'module' => 'ps_themecusto',
            'class_name' => 'AdminPsThemeCustoAdvanced',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Tab::resetStaticCache();
        Profile::setProfileAccess(1, $idTab, array('edit' => '0'));
        $this->assertFalse($module->hasEditRight());
        Profile::setProfileAccess(1, $idTab, array('edit' => 0));
        $this->assertFalse($module->hasEditRight());
    }

    public function testHasEditRightTrueWhenEditFlagIsOne()
    {
        $module = new ps_themecusto();
        $idTab = 91;
        Db::getInstance()->insert('tab', array(
            'id_tab' => (string) $idTab,
            'id_parent' => '0',
            'position' => '1',
            'module' => 'ps_themecusto',
            'class_name' => 'AdminPsThemeCustoAdvanced',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Tab::resetStaticCache();
        Profile::setProfileAccess(1, $idTab, array('edit' => '1'));
        $this->assertTrue($module->hasEditRight());
    }

    public function testHasEditRightChecksAdvancedTabOnly()
    {
        $module = new ps_themecusto();
        $advanced = 92;
        $config = 93;
        Db::getInstance()->insert('tab', array(
            'id_tab' => (string) $advanced,
            'id_parent' => '0',
            'position' => '1',
            'module' => 'ps_themecusto',
            'class_name' => 'AdminPsThemeCustoAdvanced',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Db::getInstance()->insert('tab', array(
            'id_tab' => (string) $config,
            'id_parent' => '0',
            'position' => '2',
            'module' => 'ps_themecusto',
            'class_name' => 'AdminPsThemeCustoConfiguration',
            'active' => '1',
        ), false, true, Db::INSERT, true);
        Tab::resetStaticCache();
        Profile::setProfileAccess(1, $config, array('edit' => '1'));
        $this->assertFalse($module->hasEditRight());
        Profile::setProfileAccess(1, $advanced, array('edit' => '1'));
        $this->assertTrue($module->hasEditRight());
    }

    public function testSetMediaAppendsGeneralAssetsAndJsDefs()
    {
        $module = new ps_themecusto();
        $css = array('/tmp/a.css');
        $js = array('/tmp/a.js');
        $defs = array('foo' => 'bar');
        $module->setMedia($defs, $js, $css);
        $this->assertSame('bar', Media::$jsDef['foo']);
        $this->assertContains($module->css_path . 'general.css', Context::getContext()->controller->css);
        $this->assertContains($module->js_path . 'general.js', Context::getContext()->controller->js);
    }

    public function testInstallFailureSkipsTabsAndRecordsError()
    {
        $this->insertModuleRow('ps_themecusto', 500);
        $this->seedAdminThemesTab();
        $module = new ps_themecusto();
        $this->assertFalse($module->install());
        $this->assertFalse((bool) Tab::getIdFromClassName('AdminThemesParent'));
        $this->assertNotEmpty($module->_errors);
        $this->assertContains('installation', strtolower($module->_errors[0]));
    }

    public function testInstallCreatesParentAndModuleTabsWithEnglishFallback()
    {
        $this->seedAdminThemesTab(5, 10, 1);
        $module = new ps_themecusto();
        $this->assertTrue($module->install());
        $parentId = Tab::getIdFromClassName('AdminThemesParent');
        $this->assertNotFalse($parentId);
        $themes = Tab::getInstanceFromClassName('AdminThemes');
        $this->assertSame((int) $parentId, (int) $themes->id_parent);
        $confId = Tab::getIdFromClassName('AdminPsThemeCustoConfiguration');
        $advId = Tab::getIdFromClassName('AdminPsThemeCustoAdvanced');
        $this->assertNotFalse($confId);
        $this->assertNotFalse($advId);
        $this->assertNotSame($confId, $advId);
        $conf = Tab::getInstanceFromClassName('AdminPsThemeCustoConfiguration');
        $this->assertSame('ps_themecusto', $conf->module);
        $this->assertSame((int) $parentId, (int) $conf->id_parent);
        $this->assertSame('Pages Configuration', $conf->name[1]);
        $this->assertSame('Pages Configuration', $conf->name[2]);
        $adv = Tab::getInstanceFromClassName('AdminPsThemeCustoAdvanced');
        $this->assertSame('Personnalisation avancée', $adv->name[1]);
        $this->assertSame('Advanced Customization', $adv->name[2]);
        $this->assertSame('Pages Configuration', $conf->name[3]);
        $rows = Db::getInstance()->executeS('SELECT id_lang FROM `' . _DB_PREFIX_ . 'tab_lang` WHERE id_lang = 4');
        $this->assertSame(array(), $rows);
    }

    public function testUninstallFailureSkipsTabRemoval()
    {
        $this->seedAdminThemesTab();
        $module = new ps_themecusto();
        $module->id = 0;
        $this->assertFalse($module->uninstall());
        $this->assertNotEmpty($module->_errors);
    }

    public function testUninstallRestoresAdminThemesUnderOriginalParent()
    {
        $this->seedAdminThemesTab(5, 10, 1);
        $module = new ps_themecusto();
        $module->install();
        $this->assertTrue($module->uninstall());
        $this->assertFalse(Tab::getIdFromClassName('AdminThemesParent'));
        $this->assertFalse(Tab::getIdFromClassName('AdminPsThemeCustoAdvanced'));
        $themes = Tab::getInstanceFromClassName('AdminThemes');
        $this->assertSame(10, (int) $themes->id_parent);
        $this->assertSame(0, (int) $themes->position);
    }

    public function testUpgradeRebuildsTabTree()
    {
        $this->seedAdminThemesTab(5, 10, 1);
        $module = new ps_themecusto();
        $module->install();
        $this->assertTrue(upgrade_module_1_1_0($module));
        $this->assertNotFalse(Tab::getIdFromClassName('AdminThemesParent'));
    }

    public function testUpgradeStopsWhenTabUninstallFails()
    {
        $result = ChildRunner::runScript('<?php
require "' . dirname(__DIR__) . '/bootstrap.php";
class ps_themecusto_upgrade_fail extends ps_themecusto {
    public function uninstallTabList() { return false; }
    public function installTabList() {
        Db::getInstance()->insert("tab", array(
            "id_tab" => "999",
            "id_parent" => "0",
            "position" => "1",
            "module" => "ps_themecusto",
            "class_name" => "UpgradeShouldNotExist",
            "active" => "1",
        ), false, true, Db::INSERT, true);
        return true;
    }
}
$module = new ps_themecusto_upgrade_fail();
$db = Db::getInstance();
$db->truncateTestTables();
$tab = new Tab();
$tab->force_id = true;
$tab->id = 5;
$tab->id_parent = 10;
$tab->position = 1;
$tab->class_name = "AdminThemes";
$tab->module = "";
$tab->name = array(1 => "Themes");
$tab->add();
$ok = upgrade_module_1_1_0($module);
fwrite(STDOUT, $ok ? "1" : "0");
fwrite(STDOUT, Tab::getIdFromClassName("UpgradeShouldNotExist") ? "bad" : "good");
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertContains('0good', str_replace("\n", '', $result['stdout']));
    }
}
