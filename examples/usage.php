<?php

declare(strict_types=1);

/**
 * Usage notes for statsbestcategories.
 *
 * This is a PrestaShop module — it cannot be run standalone.
 * Below is an annotated walkthrough of how the module works inside PrestaShop.
 */

/**
 * INSTALLATION
 *
 * Copy the module folder to: <prestashop_root>/modules/statsbestcategories/
 * Then install via Admin > Modules & Services, or via CLI:
 *
 *   php bin/console prestashop:module install statsbestcategories
 *
 * ACCESSING THE STATS
 *
 * Navigate to Admin > Stats > Best Categories.
 * Use the date picker to select the analysis period.
 * Check "Display final level categories only" to exclude parent categories.
 * Click "CSV Export" to download the data.
 *
 * COLUMNS RETURNED BY getData()
 *
 * id_category           — PrestaShop category ID
 * name                  — "Parent > Child" category path
 * totalQuantitySold     — Total units sold across all products in this category
 * totalPriceSold        — Total revenue (formatted in store currency)
 * totalWholeSalePriceSold — Margin (revenue minus wholesale cost, formatted)
 * totalPageViewed       — Total page views for products in this category
 *
 * HOOKS USED
 *
 * displayAdminStatsModules — renders the grid on the stats dashboard
 */

// This file is documentation-only and not intended for direct execution.
