# Architecture: ps_themecusto

## Purpose

A PrestaShop back-office module that provides a theme customisation interface, allowing merchants to configure homepage layout, widget positions, and basic visual settings without editing template files.

## Directory Structure

```
ps_themecusto.php                              - Module bootstrap; hook and route registration
controllers/admin/
  Admin_Ps_Theme_Custo_Configuration.php       - Admin controller for the main configuration page
  Admin_Ps_Theme_Custo_Advanced.php            - Admin controller for advanced CSS/JS customisation
classes/
  Theme_Custo_Requests.php                     - Helper for processing configuration form requests
src/                                           - Additional service/helper classes
views/
  templates/admin/controllers/configuration/   - Admin config page templates
  templates/admin/controllers/advanced/        - Advanced customisation templates
  css/                                         - Admin panel stylesheet
  js/                                          - Admin panel JavaScript
upgrade/                                       - SQL/PHP migration scripts
translations/                                  - Locale string overrides
```

## Key Design Decisions

- **Two admin controllers**: Separates basic layout configuration (column layout, widget display) from advanced text/CSS injection to enforce separation of concerns.
- **Request helper**: `Theme_Custo_Requests` centralises form request parsing to keep controllers thin.
- **Configuration storage**: All settings stored as PrestaShop `Configuration` key-value pairs, making them multi-shop compatible.

## Extension Points

- Add new configuration sections by adding admin controllers and registering routes.
- Extend `Theme_Custo_Requests` to handle additional form fields.

## Dependency Flow

```
ps_themecusto (Module)
  └─> Admin_Ps_Theme_Custo_Configuration  — layout and widget config form
  └─> Admin_Ps_Theme_Custo_Advanced       — advanced CSS/JS injection form
        └─> Theme_Custo_Requests           — processes and validates form data
              └─> Configuration::updateValue() — persists settings
```
