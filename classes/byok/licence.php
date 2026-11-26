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
 * A daily task posts to {licenceurl}/v1/licence/check and stores the reply here, so a lapsed or
 * revoked subscription is noticed. An unreachable licence server never blocks a site before
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
    const DEFAULT_URL = 'https://lms-labs.com/licensing';

    /** Server states that end BYOK at once. */
    const DEAD_STATES = ['revoked'];

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
     * Licence status for the site, optionally for one component.
     *
     * @param string|null $component Frankenstyle component to check the products list for, or null for any.
     * @param int|null $now
     * @return array byok (bool), reason, plan, products, exp, graceuntil, ingrace, state, checked, key (bool)
     */
    public static function status(?string $component = null, ?int $now = null): array {
        $now = $now ?? time();
        $key = trim((string) get_config('local_aiconfig', 'licencekey'));
        $check = json_decode((string) get_config('local_aiconfig', 'licencecheck'), true) ?: [];
        $result = [
            'byok' => false,
            'reason' => 'nokey',
            'plan' => '',
            'products' => [],
            'exp' => 0,
            'graceuntil' => 0,
            'ingrace' => false,
            'state' => (string) ($check['state'] ?? ''),
            'checked' => (int) get_config('local_aiconfig', 'licencechecked'),
            'renews' => (int) ($check['renews_at'] ?? 0),
            'key' => $key !== '',
        ];
        if ($key === '') {
            return $result;
        }
        $payload = self::verify($key);
        if (!$payload) {
            $result['reason'] = 'invalid';
            return $result;
        }
        $result['plan'] = (string) ($payload['plan'] ?? '');
        $result['products'] = array_values(array_map('strval', (array) ($payload['products'] ?? [])));
        // The server's later period end wins: a renewal moves exp before the new key arrives.
        $exp = max((int) ($payload['exp'] ?? 0), (int) ($check['exp'] ?? 0));
        $result['exp'] = $exp;
        $grace = max($exp + self::GRACE_DAYS * DAYSECS, (int) ($check['graceuntil'] ?? 0));
        $result['graceuntil'] = $grace;

        if (!empty($payload['site']) && strcasecmp((string) $payload['site'], self::site_host()) !== 0) {
            $result['reason'] = 'wrongsite';
        } else if (empty($payload['byok'])) {
            $result['reason'] = 'nobyok';
        } else if ($component !== null && !self::covers($result['products'], $component)) {
            $result['reason'] = 'notcovered';
        } else if (in_array($result['state'], self::DEAD_STATES, true)) {
            $result['reason'] = 'revoked';
        } else if ($exp > 0 && $now >= $grace) {
            $result['reason'] = 'expired';
        } else {
            $result['reason'] = '';
            $result['byok'] = true;
            $result['ingrace'] = ($exp > 0 && $now >= $exp) || in_array($result['state'], ['past_due', 'grace'], true);
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
        return in_array('*', $products, true) || in_array($component, $products, true);
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
     * Ask the licence server about the stored key, and store the reply. Never throws.
     *
     * @return bool True if the server answered.
     */
    public static function check(): bool {
        global $CFG;
        $key = trim((string) get_config('local_aiconfig', 'licencekey'));
        if ($key === '') {
            return false;
        }
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_aiconfig');
        $body = [
            'site' => self::site_host(),
            'component' => 'local_aiconfig',
            'version' => $plugin ? (string) $plugin->release : '',
            'moodle' => (string) ($CFG->release ?? ''),
            'activelearners' => self::active_learners(),
            'aisource' => router::summary(),
        ];
        $reply = self::post('/v1/licence/check', $body, $key);
        if (!is_array($reply) || empty($reply['state'])) {
            return false;
        }
        $store = [
            'state' => (string) $reply['state'],
            'exp' => (int) ($reply['exp'] ?? 0),
            'graceuntil' => (int) ($reply['graceuntil'] ?? 0),
            'renews_at' => (int) ($reply['renews_at'] ?? 0),
        ];
        set_config('licencecheck', json_encode($store), 'local_aiconfig');
        set_config('licencechecked', time(), 'local_aiconfig');
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
        if (!empty($payload['site']) && strcasecmp((string) $payload['site'], self::site_host()) !== 0) {
            return null;
        }
        set_config('licencekey', trim($key), 'local_aiconfig');
        return $payload;
    }

    /**
     * Start a checkout: store a fresh claim secret and return the URL of the subscribe page.
     *
     * @param string $plan Plan hint for the checkout page, e.g. "all".
     * @return \moodle_url
     */
    public static function checkout_url(string $plan = 'all'): \moodle_url {
        $secret = bin2hex(random_bytes(32));
        set_config('claimsecret', $secret, 'local_aiconfig');
        $return = new \moodle_url('/local/aiconfig/claim.php');
        return new \moodle_url(self::server_url() . '/v1/checkout', [
            'site' => self::site_host(),
            'product' => $plan === 'all' ? '*' : $plan,
            'return' => $return->out(false),
            'claim' => hash('sha256', $secret),
        ]);
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
        $reply = self::post('/v1/licence/claim', ['site' => self::site_host(), 'claim_secret' => $secret], null);
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
