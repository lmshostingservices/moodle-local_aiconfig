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

namespace local_aiconfig;

use local_aiconfig\byok\ai;
use local_aiconfig\byok\licence;
use local_aiconfig\byok\router;

/**
 * Tests for the Bring your own AI licence, routing and vendor calls.
 *
 * @package    local_aiconfig
 * @category   test
 * @copyright  2026 LMS Labs <support@lmshostingservices.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(licence::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(router::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(ai::class)]
final class byok_test extends \advanced_testcase {
    /** @var string Secret half of the test key pair. */
    protected $secret;

    /**
     * Make a test key pair and point the licence at it.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $pair = sodium_crypto_sign_keypair();
        $this->secret = sodium_crypto_sign_secretkey($pair);
        set_config('testpublickey', base64_encode(sodium_crypto_sign_publickey($pair)), 'local_aiconfig');
        unset($GLOBALS['local_aiconfig_byok_mock']);
    }

    /**
     * Clean up the HTTP mock.
     */
    protected function tearDown(): void {
        unset($GLOBALS['local_aiconfig_byok_mock'], $GLOBALS['local_aiconfig_licence_mock']);
        parent::tearDown();
    }

    /**
     * Sign a payload with the test key.
     *
     * @param array $overrides
     * @return string
     */
    protected function key(array $overrides = []): string {
        return $this->signed(array_filter($overrides + [
            'v' => 1, 'id' => 'lic_test', 'site' => licence::site_host(), 'plan' => 'all', 'learners' => 0,
            'aicredits' => false, 'byok' => true, 'products' => ['*'], 'sub' => 'sub_test', 'term' => 'year',
            'exp' => time() + 300 * DAYSECS, 'iat' => time(),
        ], function($v) {
            return $v !== null;
        }));
    }

    /**
     * Sign an exact payload with the test key.
     *
     * @param array $data
     * @return string
     */
    protected function signed(array $data): string {
        $payload = json_encode($data);
        $b64 = function(string $s): string {
            return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        };
        return $b64($payload) . '.' . $b64(sodium_crypto_sign_detached($payload, $this->secret));
    }

    public function test_no_key_means_credits(): void {
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        $this->assertSame('nokey', licence::status('mod_example')['reason']);
        $this->assertSame(router::CREDITS, router::source('mod_example', router::TEXT));
        $this->assertFalse(ai::uses_own_ai('mod_example', ai::TEXT));
    }

    public function test_valid_key_unlocks_configured_service(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        $this->assertSame(router::CREDITS, router::source('mod_example', router::TEXT), 'No key saved yet.');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        $this->assertSame(router::OWNKEY, router::source('mod_example', router::TEXT));
        $this->assertSame(router::CREDITS, router::source('mod_example', router::IMAGE), 'Images still on credits.');
    }

    public function test_tampered_or_foreign_keys_refused(): void {
        $key = $this->key();
        [$body, $sig] = explode('.', $key);
        set_config('licencekey', $body . 'x.' . $sig, 'local_aiconfig');
        $this->assertSame('invalid', licence::status()['reason']);
        set_config('licencekey', $this->key(['site' => 'other.example.com']), 'local_aiconfig');
        $this->assertSame('wrongsite', licence::status()['reason']);
        $this->assertNull(licence::install($this->key(['site' => 'other.example.com'])));
        set_config('licencekey', $this->key(['byok' => false]), 'local_aiconfig');
        $this->assertSame('nobyok', licence::status()['reason']);
    }

    public function test_products_must_cover_component(): void {
        set_config('licencekey', $this->key(['products' => ['local_lmslabs_coach']]), 'local_aiconfig');
        $this->assertFalse(licence::allows('mod_example'));
        $this->assertSame('notcovered', licence::status('mod_example')['reason']);
        $this->assertTrue(licence::allows('local_lmslabs_coach'));
        set_config('licencekey', $this->key(['products' => ['mod_example']]), 'local_aiconfig');
        $this->assertTrue(licence::allows('mod_example'));
    }

    public function test_grace_period_then_expiry(): void {
        $exp = time() - 3 * DAYSECS;
        set_config('licencekey', $this->key(['exp' => $exp]), 'local_aiconfig');
        $status = licence::status('mod_example');
        $this->assertTrue($status['byok']);
        $this->assertTrue($status['ingrace']);
        $later = licence::status('mod_example', $exp + licence::GRACE_DAYS * DAYSECS + 1);
        $this->assertFalse($later['byok']);
        $this->assertSame('expired', $later['reason']);
    }

    public function test_keys_without_sub_get_no_grace(): void {
        $exp = time() - DAYSECS;
        set_config('licencekey', $this->key(['exp' => $exp, 'sub' => null]), 'local_aiconfig');
        $this->assertSame('expired', licence::status('mod_example')['reason']);
    }

    public function test_keys_from_before_byok_keep_access(): void {
        $payload = ['v' => 1, 'id' => 'lic_old', 'site' => licence::site_host(), 'plan' => 'pro',
            'products' => ['*'], 'exp' => time() + DAYSECS];
        set_config('licencekey', $this->signed($payload), 'local_aiconfig');
        $this->assertTrue(licence::allows('mod_example'));
        unset($payload['products']);
        set_config('licencekey', $this->signed($payload), 'local_aiconfig');
        $this->assertFalse(licence::allows('mod_example'), 'No products list means the coach only.');
        $this->assertTrue(licence::allows('local_lmslabs_coach'));
    }

    public function test_daily_check_stores_answer_installs_renewal_and_honours_revocation(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        $renewed = $this->key(['exp' => time() + 700 * DAYSECS]);
        $calls = [];
        $GLOBALS['local_aiconfig_licence_mock'] = function($path, $body, $key) use (&$calls, $renewed) {
            $calls[] = [$path, $body, $key];
            return ['state' => 'active', 'exp' => time() + 700 * DAYSECS, 'key' => $renewed];
        };
        $this->assertTrue(licence::check());
        $this->assertSame('/v1/licence/check', $calls[0][0]);
        $this->assertSame('local_aiconfig', $calls[0][1]['component']);
        $this->assertSame($renewed, get_config('local_aiconfig', 'licencekey'));
        $this->assertSame('active', licence::status()['state']);

        $GLOBALS['local_aiconfig_licence_mock'] = function() {
            return ['state' => 'revoked'];
        };
        licence::check();
        $this->assertSame('revoked', licence::status('mod_example')['reason']);

        // An answer about another key id is ignored.
        set_config('licencekey', $this->key(['id' => 'lic_new']), 'local_aiconfig');
        $this->assertTrue(licence::allows('mod_example'));
        unset($GLOBALS['local_aiconfig_licence_mock']);
    }

    public function test_cancelled_answer_ends_grace_early(): void {
        $exp = time() - DAYSECS;
        set_config('licencekey', $this->key(['exp' => $exp]), 'local_aiconfig');
        $this->assertTrue(licence::allows('mod_example'), 'Inside the 14 days.');
        set_config('licencecheck', json_encode(['id' => 'lic_test', 'state' => 'cancelled', 'graceuntil' => time() - 60]),
            'local_aiconfig');
        $this->assertSame('expired', licence::status('mod_example')['reason']);
    }

    public function test_claim_after_checkout(): void {
        $url = licence::checkout_url('rto');
        $this->assertSame('all', $url->get_param('product'));
        $this->assertSame('rto', $url->get_param('tier'));
        $secret = get_config('local_aiconfig', 'claimsecret');
        $key = $this->key();
        $GLOBALS['local_aiconfig_licence_mock'] = function($path, $body) use ($secret, $key) {
            if ($path === '/v1/licence/claim') {
                return $body['secret'] === $secret ? ['key' => $key, 'state' => 'active'] : null;
            }
            return ['state' => 'active'];
        };
        $this->assertNotNull(licence::claim());
        $this->assertSame($key, get_config('local_aiconfig', 'licencekey'));
        $this->assertFalse(get_config('local_aiconfig', 'claimsecret'));
        unset($GLOBALS['local_aiconfig_licence_mock']);
    }

    public function test_voice_cannot_use_moodle_ai_and_video_is_credits_only(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_voice', 'moodle', 'local_aiconfig');
        set_config('src_video', 'ownkey', 'local_aiconfig');
        $this->assertSame(router::CREDITS, router::configured(router::VOICE));
        $this->assertSame(router::CREDITS, router::configured(router::VIDEO));
    }

    public function test_text_calls_vendor_with_system_prompt_and_counts_usage(): void {
        global $DB;
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        $seen = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use (&$seen) {
            $seen = ['url' => $url, 'headers' => $headers, 'body' => $body];
            return ['content' => [['type' => 'text', 'text' => "```json\n{\"ok\":true}\n```"]]];
        };
        $out = ai::text('mod_example', 'You mark essays.', 'Mark this.', ['json' => true, 'maxtokens' => 100]);
        $this->assertSame('{"ok":true}', $out);
        $this->assertSame(\local_aiconfig\byok\provider\anthropic::ENDPOINT, $seen['url']);
        $this->assertContains('x-api-key: sk-test', $seen['headers']);
        $this->assertStringStartsWith('You mark essays.', $seen['body']['system']);
        $this->assertSame(100, $seen['body']['max_tokens']);
        $this->assertSame(1, (int) $DB->get_field('local_aiconfig_byok_usage', 'calls',
            ['component' => 'mod_example', 'service' => 'text']));
    }

    public function test_images_route_through_vision_vendor(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_vision', 'ownkey', 'local_aiconfig');
        set_config('vendor_vision', 'openai', 'local_aiconfig');
        set_config('key_openai', 'sk-open', 'local_aiconfig');
        $seen = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use (&$seen) {
            $seen = $body;
            return ['choices' => [['message' => ['content' => 'A face.']]]];
        };
        $this->assertSame('A face.', ai::text('mod_example', '', 'Describe', [
            'images' => [['mime' => 'image/png', 'data' => 'PNGDATA']],
        ]));
        $this->assertSame('data:image/png;base64,' . base64_encode('PNGDATA'),
            $seen['messages'][0]['content'][1]['image_url']['url']);
        $this->expectException(\moodle_exception::class);
        ai::text('mod_example', '', 'No text source chosen');
    }

    public function test_speech_uses_google_voice_name(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_voice', 'ownkey', 'local_aiconfig');
        set_config('vendor_voice', 'googletts', 'local_aiconfig');
        set_config('key_gemini', 'g-key', 'local_aiconfig');
        $seen = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use (&$seen) {
            $seen = $body;
            return ['audioContent' => base64_encode('MP3')];
        };
        $this->assertSame('MP3', ai::speech('mod_example', 'Hello', ['language' => 'en-AU', 'voice' => 'Kore']));
        $this->assertSame('en-AU-Chirp3-HD-Kore', $seen['voice']['name']);
    }

    public function test_schema_is_enforced_by_each_vendor(): void {
        set_config('licencekey', $this->key(), 'local_aiconfig');
        set_config('src_text', 'ownkey', 'local_aiconfig');
        set_config('vendor_text', 'anthropic', 'local_aiconfig');
        set_config('key_anthropic', 'sk-test', 'local_aiconfig');
        set_config('key_openai', 'sk-open', 'local_aiconfig');
        $schema = ['name' => 'scenario plan', 'schema' => ['type' => 'object', 'additionalProperties' => false,
            'required' => ['title'], 'properties' => ['title' => ['type' => 'string']]]];
        $seen = [];
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use (&$seen) {
            $seen = $body;
            return ['stop_reason' => 'tool_use', 'content' => [['type' => 'tool_use', 'name' => 'scenario_plan',
                'input' => ['title' => 'Isolation drill']]]];
        };
        $this->assertSame('{"title":"Isolation drill"}', ai::text('mod_example', 'Plan.', 'Go.', ['schema' => $schema]));
        $this->assertSame(['type' => 'tool', 'name' => 'scenario_plan'], $seen['tool_choice']);
        $this->assertSame($schema['schema'], $seen['tools'][0]['input_schema']);
        $this->assertStringNotContainsString('Reply with valid JSON', $seen['system']);
        // The raw reply is what real calls get back: empty objects in a tool answer stay objects.
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body, $vendor, $raw) {
            $this->assertTrue($raw);
            return '{"stop_reason":"tool_use","content":[{"type":"tool_use","name":"scenario_plan",'
                . '"input":{"title":"Drill","nodes":{"n9":{"choices":{}}},"tags":[]}}]}';
        };
        $this->assertSame('{"title":"Drill","nodes":{"n9":{"choices":{}}},"tags":[]}',
            ai::text('mod_example', 'Plan.', 'Go.', ['schema' => $schema]));

        set_config('vendor_text', 'openai', 'local_aiconfig');
        $GLOBALS['local_aiconfig_byok_mock'] = function($url, $headers, $body) use (&$seen) {
            $seen = $body;
            return ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"title":"Drill"}']]]];
        };
        $this->assertSame('{"title":"Drill"}', ai::text('mod_example', 'Plan.', 'Go.', ['schema' => $schema]));
        $this->assertSame('json_schema', $seen['response_format']['type']);
        $this->assertTrue($seen['response_format']['json_schema']['strict']);
        $this->assertSame('scenario_plan', $seen['response_format']['json_schema']['name']);

        $GLOBALS['local_aiconfig_byok_mock'] = function() {
            return ['choices' => [['finish_reason' => 'length', 'message' => ['content' => '{"title":']]]];
        };
        $this->expectException(\moodle_exception::class);
        ai::text('mod_example', 'Plan.', 'Go.', ['schema' => $schema]);
    }

    public function test_strip_fences(): void {
        $this->assertSame('{"a":1}', ai::strip_fences("Here you go:\n```json\n{\"a\":1}\n```"));
        $this->assertSame('[1,2]', ai::strip_fences('Sure! [1,2]'));
    }
}
