<?php

declare(strict_types=1);

/**
 * Example: Working with the ps_themecusto PrestaShop module.
 *
 * ps_themecusto provides a Back Office interface for configuring theme-level
 * settings: logos, colors, fonts, homepage layout, and responsive behavior.
 * It is the primary theme customization tool for PrestaShop's Classic theme.
 *
 * This file documents common usage patterns.
 */

// --- The module operates entirely in the Back Office ---
// Access via: Design > Theme & Logo
// No front-office hook display — settings affect theme rendering directly.

// --- Configuration areas ---
// 1. Logo:
//    - Header logo (desktop)
//    - Mobile logo
//    - Favicon
//    - Email header logo
//    - Invoice logo

// 2. Theme color scheme (Classic theme):
//    - Primary color
//    - Secondary color
//    - Link color

// 3. Homepage configuration:
//    - Number of products per row
//    - Enable/disable homepage sections (featured, new, specials, etc.)

// 4. Pages configuration:
//    - Products per row on category pages
//    - Default product sort order
//    - Display product quick-view button

// --- Reading logo configuration programmatically ---
// $headerLogo = _PS_IMG_ . Configuration::get('PS_LOGO');
// $mobileLogo = _PS_IMG_ . Configuration::get('PS_LOGO_MOBILE');
// $favicon    = _PS_IMG_ . Configuration::get('PS_FAVICON');
//
// echo "Logo: $headerLogo\n";

// --- Hook: actionAdminThemesControllerUpdate_optionsAfter ---
// Fired when theme options are saved. Use for custom post-save logic:
//
// Hook::register(
//     'actionAdminThemesControllerUpdate_optionsAfter',
//     'MyModule',
//     'onThemeOptionsSaved'
// );

// --- Multistore support ---
// Logo and color settings can be configured per shop in multistore mode.
// Each shop has its own logo set independently.
