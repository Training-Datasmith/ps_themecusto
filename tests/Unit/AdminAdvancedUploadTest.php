<?php

class AdminAdvancedUploadTest extends PsThemeCustoTestCase
{
    private function sandboxEntries()
    {
        $sandbox = _PS_CACHE_DIR_ . 'sandbox/';
        if (!is_dir($sandbox)) {
            return array();
        }
        $entries = scandir($sandbox);
        sort($entries);

        return $entries;
    }

    private function runUploadAjax($setup)
    {
        return ChildRunner::runScript('<?php
require "' . dirname(__DIR__) . '/bootstrap.php";
' . $setup . '
$controller = TestableAdminPsThemeCustoAdvancedController::withModule(new ps_themecusto());
ob_start();
$controller->ajaxProcessUploadChildTheme();
$out = ob_get_clean();
fwrite(STDOUT, $out);
');
    }

    public function testUploadDeniedWithoutEditRightReturnsPermissionMessage()
    {
        $runtime = getenv('PS_TEST_RUNTIME');
        $zip = $runtime . '/deny.zip';
        file_put_contents($zip, 'x');
        $result = $this->runUploadAjax('
Profile::setProfileAccess(1, 400, array("edit" => "0"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "400", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$_FILES = array("file" => array(
    "name" => "child.zip",
    "type" => "application/zip",
    "tmp_name" => "' . $zip . '",
    "error" => UPLOAD_ERR_OK,
    "size" => 1,
));
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $payload = json_decode(trim($result['stdout']), true);
        $this->assertSame(0, $payload['state']);
        $this->assertSame('You do not have permission to edit this.', $payload['message']);
    }

    public function testUploadWithoutFileReturnsErrorJson()
    {
        $before = $this->sandboxEntries();
        $result = $this->runUploadAjax('
Profile::setProfileAccess(1, 401, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "401", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$_FILES = array();
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $payload = json_decode(trim($result['stdout']), true);
        $this->assertSame(0, $payload['state']);
        $this->assertSame('An error occured, please check your zip file', $payload['message']);
        $this->assertSame($before, $this->sandboxEntries());
    }

    public function testUploadRejectsNonArrayAttachmentPayload()
    {
        $runtime = getenv('PS_TEST_RUNTIME');
        $zip = $runtime . '/nonarray.zip';
        file_put_contents($zip, 'x');
        $result = $this->runUploadAjax('
Tools::$fileAttachmentNonArrayPayload = "broken-payload";
Profile::setProfileAccess(1, 403, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "403", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
$_FILES = array("file" => array(
    "name" => "child.zip",
    "type" => "application/zip",
    "tmp_name" => "' . $zip . '",
    "error" => UPLOAD_ERR_OK,
    "size" => 1,
));
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $payload = json_decode(trim($result['stdout']), true);
        $this->assertSame(0, $payload['state']);
        $this->assertSame('An error occured, please check your zip file', $payload['message']);
    }

    public function testUploadInstallsCleanChildThemeOverHttp()
    {
        $bootstrap = dirname(__DIR__) . '/bootstrap.php';
        $result = ChildRunner::runScript(<<<PHP
<?php
require "$bootstrap";
\$runtime = getenv('PS_TEST_RUNTIME');
\$zipPath = \$runtime . '/http-child.zip';
\$zip = new ZipArchive();
\$zip->open(\$zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
\$zip->addEmptyDir('uploaded_child');
\$zip->addEmptyDir('uploaded_child/config');
\$zip->addFromString('uploaded_child/config/theme.yml', "parent: classic\\n");
\$zip->addFromString('uploaded_child/preview.png', 'PNG');
\$zip->addFromString('uploaded_child/custom.css', 'body{}');
\$zip->addFromString('uploaded_child/index.php', Tools::getDefaultIndexContent());
\$zip->addEmptyDir('config');
\$zip->addFromString('config/theme.yml', "parent: classic\\n");
\$zip->close();
\$router = \$runtime . '/upload-router.php';
file_put_contents(\$router, '<?php
require "$bootstrap";
Profile::setProfileAccess(1, 402, array("edit" => "1"));
Db::getInstance()->insert("tab", array(
    "id_tab" => "402", "id_parent" => "0", "position" => "1", "module" => "ps_themecusto",
    "class_name" => "AdminPsThemeCustoAdvanced", "active" => "1"
), false, true, Db::INSERT, true);
Tab::resetStaticCache();
\$controller = TestableAdminPsThemeCustoAdvancedController::withModule(new ps_themecusto());
ob_start();
\$controller->ajaxProcessUploadChildTheme();
echo ob_get_clean();
');
\$sock = stream_socket_server('tcp://127.0.0.1:0', \$errno, \$errstr);
if (!\$sock) { fwrite(STDERR, 'bind failed'); exit(2); }
\$name = stream_socket_get_name(\$sock, false);
\$port = (int) substr(strrchr(\$name, ':'), 1);
fclose(\$sock);
\$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
\$proc = proc_open(
    'php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -S 127.0.0.1:' . \$port . ' ' . escapeshellarg(\$router),
    \$descriptors,
    \$pipes
);
usleep(500000);
\$ch = curl_init('http://127.0.0.1:' . \$port . '/');
\$post = array('file' => new CURLFile(\$zipPath, 'application/zip', 'uploaded_child.zip'));
curl_setopt(\$ch, CURLOPT_POST, true);
curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$post);
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
\$body = curl_exec(\$ch);
curl_close(\$ch);
if (is_resource(\$proc)) {
    proc_terminate(\$proc);
}
if (\$body === false || \$body === '') {
    fwrite(STDOUT, 'EMPTY_BODY');
} else {
    fwrite(STDOUT, \$body);
}
PHP
);
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $this->assertNotSame('EMPTY_BODY', trim($result['stdout']));
        $payload = json_decode(trim($result['stdout']), true);
        $this->assertTrue(is_array($payload), 'Upload response: ' . trim($result['stdout']));
        $this->assertSame(1, $payload['state'], isset($payload['message']) ? $payload['message'] : trim($result['stdout']));
        $this->assertSame('The child theme has been added successfully.', $payload['message']);
        $this->assertFileExists(_PS_ALL_THEMES_DIR_ . 'uploaded_child/config/theme.yml');
        $yml = file_get_contents(_PS_ALL_THEMES_DIR_ . 'uploaded_child/config/theme.yml');
        $this->assertContains('parent: classic', $yml);
    }
}
