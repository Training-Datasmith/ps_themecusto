<?php

class ChildRunner
{
    public static function runScript($scriptBody, $extraEnv = array())
    {
        $dir = getenv('PS_TEST_RUNTIME');
        if (!$dir) {
            $dir = sys_get_temp_dir();
        }
        $file = tempnam($dir, 'ps-child-');
        if ($file === false) {
            throw new RuntimeException('tempnam failed');
        }
        file_put_contents($file, $scriptBody);
        chmod($file, 0700);

        $php = getenv('PHP_BIN');
        if (!$php) {
            $php = 'php';
        }

        $env = array(
            'PS_TEST_RUNTIME' => $dir,
            'PS_TEST_DB_HOST' => getenv('PS_TEST_DB_HOST'),
            'PS_TEST_DB_PORT' => getenv('PS_TEST_DB_PORT'),
            'PS_TEST_DB_USER' => getenv('PS_TEST_DB_USER'),
            'PS_TEST_DB_PASS' => getenv('PS_TEST_DB_PASS'),
            'PS_TEST_DB_NAME' => getenv('PS_TEST_DB_NAME'),
            'SYMFONY_FINDER_PATH' => getenv('SYMFONY_FINDER_PATH'),
        );
        foreach ($extraEnv as $k => $v) {
            $env[$k] = $v;
        }

        $envExports = '';
        foreach ($env as $k => $v) {
            if ($v !== false && $v !== null && $v !== '') {
                $envExports .= $k . '=' . escapeshellarg($v) . ' ';
            }
        }

        $cmd = $envExports . escapeshellarg($php) . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 '
            . escapeshellarg($file) . ' 2>&1';
        $output = array();
        $code = 0;
        exec($cmd, $output, $code);
        @unlink($file);

        return array(
            'stdout' => implode("\n", $output),
            'stderr' => '',
            'exitCode' => $code,
            'combined' => implode("\n", $output),
        );
    }
}
