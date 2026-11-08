<?php

class IndexGuardTest extends PsThemeCustoTestCase
{
    public static $expectedIndexFiles = array(
        'classes/index.php',
        'controllers/admin/index.php',
        'controllers/index.php',
        'index.php',
        'sql/index.php',
        'src/index.php',
        'tests/index.php',
        'tests/phpstan/index.php',
        'translations/index.php',
        'upgrade/index.php',
        'views/css/controllers/advanced/index.php',
        'views/css/controllers/configuration/index.php',
        'views/css/controllers/index.php',
        'views/css/index.php',
        'views/fonts/index.php',
        'views/img/controllers/advanced/index.php',
        'views/img/controllers/configuration/index.php',
        'views/img/controllers/configuration/wireframe/homepage/index.php',
        'views/img/controllers/configuration/wireframe/index.php',
        'views/img/controllers/index.php',
        'views/img/index.php',
        'views/index.php',
        'views/js/controllers/advanced/index.php',
        'views/js/controllers/configuration/index.php',
        'views/js/controllers/index.php',
        'views/js/index.php',
        'views/templates/admin/controllers/advanced/elem/index.php',
        'views/templates/admin/controllers/advanced/index.php',
        'views/templates/admin/controllers/configuration/elem/index.php',
        'views/templates/admin/controllers/configuration/index.php',
        'views/templates/admin/controllers/index.php',
        'views/templates/admin/index.php',
        'views/templates/index.php',
    );

    public function testDirectoryIndexScriptsExitBeforeSentinel()
    {
        $repo = rtrim(_PS_MODULE_DIR_, '/');
        $onDisk = array();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($repo));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'index.php') {
                $relative = str_replace($repo . '/', '', $file->getPathname());
                $onDisk[] = $relative;
            }
        }
        sort($onDisk);
        $expected = self::$expectedIndexFiles;
        sort($expected);
        $this->assertSame($expected, $onDisk);
        foreach ($expected as $rel) {
            $result = ChildRunner::runScript('<?php
require "' . dirname(__DIR__) . '/bootstrap.php";
require "' . $repo . '/' . $rel . '";
fwrite(STDOUT, "SENTINEL_REACHED");
');
            $this->assertNoPhpDiagnostics($result['combined'], '');
            $this->assertSame(0, $result['exitCode']);
            $this->assertSame('', trim($result['stdout']));
        }
    }
}
