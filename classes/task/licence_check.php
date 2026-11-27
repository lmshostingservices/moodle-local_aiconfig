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

namespace local_aiconfig\task;

use local_aiconfig\byok\licence;

/**
 * Daily check of the "Bring your own AI" licence with LMS Labs.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licence_check extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_licencecheck', 'local_aiconfig');
    }

    /**
     * Run the check. A failed check changes nothing: the stored reply and grace period still apply.
     *
     * @return void
     */
    public function execute() {
        if (!licence::all_keys()) {
            mtrace('No Bring your own AI licence installed.');
            return;
        }
        mtrace(licence::check() ? 'Licence checked.' : 'Licence server unreachable; keeping the last known state.');
    }
}
