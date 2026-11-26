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

/**
 * Bring your own AI: licence, per-service sources and keys for every LMS Labs plugin.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_aiconfig\byok\licence;
use local_aiconfig\byok\router;

admin_externalpage_setup('local_aiconfig_byok');
$context = context_system::instance();
require_capability('local/aiconfig:manage', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$pageurl = new moodle_url('/local/aiconfig/byok.php');

if ($action === 'return') {
    // Back from Stripe Checkout. No sesskey: Stripe sends the browser here. Claiming only fetches a key for
    // this site with a secret this site made, so a forged visit can do no more than install the site's own key.
    $payload = licence::claim();
    $pending = (string) get_config('local_aiconfig', 'claimsecret') !== '';
    redirect($pageurl, get_string(
        $payload ? 'byok_claimed' : ($pending ? 'byok_claimpending' : 'byok_checkoutcancelled'),
        'local_aiconfig'
    ), null, $payload ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO);
}

if ($action !== '') {
    require_sesskey();
    switch ($action) {
        case 'subscribe':
            $plan = optional_param('plan', 'all', PARAM_ALPHA);
            redirect(licence::checkout_url(
                optional_param('tier', 'pro', PARAM_ALPHA),
                $plan === 'plugin' ? optional_param('component', '', PARAM_COMPONENT) : ''
            ));
            break;
        case 'claim':
            $payload = licence::claim();
            redirect(
                $pageurl,
                get_string($payload ? 'byok_claimed' : 'byok_claimpending', 'local_aiconfig'),
                null,
                $payload ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_INFO
            );
            break;
        case 'manage':
            $portal = licence::portal_url();
            if ($portal) {
                redirect(new moodle_url($portal));
            }
            redirect(
                $pageurl,
                get_string('byok_portalfailed', 'local_aiconfig'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
            break;
        case 'check':
            $ok = licence::check();
            redirect(
                $pageurl,
                get_string($ok ? 'byok_checked' : 'byok_checkfailed', 'local_aiconfig'),
                null,
                $ok ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_WARNING
            );
            break;
        case 'savekey':
            $key = trim(required_param('licencekey', PARAM_RAW_TRIMMED));
            $payload = $key === '' ? null : licence::install($key);
            if ($payload) {
                licence::check();
            }
            redirect(
                $pageurl,
                get_string($payload ? 'byok_keysaved' : 'byok_keyrefused', 'local_aiconfig'),
                null,
                $payload ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
            );
            break;
        case 'removekey':
            unset_config('licencekey', 'local_aiconfig');
            unset_config('licencecheck', 'local_aiconfig');
            redirect($pageurl, get_string('byok_keyremoved', 'local_aiconfig'));
            break;
        case 'save':
            foreach (router::SOURCES as $service => $sources) {
                $value = optional_param('src_' . $service, router::CREDITS, PARAM_ALPHA);
                set_config('src_' . $service, in_array($value, $sources, true) ? $value : router::CREDITS, 'local_aiconfig');
                if (router::VENDORS[$service]) {
                    $vendor = optional_param('vendor_' . $service, '', PARAM_ALPHA);
                    if (in_array($vendor, router::VENDORS[$service], true)) {
                        set_config('vendor_' . $service, $vendor, 'local_aiconfig');
                    }
                }
            }
            foreach (['anthropic', 'openai', 'gemini', 'elevenlabs'] as $vendor) {
                $value = optional_param('key_' . $vendor, null, PARAM_RAW_TRIMMED);
                // A blank field keeps the saved key; the clear box removes it.
                if (optional_param('clear_' . $vendor, 0, PARAM_BOOL)) {
                    unset_config('key_' . $vendor, 'local_aiconfig');
                } else if ($value !== null && $value !== '') {
                    set_config('key_' . $vendor, clean_param($value, PARAM_NOTAGS), 'local_aiconfig');
                }
            }
            foreach (['voice_googletts', 'voice_elevenlabs', 'voice_openai'] as $name) {
                set_config($name, optional_param($name, '', PARAM_ALPHANUMEXT), 'local_aiconfig');
            }
            redirect($pageurl, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
            break;
    }
}

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('byok_title', 'local_aiconfig'));
$PAGE->set_heading(get_string('byok_title', 'local_aiconfig'));

echo $OUTPUT->header();
// A plugin's settings can link here with ?plugin=<component> to offer its own single-plugin licence.
$forplugin = optional_param('plugin', '', PARAM_COMPONENT);
echo $OUTPUT->render_from_template('local_aiconfig/byok', \local_aiconfig\output\byok_page::export($OUTPUT, $forplugin));
echo $OUTPUT->footer();
