<?php

namespace PrestaShop\PrestaShop\Adapter;

class SymfonyContainer
{
    public static $routerPath = '/admin/product/catalog';

    public static function setRouterPath($path)
    {
        self::$routerPath = $path;
    }

    public static function getInstance()
    {
        return new self();
    }

    public function get($id)
    {
        if ($id === 'router') {
            return new SymfonyRouter();
        }
        throw new \Exception('unknown service');
    }
}

class SymfonyRouter
{
    public function generate($name)
    {
        return SymfonyContainer::$routerPath;
    }
}
