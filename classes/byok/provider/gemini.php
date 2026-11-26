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
 * Google Gemini: text and vision.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini {
    /** API base. */
    const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    /**
     * Generate text.
     *
     * @param string $key
     * @param string $model
     * @param string $system
     * @param string $user
     * @param array $options json, maxtokens, temperature, images
     * @return string
     */
    public static function text(string $key, string $model, string $system, string $user, array $options): string {
        $parts = [];
        foreach ($options['images'] ?? [] as $image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => base64_encode($image['data'])]];
        }
        $parts[] = ['text' => $user];
        $config = ['maxOutputTokens' => (int) ($options['maxtokens'] ?? 4096)];
        if (!empty($options['json'])) {
            $config['responseMimeType'] = 'application/json';
        }
        if (isset($options['temperature'])) {
            $config['temperature'] = (float) $options['temperature'];
        }
        $body = ['contents' => [['role' => 'user', 'parts' => $parts]], 'generationConfig' => $config];
        if ($system !== '') {
            $body['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        $data = http::post(self::BASE . rawurlencode($model) . ':generateContent', ['x-goog-api-key: ' . $key],
            $body, 'Google Gemini');
        $out = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $out .= $part['text'] ?? '';
        }
        if ($out === '') {
            http::empty_reply('Google Gemini');
        }
        return $out;
    }
}
