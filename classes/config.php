<?php
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
