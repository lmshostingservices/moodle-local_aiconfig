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
 * Hook callback for core\hook\output\before_footer_html_generation.
 *
 * Replaces the legacy local_aiconfig_before_footer() function (deprecated
 * in Moodle 4.3+). On Moodle 4.3+ this class is dispatched by the hook
 * system; the old function in lib.php detects the hook class and skips
 * itself to avoid double-execution. On Moodle < 4.3 the hook class does
 * not exist so the legacy function still runs.
 *
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconfig\hook\output;

/**
 * Hook callback class for before_footer_html_generation.
 */
class before_footer {
    /**
     * Inject the jQuery .default shim and PHP error bridge into the page footer.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function callback(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE;

        // 1. jQuery .default shim — fixes "(0, _jquery.default) is not a function"
        //    from Babel-compiled AMD modules that use `import $ from 'jquery'`.
        $PAGE->requires->js_call_amd('local_aiconfig/jquery_shim', 'init');

        // 2. PHP error bridge (debug-only).
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
}
