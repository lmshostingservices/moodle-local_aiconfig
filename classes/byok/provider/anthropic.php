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
 * Anthropic Claude (Messages API): text and vision.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class anthropic {
    /** Default endpoint. */
    const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    /**
     * Generate text.
     *
     * @param string $key
     * @param string $model
     * @param string $system
     * @param string $user
     * @param array $options json, maxtokens, temperature, images, endpoint
     * @return string
     */
    public static function text(string $key, string $model, string $system, string $user, array $options): string {
        $content = [];
        foreach ($options['images'] ?? [] as $image) {
            $content[] = ['type' => 'image', 'source' => [
                'type' => 'base64', 'media_type' => $image['mime'], 'data' => base64_encode($image['data']),
            ]];
        }
        $content[] = ['type' => 'text', 'text' => $user];
        if (!empty($options['json']) && empty($options['schema'])) {
            $system = trim($system . "\n\nReply with valid JSON only, with no text before or after it.");
        }
        $body = [
            'model' => $model,
            'max_tokens' => (int) ($options['maxtokens'] ?? 4096),
            'messages' => [['role' => 'user', 'content' => $content]],
        ];
        if ($system !== '') {
            $body['system'] = $system;
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        $tool = '';
        if (!empty($options['schema'])) {
            // Structured output: the answer comes back as the input of a tool the model must call.
            $tool = openai::schema_name($options['schema']['name']);
            $body['tools'] = [['name' => $tool, 'description' => 'Return the result in this exact shape.',
                'input_schema' => $options['schema']['schema']]];
            $body['tool_choice'] = ['type' => 'tool', 'name' => $tool];
        }
        $data = http::post($options['endpoint'] ?? self::ENDPOINT, [
            'x-api-key: ' . $key, 'anthropic-version: 2023-06-01',
        ], $body, 'Anthropic', false, (int) ($options['timeout'] ?? 180));
        if (!empty($options['json']) && ($data['stop_reason'] ?? '') === 'max_tokens') {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => 'Anthropic', 'detail' => 'the answer was cut off at the token limit',
            ]);
        }
        $out = '';
        foreach ($data['content'] ?? [] as $block) {
            if ($tool !== '' && ($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $tool) {
                return json_encode($block['input'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (($block['type'] ?? '') === 'text') {
                $out .= $block['text'];
            }
        }
        if ($out === '') {
            http::empty_reply('Anthropic');
        }
        return $out;
    }
}
