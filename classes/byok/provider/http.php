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

namespace local_aiconfig\byok\provider;

/**
 * Small HTTP helper shared by the vendor clients.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class http {
    /**
     * POST JSON and decode a JSON reply, or return raw bytes.
     *
     * @param string $url
     * @param array $headers
     * @param array $body
     * @param string $vendor Shown in errors.
     * @param bool $raw Return the body as bytes (audio, images) instead of decoding JSON.
     * @param int $timeout Seconds.
     * @return array|string
     */
    public static function post(
        string $url,
        array $headers,
        array $body,
        string $vendor,
        bool $raw = false,
        int $timeout = 180
    ) {
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && isset($GLOBALS['local_aiconfig_byok_mock'])) {
            return ($GLOBALS['local_aiconfig_byok_mock'])($url, $headers, $body, $vendor, $raw);
        }
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl(['ignoresecurity' => false]);
        $curl->setHeader(array_merge(['Content-Type: application/json'], $headers));
        $reply = $curl->post($url, json_encode($body), ['CURLOPT_TIMEOUT' => $timeout]);
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno()) {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => $vendor, 'detail' => $curl->error,
            ]);
        }
        if ($code < 200 || $code >= 300) {
            $data = json_decode((string) $reply, true);
            $detail = is_array($data) ? ($data['error']['message'] ?? $data['detail']['message'] ?? $data['message']
                ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => $vendor, 'detail' => is_string($detail) ? shorten_text(s($detail), 300) : 'HTTP ' . $code,
            ]);
        }
        if ($raw) {
            return (string) $reply;
        }
        $data = json_decode((string) $reply, true);
        if (!is_array($data)) {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => $vendor, 'detail' => 'unreadable reply',
            ]);
        }
        return $data;
    }

    /**
     * Throw when a reply had no usable content.
     *
     * @param string $vendor
     * @return void
     */
    public static function empty_reply(string $vendor): void {
        throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
            'vendor' => $vendor, 'detail' => 'empty reply',
        ]);
    }
}
