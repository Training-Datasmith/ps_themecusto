<?php

class TestableAdminPsThemeCustoConfigurationController extends AdminPsThemeCustoConfigurationController
{
    public static function withModule($module)
    {
        $controller = new self();
        $controller->module = $module;

        return $controller;
    }

    public function __construct()
    {
        $this->module = new ps_themecusto();
        parent::__construct();
    }
}

class TestableAdminPsThemeCustoAdvancedController extends AdminPsThemeCustoAdvancedController
{
    public static function withModule($module)
    {
        $controller = new self();
        $controller->module = $module;

        return $controller;
    }

    public function __construct()
    {
        $this->module = new ps_themecusto();
        parent::__construct();
    }

}
