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
 * The LMS Labs "Bring your own AI" licence, shared by every LMS Labs plugin on the site.
 *
 * Same contract as the Completion Coach (docs/licensing-api.md in lmslabs-completion-coach):
 * a key is base64url(JSON payload) + "." + base64url(Ed25519 signature), verified offline.
 * Payload: v, id, site (host), plan, learners, aicredits, byok, products (Frankenstyle components
 * or "*"), sub, term, exp (end of the paid period), iat.
 *
 * Bring your own AI is sold one plugin at a time: US$500 a year plus GST per plugin, no tiers. Each
 * plugin's key lists only that plugin in products, so a site with several plugins holds one key per
 * plugin (config licencekeys, keyed by component). A key from the earlier plans (coach or "All plugins",
 * products "*") stays in licencekey and keeps working.
 *
 * A daily task posts to {licenceurl}/v1/licence/check for every key and stores the replies here, so a
 * lapsed or revoked subscription is noticed. An unreachable licence server never blocks a site before
 * exp + GRACE_DAYS.
 *
 * @package    local_aiconfig
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class licence {
    /**
     * Licence signing public key (raw Ed25519, base64). This is the DEVELOPMENT key shared with the
     * Completion Coach. Release builds swap in the production key; its private half never enters a repo.
     */
    const PUBLIC_KEY = 'xPMpxlKyHZmpIh9wuwG8lqGYGItAwYDKxx3gG80Daos=';

    /** Days a site keeps BYOK after the paid period ends, while renewal or payment catches up. */
    const GRACE_DAYS = 14;

    /** Default licence server. */
    const DEFAULT_URL = 'https://licence.lms-labs.com';

    /** States the licence server reports. */
    const STATES = ['pending', 'active', 'past_due', 'grace', 'cancelled', 'expired', 'revoked'];

    /** Tiers of the earlier "All plugins" plan, by learner count. Only read from older keys; no longer sold. */
    const TIERS = ['starter' => 50, 'pro' => 250, 'rto' => 500, 'enterprise' => 0];

    /** Yearly price of one plugin's Bring your own AI licence, in US dollars, before GST. */
    const PLUGIN_PRICE_USD = 500;

    /** LMS Labs plugins offered on the Bring your own AI page when installed (others appear once they use it). */
    const PLUGINS = ['mod_coursekit', 'quiz_aigrader', 'local_lmslabs_coach'];

    /**
     * Decode and verify a key.
     *
     * @param string $key
     * @param string|null $publickey Override, for tests.
     * @return array|null Payload, or null if the signature does not verify.
     */
    public static function verify(string $key, ?string $publickey = null): ?array {
        $key = trim($key);
        if (substr_count($key, '.') !== 1 || !function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }
        [$body, $sig] = explode('.', $key);
        $payload = self::b64decode($body);
        $signature = self::b64decode($sig);
        $pk = base64_decode($publickey ?? self::public_key(), true);
        if (
            $payload === false || $signature === false || $pk === false
                || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            return null;
        }
        if (!sodium_crypto_sign_verify_detached($signature, $payload, $pk)) {
            return null;
        }
        $data = json_decode($payload, true);
        return is_array($data) ? $data : null;
    }

    /**
     * The public key in use. Tests may set local_aiconfig/testpublickey; nothing else can.
     *
     * @return string
     */
    protected static function public_key(): string {
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            $override = (string) get_config('local_aiconfig', 'testpublickey');
            if ($override !== '') {
                return $override;
            }
        }
        return self::PUBLIC_KEY;
    }

    /**
     * This site's host, as licences are bound to it.
     *
     * @return string
     */
    public static function site_host(): string {
        global $CFG;
        return strtolower((string) parse_url($CFG->wwwroot, PHP_URL_HOST));
    }

    /**
     * Single-plugin keys installed on this site, by component.
     *
     * @return string[]
     */
    public static function plugin_keys(): array {
        $keys = json_decode((string) get_config('local_aiconfig', 'licencekeys'), true);
        return is_array($keys) ? array_filter(array_map('strval', $keys), 'strlen') : [];
    }

    /**
     * Every key installed on this site: the earlier site-wide key (if any) and each plugin's key.
     *
     * @return string[]
     */
    public static function all_keys(): array {
        $legacy = trim((string) get_config('local_aiconfig', 'licencekey'));
        return array_values(array_unique(array_filter(array_merge([$legacy], array_values(self::plugin_keys())), 'strlen')));
    }

    /**
     * The key that applies to a component: its own plugin key, else the site-wide key. With no component,
     * the site-wide key, else the first plugin key.
     *
     * @param string|null $component
     * @return string
     */
    public static function key_for(?string $component): string {
        $plugin = self::plugin_keys();
        if ($component !== null && isset($plugin[$component])) {
            return trim($plugin[$component]);
        }
        $legacy = trim((string) get_config('local_aiconfig', 'licencekey'));
        if ($legacy !== '' || $component !== null) {
            return $legacy;
        }
        return trim((string) (reset($plugin) ?: ''));
    }

    /**
     * The licence server's last answer about a key, only if it was about that key.
     *
     * @param string|null $key Defaults to the site-wide key.
     * @return array|null id, state, exp, graceuntil, renews_at, cancel_at_period_end, checked
     */
    public static function remote_for_current_key(?string $key = null): ?array {
        $key = $key ?? trim((string) get_config('local_aiconfig', 'licencekey'));
        $payload = self::verify($key);
        if (!$payload || empty($payload['id'])) {
            return null;
        }
        $checks = json_decode((string) get_config('local_aiconfig', 'licencechecks'), true);
        $remote = is_array($checks) ? ($checks[$payload['id']] ?? null) : null;
        if (!is_array($remote)) {
            $remote = json_decode((string) get_config('local_aiconfig', 'licencecheck'), true);
        }
        if (!is_array($remote) || ($remote['id'] ?? '') !== $payload['id']) {
            return null;
        }
        return $remote;
    }

    /**
     * Licence status for the site, optionally for one component. Applies the rule in
     * docs/licensing-api.md ("When BYOK is allowed") exactly as every LMS Labs plugin does.
     *
     * @param string|null $component Frankenstyle component to check the products list for, or null for any.
     * @param int|null $now
     * @param string|null $key A specific installed key; by default the key that applies to the component.
     * @return array byok, reason, plan, products, exp, until, subscription, ingrace, state, ending, checked, renews, key
     */
    public static function status(?string $component = null, ?int $now = null, ?string $key = null): array {
        $now = $now ?? time();
        $key = trim($key ?? self::key_for($component));
        $remote = $key === '' ? null : self::remote_for_current_key($key);
        $result = [
            'byok' => false,
            'reason' => 'nokey',
            'plan' => '',
            'products' => [],
            'exp' => 0,
            'until' => 0,
            'subscription' => false,
            'ingrace' => false,
            'state' => (string) ($remote['state'] ?? ''),
            'ending' => !empty($remote['cancel_at_period_end']),
            'checked' => (int) ($remote['checked'] ?? 0),
            'renews' => (int) ($remote['renews_at'] ?? 0),
            'key' => $key !== '',
            'id' => '',
        ];
        if ($key === '') {
            return $result;
        }
        $payload = self::verify($key);
        if (!$payload) {
            $result['reason'] = 'invalid';
            return $result;
        }
        $exp = (int) ($payload['exp'] ?? 0);
        $subscription = !empty($payload['sub']);
        // Subscription keys carry on through the grace period; the licence server may end it sooner.
        $until = $exp ? ($subscription ? $exp + self::GRACE_DAYS * DAYSECS : $exp) : 0;
        if (
            $remote && $exp && in_array($result['state'], ['cancelled', 'expired', 'revoked'], true)
                && !empty($remote['graceuntil'])
        ) {
            $until = min($until, (int) $remote['graceuntil']);
        }
        // Keys from before Bring your own AI (no byok field) keep the own-AI access they always had.
        $byok = array_key_exists('byok', $payload) ? !empty($payload['byok']) : true;
        // No products list means the Completion Coach only.
        $products = array_values(array_map('strval', (array) ($payload['products'] ?? ['local_lmslabs_coach'])));

        $result['id'] = (string) ($payload['id'] ?? '');
        $result['plan'] = (string) ($payload['plan'] ?? '');
        $result['products'] = $products;
        $result['exp'] = $exp;
        $result['until'] = $until;
        $result['subscription'] = $subscription;

        if (strcasecmp((string) ($payload['site'] ?? ''), self::site_host()) !== 0) {
            $result['reason'] = 'wrongsite';
        } else if (!$byok) {
            $result['reason'] = 'nobyok';
        } else if ($result['state'] === 'revoked') {
            $result['reason'] = 'revoked';
        } else if ($until && $until <= $now) {
            $result['reason'] = 'expired';
        } else if ($component !== null && !self::covers($products, $component)) {
            $result['reason'] = 'notcovered';
        } else if ($component === null && !self::covers($products, '*') && !array_diff($products, ['local_lmslabs_coach'])) {
            // A coach-only key unlocks nothing that this page controls.
            $result['reason'] = 'notcovered';
        } else {
            $result['reason'] = '';
            $result['byok'] = true;
            $result['ingrace'] = ($exp && $exp < $now) || $result['state'] === 'past_due';
        }
        return $result;
    }

    /**
     * Does a products list cover a component?
     *
     * @param array $products
     * @param string $component
     * @return bool
     */
    public static function covers(array $products, string $component): bool {
        return in_array('*', $products, true) || ($component !== '*' && in_array($component, $products, true));
    }

    /**
     * Is bring-your-own-AI licensed for a component right now?
     *
     * @param string $component
     * @return bool
     */
    public static function allows(string $component): bool {
        return self::status($component)['byok'];
    }

    /**
     * Licence server base URL, without a trailing slash.
     *
     * @return string
     */
    public static function server_url(): string {
        $url = trim((string) get_config('local_aiconfig', 'licenceurl'));
        return rtrim($url !== '' ? $url : self::DEFAULT_URL, '/');
    }

    /**
     * Ask the licence server about every installed key, and store the replies. Never throws.
     *
     * @return bool True if the server answered for at least one key.
     */
    public static function check(): bool {
        $answered = false;
        foreach (self::all_keys() as $key) {
            $answered = self::check_key($key) || $answered;
        }
        return $answered;
    }

    /**
     * Ask the licence server about one key, store the reply and install any newer key it sends.
     *
     * @param string $key
     * @return bool True if the server answered.
     */
    protected static function check_key(string $key): bool {
        global $CFG;
        $payload = $key !== '' ? self::verify($key) : null;
        if (!$payload || empty($payload['id'])) {
            return false;
        }
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_aiconfig');
        $products = array_values((array) ($payload['products'] ?? []));
        $body = [
            'site' => self::site_host(),
            'component' => count($products) === 1 && $products[0] !== '*' ? (string) $products[0] : 'local_aiconfig',
            'version' => $plugin ? (string) $plugin->release : '',
            'moodle' => (string) ($CFG->release ?? ''),
            'activelearners' => self::active_learners(),
            'aisource' => router::summary(),
        ];
        $reply = self::post('/v1/licence/check', $body, $key);
        if (!is_array($reply) || !in_array($reply['state'] ?? '', self::STATES, true)) {
            return false;
        }
        $store = ['id' => (string) $payload['id'], 'checked' => time()];
        foreach (['state', 'exp', 'graceuntil', 'renews_at', 'cancel_at_period_end'] as $field) {
            $store[$field] = $reply[$field] ?? null;
        }
        $checks = json_decode((string) get_config('local_aiconfig', 'licencechecks'), true);
        $checks = is_array($checks) ? $checks : [];
        $checks[$store['id']] = $store;
        set_config('licencechecks', json_encode($checks), 'local_aiconfig');
        if ($key === trim((string) get_config('local_aiconfig', 'licencekey'))) {
            set_config('licencecheck', json_encode($store), 'local_aiconfig');
        }
        if (!empty($reply['key']) && is_string($reply['key'])) {
            self::install((string) $reply['key']);
        }
        return true;
    }

    /**
     * Install a key after checking its signature and site. Returns the payload, or null if refused.
     *
     * @param string $key
     * @return array|null
     */
    public static function install(string $key): ?array {
        $payload = self::verify($key);
        if (!$payload) {
            return null;
        }
        if (strcasecmp((string) ($payload['site'] ?? ''), self::site_host()) !== 0) {
            return null;
        }
        $component = self::single_component($payload);
        if ($component !== null) {
            $keys = self::plugin_keys();
            $keys[$component] = trim($key);
            set_config('licencekeys', json_encode($keys), 'local_aiconfig');
        } else {
            set_config('licencekey', trim($key), 'local_aiconfig');
        }
        return $payload;
    }

    /**
     * The one plugin a single-plugin key covers, or null for keys from the earlier plans.
     *
     * @param array $payload
     * @return string|null
     */
    public static function single_component(array $payload): ?string {
        $products = array_values((array) ($payload['products'] ?? []));
        if (($payload['plan'] ?? '') !== 'plugin' || count($products) !== 1 || $products[0] === '*') {
            return null;
        }
        $component = clean_param((string) $products[0], PARAM_COMPONENT);
        return $component === (string) $products[0] ? $component : null;
    }

    /**
     * Remove an installed key: a plugin's own key, or the site-wide key when no component is given.
     *
     * @param string|null $component
     * @return void
     */
    public static function remove(?string $component = null): void {
        if ($component === null) {
            unset_config('licencekey', 'local_aiconfig');
            unset_config('licencecheck', 'local_aiconfig');
            return;
        }
        $keys = self::plugin_keys();
        unset($keys[$component]);
        set_config('licencekeys', json_encode($keys), 'local_aiconfig');
    }

    /**
     * Plugins to offer a licence for: the known LMS Labs plugins, plugins that already used Bring your own AI
     * and plugins with a key, limited to those installed here.
     *
     * @param string[] $extra More components to include, such as the plugin that linked to the page.
     * @return string[]
     */
    public static function offered_plugins(array $extra = []): array {
        global $DB;
        $components = array_merge($extra, self::PLUGINS, array_keys(self::plugin_keys()));
        try {
            $components = array_merge($components, $DB->get_fieldset_select('local_aiconfig_byok_usage', 'DISTINCT component', ''));
        } catch (\Throwable $e) {
            debugging('Bring your own AI usage could not be read: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        $offered = [];
        foreach ($components as $component) {
            $component = (string) $component;
            if (
                $component !== '' && clean_param($component, PARAM_COMPONENT) === $component
                    && \core_component::get_component_directory($component) !== null
            ) {
                $offered[$component] = $component;
            }
        }
        return array_values($offered);
    }

    /**
     * Start a checkout for one plugin's yearly Bring your own AI licence (US$500 a year plus GST): store a
     * fresh claim secret and return the checkout URL.
     *
     * @param string $component The plugin's Frankenstyle component, e.g. mod_coursekit.
     * @return \moodle_url
     */
    public static function checkout_url(string $component): \moodle_url {
        $secret = bin2hex(random_bytes(32));
        set_config('claimsecret', $secret, 'local_aiconfig');
        return new \moodle_url(self::server_url() . '/v1/checkout', [
            'site' => self::site_host(),
            'product' => clean_param($component, PARAM_COMPONENT),
            'claim' => hash('sha256', $secret),
            'return' => (new \moodle_url('/local/aiconfig/byok.php', ['action' => 'return']))->out(false),
        ]);
    }

    /**
     * A Stripe billing portal link (card, invoices, plan, cancel), or null.
     *
     * @param string|null $component The plugin whose subscription to manage, or null for the site-wide key.
     * @return string|null
     */
    public static function portal_url(?string $component = null): ?string {
        $key = $component === null ? trim((string) get_config('local_aiconfig', 'licencekey')) : self::key_for($component);
        if ($key === '') {
            return null;
        }
        $reply = self::post('/v1/licence/portal', [
            'return' => (new \moodle_url('/local/aiconfig/byok.php'))->out(false),
        ], $key);
        $url = $reply['url'] ?? '';
        return is_string($url) && strpos($url, 'https://') === 0 ? $url : null;
    }

    /**
     * After payment, fetch the key with the stored claim secret and install it.
     *
     * @return array|null The installed payload, or null.
     */
    public static function claim(): ?array {
        $secret = (string) get_config('local_aiconfig', 'claimsecret');
        if ($secret === '') {
            return null;
        }
        $reply = self::post('/v1/licence/claim', ['site' => self::site_host(), 'secret' => $secret], null);
        if (!is_array($reply) || empty($reply['key']) || !is_string($reply['key'])) {
            return null;
        }
        $payload = self::install($reply['key']);
        if ($payload) {
            unset_config('claimsecret', 'local_aiconfig');
            self::check();
        }
        return $payload;
    }

    /**
     * Distinct users who used an LMS Labs plugin this month. Reported, never enforced here.
     *
     * @return int
     */
    protected static function active_learners(): int {
        global $DB;
        $since = time() - 30 * DAYSECS;
        try {
            $mod = $DB->sql_like('component', ':mod');
            $local = $DB->sql_like('component', ':local');
            return (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
                  WHERE timecreated > :since AND ($mod OR $local OR component = :grader)",
                ['since' => $since, 'mod' => 'mod\\_ai%', 'local' => 'local\\_ai%', 'grader' => 'quiz_aigrader']
            );
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * POST JSON to the licence server.
     *
     * @param string $path
     * @param array $body
     * @param string|null $key Bearer licence key.
     * @return array|null Decoded reply, or null.
     */
    protected static function post(string $path, array $body, ?string $key): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST && isset($GLOBALS['local_aiconfig_licence_mock'])) {
            return ($GLOBALS['local_aiconfig_licence_mock'])($path, $body, $key);
        }
        $curl = new \curl(['ignoresecurity' => false]);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'X-LMSLabs-Site: ' . self::site_host()];
        if ($key) {
            $headers[] = 'Authorization: Bearer ' . $key;
        }
        $curl->setHeader($headers);
        try {
            $raw = $curl->post(self::server_url() . $path, json_encode($body), ['CURLOPT_TIMEOUT' => 20]);
        } catch (\Throwable $e) {
            return null;
        }
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        if ($curl->get_errno() || $code < 200 || $code >= 300) {
            return null;
        }
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Base64url decode.
     *
     * @param string $s
     * @return string|false
     */
    protected static function b64decode(string $s) {
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad) {
            $s .= str_repeat('=', 4 - $pad);
        }
        return base64_decode($s, true);
    }
}
