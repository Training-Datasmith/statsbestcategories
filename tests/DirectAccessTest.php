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

class DirectAccessTest extends PHPUnit_Framework_TestCase
{
    private static $phpBinary;

    public static function setUpBeforeClass()
    {
        self::$phpBinary = getenv('STATSBC_PHP_BIN');
        if (!self::$phpBinary) {
            self::$phpBinary = 'php';
        }
    }

    private function runIncludedFile($absoluteTarget, $definePsVersion)
    {
        $helper = dirname(__FILE__) . '/support/invoke-file.php';
        $flag = $definePsVersion ? '1' : '0';
        $cmd = escapeshellarg(self::$phpBinary)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d html_errors=0 -d log_errors=0 '
            . escapeshellarg($helper) . ' '
            . escapeshellarg($absoluteTarget) . ' '
            . escapeshellarg($flag);

        $pipes = array();
        $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        $this->assertTrue(is_resource($proc));
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $diagnosticPattern = '/(Notice:|Warning:|Deprecated:|Strict Standards:|Parse error:|Fatal error:)/';
        $this->assertNotRegExp($diagnosticPattern, $stdout, 'stdout: ' . $stdout);
        $this->assertNotRegExp($diagnosticPattern, $stderr, 'stderr: ' . $stderr);

        return array($exitCode, $stdout, $stderr);
    }

    public function testModuleFileExitsWhenPsVersionIsUndefined()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/statsbestcategories.php', false);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }

    public function testRootIndexExitsCleanly()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/index.php', true);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }

    public function testTestsIndexExitsCleanly()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/tests/index.php', true);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }

    public function testPhpstanIndexExitsCleanly()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/tests/phpstan/index.php', true);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }

    public function testTranslationsIndexExitsCleanly()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/translations/index.php', true);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }

    public function testUpgradeIndexExitsCleanly()
    {
        list($code, $stdout) = $this->runIncludedFile(dirname(__DIR__) . '/upgrade/index.php', true);
        $this->assertSame(0, $code);
        $this->assertNotContains('STATSBC_INVOKE_SENTINEL', $stdout);
    }
}
