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
 * Library functions for AI Grader Central Config.
 *
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ─────────────────────────────────────────────────────────────────────────────
// PHP → browser console bridge
// Captures PHP errors/warnings/notices and stores them in a global array.
// local_aiconfig_before_footer() then outputs them via Moodle's AMD API,
// NOT via raw echo, so no HTML is ever emitted before Moodle's DOCTYPE.
//
// Fatal errors are written to PHP's error_log instead of the page, because
// the page output buffer may be in an undefined state when shutdown fires.
// ─────────────────────────────────────────────────────────────────────────────
global $CFG;
if (isset($CFG) && !empty($CFG->debug)) {
    if (!isset($GLOBALS['_aiconfig_php_errors'])) {
        $GLOBALS['_aiconfig_php_errors'] = [];
    }

    set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
        $GLOBALS['_aiconfig_php_errors'][] = [
            'errno'   => $errno,
            'errstr'  => $errstr,
            'errfile' => $errfile,
            'errline' => $errline,
        ];
        return false; // let Moodle's default handler continue
    });

    // Catch fatal errors that set_error_handler cannot intercept.
    // Write to PHP error_log — NOT to echo/print — to avoid injecting any
    // raw HTML into the page output before or after Moodle's DOCTYPE.
    register_shutdown_function(function(): void {
        $last = error_get_last();
        if ($last === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($last['type'], $fatal, true)) {
            return;
        }
        // Safe: error_log() goes to the server log, never to page output.
        error_log('local_aiconfig: PHP Fatal [' . $last['type'] . ']: '
                  . $last['message'] . ' in ' . $last['file']
                  . ' on line ' . $last['line']);
    });
}

/**
 * Moodle hook: runs just before </body> on every page.
 *
 * 1. jQuery .default shim (ALWAYS active, not debug-only):
 *    Injected via $PAGE->requires->js_call_amd() so it NEVER produces raw
 *    output before Moodle's DOCTYPE. Previously used raw echo which caused
 *    the browser to enter Quirks Mode (BackCompat) and broke TinyMCE.
 *
 * 2. PHP error bridge (debug-only):
 *    Dumps any buffered PHP errors/warnings/notices to the browser console
 *    via the same AMD module's logErrors() method — again, no raw echo.
 */
function local_aiconfig_before_footer(): void {
    // HOOK-MIGRATION (v1.0.11): On Moodle 4.3+ the hook system dispatches
    // local_aiconfig\hook\output\before_footer::callback() automatically via
    // db/hooks.php. Returning early here prevents double-execution of the
    // jQuery shim and PHP error bridge. On Moodle < 4.3 the hook class does
    // not exist so this function still runs as the legacy callback.
    if (class_exists('\core\hook\output\before_footer_html_generation')) {
        return;
    }

    // Legacy path for Moodle < 4.3 only.
    global $PAGE;

    // ── 1. jQuery .default shim via Moodle AMD API ────────────────────────────
    $PAGE->requires->js_call_amd('local_aiconfig/jquery_shim', 'init');

    // ── 2. PHP error bridge (debug-only) ─────────────────────────────────────
    $errors = $GLOBALS['_aiconfig_php_errors'] ?? [];
    if (empty($errors)) {
        return;
    }

    $amdErrors = [];
    foreach ($errors as $e) {
        $errno = $e['errno'];
        $short = basename($e['errfile']);

        if (in_array($errno, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true)) {
            $label  = 'PHP Warning';
            $method = 'warn';
        } else if (in_array($errno, [E_NOTICE, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED], true)) {
            $label  = ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED)
                      ? 'PHP Deprecated' : 'PHP Notice';
            $method = 'warn';
        } else {
            $label  = 'PHP Error [' . $errno . ']';
            $method = 'error';
        }

        $amdErrors[] = [
            'method' => $method,
            'msg'    => "{$label}: {$e['errstr']} in {$short} on line {$e['errline']}",
        ];
    }

    // Moodle warns when js_call_amd arguments exceed 1024 characters.
    // Check the payload size first and fall back to error_log() if too large.
    $encoded = json_encode($amdErrors);
    if (strlen($encoded) <= 900) {
        $PAGE->requires->js_call_amd('local_aiconfig/jquery_shim', 'logErrors', [$amdErrors]);
    } else {
        foreach ($errors as $e) {
            error_log('local_aiconfig PHP bridge: ' . $e['errstr'] . ' in ' . $e['errfile'] . ':' . $e['errline']);
        }
    }
}

/**
 * Get the AI Grader API key from central config or fallback to plugin-specific config.
 *
 * @param string $fallback_plugin Optional plugin component to check for fallback API key
 * @return string|null The API key or null if not configured
 */
function local_aiconfig_get_apikey($fallback_plugin = null) {
    // First try central config
    $apikey = trim(get_config('local_aiconfig', 'apikey') ?? '');
    if (!empty($apikey)) {
        return $apikey;
    }

    // Fallback to plugin-specific config if provided
    if (!empty($fallback_plugin)) {
        $apikey = trim(get_config($fallback_plugin, 'apikey') ?? '');
        if (!empty($apikey)) {
            return $apikey;
        }
    }

    return null;
}

/**
 * Get the AI Grader Site ID from central config or fallback to plugin-specific config.
 *
 * @param string $fallback_plugin Optional plugin component to check for fallback Site ID
 * @return string|null The Site ID or null if not configured
 */
function local_aiconfig_get_siteid($fallback_plugin = null) {
    // First try central config
    $siteid = trim(get_config('local_aiconfig', 'siteid') ?? '');
    if (!empty($siteid)) {
        return $siteid;
    }

    // Fallback to plugin-specific config if provided
    if (!empty($fallback_plugin)) {
        $siteid = trim(get_config($fallback_plugin, 'siteid') ?? '');
        if (!empty($siteid)) {
            return $siteid;
        }
    }

    return null;
}

/**
 * Check if central config is properly configured.
 *
 * @return bool True if API key is configured
 */
function local_aiconfig_is_configured() {
    $apikey = trim(get_config('local_aiconfig', 'apikey') ?? '');
    return !empty($apikey);
}
