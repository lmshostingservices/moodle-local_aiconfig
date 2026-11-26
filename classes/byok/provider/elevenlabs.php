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
 * ElevenLabs text to speech.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class elevenlabs {
    /** API base. */
    const BASE = 'https://api.elevenlabs.io/v1/text-to-speech/';

    /**
     * Text to speech.
     *
     * @param string $key
     * @param string $model e.g. eleven_multilingual_v2
     * @param string $text
     * @param array $options voice (ElevenLabs voice id; blank = admin default)
     * @return string MP3 bytes.
     */
    public static function speech(string $key, string $model, string $text, array $options): string {
        $voice = $options['voice'] ?: (string) get_config('local_aiconfig', 'voice_elevenlabs');
        if ($voice === '' || !preg_match('/^[A-Za-z0-9]{10,40}$/', $voice)) {
            $voice = '21m00Tcm4TlvDq8ikWAM';
        }
        return http::post(self::BASE . $voice . '?output_format=mp3_44100_128', [
            'xi-api-key: ' . $key, 'Accept: audio/mpeg',
        ], ['text' => $text, 'model_id' => $model], 'ElevenLabs', true);
    }
}
