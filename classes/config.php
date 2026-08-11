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
 * Central Config API class for AI Grader plugins.
 * Provides static methods for other plugins to access central credentials.
 *
 * @package    local_aiconfig
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aiconfig;

defined('MOODLE_INTERNAL') || die();

class config {
    
    /**
     * Get the Site ID from central config.
     *
     * @return string Site ID or empty string if not configured
     */
    public static function get_site_id(): string {
        $siteid = get_config('local_aiconfig', 'siteid');
        return !empty($siteid) ? trim($siteid) : '';
    }
    
    /**
     * Get the API Key from central config.
     *
     * @return string API Key or empty string if not configured
     */
    public static function get_api_key(): string {
        $apikey = get_config('local_aiconfig', 'apikey');
        return !empty($apikey) ? trim($apikey) : '';
    }
    
    /**
     * Check if central config is properly configured with credentials.
     *
     * @return bool True if both Site ID and API Key are configured
     */
    public static function is_configured(): bool {
        return !empty(self::get_site_id()) && !empty(self::get_api_key());
    }
    
    /**
     * Get both credentials at once.
     *
     * @return array ['siteid' => string, 'apikey' => string]
     */
    public static function get_credentials(): array {
        return [
            'siteid' => self::get_site_id(),
            'apikey' => self::get_api_key(),
        ];
    }
}
