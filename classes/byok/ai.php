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
 * The API LMS Labs plugins call to run AI on the site's own keys.
 *
 * Usage from a plugin (guard with class_exists so the plugin still works without this version):
 *
 *     use local_aiconfig\byok\ai;
 *     if (class_exists(ai::class) && ai::uses_own_ai('mod_example', ai::TEXT)) {
 *         $json = ai::text('mod_example', $system, $user, ['json' => true, 'maxtokens' => 4000]);
 *     } else {
 *         // Existing LMS Labs credits call.
 *     }
 *
 * Every call checks the licence again, so a plugin cannot spend the site's keys without one.
 * Calls throw \moodle_exception on failure; they never fall back to credits on their own,
 * so a site is never charged credits it did not choose to spend.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai {
    /** Text service. */
    const TEXT = router::TEXT;
    /** Image service. */
    const IMAGE = router::IMAGE;
    /** Voice service. */
    const VOICE = router::VOICE;
    /** Vision service. */
    const VISION = router::VISION;
    /** Video service. */
    const VIDEO = router::VIDEO;

    /** API version plugins can check for new options. */
    const API_VERSION = 1;

    /**
     * Should this component use the site's own AI for a service (rather than LMS Labs credits)?
     *
     * @param string $component
     * @param string $service
     * @return bool
     */
    public static function uses_own_ai(string $component, string $service): bool {
        return router::source($component, $service) !== router::CREDITS;
    }

    /**
     * Generate text. Pass options['images'] to read images (routes through the vision service).
     *
     * @param string $component Calling plugin.
     * @param string $system System / role instructions.
     * @param string $user The request.
     * @param array $options json (bool), maxtokens (int), temperature (float),
     *                       images (list of ['mime' => 'image/png', 'data' => raw bytes]),
     *                       schema (['name' => 'scenario', 'schema' => JSON Schema array]): structured output.
     *                       OpenAI enforces it (strict json_schema), Anthropic answers through a forced tool
     *                       with that input schema, Gemini and the Moodle AI subsystem get it in the prompt.
     *                       Callers still validate the result.
     *                       timeout (int seconds, default 180): for long generations run from a task.
     * @return string The text; with json or schema, a JSON string with any code fences removed.
     */
    public static function text(string $component, string $system, string $user, array $options = []): string {
        $service = !empty($options['images']) ? router::VISION : router::TEXT;
        $source = self::require_source($component, $service);
        $options += ['json' => false, 'maxtokens' => 4096, 'temperature' => null, 'images' => [], 'schema' => null];
        if (!empty($options['schema'])) {
            $options['json'] = true;
            $options['schema'] += ['name' => 'result'];
        }
        $started = microtime(true);
        if ($source === router::MOODLE) {
            $out = provider\core::text($system, $user, $options, $service);
        } else {
            $vendor = router::vendor($service);
            $class = self::vendor_class($vendor);
            $out = $class::text(self::key($vendor), self::model($vendor, 'text'), $system, $user, $options);
        }
        if ($options['json']) {
            $out = self::strip_fences($out);
        }
        usage::log($component, $service, $source, microtime(true) - $started);
        return $out;
    }

    /**
     * Generate one image.
     *
     * @param string $component Calling plugin.
     * @param string $prompt
     * @param array $options size ("1024x1024", "1536x1024" or "1024x1536"), quality ("low"|"medium"|"high").
     * @return string Raw PNG (or JPEG) bytes.
     */
    public static function image(string $component, string $prompt, array $options = []): string {
        $source = self::require_source($component, router::IMAGE);
        $options += ['size' => '1024x1024', 'quality' => 'medium'];
        $started = microtime(true);
        if ($source === router::MOODLE) {
            $out = provider\core::image($prompt, $options);
        } else {
            $vendor = router::vendor(router::IMAGE);
            $out = provider\openai::image(self::key($vendor), self::model($vendor, 'image'), $prompt, $options);
        }
        usage::log($component, router::IMAGE, $source, microtime(true) - $started);
        return $out;
    }

    /**
     * Text to speech.
     *
     * @param string $component Calling plugin.
     * @param string $text
     * @param array $options language (BCP-47, e.g. "en-AU"), voice (vendor voice name or id; blank = admin default),
     *                       format ('mp3', the default, or 'ogg' for Ogg Opus, which some plugins play as audio/ogg),
     *                       rate (speaking rate, 0.25 to 4, 1 = normal; Google and OpenAI voices).
     * @return string Raw audio bytes in the requested format.
     */
    public static function speech(string $component, string $text, array $options = []): string {
        self::require_source($component, router::VOICE);
        $options += ['language' => 'en-AU', 'voice' => '', 'format' => 'mp3'];
        $options['format'] = $options['format'] === 'ogg' ? 'ogg' : 'mp3';
        if (isset($options['rate'])) {
            $options['rate'] = max(0.25, min(4.0, (float) $options['rate']));
        }
        $vendor = router::vendor(router::VOICE);
        $class = self::vendor_class($vendor);
        $started = microtime(true);
        $out = $class::speech(self::key($vendor), self::model($vendor, 'voice'), $text, $options);
        usage::log($component, router::VOICE, router::OWNKEY, microtime(true) - $started);
        return $out;
    }

    /**
     * Check the source is not credits, or throw.
     *
     * @param string $component
     * @param string $service
     * @return string
     */
    protected static function require_source(string $component, string $service): string {
        $source = router::source($component, $service);
        if ($source === router::CREDITS) {
            throw new \moodle_exception('byok_notavailable', 'local_aiconfig', '', $service);
        }
        return $source;
    }

    /**
     * Provider class for a vendor.
     *
     * @param string $vendor
     * @return string
     */
    protected static function vendor_class(string $vendor): string {
        return __NAMESPACE__ . '\\provider\\' . $vendor;
    }

    /**
     * The stored key for a vendor.
     *
     * @param string $vendor
     * @return string
     */
    protected static function key(string $vendor): string {
        return trim((string) get_config('local_aiconfig', 'key_' . router::key_for($vendor)));
    }

    /**
     * The model (or voice model) setting for a vendor and kind, with defaults.
     *
     * @param string $vendor
     * @param string $kind text, image or voice.
     * @return string
     */
    public static function model(string $vendor, string $kind): string {
        $value = trim((string) get_config('local_aiconfig', 'model_' . $vendor . '_' . $kind));
        if ($value !== '') {
            return $value;
        }
        $defaults = [
            'anthropic_text' => 'claude-sonnet-5-5',
            'openai_text' => 'gpt-5.5',
            'openai_image' => 'gpt-image-1',
            'openai_voice' => 'gpt-4o-mini-tts',
            'gemini_text' => 'gemini-2.5-flash',
            'elevenlabs_voice' => 'eleven_multilingual_v2',
            'googletts_voice' => 'Chirp3-HD',
        ];
        return $defaults[$vendor . '_' . $kind] ?? '';
    }

    /**
     * Prompt text asking for JSON in a schema, for providers that cannot enforce one.
     *
     * @param array $schema JSON Schema.
     * @return string
     */
    public static function schema_instruction(array $schema): string {
        return "Reply with one JSON value that matches this JSON Schema exactly (every required property, no others):\n"
            . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Remove ```json fences and text around a JSON value.
     *
     * @param string $text
     * @return string
     */
    public static function strip_fences(string $text): string {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $m)) {
            $text = trim($m[1]);
        }
        $first = strcspn($text, '{[');
        if ($first > 0 && $first < strlen($text)) {
            $text = substr($text, $first);
        }
        return $text;
    }
}
