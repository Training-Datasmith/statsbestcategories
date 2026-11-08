<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!function_exists('bqSQL')) {
    function bqSQL($string)
    {
        return str_replace('`', '\`', $string);
    }
}

if (!function_exists('pSQL')) {
    function pSQL($string)
    {
        return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $string);
    }
}

class Validate
{
    public static function isName($name)
    {
        return preg_match('/^[^0-9!<>,;?=+()@#"°{}_$%:¤|]*$/u', $name);
    }

    public static function isSortDirection($value)
    {
        return $value !== null && ($value === 'ASC' || $value === 'DESC');
    }

    public static function isUnsignedInt($value)
    {
        return (is_numeric($value) || is_string($value))
            && (string) (int) $value === (string) $value
            && $value < 4294967296
            && $value >= 0;
    }

}

class Tools
{
    public static function getValue($key, $default_value = false)
    {
        if (empty($key) || !is_string($key)) {
            return false;
        }

        if (isset($_POST[$key]) || isset($_GET[$key])) {
            $value = isset($_POST[$key]) ? $_POST[$key] : $_GET[$key];
        } else {
            $value = null;
        }

        if (!isset($value)) {
            $value = $default_value;
        }

        if (is_string($value)) {
            return urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($value)));
        }

        return $value;
    }

    public static function getIsset($key)
    {
        if (!is_string($key)) {
            return false;
        }

        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }

        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }
}

class Configuration
{
    public static function get($key)
    {
        if ($key === 'PS_CURRENCY_DEFAULT') {
            return 1;
        }

        return null;
    }
}

class Currency
{
    public $iso_code;

    public function __construct($id)
    {
        $this->iso_code = 'XTS';
    }
}

class PrestaShopLocale
{
    public function formatPrice($amount, $iso)
    {
        return sprintf('%.2f %s', (float) $amount, $iso);
    }
}

class Shop
{
    const CONTEXT_SHOP = 1;
    const CONTEXT_GROUP = 2;
    const CONTEXT_ALL = 4;
    const SHARE_ORDER = 'share_order';

    private static $context = 4;
    private static $contextShopIds = array(1);
    private static $contextShopId = 1;

    public static function resetForTests()
    {
        self::$context = self::CONTEXT_ALL;
        self::$contextShopIds = array(1);
        self::$contextShopId = 1;
    }

    public static function setContextForTests($context, $shopIds)
    {
        self::$context = (int) $context;
        self::$contextShopIds = $shopIds;
        self::$contextShopId = (int) $shopIds[0];
    }

    public static function getContextShopID()
    {
        return self::$contextShopId;
    }

    public static function getSharedShops($shop_id, $type)
    {
        return array((int) $shop_id);
    }

    public static function getContext()
    {
        return self::$context;
    }

    public static function getContextListShopID($share = false)
    {
        if (self::$context === self::CONTEXT_SHOP) {
            if ($share) {
                return self::getSharedShops(self::getContextShopID(), $share);
            }

            return array(self::getContextShopID());
        }

        if (self::$context === self::CONTEXT_GROUP) {
            return self::$contextShopIds;
        }

        return array(1);
    }

    public static function addSqlRestriction($share = false, $alias = null)
    {
        if ($alias) {
            $alias .= '.';
        }

        $ids = self::getContextListShopID($share);
        if (!$ids) {
            $ids = array(1);
        }

        return ' AND ' . $alias . 'id_shop IN (' . implode(', ', array_map('intval', $ids)) . ') ';
    }

    public static function addSqlRestrictionOnLang($alias = null)
    {
        $shopId = 1;

        return ' AND ' . ($alias ? $alias . '.' : '') . 'id_shop = ' . (int) $shopId . ' ';
    }
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;
    public $context;

    public static $parentInstallResult = true;
    public static $registerHookResult = true;
    public static $unregisterHookResult = true;
    public static $recordedRegisterHooks = array();
    public static $recordedUnregisterHooks = array();

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public static function resetRecordingForTests()
    {
        self::$parentInstallResult = true;
        self::$registerHookResult = true;
        self::$unregisterHookResult = true;
        self::$recordedRegisterHooks = array();
        self::$recordedUnregisterHooks = array();
    }

    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return $id;
    }

    public function install()
    {
        return self::$parentInstallResult;
    }

    public function registerHook($hookName)
    {
        self::$recordedRegisterHooks[] = $hookName;

        return self::$registerHookResult;
    }

    public function unregisterHook($hookName)
    {
        self::$recordedUnregisterHooks[] = $hookName;

        return self::$unregisterHookResult;
    }
}

class ModuleGrid extends Module
{
    protected $_values = array();
    protected $_totalCount = 0;
    protected $_sort = null;
    protected $_direction = null;
    protected $_start;
    protected $_limit;
    protected $_id_lang = 1;

    public $engineArgs;
    public $csvExportCalled = false;
    public $csvExportArgs;

    public function engine($params)
    {
        $this->engineArgs = $params;

        return '[[GRID-ENGINE]]';
    }

    protected function csvExport($datas)
    {
        $this->csvExportCalled = true;
        $this->csvExportArgs = $datas;
    }

    public function getDate()
    {
        return "'2016-01-01 00:00:00' AND '2016-01-31 23:59:59'";
    }

    public function setLang($id_lang)
    {
        $this->_id_lang = (int) $id_lang;
    }

    public function getLang()
    {
        return $this->_id_lang;
    }
}

class Context
{
    private static $instance;

    public $shop;

    public static function getContext()
    {
        if (!self::$instance) {
            self::$instance = new Context();
            self::$instance->shop = new stdClass();
            self::$instance->shop->id = 1;
        }

        return self::$instance;
    }

    public function getCurrentLocale()
    {
        return new PrestaShopLocale();
    }
}

class Db
{
    /** @var Db[] */
    private static $instances = array();

    /** @var array[] */
    private static $serverConfigs = array();

    /** @var string[] */
    public static $statementLog = array();

    /** @var mysqli */
    private $link;

    private $lastNumRows;

    private function __construct($config)
    {
        $port = isset($config['port']) ? (int) $config['port'] : 3306;
        $this->link = mysqli_connect(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database'],
            $port
        );
        if (!$this->link) {
            throw new RuntimeException('mysqli_connect failed: ' . mysqli_connect_error());
        }
        mysqli_set_charset($this->link, 'utf8');
        if (!mysqli_query($this->link, "SET SESSION sql_mode=''")) {
            throw new RuntimeException('SET sql_mode failed: ' . mysqli_error($this->link));
        }
    }

    public static function bootstrapDatabase(array $defaultConfig)
    {
        self::$serverConfigs = array($defaultConfig);
        self::$instances = array();
        self::$statementLog = array();
    }

    public static function configureServers(array $servers)
    {
        self::$serverConfigs = $servers;
        self::$instances = array();
        self::$statementLog = array();
    }

    public static function getInstance($master = true)
    {
        $serverCount = count(self::$serverConfigs);
        if ($serverCount === 0) {
            throw new RuntimeException('Db servers not configured');
        }

        $key = 0;
        if (!$master && $serverCount > 1) {
            $key = 1;
        }

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new Db(self::$serverConfigs[$key]);
        }

        return self::$instances[$key];
    }

    public static function setInstanceForTesting($testDb)
    {
        self::$instances[0] = $testDb;
    }

    public static function deleteTestingInstance()
    {
        self::$instances = array();
    }

    public static function resetStatementLogForTests()
    {
        self::$statementLog = array();
    }

    public function getLinkIdForTests()
    {
        return spl_object_hash($this->link);
    }

    private function logStatement($sql)
    {
        self::$statementLog[] = $sql;
    }

    public function query($sql)
    {
        $this->logStatement($sql);
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            throw new RuntimeException('SQL error: ' . mysqli_error($this->link) . ' in ' . $sql);
        }
        if (is_object($result)) {
            $this->lastNumRows = mysqli_num_rows($result);
            mysqli_free_result($result);
        } else {
            $this->lastNumRows = null;
        }

        return $result;
    }

    public function execute($sql, $use_cache = true)
    {
        $this->logStatement($sql);
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            throw new RuntimeException('SQL error: ' . mysqli_error($this->link) . ' in ' . $sql);
        }
        $this->lastNumRows = null;

        return true;
    }

    public function executeS($sql, $array = true, $use_cache = true)
    {
        $this->logStatement($sql);
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            throw new RuntimeException('SQL error: ' . mysqli_error($this->link) . ' in ' . $sql);
        }

        $rows = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
        $this->lastNumRows = count($rows);

        return $rows;
    }

    public function getValue($sql, $use_cache = true)
    {
        $this->logStatement($sql);
        $result = mysqli_query($this->link, $sql);
        if ($result === false) {
            throw new RuntimeException('SQL error: ' . mysqli_error($this->link) . ' in ' . $sql);
        }
        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        $this->lastNumRows = $row ? 1 : 0;
        if (!$row) {
            return false;
        }

        return array_shift($row);
    }

    public function NumRows()
    {
        return $this->lastNumRows;
    }
}

class PrestaShopStub
{
    public static function bootstrapDatabase(array $config)
    {
        Db::bootstrapDatabase($config);
    }
}
