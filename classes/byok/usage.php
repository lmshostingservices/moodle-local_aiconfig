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

namespace local_aiconfig\byok;

/**
 * Daily call counts for bring-your-own-AI, per plugin, service and source. No personal data.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /**
     * Count one call.
     *
     * @param string $component
     * @param string $service
     * @param string $source
     * @param float $seconds How long the call took.
     * @return void
     */
    public static function log(string $component, string $service, string $source, float $seconds): void {
        global $DB;
        $day = (int) floor(time() / DAYSECS) * DAYSECS;
        $key = ['component' => $component, 'service' => $service, 'source' => $source, 'day' => $day];
        try {
            $row = $DB->get_record('local_aiconfig_byok_usage', $key);
            if ($row) {
                $DB->update_record('local_aiconfig_byok_usage', (object) [
                    'id' => $row->id, 'calls' => $row->calls + 1, 'seconds' => $row->seconds + (int) ceil($seconds),
                ]);
            } else {
                $DB->insert_record('local_aiconfig_byok_usage', (object) ($key + [
                    'calls' => 1, 'seconds' => (int) ceil($seconds),
                ]));
            }
        } catch (\Throwable $e) {
            // Counting must never break the AI call it counts.
            debugging('local_aiconfig usage log failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Totals for the last N days, per component and service.
     *
     * @param int $days
     * @return array of objects: component, service, source, calls
     */
    public static function recent(int $days = 30): array {
        global $DB;
        return array_values($DB->get_records_sql(
            "SELECT " . $DB->sql_concat('component', "'/'", 'service', "'/'", 'source') . " AS id,
                    component, service, source, SUM(calls) AS calls
               FROM {local_aiconfig_byok_usage}
              WHERE day >= ?
           GROUP BY component, service, source
           ORDER BY component, service",
            [time() - $days * DAYSECS]
        ));
    }
}
