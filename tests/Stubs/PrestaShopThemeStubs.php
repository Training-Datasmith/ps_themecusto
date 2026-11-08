<?php

namespace PrestaShop\PrestaShop\Core\Addon\Theme;

class ThemeManager
{
    public static $throwOnInstall = false;

    public function install($dest)
    {
        if (self::$throwOnInstall) {
            throw new \Exception('install failed');
        }
        if (!is_file($dest)) {
            throw new \Exception('missing zip');
        }
        $zip = new \ZipArchive();
        if ($zip->open($dest) !== true) {
            throw new \Exception('bad zip');
        }
        $themeName = null;
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^([^/]+)/config/theme\.yml$#', $name, $m)) {
                $themeName = $m[1];
                break;
            }
        }
        if ($themeName === null) {
            $themeName = 'uploaded_child';
        }
        $target = _PS_ALL_THEMES_DIR_ . $themeName;
        if (!is_dir($target)) {
            mkdir($target, 0700, true);
        }
        if (!is_dir($target . '/config')) {
            mkdir($target . '/config', 0700, true);
        }
        $zip->extractTo($target);
        $zip->close();
        touch($target, time());

        return true;
    }
}

class ThemeRepository
{
}

class ThemeManagerBuilder
{
    public function __construct($context, $db)
    {
    }

    public function build()
    {
        return new ThemeManager();
    }

    public function buildRepository()
    {
        return new ThemeRepository();
    }
}
