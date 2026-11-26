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

namespace local_aiconfig\output;

use local_aiconfig\byok\licence;
use local_aiconfig\byok\router;
use local_aiconfig\byok\usage;
use local_aiconfig\byok\provider\core;

/**
 * Template context for the "Bring your own AI" page.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok_page {
    /** Font Awesome icon per service. */
    const ICONS = [
        router::TEXT => 'fa-align-left',
        router::IMAGE => 'fa-image',
        router::VOICE => 'fa-microphone-lines',
        router::VISION => 'fa-eye',
        router::VIDEO => 'fa-film',
    ];

    /** Vendor display names. */
    const VENDORS = [
        'anthropic' => 'Anthropic Claude',
        'openai' => 'OpenAI',
        'gemini' => 'Google Gemini',
        'googletts' => 'Google Chirp 3 HD voices',
        'elevenlabs' => 'ElevenLabs',
    ];

    /**
     * Build the context.
     *
     * @param \renderer_base $output
     * @return array
     */
    public static function export(\renderer_base $output): array {
        $status = licence::status();
        $sesskey = sesskey();
        $url = (new \moodle_url('/local/aiconfig/byok.php'))->out(false);
        $fmt = get_string('strftimedate', 'langconfig');

        $services = [];
        foreach (router::SOURCES as $service => $sources) {
            $current = router::configured($service);
            $options = [];
            foreach ($sources as $source) {
                $ready = router::ready($service, $source);
                $options[] = [
                    'value' => $source,
                    'id' => 'src_' . $service . '_' . $source,
                    'label' => get_string('byok_source_' . $source, 'local_aiconfig'),
                    'detail' => self::source_detail($service, $source, $ready),
                    'checked' => $current === $source,
                    'notready' => !$ready,
                    'icon' => ['credits' => 'fa-coins', 'moodle' => 'fa-graduation-cap', 'ownkey' => 'fa-key'][$source],
                ];
            }
            $vendors = [];
            $chosen = (string) get_config('local_aiconfig', 'vendor_' . $service) ?: (router::VENDORS[$service][0] ?? '');
            foreach (router::VENDORS[$service] as $vendor) {
                $vendors[] = ['value' => $vendor, 'label' => self::VENDORS[$vendor], 'selected' => $vendor === $chosen];
            }
            $effective = $status['byok'] && $current !== router::CREDITS && router::ready($service, $current);
            $services[] = [
                'service' => $service,
                'name' => get_string('byok_service_' . $service, 'local_aiconfig'),
                'help' => get_string('byok_service_' . $service . '_help', 'local_aiconfig'),
                'icon' => self::ICONS[$service],
                'options' => $options,
                'hasvendors' => !empty($vendors),
                'vendors' => $vendors,
                'creditsonly' => count($sources) === 1,
                'effective' => get_string('byok_effective_' . ($effective ? $current : 'credits'), 'local_aiconfig'),
                'isown' => $effective,
            ];
        }

        $keys = [];
        foreach (['anthropic' => 'Anthropic', 'openai' => 'OpenAI', 'gemini' => 'Google (Gemini and voices)',
                'elevenlabs' => 'ElevenLabs'] as $vendor => $label) {
            $saved = trim((string) get_config('local_aiconfig', 'key_' . $vendor));
            $keys[] = [
                'vendor' => $vendor,
                'label' => $label,
                'saved' => $saved !== '',
                'hint' => $saved !== '' ? get_string('byok_keyhint', 'local_aiconfig', substr($saved, -4)) : '',
            ];
        }

        $usage = array_map(function($row) {
            return [
                'component' => $row->component,
                'name' => get_string_manager()->string_exists('pluginname', $row->component)
                    ? get_string('pluginname', $row->component) : $row->component,
                'service' => get_string('byok_service_' . $row->service, 'local_aiconfig'),
                'source' => get_string('byok_source_' . $row->source, 'local_aiconfig'),
                'calls' => (int) $row->calls,
            ];
        }, usage::recent(30));
        usort($usage, function($a, $b) {
            return $b['calls'] <=> $a['calls'];
        });

        if ($status['byok']) {
            if ($status['exp'] && $status['exp'] < time()) {
                $statekey = 'grace';
            } else if ($status['state'] === 'past_due') {
                $statekey = 'past_due';
            } else if ($status['ending']) {
                $statekey = 'ending';
            } else {
                $statekey = 'active';
            }
        } else {
            $statekey = $status['key'] ? $status['reason'] : 'none';
        }
        $tiers = [];
        foreach (licence::TIERS as $tier => $learners) {
            $tiers[] = [
                'tier' => $tier,
                'name' => get_string('byok_tier_' . $tier, 'local_aiconfig'),
                'detail' => $learners ? get_string('byok_tier_learners', 'local_aiconfig', $learners)
                    : get_string('byok_tier_unlimited', 'local_aiconfig'),
                'checked' => $tier === 'pro',
            ];
        }
        $claimpending = (string) get_config('local_aiconfig', 'claimsecret') !== '';
        return [
            'url' => $url,
            'sesskey' => $sesskey,
            'state' => $statekey,
            'statelabel' => get_string('byok_state_' . $statekey, 'local_aiconfig'),
            'statedesc' => get_string('byok_statedesc_' . $statekey, 'local_aiconfig', (object) [
                'exp' => $status['exp'] ? userdate($status['exp'], $fmt) : '',
                'grace' => $status['until'] ? userdate($status['until'], $fmt) : '',
                'site' => licence::site_host(),
            ]),
            'badge' => ['active' => 'success', 'grace' => 'warning', 'past_due' => 'warning', 'ending' => 'warning',
                'revoked' => 'danger', 'expired' => 'danger', 'invalid' => 'danger', 'wrongsite' => 'danger'][$statekey]
                ?? 'secondary',
            'active' => $status['byok'],
            'ingrace' => $status['byok'] && $statekey !== 'active',
            'subscription' => $status['subscription'],
            'tiers' => $tiers,
            'claimpending' => $claimpending && !$status['byok'],
            'haskey' => $status['key'],
            'plan' => $status['plan'] === '' ? '' : ucfirst($status['plan']),
            'allplugins' => in_array('*', $status['products'], true),
            'renews' => $status['renews'] ? userdate($status['renews'], $fmt) : '',
            'exp' => $status['exp'] ? userdate($status['exp'], $fmt) : '',
            'keyid' => $status['id'],
            'checked' => $status['checked'] ? userdate($status['checked'], get_string('strftimedatetimeshort', 'langconfig')) : '',
            'services' => $services,
            'keys' => $keys,
            'voice_googletts' => (string) get_config('local_aiconfig', 'voice_googletts'),
            'voice_elevenlabs' => (string) get_config('local_aiconfig', 'voice_elevenlabs'),
            'voice_openai' => (string) get_config('local_aiconfig', 'voice_openai'),
            'usage' => $usage,
            'hasusage' => !empty($usage),
            'moodleai' => (new \moodle_url('/admin/settings.php', ['section' => 'aiprovider']))->out(false),
            'hasmoodleai' => class_exists('\core_ai\manager'),
        ];
    }

    /**
     * One line under a source option saying whether it is ready.
     *
     * @param string $service
     * @param string $source
     * @param bool $ready
     * @return string
     */
    protected static function source_detail(string $service, string $source, bool $ready): string {
        if ($source === router::CREDITS) {
            return get_string('byok_detail_credits', 'local_aiconfig');
        }
        if ($source === router::OWNKEY) {
            $vendor = router::vendor($service);
            return $vendor ? get_string('byok_detail_ownkey', 'local_aiconfig', self::VENDORS[$vendor])
                : get_string('byok_detail_ownkey_missing', 'local_aiconfig');
        }
        if (!class_exists('\core_ai\manager')) {
            return get_string('byok_detail_moodle_old', 'local_aiconfig');
        }
        if (!$ready) {
            return get_string('byok_detail_moodle_missing_' . $service, 'local_aiconfig');
        }
        $native = $service === router::IMAGE ? null : core::native($service);
        return $native ? get_string('byok_detail_moodle_native', 'local_aiconfig', s($native['name']))
            : get_string('byok_detail_moodle', 'local_aiconfig');
    }
}
