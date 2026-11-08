<?php

class AdminConfigurationListsTest extends PsThemeCustoTestCase
{
    private function listsForVersion($version)
    {
        $result = ChildRunner::runScript('<?php
define("_PS_VERSION_", ' . var_export($version, true) . ');
require "' . dirname(__DIR__) . '/bootstrap.php";
$controller = TestableAdminPsThemeCustoConfigurationController::withModule(new ps_themecusto());
fwrite(STDOUT, json_encode(array(
    "isPsVersion174Plus" => $controller->isPsVersion174Plus,
    "home" => $controller->getHomepageListConfiguration(),
    "category" => $controller->getCategoryListConfiguration(),
    "product" => $controller->getProductListConfiguration(),
)));
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertSame(0, $result['exitCode']);

        return json_decode($result['stdout'], true);
    }

    public function testListsForVersion173()
    {
        $d = $this->listsForVersion('1.7.3.0');
        $this->assertFalse($d['isPsVersion174Plus']);
        $this->assertSame(array('ps_linklist' => 24360), $d['home']['footer']['modules']);
        $this->assertArrayHasKey('AdminPPreferences', $d['category']['content']['pages']);
        $this->assertSame(array('ps_sharebuttons' => 22322), $d['product']['product_detail']['modules']);
        $this->assertArrayHasKey('AdminStockManagement', $d['product']['product_management']['pages']);
        $this->assertSame(array('admin_product_catalog'), array_keys($d['product']['product_management']['sfRoutePages']));
        $this->assertSame(22321, $d['home']['menu']['modules']['ps_mainmenu']);
    }

    public function testListsForVersion174()
    {
        $d = $this->listsForVersion('1.7.4.0');
        $this->assertTrue($d['isPsVersion174Plus']);
        $this->assertSame(array('blockreassurance' => 22312, 'ps_linklist' => 24360), $d['home']['footer']['modules']);
        $this->assertArrayHasKey('admin_product_preferences', $d['category']['content']['sfRoutePages']);
        $this->assertSame(array('blockreassurance' => 22312, 'ps_sharebuttons' => 22322), $d['product']['product_detail']['modules']);
        $this->assertArrayHasKey('admin_stock_overview', $d['product']['product_management']['sfRoutePages']);
        $this->assertArrayHasKey('AdminAttributesGroups', $d['product']['product_management']['pages']);
        $this->assertArrayNotHasKey('AdminStockManagement', $d['product']['product_management']['pages']);
    }

    public function testListsForVersion176()
    {
        $d = $this->listsForVersion('1.7.6.0');
        $this->assertArrayHasKey('productcomments', $d['product']['product_detail']['modules']);
        $this->assertSame(9144, $d['product']['product_detail']['modules']['productcomments']);
    }

    public function testHelpUrlSelectsDocVersion()
    {
        $controller = $this->createAdvancedController();
        $ref = new ReflectionClass($controller);
        $method = $ref->getMethod('getChildThemeHelpUrl');
        $method->setAccessible(true);
        $this->assertContains('/1.7/', $method->invoke($controller));

        foreach (array('8.0.0' => '/8/', '8.1.2' => '/8/', '9.0.0' => '/9/') as $ver => $needle) {
            $result = ChildRunner::runScript('<?php
define("_PS_VERSION_", ' . var_export($ver, true) . ');
require "' . dirname(__DIR__) . '/bootstrap.php";
$c = TestableAdminPsThemeCustoAdvancedController::withModule(new ps_themecusto());
$r = new ReflectionClass($c);
$m = $r->getMethod("getChildThemeHelpUrl");
$m->setAccessible(true);
fwrite(STDOUT, $m->invoke($c));
');
            $this->assertContains($needle, $result['stdout']);
        }
    }

    public function testCategoryLabelKeys()
    {
        $controller = $this->createConfigurationController();
        $expected = array('menu', 'slider', 'home_products', 'block_text', 'banner', 'social_newsletter', 'footer', 'content', 'categories', 'navigation_column', 'product_management', 'product_detail', 'product_block');
        $this->assertSame($expected, array_keys($controller->categoryList));
        $this->assertSame('Menu', $controller->categoryList['menu']);
        $this->assertSame('content', $controller->categoryList['content']);
        $this->assertNotEmpty($controller->categoryList['content']);
    }

    public function testSetFinalListSkipsModulesNotOnDisk()
    {
        $controller = $this->createConfigurationController();
        $list = array('seg' => array('modules' => array('ps_missing_disk' => 1, 'ps_banner' => 2)));
        $this->insertModuleRow('ps_banner');
        Module::resetInstances();
        $final = $controller->setFinalList($list);
        $this->assertArrayHasKey('ps_banner', $final['seg']['modules']);
        $this->assertArrayNotHasKey('ps_missing_disk', $final['seg']['modules']);
    }

    public function testSetFinalListBuildsPageLinks()
    {
        $controller = $this->createConfigurationController();
        $list = array('seg' => array('pages' => array('AdminCategories' => array('Categories', 'Cat desc'))));
        $final = $controller->setFinalList($list);
        $page = $final['seg']['pages']['AdminCategories'];
        $this->assertSame('Categories', $page['displayName']);
        $this->assertContains('AdminCategories', $page['url']);
        $this->assertSame('Cat desc', $page['description']);
        $this->assertSame('Configure', $page['action']);
    }

    public function testSetFinalListSortsInstalledModulesFirst()
    {
        $controller = $this->createConfigurationController();
        $this->insertModuleRow('ps_banner');
        $this->insertModuleRow('ps_nocontent');
        Module::resetInstances();
        $list = array('seg' => array('modules' => array('ps_nocontent' => 1, 'ps_banner' => 2)));
        $final = $controller->setFinalList($list);
        $keys = array_keys($final['seg']['modules']);
        $this->assertSame('ps_banner', $keys[0]);
    }

    public function testSetModuleFinalListActionKeys()
    {
        $controller = $this->createConfigurationController();
        $installed = Module::getInstanceByName('ps_banner');
        $this->insertModuleRow('ps_banner');
        $installed = Module::getInstanceByName('ps_banner');
        $row = $controller->setModuleFinalList($installed, true);
        $this->assertSame('configure', $row['url_active']);
        $this->assertTrue($row['can_configure']);
        $notInstalled = Module::getInstanceByName('ps_nocontent');
        $row2 = $controller->setModuleFinalList($notInstalled, false);
        $this->assertSame('install', $row2['url_active']);
        $this->assertSame(0, $row2['installed']);
        $this->assertSame('/modules/ps_nocontent/logo.png', $row2['logo']);
    }

    public function testSetModuleFinalListEnableMobileUsesCurrentShop()
    {
        $id = $this->insertModuleRow('ps_banner');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $id);
        $this->insertModuleShop($id, 1, 1);
        $this->insertModuleShop($id, 2, 7);
        Shop::$contextShopId = 2;
        Context::getContext()->shop->id = 2;
        $controller = $this->createConfigurationController();
        $mod = Module::getInstanceByName('ps_banner');
        $row = $controller->setModuleFinalList($mod, true);
        $this->assertSame(7, $row['enable_mobile']);
    }

    public function testSortComparatorSign()
    {
        $controller = $this->createConfigurationController();
        $this->assertGreaterThan(0, $controller->sortArrayInstalledModulesFirst(array('installed' => 0), array('installed' => 1)));
        $this->assertLessThan(0, $controller->sortArrayInstalledModulesFirst(array('installed' => 1), array('installed' => 0)));
        $this->assertSame(0, $controller->sortArrayInstalledModulesFirst(array('installed' => 1), array('installed' => 1)));
    }

    public function testEmptyModuleSegmentBecomesNull()
    {
        $controller = $this->createConfigurationController();
        $list = array('empty_seg' => array('modules' => array('ps_missing_disk' => 1)));
        $final = $controller->setFinalList($list);
        $this->assertNull($final['empty_seg']);
    }

    public function testSfRoutePagesUseGeneratedUrl()
    {
        PrestaShop\PrestaShop\Adapter\SymfonyContainer::setRouterPath('/admin/product/catalog');
        $controller = $this->createConfigurationController();
        $list = array('seg' => array('sfRoutePages' => array('admin_product_catalog' => array('Catalog', 'desc'))));
        $final = $controller->setFinalList($list);
        $this->assertSame('/admin/product/catalog', $final['seg']['sfRoutePages']['admin_product_catalog']['url']);
    }
}
