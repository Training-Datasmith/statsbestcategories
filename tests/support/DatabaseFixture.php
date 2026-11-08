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
 * obtain it through the world-side-web, please send an email
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

class DatabaseFixture
{
    public static function resetAll()
    {
        $db = Db::getInstance();
        $tables = array(
            'order_detail',
            'orders',
            'category_product',
            'product_attribute',
            'product',
            'page_viewed',
            'page',
            'page_type',
            'date_range',
            'category_lang',
            'category',
            'shop',
        );

        $db->execute('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
            $db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $table . '`');
        }
        $db->execute('SET FOREIGN_KEY_CHECKS=1');

        self::createSchema($db);
        self::seedBaseline($db);
    }

    public static function dropOriginalWholesaleColumn()
    {
        $db = Db::getInstance();
        $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'order_detail` DROP COLUMN `original_wholesale_price`');
    }

    public static function insertShopTwoExtraOrder()
    {
        $db = Db::getInstance();
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'orders` (`id_order`, `id_shop`, `conversion_rate`, `valid`, `invoice_date`) VALUES
            (90, 2, 1.000000, 1, "2016-01-15 12:00:00")'
        );
        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'order_detail` (`id_order_detail`, `id_order`, `product_id`, `product_attribute_id`, `product_quantity`, `unit_price_tax_excl`, `original_wholesale_price`) VALUES
            (90, 90, 10, 0, 100, 10.000000, 4.000000)'
        );
    }

    private static function createSchema($db)
    {
        $prefix = _DB_PREFIX_;

        $db->execute(
            'CREATE TABLE `' . $prefix . 'category` (
                `id_category` INT UNSIGNED NOT NULL,
                `id_parent` INT UNSIGNED NOT NULL,
                `nleft` INT UNSIGNED NOT NULL,
                `nright` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_category`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'category_lang` (
                `id_category` INT UNSIGNED NOT NULL,
                `id_lang` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `name` VARCHAR(128) NOT NULL,
                PRIMARY KEY (`id_category`, `id_lang`, `id_shop`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'category_product` (
                `id_category` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_category`, `id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'product` (
                `id_product` INT UNSIGNED NOT NULL,
                `wholesale_price` DECIMAL(20, 6) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_product`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'product_attribute` (
                `id_product_attribute` INT UNSIGNED NOT NULL,
                `id_product` INT UNSIGNED NOT NULL,
                `wholesale_price` DECIMAL(20, 6) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_product_attribute`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'orders` (
                `id_order` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `conversion_rate` DECIMAL(13, 6) NOT NULL DEFAULT 1.000000,
                `valid` TINYINT(1) NOT NULL DEFAULT 0,
                `invoice_date` DATETIME NOT NULL,
                PRIMARY KEY (`id_order`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'order_detail` (
                `id_order_detail` INT UNSIGNED NOT NULL,
                `id_order` INT UNSIGNED NOT NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `product_attribute_id` INT UNSIGNED NOT NULL DEFAULT 0,
                `product_quantity` INT UNSIGNED NOT NULL,
                `unit_price_tax_excl` DECIMAL(20, 6) NOT NULL,
                `original_wholesale_price` DECIMAL(20, 6) NOT NULL DEFAULT 0.000000,
                PRIMARY KEY (`id_order_detail`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'shop` (
                `id_shop` INT UNSIGNED NOT NULL,
                `id_category` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_shop`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'page_type` (
                `id_page_type` INT UNSIGNED NOT NULL,
                `name` VARCHAR(64) NOT NULL,
                PRIMARY KEY (`id_page_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'page` (
                `id_page` INT UNSIGNED NOT NULL,
                `id_page_type` INT UNSIGNED NOT NULL,
                `id_object` VARCHAR(64) NOT NULL,
                PRIMARY KEY (`id_page`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'date_range` (
                `id_date_range` INT UNSIGNED NOT NULL,
                `time_start` DATETIME NOT NULL,
                `time_end` DATETIME NOT NULL,
                PRIMARY KEY (`id_date_range`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );

        $db->execute(
            'CREATE TABLE `' . $prefix . 'page_viewed` (
                `id_page_viewed` INT UNSIGNED NOT NULL,
                `id_page` INT UNSIGNED NOT NULL,
                `id_date_range` INT UNSIGNED NOT NULL,
                `counter` INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_page_viewed`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
    }

    private static function seedBaseline($db)
    {
        $prefix = _DB_PREFIX_;

        $categories = array(
            array(1, 0, 1, 20),
            array(2, 1, 2, 19),
            array(3, 2, 3, 14),
            array(4, 3, 4, 5),
            array(5, 3, 6, 7),
            array(6, 2, 15, 18),
            array(7, 3, 8, 9),
            array(8, 3, 10, 11),
            array(9, 3, 12, 13),
        );
        foreach ($categories as $row) {
            $db->execute(
                'INSERT INTO `' . $prefix . 'category` (`id_category`, `id_parent`, `nleft`, `nright`) VALUES
                (' . (int) $row[0] . ', ' . (int) $row[1] . ', ' . (int) $row[2] . ', ' . (int) $row[3] . ')'
            );
        }

        $names = array(
            array(1, 'Root'),
            array(2, 'Home'),
            array(3, 'Clothes'),
            array(4, 'T-shirts'),
            array(5, 'Socks'),
            array(6, 'Posters'),
            array(7, 'Hats'),
            array(8, 'Belts'),
            array(9, 'Scarves'),
        );
        foreach ($names as $row) {
            $db->execute(
                'INSERT INTO `' . $prefix . 'category_lang` (`id_category`, `id_lang`, `id_shop`, `name`) VALUES
                (' . (int) $row[0] . ', 1, 1, "' . pSQL($row[1]) . '")'
            );
        }

        $db->execute('INSERT INTO `' . $prefix . 'shop` (`id_shop`, `id_category`) VALUES (1, 3), (2, 6)');

        $products = array(
            array(10, 99.000000),
            array(11, 8.000000),
            array(12, 2.500000),
            array(13, 0.000000),
            array(14, 0.000000),
        );
        foreach ($products as $row) {
            $db->execute(
                'INSERT INTO `' . $prefix . 'product` (`id_product`, `wholesale_price`) VALUES
                (' . (int) $row[0] . ', ' . $row[1] . ')'
            );
        }

        $db->execute(
            'INSERT INTO `' . $prefix . 'product_attribute` (`id_product_attribute`, `id_product`, `wholesale_price`) VALUES
            (1, 11, 3.000000)'
        );

        $db->execute(
            'INSERT INTO `' . $prefix . 'category_product` (`id_category`, `id_product`) VALUES
            (4, 10), (5, 10), (7, 11), (8, 12), (6, 13), (4, 14)'
        );

        $db->execute(
            'INSERT INTO `' . $prefix . 'orders` (`id_order`, `id_shop`, `conversion_rate`, `valid`, `invoice_date`) VALUES
            (1, 1, 1.000000, 1, "2016-01-10 10:00:00"),
            (2, 1, 1.000000, 1, "2016-01-11 10:00:00"),
            (3, 1, 1.000000, 1, "2016-01-12 10:00:00"),
            (4, 1, 1.000000, 0, "2016-01-13 10:00:00"),
            (5, 1, 1.000000, 1, "2015-12-01 10:00:00"),
            (6, 1, 2.000000, 1, "2016-01-14 10:00:00")'
        );

        $db->execute(
            'INSERT INTO `' . $prefix . 'order_detail` (`id_order_detail`, `id_order`, `product_id`, `product_attribute_id`, `product_quantity`, `unit_price_tax_excl`, `original_wholesale_price`) VALUES
            (1, 1, 10, 0, 2, 10.000000, 4.000000),
            (2, 2, 11, 1, 1, 10.000000, 0.000000),
            (3, 3, 12, 0, 4, 5.000000, 0.000000),
            (4, 4, 14, 0, 9, 10.000000, 0.000000),
            (5, 5, 14, 0, 7, 10.000000, 0.000000),
            (6, 6, 13, 0, 1, 10.000000, 4.000000)'
        );

        $db->execute('INSERT INTO `' . $prefix . 'page_type` (`id_page_type`, `name`) VALUES (1, "product"), (2, "category")');

        $db->execute(
            'INSERT INTO `' . $prefix . 'page` (`id_page`, `id_page_type`, `id_object`) VALUES
            (1, 1, "10"),
            (2, 1, "11"),
            (3, 1, "13"),
            (4, 2, "4")'
        );

        $db->execute(
            'INSERT INTO `' . $prefix . 'date_range` (`id_date_range`, `time_start`, `time_end`) VALUES
            (1, "2016-01-01 00:00:00", "2016-01-31 23:59:59"),
            (2, "2015-01-01 00:00:00", "2015-12-31 23:59:59"),
            (3, "2016-01-01 00:00:00", "2016-01-31 23:59:59")'
        );

        $db->execute(
            'INSERT INTO `' . $prefix . 'page_viewed` (`id_page_viewed`, `id_page`, `id_date_range`, `counter`) VALUES
            (1, 1, 1, 7),
            (2, 1, 2, 100),
            (3, 4, 3, 50),
            (4, 2, 1, 3),
            (5, 3, 1, 4)'
        );
    }
}
