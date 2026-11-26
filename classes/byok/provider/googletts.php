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
 * Google Cloud Text-to-Speech (Chirp 3 HD voices, the same family LMS Labs credits use).
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class googletts {
    /** Endpoint. */
    const ENDPOINT = 'https://texttospeech.googleapis.com/v1/text:synthesize';

    /**
     * Text to speech.
     *
     * @param string $key Google API key with the Text-to-Speech API enabled.
     * @param string $model Voice family, e.g. "Chirp3-HD".
     * @param string $text
     * @param array $options language, voice (short name like "Aoede", or a full voice name)
     * @return string MP3 bytes.
     */
    public static function speech(string $key, string $model, string $text, array $options): string {
        $language = $options['language'] ?: 'en-AU';
        $voice = $options['voice'] ?: ((string) get_config('local_aiconfig', 'voice_googletts') ?: 'Aoede');
        if (!preg_match('/^[a-z]{2,3}-[A-Za-z]{2,4}-/', $voice)) {
            $voice = $language . '-' . $model . '-' . $voice;
        }
        $data = http::post(self::ENDPOINT, ['x-goog-api-key: ' . $key], [
            'input' => ['text' => $text],
            'voice' => ['languageCode' => $language, 'name' => $voice],
            'audioConfig' => ['audioEncoding' => 'MP3'],
        ], 'Google Text-to-Speech');
        if (empty($data['audioContent'])) {
            http::empty_reply('Google Text-to-Speech');
        }
        return base64_decode($data['audioContent']);
    }
}
