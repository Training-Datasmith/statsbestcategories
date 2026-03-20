# Architecture: statsbestcategories

## Purpose

A PrestaShop statistics module that surfaces the best-performing product categories on the admin dashboard. It ranks categories by total quantity sold, total revenue, total margin, and total page views within a configurable date range.

## Directory Structure

```
statsbestcategories.php   - Module class (ModuleGrid subclass); all business logic
upgrade/                  - SQL/PHP migration scripts for version upgrades
tests/                    - PHPUnit test stubs and PHPStan bootstrap
translations/             - Locale string overrides (empty placeholder)
```

## Key Design Decisions

- **ModuleGrid inheritance**: Extends PrestaShop's built-in `ModuleGrid` class which handles column definitions, sorting, pagination, and CSV export automatically.
- **Single-file module**: All logic lives in the module class file per PrestaShop conventions.
- **Dynamic column detection**: Checks for the `original_wholesale_price` column at runtime and adds it if missing to support older databases.
- **Shop context filtering**: Limits category results to the current shop context when running in multi-shop mode.

## Extension Points

- Override `getData()` to change the SQL query or add extra columns.
- Register additional hooks in `install()` to display the grid in other admin sections.

## Dependency Flow

```
statsbestcategories (ModuleGrid)
  └─> hookDisplayAdminStatsModules() — renders the grid widget
  └─> getData()                      — builds and executes the ranking query
        └─> Db::getInstance()        — PrestaShop database abstraction
        └─> Shop::getContextListShopID() — multi-shop scope
```
