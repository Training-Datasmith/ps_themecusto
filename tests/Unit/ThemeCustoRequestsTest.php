<?php

class ThemeCustoRequestsTest extends PsThemeCustoTestCase
{
    public function testGetModulesListByNameReturnsRow()
    {
        $id = $this->insertModuleRow('ps_banner');
        $rows = ThemeCustoRequests::getModulesListByName('ps_banner');
        $this->assertTrue(is_array($rows));
        $this->assertCount(1, $rows);
        $this->assertSame((string) $id, $rows[0]['id_module']);
        $this->assertSame('ps_banner', $rows[0]['name']);
        $missing = ThemeCustoRequests::getModulesListByName('no_such_module_xyz');
        $this->assertTrue(is_array($missing));
        $this->assertCount(0, $missing);
    }

    public function testGetModulesListByNameRejectsQuoteInjection()
    {
        $this->insertModuleRow('ps_banner');
        $injected = ThemeCustoRequests::getModulesListByName("ps_banner' OR '1'='1");
        $this->assertCount(0, $injected);
        $this->assertCount(1, ThemeCustoRequests::getModulesListByName('ps_banner'));
    }

    public function testGetModuleDeviceStatusReturnsFalseWhenMissing()
    {
        $this->assertFalse(ThemeCustoRequests::getModuleDeviceStatus(99999));
    }

    public function testDeviceStatusUsesCurrentShop()
    {
        $id = $this->insertModuleRow('ps_shop_device');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $id);
        $this->insertModuleShop($id, 1, 1);
        $this->insertModuleShop($id, 2, 7);
        Shop::$contextShopId = 2;
        Context::getContext()->shop->id = 2;
        $this->assertSame('7', ThemeCustoRequests::getModuleDeviceStatus($id));
    }
}
