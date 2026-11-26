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

use local_aiconfig\byok\router;

/**
 * The site's Moodle AI subsystem (Site administration > General > AI), Moodle 4.5 and later.
 *
 * Where the site has an enabled Anthropic, OpenAI or Gemini provider, calls go straight to that vendor
 * with the provider's own key and model, because core actions cannot carry a system prompt, JSON mode
 * or images. Any other provider (Azure, Bedrock, Ollama, DeepSeek) is reached through core's
 * generate_text and generate_image actions, so every provider Moodle supports works.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core {
    /** Core providers this plugin can call natively, by plugin name. */
    const NATIVE = ['aiprovider_anthropic' => 'anthropic', 'aiprovider_openai' => 'openai', 'aiprovider_gemini' => 'gemini'];

    /**
     * Can Moodle AI serve a service on this site?
     *
     * @param string $service
     * @return bool
     */
    public static function available(string $service): bool {
        if (!class_exists('\core_ai\manager')) {
            return false;
        }
        switch ($service) {
            case router::TEXT:
                return self::native(router::TEXT) !== null || self::action_ready('generate_text');
            case router::VISION:
                return self::native(router::VISION) !== null;
            case router::IMAGE:
                return self::action_ready('generate_image');
        }
        return false;
    }

    /**
     * Generate text with Moodle AI.
     *
     * @param string $system
     * @param string $user
     * @param array $options
     * @param string $service text or vision
     * @return string
     */
    public static function text(string $system, string $user, array $options, string $service): string {
        $native = self::native($service);
        if ($native) {
            $class = __NAMESPACE__ . '\\' . $native['vendor'];
            if (!empty($native['endpoint'])) {
                $options['endpoint'] = $native['endpoint'];
            }
            if (!empty($native['orgid'])) {
                $options['orgid'] = $native['orgid'];
            }
            return $class::text($native['apikey'], $native['model'], $system, $user, $options);
        }
        if ($service === router::VISION) {
            throw new \moodle_exception('byok_notavailable', 'local_aiconfig', '', $service);
        }
        $prompt = trim($system . "\n\n" . $user);
        if (!empty($options['json'])) {
            $prompt .= "\n\nReply with valid JSON only, with no text before or after it.";
        }
        $action = new \core_ai\aiactions\generate_text(
            \context_system::instance()->id,
            self::userid(),
            $prompt,
        );
        $response = self::manager()->process_action($action);
        if (!$response->get_success()) {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => 'Moodle AI', 'detail' => s($response->get_errormessage()),
            ]);
        }
        return (string) ($response->get_response_data()['generatedcontent'] ?? '');
    }

    /**
     * Generate an image with Moodle AI.
     *
     * @param string $prompt
     * @param array $options size, quality
     * @return string Raw bytes.
     */
    public static function image(string $prompt, array $options): string {
        [$w, $h] = array_map('intval', explode('x', $options['size'] ?? '1024x1024') + [1024, 1024]);
        $ratio = $w > $h ? 'landscape' : ($h > $w ? 'portrait' : 'square');
        $action = new \core_ai\aiactions\generate_image(
            \context_system::instance()->id,
            self::userid(),
            $prompt,
            ($options['quality'] ?? '') === 'high' ? 'hd' : 'standard',
            $ratio,
            1,
            'natural',
        );
        $response = self::manager()->process_action($action);
        $file = $response->get_success() ? ($response->get_response_data()['draftfile'] ?? null) : null;
        if (!$file instanceof \stored_file) {
            throw new \moodle_exception('byok_callfailed', 'local_aiconfig', '', (object) [
                'vendor' => 'Moodle AI', 'detail' => s($response->get_errormessage() ?: 'no image'),
            ]);
        }
        $bytes = $file->get_content();
        $file->delete();
        return $bytes;
    }

    /**
     * The first enabled native-capable core provider: vendor, apikey, model, endpoint, orgid, name.
     *
     * @param string $service text or vision
     * @return array|null
     */
    public static function native(string $service): ?array {
        if (!class_exists('\core_ai\manager')) {
            return null;
        }
        $action = 'core_ai\aiactions\generate_text';
        try {
            if (class_exists('\core\di') && method_exists('\core_ai\manager', 'get_provider_instances')) {
                // Moodle 5.0+: provider instances with their own config.
                $manager = \core\di::get(\core_ai\manager::class);
                foreach ($manager->get_sorted_providers() as $instance) {
                    $plugin = explode('\\', get_class($instance))[0];
                    if (empty($instance->enabled) || !isset(self::NATIVE[$plugin]) || empty($instance->config['apikey'])) {
                        continue;
                    }
                    $settings = $instance->actionconfig[$action]['settings'] ?? [];
                    if (isset($instance->actionconfig[$action]['enabled']) && !$instance->actionconfig[$action]['enabled']) {
                        continue;
                    }
                    $vendor = self::NATIVE[$plugin];
                    return [
                        'vendor' => $vendor,
                        'name' => (string) ($instance->name ?? $plugin),
                        'apikey' => (string) $instance->config['apikey'],
                        'orgid' => (string) ($instance->config['orgid'] ?? ''),
                        'model' => (string) (($settings['model'] ?? '') ?: \local_aiconfig\byok\ai::model($vendor, 'text')),
                        'endpoint' => $vendor === 'gemini' ? '' : (string) ($settings['endpoint'] ?? ''),
                    ];
                }
                return null;
            }
            // Moodle 4.5: one OpenAI provider configured in plugin settings.
            if (\core_ai\manager::is_action_enabled('aiprovider_openai', $action)
                    && ($key = (string) get_config('aiprovider_openai', 'apikey')) !== '') {
                return [
                    'vendor' => 'openai',
                    'name' => 'OpenAI',
                    'apikey' => $key,
                    'orgid' => (string) get_config('aiprovider_openai', 'orgid'),
                    'model' => (string) (get_config('aiprovider_openai', 'action_generate_text_model')
                        ?: \local_aiconfig\byok\ai::model('openai', 'text')),
                    'endpoint' => (string) get_config('aiprovider_openai', 'action_generate_text_endpoint'),
                ];
            }
        } catch (\Throwable $e) {
            debugging('local_aiconfig could not read Moodle AI providers: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return null;
    }

    /**
     * Is a core action enabled with at least one enabled provider?
     *
     * @param string $basename generate_text or generate_image
     * @return bool
     */
    public static function action_ready(string $basename): bool {
        $class = 'core_ai\\aiactions\\' . $basename;
        try {
            $manager = self::manager();
            if (method_exists($manager, 'is_action_available') && !$manager->is_action_available($class)) {
                return false;
            }
            $providers = $manager->get_providers_for_actions([$class], true);
            return !empty($providers[$class]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The AI manager on 4.5 (plain object) or 5.0+ (dependency injection).
     *
     * @return \core_ai\manager
     */
    protected static function manager() {
        if (class_exists('\core\di')) {
            return \core\di::get(\core_ai\manager::class);
        }
        return new \core_ai\manager();
    }

    /**
     * User the call is logged against: the current user, or the admin for cron and CLI.
     *
     * @return int
     */
    protected static function userid(): int {
        global $USER;
        return !empty($USER->id) ? (int) $USER->id : (int) get_admin()->id;
    }
}
