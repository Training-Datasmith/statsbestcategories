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

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '0');
ini_set('log_errors', '0');

define('_PS_VERSION_', '9.9.9.9');
define('_PS_ROOT_DIR_', dirname(dirname(__DIR__)));
define('_DB_PREFIX_', 'ps_');
define('_PS_USE_SQL_SLAVE_', false);

$dbHost = getenv('STATSBC_DB_HOST');
$dbUser = getenv('STATSBC_DB_USER');
$dbPass = getenv('STATSBC_DB_PASS');
$dbName = getenv('STATSBC_DB_NAME');
$dbPort = getenv('STATSBC_DB_PORT');
if ($dbPort === false || $dbPort === '') {
    $dbPort = 3306;
}

$config = array(
    'host' => $dbHost,
    'port' => (int) $dbPort,
    'user' => $dbUser,
    'password' => $dbPass,
    'database' => $dbName,
);

require_once dirname(__FILE__) . '/../stubs/PrestaShopStub.php';

Db::configureServers(array($config, $config));

$probeSlave = Db::getInstance((bool) _PS_USE_SQL_SLAVE_);
$probeMaster = Db::getInstance();
if ($probeSlave->getLinkIdForTests() === $probeMaster->getLinkIdForTests()) {
    fwrite(STDERR, "Slave and master links must differ when two servers are configured\n");
    exit(3);
}

require_once dirname(__DIR__) . '/../statsbestcategories.php';

$module = new statsbestcategories();
$module->setLang(1);
$module->getData();

echo json_encode(Db::$statementLog);
