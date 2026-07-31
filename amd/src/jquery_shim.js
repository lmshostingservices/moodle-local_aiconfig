/**
 * jQuery .default shim — local_aiconfig
 *
 * Fixes "(0, _jquery.default) is not a function" crash from Babel-compiled
 * AMD modules (e.g. theme first.js) that use `import $ from 'jquery'`.
 * Babel transpiles that to _jquery.default, but Moodle's jQuery has no
 * .default property. This AMD module is loaded via $PAGE->requires->js_call_amd
 * so it runs through Moodle's proper page-requirements system — never before
 * the DOCTYPE — and applies the shim once jQuery is available in the cache.
 *
 * Also provides logErrors() used by the PHP → console bridge.
 *
 * @module     local_aiconfig/jquery_shim
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    return {
        /**
         * Apply the jQuery .default shim.
         * Called by local_aiconfig_before_footer() via js_call_amd.
         */
        init: function() {
            if ($ && typeof $ === 'function' && !$.default) {
                $.default = $;
            }
        },

        /**
         * Log PHP errors/warnings captured by the server-side error handler
         * to the browser console. Called by local_aiconfig_before_footer()
         * via js_call_amd when debug mode is active and errors were collected.
         *
         * @param {Array} errors Array of {method: 'error'|'warn', msg: string}
         */
        logErrors: function(errors) {
            if (!Array.isArray(errors)) {
                return;
            }
            errors.forEach(function(e) {
                if (e.method === 'warn') {
                    window.console.warn('[local_aiconfig] ' + e.msg);
                } else {
                    window.console.error('[local_aiconfig] ' + e.msg);
                }
            });
        }
    };
});
