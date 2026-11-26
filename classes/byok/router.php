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
 * Decides, per AI service, where an LMS Labs plugin's AI calls go.
 *
 * Services: text, image, voice, vision (reading images), video. Sources:
 * - credits: LMS Labs AI credits, through lms-labs.com (the default, and the only source for video).
 * - moodle:  the site's Moodle AI providers (Site administration > General > AI).
 * - ownkey:  a key for a named vendor (Anthropic, OpenAI, Google, ElevenLabs) entered once here.
 *
 * Anything but credits needs a "Bring your own AI" licence covering the calling component.
 * Moodle's AI subsystem has no voice or video actions, so voice can only use ownkey or credits.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class router {
    /** Text generation: prompts, marking, feedback, chat, SVG markup. */
    const TEXT = 'text';
    /** Image generation. */
    const IMAGE = 'image';
    /** Text to speech. */
    const VOICE = 'voice';
    /** Reading images: photos, scans, face checks. */
    const VISION = 'vision';
    /** Video generation. */
    const VIDEO = 'video';

    /** Credits source. */
    const CREDITS = 'credits';
    /** Moodle AI subsystem source. */
    const MOODLE = 'moodle';
    /** Own vendor key source. */
    const OWNKEY = 'ownkey';

    /** Which sources each service can use. */
    const SOURCES = [
        self::TEXT => [self::CREDITS, self::MOODLE, self::OWNKEY],
        self::IMAGE => [self::CREDITS, self::MOODLE, self::OWNKEY],
        self::VOICE => [self::CREDITS, self::OWNKEY],
        self::VISION => [self::CREDITS, self::MOODLE, self::OWNKEY],
        self::VIDEO => [self::CREDITS],
    ];

    /** Which own-key vendors each service can use. */
    const VENDORS = [
        self::TEXT => ['anthropic', 'openai', 'gemini'],
        self::IMAGE => ['openai'],
        self::VOICE => ['googletts', 'elevenlabs', 'openai'],
        self::VISION => ['anthropic', 'openai', 'gemini'],
        self::VIDEO => [],
    ];

    /**
     * The configured source for a service, before licence and readiness checks.
     *
     * @param string $service
     * @return string
     */
    public static function configured(string $service): string {
        $value = (string) get_config('local_aiconfig', 'src_' . $service);
        return in_array($value, self::SOURCES[$service] ?? [], true) ? $value : self::CREDITS;
    }

    /**
     * The own-key vendor chosen for a service, if its key is set.
     *
     * @param string $service
     * @return string|null
     */
    public static function vendor(string $service): ?string {
        $vendor = (string) get_config('local_aiconfig', 'vendor_' . $service);
        $allowed = self::VENDORS[$service] ?? [];
        if (!in_array($vendor, $allowed, true)) {
            $vendor = $allowed[0] ?? '';
        }
        if ($vendor === '' || trim((string) get_config('local_aiconfig', 'key_' . self::key_for($vendor))) === '') {
            return null;
        }
        return $vendor;
    }

    /**
     * The key setting a vendor reads. Gemini and Google voices share one Google key.
     *
     * @param string $vendor
     * @return string
     */
    public static function key_for(string $vendor): string {
        return $vendor === 'googletts' ? 'gemini' : $vendor;
    }

    /**
     * Where a component's calls for a service actually go now.
     *
     * Returns credits whenever the licence, the setting or the source isn't ready, so a plugin that
     * gets "credits" back simply keeps doing what it did before.
     *
     * @param string $component Frankenstyle name of the calling plugin.
     * @param string $service
     * @return string
     */
    public static function source(string $component, string $service): string {
        $source = self::configured($service);
        if ($source === self::CREDITS || !licence::allows($component)) {
            return self::CREDITS;
        }
        return self::ready($service, $source) ? $source : self::CREDITS;
    }

    /**
     * Is a source usable for a service right now (keys or providers present)?
     *
     * @param string $service
     * @param string $source
     * @return bool
     */
    public static function ready(string $service, string $source): bool {
        switch ($source) {
            case self::CREDITS:
                return true;
            case self::OWNKEY:
                return self::vendor($service) !== null;
            case self::MOODLE:
                return provider\core::available($service);
        }
        return false;
    }

    /**
     * One-line summary for the licence check, e.g. "text:moodle,image:ownkey,voice:credits".
     *
     * @return string
     */
    public static function summary(): string {
        $parts = [];
        foreach (array_keys(self::SOURCES) as $service) {
            $parts[] = $service . ':' . self::configured($service);
        }
        return implode(',', $parts);
    }
}
