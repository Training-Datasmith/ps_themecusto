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
$_FILES = array();
');
        $this->assertNoPhpDiagnostics($result['combined'], '');
        $payload = json_decode(trim($result['stdout']), true);
        $this->assertSame(0, $payload['state']);
        $this->assertSame('An error occured, please check your zip file', $payload['message']);
        $this->assertSame($before, $this->sandboxEntries());
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
\$body = false;
\$serverErr = '';
\$port = 0;
for (\$bindTry = 0; \$bindTry < 5; ++\$bindTry) {
    \$sock = stream_socket_server('tcp://127.0.0.1:0', \$errno, \$errstr);
    if (!\$sock) {
        continue;
    }
    \$name = stream_socket_get_name(\$sock, false);
    \$port = (int) substr(strrchr(\$name, ':'), 1);
    fclose(\$sock);
    \$descriptors = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    \$proc = proc_open(
        'php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -S 127.0.0.1:' . \$port . ' ' . escapeshellarg(\$router),
        \$descriptors,
        \$pipes
    );
    if (!is_resource(\$proc)) {
        continue;
    }
    stream_set_blocking(\$pipes[2], false);
    \$deadline = time() + 5;
    while (time() < \$deadline) {
        \$read = array(\$pipes[2]);
        \$write = null;
        \$except = null;
        if (stream_select(\$read, \$write, \$except, 1) > 0) {
            \$chunk = stream_get_contents(\$pipes[2]);
            if (\$chunk !== false && \$chunk !== '') {
                \$serverErr .= \$chunk;
            }
        }
        \$ch = curl_init('http://127.0.0.1:' . \$port . '/');
        \$post = array('file' => new CURLFile(\$zipPath, 'application/zip', 'uploaded_child.zip'));
        curl_setopt(\$ch, CURLOPT_POST, true);
        curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$post);
        curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(\$ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt(\$ch, CURLOPT_TIMEOUT, 2);
        \$tryBody = curl_exec(\$ch);
        curl_close(\$ch);
        if (\$tryBody !== false && \$tryBody !== '') {
            \$body = \$tryBody;
            break 2;
        }
    }
    if (is_resource(\$proc)) {
        proc_terminate(\$proc);
        proc_close(\$proc);
    }
}
if (\$body === false || \$body === '') {
    fwrite(STDOUT, 'EMPTY_BODY');
} else {
    fwrite(STDOUT, \$body);
}
fwrite(STDOUT, "\n__PHP_SERVER_ERR__\n" . \$serverErr);
PHP
);
        $this->assertNoPhpDiagnostics($result['combined'], '');
        if (preg_match('/__PHP_SERVER_ERR__\s*(.*)$/s', $result['stdout'], $m)) {
            $this->assertNoPhpDiagnostics($m[1], '');
        }
        $responseBody = preg_replace('/\s*__PHP_SERVER_ERR__.*$/s', '', $result['stdout']);
        $jsonLine = trim(strtok($responseBody, "\n"));
        $this->assertNotSame('EMPTY_BODY', $jsonLine);
        $payload = json_decode($jsonLine, true);
        $this->assertTrue(is_array($payload), 'Upload response: ' . $jsonLine);
        $this->assertSame(1, $payload['state'], isset($payload['message']) ? $payload['message'] : $jsonLine);
        $this->assertSame('The child theme has been added successfully.', $payload['message']);
        $this->assertFileExists(_PS_ALL_THEMES_DIR_ . 'uploaded_child/config/theme.yml');
        $yml = file_get_contents(_PS_ALL_THEMES_DIR_ . 'uploaded_child/config/theme.yml');
        $this->assertContains('parent: classic', $yml);
    }
}
