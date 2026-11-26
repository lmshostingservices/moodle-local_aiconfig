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
 * OpenAI: text, vision, images and voice.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class openai {
    /** API base. */
    const BASE = 'https://api.openai.com/v1';

    /**
     * Generate text (Chat Completions).
     *
     * @param string $key
     * @param string $model
     * @param string $system
     * @param string $user
     * @param array $options json, maxtokens, temperature, images, endpoint, orgid
     * @return string
     */
    public static function text(string $key, string $model, string $system, string $user, array $options): string {
        $content = [['type' => 'text', 'text' => $user]];
        foreach ($options['images'] ?? [] as $image) {
            $content[] = ['type' => 'image_url', 'image_url' => [
                'url' => 'data:' . $image['mime'] . ';base64,' . base64_encode($image['data']),
            ]];
        }
        $messages = [];
        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }
        $messages[] = ['role' => 'user', 'content' => $content];
        $body = [
            'model' => $model,
            'messages' => $messages,
            'max_completion_tokens' => (int) ($options['maxtokens'] ?? 4096),
        ];
        if (!empty($options['schema'])) {
            $body['response_format'] = ['type' => 'json_schema', 'json_schema' => [
                'name' => self::schema_name($options['schema']['name']), 'strict' => true, 'schema' => $options['schema']['sc' .
                    'hema'],
            ]];
        } else if (!empty($options['json'])) {
            $body['response_format'] = ['type' => 'json_object'];
            if (stripos($system . $user, 'json') === false) {
                $body['messages'][0]['content'] = trim($system . "\n\nReply in JSON.");
            }
        }
        if (isset($options['temperature'])) {
            $body['temperature'] = (float) $options['temperature'];
        }
        $data = http::post(
            $options['endpoint'] ?? self::BASE . '/chat/completions',
            self::headers($key, $options),
            $body,
            'OpenAI',
            false,
            (int) ($options['timeout'] ?? 180)
        );
        if (!empty($options['json']) && ($data['choices'][0]['finish_reason'] ?? '') === 'length') {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => 'OpenAI', 'detail' => 'the answer was cut off at the token limit',
            ]);
        }
        $out = (string) ($data['choices'][0]['message']['content'] ?? '');
        if ($out === '') {
            http::empty_reply('OpenAI');
        }
        return $out;
    }

    /**
     * A schema name OpenAI accepts.
     *
     * @param string $name
     * @return string
     */
    public static function schema_name(string $name): string {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
        return substr($name !== '' ? $name : 'result', 0, 64);
    }

    /**
     * Generate an image.
     *
     * @param string $key
     * @param string $model
     * @param string $prompt
     * @param array $options size, quality
     * @return string Raw bytes.
     */
    public static function image(string $key, string $model, string $prompt, array $options): string {
        $body = ['model' => $model, 'prompt' => $prompt, 'n' => 1, 'size' => $options['size'] ?? '1024x1024'];
        if (strpos($model, 'dall-e') === 0) {
            $body['response_format'] = 'b64_json';
        } else {
            $body['quality'] = $options['quality'] ?? 'medium';
        }
        $data = http::post(self::BASE . '/images/generations', self::headers($key, $options), $body, 'OpenAI');
        $b64 = $data['data'][0]['b64_json'] ?? '';
        if ($b64 === '') {
            http::empty_reply('OpenAI');
        }
        return base64_decode($b64);
    }

    /**
     * Text to speech.
     *
     * @param string $key
     * @param string $model
     * @param string $text
     * @param array $options voice, format (mp3 or ogg)
     * @return string MP3 or Ogg Opus bytes.
     */
    public static function speech(string $key, string $model, string $text, array $options): string {
        $voice = $options['voice'] ?: ((string) get_config('local_aiconfig', 'voice_openai') ?: 'alloy');
        $body = ['model' => $model, 'voice' => $voice, 'input' => $text,
            'response_format' => ($options['format'] ?? 'mp3') === 'ogg' ? 'opus' : 'mp3'];
        if (!empty($options['rate'])) {
            $body['speed'] = (float) $options['rate'];
        }
        return http::post(self::BASE . '/audio/speech', self::headers($key, $options), $body, 'OpenAI', true);
    }

    /**
     * Auth headers.
     *
     * @param string $key
     * @param array $options
     * @return array
     */
    protected static function headers(string $key, array $options): array {
        $headers = ['Authorization: Bearer ' . $key];
        if (!empty($options['orgid'])) {
            $headers[] = 'OpenAI-Organization: ' . $options['orgid'];
        }
        return $headers;
    }
}
