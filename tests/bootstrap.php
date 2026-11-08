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

define('_PS_VERSION_', '9.9.9.9');
define('_PS_ROOT_DIR_', dirname(__DIR__));
define('_DB_PREFIX_', 'ps_');
define('_PS_USE_SQL_SLAVE_', false);

$dbHost = getenv('STATSBC_DB_HOST');
if ($dbHost === false || $dbHost === '') {
    $dbHost = '127.0.0.1';
}
$dbPort = getenv('STATSBC_DB_PORT');
if ($dbPort === false || $dbPort === '') {
    $dbPort = '3306';
}
$dbUser = getenv('STATSBC_DB_USER');
if ($dbUser === false || $dbUser === '') {
    $dbUser = 'statsbc';
}
$dbPass = getenv('STATSBC_DB_PASS');
if ($dbPass === false) {
    $dbPass = 'statsbc';
}
$dbName = getenv('STATSBC_DB_NAME');
if ($dbName === false || $dbName === '') {
    $dbName = 'stats_best_categories';
}

require_once dirname(__FILE__) . '/stubs/PrestaShopStub.php';

PrestaShopStub::bootstrapDatabase(array(
    'host' => $dbHost,
    'port' => (int) $dbPort,
    'user' => $dbUser,
    'password' => $dbPass,
    'database' => $dbName,
));

require_once dirname(__DIR__) . '/statsbestcategories.php';
