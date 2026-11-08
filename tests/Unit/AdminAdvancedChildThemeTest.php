<?php

class AdminAdvancedChildThemeTest extends PsThemeCustoTestCase
{
    private function invokePrivate($object, $method, $args = array())
    {
        $ref = new ReflectionClass($object);
        $m = $ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($object, $args);
    }

    private function zipPath($name, $entries)
    {
        $path = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/' . $name;
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        $zip->close();

        return $path;
    }

    public function testDownloadChildThemeWritesZipAndReplacesConfigTokens()
    {
        file_put_contents(_PS_THEME_DIR_ . 'preview.png', 'PREVIEW-BYTES-v1');
        $result = ChildRunner::runScript('<?php
require "' . dirname(__DIR__) . '/bootstrap.php";
Tools::$secureMode = true;
file_put_contents(_PS_THEME_DIR_ . "preview.png", "PREVIEW-BYTES-v1");
$controller = TestableAdminPsThemeCustoAdvancedController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessDownloadChildTheme();
$out = ob_get_clean();
fwrite(STDOUT, $out);
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertContains('https://shop.test/', $result['stdout']);
        $this->assertContains('child_classic.zip', $result['stdout']);
        $zipFile = _PS_ROOT_DIR_ . '/themes/child_classic.zip';
        $this->assertFileExists($zipFile);
        $zip = new ZipArchive();
        $zip->open($zipFile);
        $yml = $zip->getFromName('child_classic/config/theme.yml');
        $this->assertContains('parent: classic', $yml);
        $this->assertContains('name: child_classic', $yml);
        $this->assertContains("Child theme of classic's theme", $yml);
        $this->assertSame('PREVIEW-BYTES-v1', $zip->getFromName('child_classic/preview.png'));
        $zip->close();
    }

    public function testDownloadChildThemeEmptyBodyWhenPreviewMissing()
    {
        @unlink(_PS_THEME_DIR_ . 'preview.png');
        $result = ChildRunner::runScript('<?php
require "' . dirname(__DIR__) . '/bootstrap.php";
Tools::$secureMode = true;
@unlink(_PS_THEME_DIR_ . "preview.png");
$controller = TestableAdminPsThemeCustoAdvancedController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessDownloadChildTheme();
$out = ob_get_clean();
fwrite(STDOUT, "|" . $out . "|");
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', trim($result['stdout']));
    }

    public function testCheckIfIsChildThemeMatchesTrimmedParent()
    {
        $dir = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/child_check';
        $this->ensureDirectory($dir . '/config');
        file_put_contents($dir . '/config/theme.yml', "parent:   classic\n");
        $controller = $this->createAdvancedController();
        $this->assertTrue($this->invokePrivate($controller, 'checkIfIsChildTheme', array($dir)));
    }

    public function testCheckIfIsChildThemeRejectsOtherParent()
    {
        $dir = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/child_check2';
        $this->ensureDirectory($dir . '/config');
        file_put_contents($dir . '/config/theme.yml', "parent: other\n");
        $controller = $this->createAdvancedController();
        $this->assertFalse($this->invokePrivate($controller, 'checkIfIsChildTheme', array($dir)));
    }

    public function testRecursiveDeleteRemovesNestedDirectory()
    {
        $base = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/recursive_del';
        $this->ensureDirectory($base . '/nested');
        file_put_contents($base . '/nested/file.txt', 'x');
        $controller = $this->createAdvancedController();
        $this->assertTrue($this->invokePrivate($controller, 'recursiveDelete', array($base)));
        $this->assertFileNotExists($base);
    }

    public function testProcessCheckZipFormatRequiresConfigAndDeletesInvalidZip()
    {
        $valid = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/valid-theme.zip';
        $zip = new ZipArchive();
        $zip->open($valid, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addEmptyDir('config');
        $zip->addFromString('config/theme.yml', 'parent: classic');
        $zip->close();
        $controller = $this->createAdvancedController();
        $this->assertTrue($controller->processCheckZipFormat($valid));
        $this->assertFileExists($valid);
        $invalid = $this->zipPath('invalid-theme.zip', array('readme.txt' => 'hello'));
        $this->assertFalse($controller->processCheckZipFormat($invalid));
        $this->assertFileNotExists($invalid);
    }

    public function testProcessCheckFilesRejectsPhpAndRewritesIndex()
    {
        $dirty = $this->zipPath('dirty.zip', array(
            'child_classic/config/theme.yml' => 'parent: classic',
            'child_classic/assets/evil.php' => '<?php echo 1;',
        ));
        $controller = $this->createAdvancedController();
        $idTab = 300;
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
        $sandbox = _PS_CACHE_DIR_ . 'sandbox_chk_' . uniqid();
        mkdir($sandbox, 0700, true);
        $this->assertFalse($controller->processCheckFiles($dirty, $sandbox));
        $clean = $this->zipPath('clean.zip', array(
            'child_classic/config/theme.yml' => 'parent: classic',
            'child_classic/index.php' => '<?php echo "pwned";',
        ));
        $sandbox2 = _PS_CACHE_DIR_ . 'sandbox_chk2_' . uniqid();
        mkdir($sandbox2, 0700, true);
        $this->assertTrue($controller->processCheckFiles($clean, $sandbox2));
        $zip = new ZipArchive();
        $zip->open($clean);
        $this->assertSame(Tools::getDefaultIndexContent(), $zip->getFromName('child_classic/index.php'));
        $zip->close();
    }

    public function testPhpExtensionCheckIsCaseInsensitive()
    {
        $zipPath = $this->zipPath('evil-php-case.zip', array(
            'child_classic/config/theme.yml' => 'parent: classic',
            'child_classic/assets/Evil.PHP' => 'not-php-source',
        ));
        $controller = $this->createAdvancedController();
        $idTab = 301;
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
        $sandbox = _PS_CACHE_DIR_ . 'sandbox_case_' . uniqid();
        mkdir($sandbox, 0700, true);
        $this->assertFalse($controller->processCheckFiles($zipPath, $sandbox));
    }

    private function grantAdvancedEditRight()
    {
        $idTab = 310;
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
    }

    public function testUploadRejectsOversizedFiles()
    {
        $this->grantAdvancedEditRight();
        $controller = $this->createAdvancedController();
        foreach (array(UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE) as $code) {
            $ret = $controller->processUploadFileChild(array(
                'error' => $code,
                'tmp_name' => '',
                'name' => 'a.zip',
            ), '/tmp/x.zip');
            $this->assertFalse($ret);
            $this->assertContains('too large', strtolower($controller->errors[0]));
            $controller->errors = array();
        }
    }

    public function testUploadRejectsUnknownUploadError()
    {
        $this->grantAdvancedEditRight();
        $controller = $this->createAdvancedController();
        $ret = $controller->processUploadFileChild(array(
            'error' => UPLOAD_ERR_PARTIAL,
            'tmp_name' => '',
            'name' => 'a.zip',
        ), '/tmp/x.zip');
        $this->assertFalse($ret);
        $this->assertContains('Unknown error', $controller->errors[0]);
    }

    public function testUploadRejectsNonZipBeforeMove()
    {
        $this->grantAdvancedEditRight();
        $txt = rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/notzip.txt';
        file_put_contents($txt, 'hello');
        $controller = $this->createAdvancedController();
        $ret = $controller->processUploadFileChild(array(
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $txt,
            'name' => 'x.txt',
            'type' => 'text/plain',
        ), rtrim(getenv('PS_TEST_RUNTIME'), '/') . '/dest.zip');
        $this->assertFalse($ret);
        $this->assertContains('Invalid file format', $controller->errors[0]);
    }

    public function testUploadReportsFailureWhenMoveFails()
    {
        $zip = $this->zipPath('movefail-source.zip', array('config/theme.yml' => 'parent: classic'));
        $controller = $this->createAdvancedController();
        $idTab = 302;
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
        $ret = $controller->processUploadFileChild(array(
            'error' => UPLOAD_ERR_OK,
            'tmp_name' => $zip,
            'name' => 'child.zip',
            'type' => 'application/zip',
        ), '/cannot/create/this/path/dest.zip');
        $this->assertFalse($ret);
        $this->assertContains('Failed to move uploaded file', $controller->errors[0]);
    }
}
