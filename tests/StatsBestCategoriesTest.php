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

require_once dirname(__FILE__) . '/support/DatabaseFixture.php';

class TestableStatsBestCategories extends statsbestcategories
{
    public function valuesForTest()
    {
        return $this->_values;
    }

    public function totalCountForTest()
    {
        return $this->_totalCount;
    }

    public function setSortForTest($sort)
    {
        $this->_sort = $sort;
    }

    public function setDirectionForTest($direction)
    {
        $this->_direction = $direction;
    }

    public function setStartForTest($start)
    {
        $this->_start = $start;
    }

    public function setLimitForTest($limit)
    {
        $this->_limit = $limit;
    }
}

class StatsBestCategoriesTest extends PHPUnit_Framework_TestCase
{
    private static $phpBinary;

    protected function setUp()
    {
        $_GET = array();
        $_POST = array();
        $_SERVER['REQUEST_URI'] = '/stats';
        Module::resetRecordingForTests();
        Shop::resetForTests();
        Db::resetStatementLogForTests();
        DatabaseFixture::resetAll();
    }

    public static function setUpBeforeClass()
    {
        self::$phpBinary = getenv('STATSBC_PHP_BIN');
        if (!self::$phpBinary) {
            self::$phpBinary = 'php';
        }
    }

    private function newModule()
    {
        return new TestableStatsBestCategories();
    }

    private function rowByCategoryId($values, $id)
    {
        foreach ($values as $row) {
            if ((int) $row['id_category'] === (int) $id) {
                return $row;
            }
        }

        return null;
    }

    private function categoryIds($values)
    {
        $ids = array();
        foreach ($values as $row) {
            $ids[] = (int) $row['id_category'];
        }
        sort($ids);

        return $ids;
    }

    private function loggedSqlJoined()
    {
        return implode("\n", Db::$statementLog);
    }

    private function loadConfigXml()
    {
        $xml = simplexml_load_file(dirname(__DIR__) . '/config.xml');
        $this->assertInstanceOf('SimpleXMLElement', $xml);

        return $xml;
    }

  public function testConstructorPublishesIdentityCompliancyAndCopy()
    {
        $module = $this->newModule();
        $this->assertSame('statsbestcategories', $module->name);
        $this->assertSame('2.1.0', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
        $this->assertSame('1.7.6.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
        $this->assertSame('Best categories', $module->displayName);
        $this->assertSame(
            'Enrich your stats, add a list of the best selling categories to the dashboard.',
            $module->description
        );
    }

    public function testConfigXmlNameAndVersionMatchTheClass()
    {
        $module = $this->newModule();
        $xml = $this->loadConfigXml();
        $this->assertSame((string) $module->name, (string) $xml->name);
        $this->assertSame((string) $module->version, (string) $xml->version);
    }

    public function testInstallRegistersDisplayAdminStatsModules()
    {
        Module::$parentInstallResult = true;
        Module::$registerHookResult = true;
        $module = $this->newModule();
        $this->assertTrue($module->install());
        $this->assertSame(array('displayAdminStatsModules'), Module::$recordedRegisterHooks);
    }

    public function testInstallDoesNotRegisterHookWhenParentInstallFails()
    {
        Module::$parentInstallResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame(array(), Module::$recordedRegisterHooks);
    }

    public function testInstallFailsWhenHookRegistrationFails()
    {
        Module::$parentInstallResult = true;
        Module::$registerHookResult = false;
        $module = $this->newModule();
        $this->assertFalse($module->install());
    }

    public function testHookRendersGridLinkAndUncheckedLeafBox()
    {
        $module = $this->newModule();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('[[GRID-ENGINE]]', $html);
        $this->assertContains('Best categories', $html);
        $this->assertContains('icon-sitemap', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertContains('export=1', $html);
        $this->assertContains('onlyChildren', $html);
        $this->assertContains('calendar_form', $html);
        $this->assertNotContains('checked="checked"', $html);

        $params = $module->engineArgs;
        $this->assertSame('id_category', $params['id']);
        $this->assertSame('totalPriceSold', $params['defaultSortColumn']);
        $this->assertSame('DESC', $params['defaultSortDirection']);
        $this->assertSame('Empty recordset returned', $params['emptyMessage']);
        $this->assertSame('Displaying %1$s of %2$s', $params['pagingMessage']);
        $this->assertSame(0, $params['customParams']['onlyChildren']);
        $this->assertCount(5, $params['columns']);
    }

    public function testHookChecksLeafBoxWhenOnlyChildrenIsOne()
    {
        $_GET['onlyChildren'] = '1';
        $module = $this->newModule();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('checked="checked"', $html);
        $this->assertSame(1, $module->engineArgs['customParams']['onlyChildren']);
    }

    public function testHookEscapesTheExportHref()
    {
        $_SERVER['REQUEST_URI'] = '/stats?token=a"b<c>';
        $module = $this->newModule();
        $html = $module->hookDisplayAdminStatsModules(array());
        $this->assertContains('a&quot;b&amp;export=1', $html);
        $this->assertNotContains('a"b', $html);
    }

    public function testExportRequestCallsCsvExportAndIgnoresZero()
    {
        $module = $this->newModule();
        $module->hookDisplayAdminStatsModules(array());
        $this->assertFalse($module->csvExportCalled);

        $_GET['export'] = '0';
        $module = $this->newModule();
        $module->hookDisplayAdminStatsModules(array());
        $this->assertFalse($module->csvExportCalled);

        $_GET['export'] = '1';
        $_GET['onlyChildren'] = '1';
        $module = $this->newModule();
        $module->hookDisplayAdminStatsModules(array());
        $this->assertTrue($module->csvExportCalled);
        $this->assertSame('totalPriceSold', $module->csvExportArgs['defaultSortColumn']);
        $this->assertSame('DESC', $module->csvExportArgs['defaultSortDirection']);
        $this->assertSame(1, $module->csvExportArgs['customParams']['onlyChildren']);
    }

    public function testNullOrEmptySortDoesNotWarnOrAppendOrderBy()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest(null);
        $module->getData();
        $this->assertNotContains('ORDER BY', $this->loggedSqlJoined());
        $this->assertCount(8, $module->valuesForTest());

        Db::resetStatementLogForTests();
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('');
        $module->getData();
        $this->assertNotContains('ORDER BY', $this->loggedSqlJoined());
    }

    public function testMissingOriginalWholesalePriceColumnIsCreated()
    {
        DatabaseFixture::dropOriginalWholesaleColumn();
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $this->assertContains('ALTER TABLE', $this->loggedSqlJoined());
        $this->assertContains('DECIMAL( 20, 6 )', $this->loggedSqlJoined());

        $row = $this->rowByCategoryId($module->valuesForTest(), 4);
        $this->assertSame('20.00 XTS', $row['totalPriceSold']);

        Db::resetStatementLogForTests();
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $this->assertNotContains('ALTER TABLE', $this->loggedSqlJoined());
    }

    public function testExistingOriginalWholesalePriceColumnIsNotAltered()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $log = $this->loggedSqlJoined();
        $this->assertContains('SHOW COLUMNS', $log);
        $this->assertContains('original_wholesale_price', $log);
        $this->assertNotContains('ALTER TABLE', $log);
    }

    public function testSchemaCheckUsesTheSameDatabaseConnectionAsItsRowCount()
    {
        $helper = dirname(__FILE__) . '/support/schema-check-slave.php';
        $cmd = escapeshellarg(self::$phpBinary)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d html_errors=0 -d log_errors=0 '
            . escapeshellarg($helper);
        $env = $_ENV;
        $env['STATSBC_DB_HOST'] = getenv('STATSBC_DB_HOST') ? getenv('STATSBC_DB_HOST') : '127.0.0.1';
        $env['STATSBC_DB_PORT'] = getenv('STATSBC_DB_PORT') ? getenv('STATSBC_DB_PORT') : '3306';
        $env['STATSBC_DB_USER'] = getenv('STATSBC_DB_USER') ? getenv('STATSBC_DB_USER') : 'statsbc';
        $env['STATSBC_DB_PASS'] = getenv('STATSBC_DB_PASS') !== false ? getenv('STATSBC_DB_PASS') : 'statsbc';
        $env['STATSBC_DB_NAME'] = getenv('STATSBC_DB_NAME') ? getenv('STATSBC_DB_NAME') : 'stats_best_categories';

        $pipes = array();
        $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $env);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        $diagnosticPattern = '/(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:)/';
        $this->assertSame(0, $code, $stderr);
        $this->assertNotRegExp($diagnosticPattern, $stdout);
        $this->assertNotRegExp($diagnosticPattern, $stderr);

        $log = json_decode($stdout, true);
        $this->assertInternalType('array', $log);
        $joined = implode("\n", $log);
        $this->assertContains('SHOW COLUMNS', $joined);
        $this->assertNotContains('ALTER TABLE', $joined);
    }

    public function testGetDataComputesQuantityPriceMarginAndPageViews()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('totalPriceSold');
        $module->setDirectionForTest('DESC');
        $module->getData();

        $row = $this->rowByCategoryId($module->valuesForTest(), 4);
        $this->assertSame(2, (int) $row['totalQuantitySold']);
        $this->assertSame('20.00 XTS', $row['totalPriceSold']);
        $this->assertSame('12.00 XTS', $row['totalWholeSalePriceSold']);
        $this->assertSame(7, (int) $row['totalPageViewed']);
        $this->assertContains('icon-folder-open', $row['name']);
        $this->assertContains('&bull;', $row['name']);

        $sql = $this->loggedSqlJoined();
        $this->assertContains(' AND o.id_shop IN (1) ', $sql);
        $this->assertContains(' AND calang.id_shop = 1 ', $sql);
        $this->assertContains(' AND parent.id_shop = 1 ', $sql);
        $this->assertContains('calang.`id_lang` = 1', $sql);
        $this->assertContains('parent.`id_lang` = 1', $sql);
        $this->assertContains('o.valid = 1', $sql);
        $this->assertContains('id_page_type` = 1', $sql);
    }

    public function testWholesaleCostFallsBackFromLineToAttributeToProduct()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $hats = $this->rowByCategoryId($module->valuesForTest(), 7);
        $belts = $this->rowByCategoryId($module->valuesForTest(), 8);
        $this->assertSame('7.00 XTS', $hats['totalWholeSalePriceSold']);
        $this->assertSame('10.00 XTS', $belts['totalWholeSalePriceSold']);
    }

    public function testMoneyTotalsAreDividedByConversionRate()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $posters = $this->rowByCategoryId($module->valuesForTest(), 6);
        $this->assertSame('5.00 XTS', $posters['totalPriceSold']);
        $this->assertSame('3.00 XTS', $posters['totalWholeSalePriceSold']);
        $this->assertSame(1, (int) $posters['totalQuantitySold']);
    }

    public function testInvalidAndOutOfRangeOrdersAreExcluded()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $row = $this->rowByCategoryId($module->valuesForTest(), 4);
        $this->assertSame(2, (int) $row['totalQuantitySold']);
        $this->assertSame('20.00 XTS', $row['totalPriceSold']);
    }

    public function testProductAssignedToTwoCategoriesCountsInBoth()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $socks = $this->rowByCategoryId($module->valuesForTest(), 5);
        $this->assertSame(2, (int) $socks['totalQuantitySold']);
        $this->assertSame('20.00 XTS', $socks['totalPriceSold']);
        $this->assertSame(7, (int) $socks['totalPageViewed']);
    }

    public function testRootCategoryIsExcludedAndEmptyLeafRemains()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $ids = $this->categoryIds($module->valuesForTest());
        $this->assertNotContains(1, $ids);
        $scarves = $this->rowByCategoryId($module->valuesForTest(), 9);
        $this->assertSame(0, (int) $scarves['totalQuantitySold']);
        $this->assertSame('0.00 XTS', $scarves['totalPriceSold']);
        $this->assertSame(0, (int) $scarves['totalPageViewed']);
    }

    public function testLeafFilterIsValidSqlAndDropsParents()
    {
        $_GET['onlyChildren'] = '1';
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $ids = $this->categoryIds($module->valuesForTest());
        $this->assertSame(array(4, 5, 6, 7, 8, 9), $ids);
    }

    public function testShopContextKeepsOnlyCategoriesInsideTheShopTree()
    {
        Shop::setContextForTests(Shop::CONTEXT_SHOP, array(1));
        DatabaseFixture::insertShopTwoExtraOrder();
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $ids = $this->categoryIds($module->valuesForTest());
        $this->assertSame(array(3, 4, 5, 7, 8, 9), $ids);
        $this->assertNotContains(6, $ids);
    }

    public function testShopContextLeafFilterKeepsOnlyLeavesInThatTree()
    {
        Shop::setContextForTests(Shop::CONTEXT_SHOP, array(1));
        $_GET['onlyChildren'] = '1';
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $ids = $this->categoryIds($module->valuesForTest());
        $this->assertSame(array(4, 5, 7, 8, 9), $ids);
    }

    public function testSortAppendsBacktickedColumnAndDirection()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('totalQuantitySold');
        $module->setDirectionForTest('DESC');
        $module->getData();
        $this->assertContains('ORDER BY `totalQuantitySold` DESC', $this->loggedSqlJoined());
    }

    public function testLowercaseDirectionIsNotAppended()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('totalQuantitySold');
        $module->setDirectionForTest('desc');
        $module->getData();
        $sql = $this->loggedSqlJoined();
        $this->assertContains('ORDER BY `totalQuantitySold`', $sql);
        $this->assertNotContains(' DESC', $sql);
        $this->assertNotContains(' ASC', $sql);
    }

    public function testSortThatFailsIsNameIsOmitted()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('bad;drop');
        $module->setLimitForTest(10);
        $module->setStartForTest(0);
        $module->getData();
        $sql = $this->loggedSqlJoined();
        $this->assertNotContains('ORDER BY', $sql);
        $this->assertNotContains('bad;drop', $sql);
        $this->assertCount(8, $module->valuesForTest());
    }

    public function testBacktickInSortColumnIsEscaped()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest("bad`col");
        try {
            $module->getData();
            $this->fail('Expected SQL exception for invalid ORDER BY');
        } catch (RuntimeException $e) {
            $this->assertContains('ORDER BY `bad\`col`', $this->loggedSqlJoined());
        }
    }

    public function testLimitAndFoundRowsReflectTheUnpagedTotal()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('totalQuantitySold');
        $module->setDirectionForTest('DESC');
        $module->setStartForTest(0);
        $module->setLimitForTest(1);
        $module->getData();
        $this->assertCount(1, $module->valuesForTest());
        $this->assertSame(8, $module->totalCountForTest());
        $this->assertSame(8, (int) $module->valuesForTest()[0]['id_category']);
        $this->assertSame(4, (int) $module->valuesForTest()[0]['totalQuantitySold']);

        $module = $this->newModule();
        $module->setLang(1);
        $module->setSortForTest('totalQuantitySold');
        $module->setDirectionForTest('DESC');
        $module->setStartForTest(1);
        $module->setLimitForTest(1);
        $module->getData();
        $this->assertCount(1, $module->valuesForTest());
        $this->assertSame(8, $module->totalCountForTest());
        $this->assertSame(2, (int) $module->valuesForTest()[0]['totalQuantitySold']);
    }

    public function testInvalidStartOrLimitOmitsLimit()
    {
        $module = $this->newModule();
        $module->setLang(1);
        $module->setStartForTest(-1);
        $module->setLimitForTest(10);
        $module->getData();
        $this->assertNotContains(' LIMIT ', $this->loggedSqlJoined());
        $this->assertCount(8, $module->valuesForTest());

        Db::resetStatementLogForTests();
        $module = $this->newModule();
        $module->setLang(1);
        $module->setStartForTest('1 or 1');
        $module->setLimitForTest(1);
        $module->getData();
        $sql = $this->loggedSqlJoined();
        $this->assertNotContains(' LIMIT ', $sql);
        $this->assertNotContains('1 or 1', $sql);
    }

    public function testExportLeavesCategoryNamesUnformatted()
    {
        $_GET['export'] = '1';
        $module = $this->newModule();
        $module->setLang(1);
        $module->getData();
        $row = $this->rowByCategoryId($module->valuesForTest(), 4);
        $this->assertSame('Clothes > T-shirts', $row['name']);
        $this->assertNotContains('<', $row['name']);
        $this->assertNotContains('&bull;', $row['name']);
    }
}
