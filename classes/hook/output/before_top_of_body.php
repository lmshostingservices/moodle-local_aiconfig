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

namespace local_aiconfig\hook\output;

use local_aiconfig\byok\licence;

/**
 * Warns site admins on admin pages while the "Bring your own AI" licence is in its grace period.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_top_of_body {
    /**
     * Add the banner.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     * @return void
     */
    public static function callback(\core\hook\output\before_standard_top_of_body_html_generation $hook): void {
        global $PAGE;
        if (during_initial_install() || !isloggedin() || !is_siteadmin() || $PAGE->pagelayout !== 'admin') {
            return;
        }
        if (trim((string) get_config('local_aiconfig', 'licencekey')) === '') {
            return;
        }
        $status = licence::status();
        if (!$status['byok'] || !$status['ingrace']) {
            return;
        }
        $link = \html_writer::link(new \moodle_url('/local/aiconfig/byok.php'),
            get_string('byok_grace_link', 'local_aiconfig'));
        $text = get_string('byok_grace_banner', 'local_aiconfig',
            userdate($status['until'], get_string('strftimedate', 'langconfig')));
        $hook->add_html(\html_writer::div($text . ' ' . $link, 'alert alert-warning m-3', ['role' => 'alert']));
    }
}
