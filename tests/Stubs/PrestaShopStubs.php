<?php

class PrestaShopException extends Exception
{
}

class PrestaShopDatabaseException extends Exception
{
}

function pSQL($string, $htmlOK = false)
{
    return Db::getInstance()->escape($string, $htmlOK);
}

function bqSQL($string)
{
    return str_replace('`', '\\`', pSQL($string));
}

class Shop
{
    public static $contextShopId = 1;

    public static function getContextListShopID()
    {
        return array(self::$contextShopId);
    }

    public static function addSqlRestriction($alias = '')
    {
        $prefix = $alias !== '' ? $alias . '.' : '';

        return ' AND ' . $prefix . 'id_shop = ' . (int) self::$contextShopId;
    }

    public static function getShops($active = true, $idShopGroup = null, $getAsListId = false)
    {
        return array(self::$contextShopId);
    }
}

class Link
{
    public function getAdminLink($controller, $withToken = true, $params = array(), $params2 = array())
    {
        $configure = '';
        if (isset($params2['configure'])) {
            $configure = '&configure=' . $params2['configure'];
        }

        return '/admin/index.php?controller=' . $controller . $configure;
    }

    public function getMediaLink($path)
    {
        return $path;
    }
}

class PsShop
{
    public $id = 1;
}

class Employee
{
    public $id_profile = 1;
}

class LanguageContext
{
    public $id = 1;
}

class ControllerStub
{
    public $css = array();
    public $js = array();

    public function addCSS($files)
    {
        if (!is_array($files)) {
            $files = array($files);
        }
        $this->css = array_merge($this->css, $files);
    }

    public function addJS($files)
    {
        if (!is_array($files)) {
            $files = array($files);
        }
        $this->js = array_merge($this->js, $files);
    }
}

class SmartyStub
{
    public $assigned = array();

    public function assign($key, $value = null)
    {
        if (is_array($key)) {
            $this->assigned = array_merge($this->assigned, $key);
        } else {
            $this->assigned[$key] = $value;
        }
    }

    public function fetch($tpl)
    {
        $module = isset($this->assigned['module']) ? $this->assigned['module'] : array();
        $parts = array();
        foreach ($module as $k => $v) {
            if (is_scalar($v)) {
                $parts[] = $k . '=' . $v;
            }
        }

        return 'TPL:' . $tpl . '|' . implode('|', $parts);
    }
}

class Context
{
    const DEVICE_COMPUTER = 1;
    const DEVICE_TABLET = 2;
    const DEVICE_MOBILE = 4;

    /** @var Context */
    private static $instance;

    /** @var PsShop */
    public $shop;

    /** @var Employee */
    public $employee;

    /** @var LanguageContext */
    public $language;

    /** @var SmartyStub */
    public $smarty;

    /** @var Link */
    public $link;

    /** @var ControllerStub */
    public $controller;

    /** @var CookieStub */
    public $cookie;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->shop = new PsShop();
            self::$instance->employee = new Employee();
            self::$instance->language = new LanguageContext();
            self::$instance->smarty = new SmartyStub();
            self::$instance->link = new Link();
            self::$instance->controller = new ControllerStub();
            self::$instance->cookie = new CookieStub();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }
}

class CookieStub
{
    public $profile = 1;
}

class Media
{
    public static $jsDef = array();

    public static function addJsDef($defs)
    {
        self::$jsDef = array_merge(self::$jsDef, $defs);
    }

    public static function reset()
    {
        self::$jsDef = array();
    }
}

class Validate
{
    public static function isLoadedObject($object)
    {
        return is_object($object) && isset($object->id) && (int) $object->id > 0;
    }

    public static function isModuleName($name)
    {
        return is_string($name) && preg_match('/^[a-z0-9_]+$/', $name);
    }

    public static function isFileName($name)
    {
        return is_string($name) && preg_match('/^[^<>;=#{}]+$/', $name);
    }

    public static function isUnsignedId($id)
    {
        return is_numeric($id) && (int) $id > 0 && (string) (int) $id === (string) $id;
    }
}

class Language
{
    public static function getLanguages($active = true)
    {
        $all = array(
            array('id_lang' => 1, 'iso_code' => 'fr', 'active' => 1),
            array('id_lang' => 2, 'iso_code' => 'en', 'active' => 1),
            array('id_lang' => 3, 'iso_code' => 'de', 'active' => 1),
            array('id_lang' => 4, 'iso_code' => 'zz', 'active' => 0),
        );
        if (!$active) {
            return $all;
        }
        $out = array();
        foreach ($all as $lang) {
            if ((int) $lang['active'] === 1) {
                $out[] = $lang;
            }
        }

        return $out;
    }
}

class Profile
{
    private static $access = array();

    public static function setProfileAccess($idProfile, $idTab, $access)
    {
        if (!isset(self::$access[$idProfile])) {
            self::$access[$idProfile] = array();
        }
        self::$access[$idProfile][$idTab] = $access;
    }

    public static function reset()
    {
        self::$access = array();
    }

    public static function getProfileAccess($idProfile, $idTab)
    {
        if (isset(self::$access[$idProfile][$idTab])) {
            return self::$access[$idProfile][$idTab];
        }

        return false;
    }
}

class Db
{
    const INSERT = 1;
    const INSERT_IGNORE = 2;
    const REPLACE = 3;
    const ON_DUPLICATE_KEY = 4;

    /** @var Db */
    private static $instance;

    /** @var mysqli */
    private $link;

    private $lastInsertId = 0;

    public static function getInstance($master = true)
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        if (self::$instance !== null && self::$instance->link) {
            self::$instance->link->close();
        }
        self::$instance = null;
    }

    private function connect()
    {
        if ($this->link) {
            return;
        }
        $host = getenv('PS_TEST_DB_HOST') ? getenv('PS_TEST_DB_HOST') : '127.0.0.1';
        $user = getenv('PS_TEST_DB_USER') ? getenv('PS_TEST_DB_USER') : 'ps_test';
        $pass = getenv('PS_TEST_DB_PASS') ? getenv('PS_TEST_DB_PASS') : 'ps_test';
        $name = getenv('PS_TEST_DB_NAME') ? getenv('PS_TEST_DB_NAME') : 'ps_test';
        $port = getenv('PS_TEST_DB_PORT') ? (int) getenv('PS_TEST_DB_PORT') : 3306;
        $this->link = new mysqli($host, $user, $pass, $name, $port);
        if ($this->link->connect_error) {
            throw new PrestaShopDatabaseException('connect: ' . $this->link->connect_error);
        }
    }

    public function escape($string, $html_ok = false, $bq_sql = false)
    {
        $this->connect();
        if (is_numeric($string)) {
            return $string;
        }
        $escaped = $this->link->real_escape_string($string);
        if (!$html_ok) {
            $escaped = strip_tags(Tools::nl2br($escaped));
        }
        if ($bq_sql) {
            $escaped = str_replace('`', '\\`', $escaped);
        }

        return $escaped;
    }

    public function query($sql)
    {
        $this->connect();
        $result = $this->link->query($sql);
        if ($result === false) {
            throw new PrestaShopDatabaseException($this->link->error . ' SQL=' . $sql);
        }

        return $result;
    }

    public function execute($sql)
    {
        $this->query($sql);

        return true;
    }

    public function executeS($sql, $array = true, $use_cache = true)
    {
        $result = $this->query($sql);
        if (!$array) {
            return $result;
        }
        $rows = array();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getRow($sql, $use_cache = true)
    {
        $sql = rtrim($sql, " \t\n\r\0\x0B;") . ' LIMIT 1';
        $rows = $this->executeS($sql);
        if (!$rows) {
            return false;
        }

        return $rows[0];
    }

    public function getValue($sql, $use_cache = true)
    {
        $row = $this->getRow($sql, $use_cache);
        if ($row === false) {
            return false;
        }

        return array_shift($row);
    }

    public function insert($table, $data, $null_values = false, $use_cache = true, $type = Db::INSERT, $add_prefix = true)
    {
        if ($add_prefix) {
            $table = _DB_PREFIX_ . $table;
        }
        $keys = array();
        $values = array();
        foreach ($data as $key => $value) {
            $keys[] = '`' . bqSQL($key) . '`';
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $value . "'";
            }
        }
        $sql = 'INSERT INTO `' . $table . '` (' . implode(', ', $keys) . ') VALUES (' . implode(', ', $values) . ')';
        $this->query($sql);
        $this->lastInsertId = (int) $this->link->insert_id;

        return true;
    }

    public function Insert_ID()
    {
        return $this->lastInsertId;
    }

    public function truncateTestTables()
    {
        $this->connect();
        $tables = array('tab_lang', 'tab', 'module_shop', 'module');
        foreach ($tables as $table) {
            $this->query('DELETE FROM `' . _DB_PREFIX_ . $table . '`');
        }
        $this->query('ALTER TABLE `' . _DB_PREFIX_ . 'module` AUTO_INCREMENT = 1');
        $this->query('ALTER TABLE `' . _DB_PREFIX_ . 'tab` AUTO_INCREMENT = 1');
    }

    public function ensureSchema()
    {
        $this->connect();
        $this->query('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'module` (
            `id_module` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(128) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_module`),
            UNIQUE KEY `name` (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $this->query('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'module_shop` (
            `id_module` INT UNSIGNED NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `enable_device` TINYINT UNSIGNED NOT NULL DEFAULT 7,
            PRIMARY KEY (`id_module`, `id_shop`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $this->query('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'tab` (
            `id_tab` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_parent` INT NOT NULL DEFAULT 0,
            `position` INT NOT NULL DEFAULT 0,
            `module` VARCHAR(64) NULL,
            `class_name` VARCHAR(64) NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id_tab`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        $this->query('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'tab_lang` (
            `id_tab` INT UNSIGNED NOT NULL,
            `id_lang` INT UNSIGNED NOT NULL,
            `name` VARCHAR(128) NOT NULL,
            PRIMARY KEY (`id_tab`, `id_lang`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    }
}

class ObjectModel
{
    public $id;
    public $force_id = false;
    public $def = array();

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = (int) $id;
            if ($this->id > 0) {
                $this->hydrate();
            }
        }
    }

    protected function hydrate()
    {
    }

    public function add($autoDate = true, $nullValues = false)
    {
        $table = $this->getTable();
        $data = $this->formatFields();
        if (!$this->force_id) {
            unset($data['id_tab']);
        }
        Db::getInstance()->insert($table, $data, false, true, Db::INSERT, true);
        if (!$this->force_id || (int) $this->id === 0) {
            $this->id = Db::getInstance()->Insert_ID();
        }
        Tab::resetStaticCache();

        return true;
    }

    public function update($nullValues = false)
    {
        $table = $this->getTable();
        $data = $this->formatFields();
        $idField = $this->getPrimary();
        unset($data[$idField]);
        $sets = array();
        foreach ($data as $k => $v) {
            if ($v === null) {
                $sets[] = '`' . bqSQL($k) . '` = NULL';
            } else {
                $sets[] = '`' . bqSQL($k) . '` = \'' . $v . '\'';
            }
        }
        Db::getInstance()->execute('UPDATE `' . _DB_PREFIX_ . $table . '` SET ' . implode(', ', $sets)
            . ' WHERE `' . bqSQL($idField) . '` = ' . (int) $this->id);
        Tab::resetStaticCache();

        return true;
    }

    public function save($nullValues = false, $autoDate = true)
    {
        if ((int) $this->id > 0) {
            return $this->update($nullValues);
        }

        return $this->add($autoDate, $nullValues);
    }

    public function delete()
    {
        $table = $this->getTable();
        $idField = $this->getPrimary();
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '` WHERE `' . bqSQL($idField) . '` = ' . (int) $this->id);
        Tab::resetStaticCache();

        return true;
    }

    protected function getTable()
    {
        return 'tab';
    }

    protected function getPrimary()
    {
        return 'id_tab';
    }

    protected function formatFields()
    {
        return array();
    }
}

class Tab extends ObjectModel
{
    public $name = array();
    public $class_name;
    public $module;
    public $id_parent;
    public $position;
    public $active = 1;

    protected static $_getIdFromClassName = null;

    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id);
        if ($idLang !== null) {
            // no-op for stub
        }
    }

    protected function hydrate()
    {
        $row = Db::getInstance()->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'tab` WHERE id_tab = ' . (int) $this->id);
        if ($row) {
            $this->id_parent = (int) $row['id_parent'];
            $this->position = (int) $row['position'];
            $this->module = $row['module'];
            $this->class_name = $row['class_name'];
            $this->active = (int) $row['active'];
        }
        $langs = Db::getInstance()->executeS('SELECT id_lang, name FROM `' . _DB_PREFIX_ . 'tab_lang` WHERE id_tab = ' . (int) $this->id);
        $this->name = array();
        foreach ($langs as $lang) {
            $this->name[(int) $lang['id_lang']] = $lang['name'];
        }
    }

    protected function getTable()
    {
        return 'tab';
    }

    protected function getPrimary()
    {
        return 'id_tab';
    }

    protected function formatFields()
    {
        return array(
            'id_tab' => (int) $this->id,
            'id_parent' => (int) $this->id_parent,
            'position' => (int) $this->position,
            'module' => $this->module,
            'class_name' => $this->class_name,
            'active' => (int) $this->active,
        );
    }

    public function add($autoDate = true, $nullValues = false)
    {
        $this->position = self::getNewLastPosition($this->id_parent);
        if ($this->module) {
            $this->module = Tools::strtolower($this->module);
        }
        $result = parent::add($autoDate, $nullValues);
        foreach ($this->name as $idLang => $name) {
            Db::getInstance()->insert('tab_lang', array(
                'id_tab' => (int) $this->id,
                'id_lang' => (int) $idLang,
                'name' => $name,
            ), false, true, Db::INSERT, true);
        }

        return $result;
    }

    public static function resetStaticCache()
    {
        self::$_getIdFromClassName = null;
    }

    public static function getNewLastPosition($idParent)
    {
        $val = Db::getInstance()->getValue(
            'SELECT IFNULL(MAX(position), 0) + 1 FROM `' . _DB_PREFIX_ . 'tab` WHERE id_parent = ' . (int) $idParent
        );

        return (int) $val;
    }

    public static function getIdFromClassName($className)
    {
        $className = strtolower($className);
        if (self::$_getIdFromClassName === null) {
            self::$_getIdFromClassName = array();
            $rows = Db::getInstance()->executeS('SELECT id_tab, class_name FROM `' . _DB_PREFIX_ . 'tab`');
            foreach ($rows as $row) {
                self::$_getIdFromClassName[strtolower($row['class_name'])] = (int) $row['id_tab'];
            }
        }
        if (isset(self::$_getIdFromClassName[$className])) {
            return self::$_getIdFromClassName[$className];
        }

        return false;
    }

    public static function getInstanceFromClassName($className, $idLang = null)
    {
        $id = self::getIdFromClassName($className);
        if (!$id) {
            return new Tab(0);
        }

        return new Tab($id, $idLang);
    }

    public function update($nullValues = false)
    {
        $current = new Tab($this->id);
        if ($current->id_parent != $this->id_parent) {
            $this->position = self::getNewLastPosition($this->id_parent);
        }

        return parent::update($nullValues);
    }
}

class Tools
{
    public static $secureMode = false;

    public static function usingSecureMode()
    {
        return (bool) self::$secureMode;
    }

    public static function getShopDomain($http = false, $entities = false)
    {
        $host = 'shop.test';

        return $http ? 'http://' . $host : $host;
    }

    public static function getShopDomainSsl($http = false, $entities = false)
    {
        $host = 'shop.test';

        return $http ? 'https://' . $host : $host;
    }

    public static function nl2br($str)
    {
        return str_replace(array("\r\n", "\r", "\n", PHP_EOL), ' ', $str);
    }

    public static function strtolower($str)
    {
        return strtolower($str);
    }

    public static function getValue($key, $default = false)
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }
        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function fileAttachment($input = 'fileUpload', $return_content = true)
    {
        if (!isset($_FILES[$input]['name']) || empty($_FILES[$input]['name']) || empty($_FILES[$input]['tmp_name'])) {
            return null;
        }
        $attachment = array();
        $attachment['rename'] = uniqid() . self::strtolower(substr($_FILES[$input]['name'], -5));
        if ($return_content) {
            $attachment['content'] = file_get_contents($_FILES[$input]['tmp_name']);
        }
        $attachment['tmp_name'] = $_FILES[$input]['tmp_name'];
        $attachment['name'] = $_FILES[$input]['name'];
        $attachment['mime'] = $_FILES[$input]['type'];
        $attachment['error'] = $_FILES[$input]['error'];
        $attachment['size'] = $_FILES[$input]['size'];

        return $attachment;
    }

    public static function ZipExtract($from_file, $to_dir)
    {
        if (!file_exists($to_dir)) {
            mkdir($to_dir, 0700, true);
        }
        $zip = new ZipArchive();
        if ($zip->open($from_file) === true && $zip->extractTo($to_dir) && $zip->close()) {
            return true;
        }

        return false;
    }

    public static function getDefaultIndexContent()
    {
        return '<?php /* default-index-sentinel */ exit;';
    }

    public static function file_exists_no_cache($filename)
    {
        return file_exists($filename);
    }

    public static function file_exists_cache($filename)
    {
        return file_exists($filename);
    }

}

class Module
{
    public $id;
    public $name;
    public $active = 1;
    public $displayName;
    public $description;
    public $version = '1.0.0';
    public $table = 'module';
    public $controller_name;
    public $_errors = array();
    public $_path;
    public $local_path;
    public $context;

    protected static $_INSTANCE = array();
    protected static $cachedModuleNames = null;

    public function __construct()
    {
        if (!defined('PS_TEST_NULL_CONTEXT')) {
            $this->context = Context::getContext();
        }
        if (!empty($this->name)) {
            $this->_path = '/modules/' . $this->name . '/';
            $this->local_path = _PS_MODULE_DIR_;
        }
    }

    public function trans($id, $parameters = array(), $domain = null, $locale = null)
    {
        return $id;
    }

    public function isEnabled($module_name = null)
    {
        $name = $module_name !== null ? $module_name : $this->name;
        $id = self::getModuleIdByName($name);
        if (!$id) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT id_module FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $id
            . ' AND id_shop = ' . (int) Context::getContext()->shop->id
        );
    }

    public static function getModuleIdByName($name)
    {
        return (int) Db::getInstance()->getValue(
            'SELECT id_module FROM `' . _DB_PREFIX_ . 'module` WHERE name = "' . pSQL($name) . '"'
        );
    }

    public static function isInstalled($module_name)
    {
        return (bool) self::getModuleIdByName($module_name);
    }

    protected static function resolveModulePhpPath($module_name)
    {
        $dirs = array(_PS_MODULE_DIR_);
        $fakeRoot = getenv('PS_TEST_FAKE_MODULES');
        if ($fakeRoot !== false && $fakeRoot !== '') {
            array_unshift($dirs, rtrim($fakeRoot, '/') . '/');
        }
        foreach ($dirs as $dir) {
            $path = $dir . $module_name . '/' . $module_name . '.php';
            if (file_exists($path)) {
                return $path;
            }
        }

        return false;
    }

    public static function getInstanceByName($module_name)
    {
        if (!Validate::isModuleName($module_name)) {
            return false;
        }
        if (isset(self::$_INSTANCE[$module_name])) {
            return self::$_INSTANCE[$module_name];
        }
        $path = self::resolveModulePhpPath($module_name);
        if ($path === false) {
            return false;
        }
        require_once $path;
        if (!class_exists($module_name)) {
            return false;
        }
        $instance = new $module_name();
        self::$_INSTANCE[$module_name] = $instance;

        return $instance;
    }

    public static function resetInstances()
    {
        self::$_INSTANCE = array();
        self::$cachedModuleNames = null;
    }

    public static function getModulesDirOnDisk()
    {
        $list = array();
        $dirs = array(_PS_MODULE_DIR_);
        $fakeRoot = getenv('PS_TEST_FAKE_MODULES');
        if ($fakeRoot !== false && $fakeRoot !== '') {
            array_unshift($dirs, rtrim($fakeRoot, '/') . '/');
        }
        foreach ($dirs as $base) {
            if (!is_dir($base)) {
                continue;
            }
            $modules = scandir($base);
            foreach ($modules as $name) {
                if ($name === '.' || $name === '..' || in_array($name, $list, true)) {
                    continue;
                }
                $path = $base . $name . '/' . $name . '.php';
                if (is_file($path) && Validate::isModuleName($name)) {
                    $list[] = $name;
                }
            }
        }

        return $list;
    }

    public function install()
    {
        if (self::getModuleIdByName($this->name)) {
            return false;
        }
        Db::getInstance()->insert('module', array(
            'name' => $this->name,
            'active' => '1',
        ));
        $this->id = Db::getInstance()->Insert_ID();
        $this->enable();

        return true;
    }

    public function uninstall()
    {
        if (!Validate::isUnsignedId($this->id)) {
            return false;
        }
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $this->id);
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'module` WHERE id_module = ' . (int) $this->id);
        $this->id = null;

        return true;
    }

    public function enable($force_all = false)
    {
        if (!$this->id) {
            return false;
        }
        $shopId = (int) Context::getContext()->shop->id;
        $exists = Db::getInstance()->getValue(
            'SELECT id_module FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $this->id
            . ' AND id_shop = ' . $shopId
        );
        if (!$exists) {
            Db::getInstance()->insert('module_shop', array(
                'id_module' => (int) $this->id,
                'id_shop' => $shopId,
                'enable_device' => '7',
            ));
        }

        return true;
    }

    public function disable($force_all = false)
    {
        if (!$this->id) {
            return false;
        }
        $shops = Shop::getContextListShopID();
        if (empty($shops)) {
            return true;
        }
        Db::getInstance()->execute(
            'DELETE FROM `' . _DB_PREFIX_ . 'module_shop` WHERE id_module = ' . (int) $this->id
            . ' AND id_shop IN (' . implode(',', array_map('intval', $shops)) . ')'
        );

        return true;
    }

    public function enableDevice($device)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'module_shop`
            SET enable_device = enable_device + ' . (int) $device . '
            WHERE (enable_device &~ ' . (int) $device . ' OR enable_device = 0) AND id_module = ' . (int) $this->id
            . Shop::addSqlRestriction()
        );

        return true;
    }

    public function disableDevice($device)
    {
        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'module_shop`
            SET enable_device = enable_device - ' . (int) $device . '
            WHERE enable_device & ' . (int) $device . ' AND id_module = ' . (int) $this->id
            . Shop::addSqlRestriction()
        );

        return true;
    }
}

class ModuleAdminController
{
    public $module;
    public $context;
    public $errors = array();
    protected $ajaxRendered = '';

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function trans($id, $parameters = array(), $domain = null, $locale = null)
    {
        return $id;
    }

    public function ajaxRender($content)
    {
        echo $content;
        $this->ajaxRendered = $content;
    }

    public function ajaxDie($content)
    {
        if (version_compare(_PS_VERSION_, '1.7.5', '>=')) {
            @trigger_error('ajaxDie is deprecated', E_USER_DEPRECATED);
        }
        echo $content;
        exit;
    }
}
