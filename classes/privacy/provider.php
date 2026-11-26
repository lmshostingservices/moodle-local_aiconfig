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

namespace local_aiconfig\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider for AI Grader Central Config.
 *
 * This plugin stores no personal data. With Bring your own AI on, plugins send request content from
 * this site to the AI provider the administrator chose, and the daily licence check sends site details
 * (no personal data) to LMS Labs.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe the data that leaves the site.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('aiprovider', [
            'content' => 'privacy:metadata:aiprovider:content',
        ], 'privacy:metadata:aiprovider');
        $collection->add_external_location_link('lmslabs', [
            'site' => 'privacy:metadata:lmslabs:site',
        ], 'privacy:metadata:lmslabs');
        return $collection;
    }
}
