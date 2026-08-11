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
 * Version information for AI Grader Central Config.
 *
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_aiconfig';
$plugin->version   = 2026071500;  // 2026-07-15, v1.0.12
$plugin->requires  = 2022041900;
$plugin->supported = [400, 500];  // Moodle 4.0 to 5.x
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.15'; // FIX-AMD-OVERFLOW (v1.0.12): js_call_amd logErrors now checks JSON payload size before calling AMD; if serialized errors exceed 900 chars, falls back to error_log() to prevent Moodle's "Too much data passed as arguments" debugging warning. Fix applied in both classes/hook/output/before_footer.php and lib.php legacy path. // HOOK-MIGRATION (v1.0.11): Migrated local_aiconfig_before_footer() legacy callback to the Moodle 4.3+ hook system (core\hook\output\before_footer_html_generation). Added db/hooks.php registering local_aiconfig\hook\output\before_footer::callback() at priority 500. Added classes/hook/output/before_footer.php with the same jQuery shim + PHP error bridge logic. The legacy lib.php function now returns early on Moodle 4.3+ (detects hook class existence) to prevent double-execution; still works as fallback on Moodle < 4.3. No DB schema changes. // FIX-QUIRKS-MODE (v1.0.10): Remove all raw echo/print from lib.php to stop local_aiconfig outputting HTML before Moodle's DOCTYPE, which was switching the browser into Quirks Mode (BackCompat) and breaking TinyMCE ("document is not in standards mode"). Root causes removed: (1) local_aiconfig_before_footer() was using echo <<<HTML <script>...</script> HTML for the jQuery .default shim — now replaced with $PAGE->requires->js_call_amd('local_aiconfig/jquery_shim', 'init'). (2) local_aiconfig_before_footer() was using echo "<script>...</script>" for the PHP error bridge — now replaced with $PAGE->requires->js_call_amd('local_aiconfig/jquery_shim', 'logErrors', [$errors]). (3) register_shutdown_function() was using echo "<script>console.error()</script>" for fatal PHP errors — now replaced with error_log() which writes to the server log, never to page output. New AMD module: local_aiconfig/jquery_shim (amd/src/jquery_shim.js + amd/build/jquery_shim.js + amd/build/jquery_shim.min.js) with named define('local_aiconfig/jquery_shim', ...). No PHP or DB schema changes beyond lib.php. Savepoint 2026061700010.
