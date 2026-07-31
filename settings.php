<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for AI Grader Central Config.
 *
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Check if user has site config access OR the local/aiconfig:manage capability
// This allows custom roles like 'lmshsadmin' to manage AI Grader settings
$hasaccess = $hassiteconfig || has_capability('local/aiconfig:manage', context_system::instance());

if ($hasaccess) {
    $settings = new admin_settingpage('local_aiconfig', get_string('pluginname', 'local_aiconfig'));

    // ── Marketplace activation banner (shown only when not yet configured) ──
    $aiconfig_apikey_set = !empty(trim(get_config('local_aiconfig', 'apikey') ?? ''));
    if (!$aiconfig_apikey_set) {
        $settings->add(new admin_setting_heading(
            'local_aiconfig/marketplace_activate_banner',
            '',
            '<div style="padding:20px 24px;background:linear-gradient(135deg,#6366f1 0%,#4f46e5 100%);border-radius:10px;margin:0 0 20px;color:#fff;">' .
            '<div style="font-size:20px;font-weight:700;margin:0 0 6px;">🛒 Purchased from Moodle Marketplace?</div>' .
            '<p style="margin:0 0 14px;opacity:.9;font-size:14px;">Activate your plugin and claim your <strong>200 free starter credits</strong> — takes 30 seconds.</p>' .
            '<a href="https://lms-labs.app/marketplace-setup" target="_blank" ' .
            '   style="display:inline-block;padding:10px 22px;background:#fff;color:#4f46e5;border-radius:6px;font-weight:700;text-decoration:none;font-size:14px;">' .
            '   Activate at lms-labs.app/marketplace-setup &rarr;' .
            '</a>' .
            '<p style="margin:14px 0 0;font-size:12px;opacity:.75;">Already have credentials? Enter them in the Site ID and API Key fields below.</p>' .
            '</div>'
        ));
    }

    // Header description
    $settings->add(new admin_setting_heading(
        'local_aiconfig/header',
        get_string('settings_header', 'local_aiconfig'),
        get_string('settings_desc', 'local_aiconfig')
    ));
    
    // Site ID
    $settings->add(new admin_setting_configtext(
        'local_aiconfig/siteid',
        get_string('siteid', 'local_aiconfig'),
        get_string('siteid_desc', 'local_aiconfig'),
        '',
        PARAM_TEXT
    ));
    
    // API Key
    $settings->add(new admin_setting_configpasswordunmask(
        'local_aiconfig/apikey',
        get_string('apikey', 'local_aiconfig'),
        get_string('apikey_desc', 'local_aiconfig'),
        ''
    ));
    
    $ADMIN->add('localplugins', $settings);
}

// =========================================================================
// COMPANION REGISTRATION: gradingform_benchmarks
// =========================================================================
// Moodle 4.4's admin tree builder does NOT include gradingform plugins in its
// standard settings.php loading loop. As a result, gradingform_benchmarks/settings.php
// is never called and its admin page is never registered. We register it here
// inside local_aiconfig/settings.php (which IS always loaded) as the reliable
// workaround. The page is placed under 'localplugins' (same category as aiconfig)
// and accessible at: /admin/settings.php?section=gradingformbenchmarks
// =========================================================================
if ($hassiteconfig
    && file_exists($CFG->dirroot . '/grade/grading/form/benchmarks/version.php')
    && !$ADMIN->locate('gradingformbenchmarks')) {

    $gbpage = new admin_settingpage(
        'gradingformbenchmarks',
        get_string('pluginname', 'gradingform_benchmarks')
    );

    $gbcentralinstalled = file_exists($CFG->dirroot . '/local/aiconfig/version.php');

    $gbpage->add(new admin_setting_heading(
        'gradingform_benchmarks/apicredentials',
        get_string('apicredentials', 'gradingform_benchmarks'),
        get_string('apicredentials_desc', 'gradingform_benchmarks')
    ));

    $gbpage->add(new admin_setting_configtext(
        'gradingform_benchmarks/siteid',
        get_string('siteid', 'gradingform_benchmarks'),
        get_string('siteid_desc', 'gradingform_benchmarks')
            . ($gbcentralinstalled ? ' ' . get_string('centralconfig_fallback', 'gradingform_benchmarks') : ''),
        '',
        PARAM_TEXT
    ));

    $gbpage->add(new admin_setting_configpasswordunmask(
        'gradingform_benchmarks/apikey',
        get_string('apikey', 'gradingform_benchmarks'),
        get_string('apikey_desc', 'gradingform_benchmarks')
            . ($gbcentralinstalled ? ' ' . get_string('centralconfig_fallback', 'gradingform_benchmarks') : ''),
        ''
    ));

    $ADMIN->add('localplugins', $gbpage);
}
