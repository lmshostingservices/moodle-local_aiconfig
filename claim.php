<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Return page after a "Bring your own AI" checkout: fetches and installs the licence key.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/aiconfig:manage', context_system::instance());

// Claiming only uses the secret this site stored when the admin pressed Subscribe, so a forged
// visit can do no more than fetch this site's own key early.
$payload = \local_aiconfig\byok\licence::claim();
redirect(
    new moodle_url('/local/aiconfig/byok.php'),
    get_string($payload ? 'byok_claimed' : 'byok_claimpending', 'local_aiconfig'),
    null,
    $payload ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
);
