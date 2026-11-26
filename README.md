# AI Grader Central Config

A free Moodle local plugin that stores one LMS Labs Site ID and API key for
compatible LMS Labs integrations to read.

Central Config removes repeated credential entry across supported plugins.
Integrated plugins check the central values first and may fall back to their own
settings when Central Config is absent or a central value has not been set.

## Highlights

- One site-level location for the LMS Labs Site ID and API key.
- Password-style API-key field in Moodle administration.
- Reusable procedural and typed APIs for compatible Moodle plugins.
- Optional plugin-specific fallback values for backwards compatibility.
- No AI-credit charge for Central Config itself.

Central Config stores credentials; it does not generate AI content itself.
AI-powered plugins use the site's shared LMS Labs credit balance when they make
their own service requests.

## Requirements

- Moodle 4.0 to 5.0
- An LMS Labs Site ID and API key for connected services

## Installation

1. Install the ZIP through **Site administration > Plugins > Install plugins**.
2. Complete the Moodle upgrade.
3. Open **Site administration > Plugins > Local plugins > AI Grader Central Config**.
4. Enter the Site ID and API key supplied for the Moodle site.

## Documentation and support

- [Complete documentation](https://lms-labs.com/docs/ai-central-config)
- [AI Dashboard Quick Links](https://lms-labs.com/docs/ai-dashboard-quick-links)
- [AI credits and pricing](https://lms-labs.com/pricing)
- [Issue tracker](https://github.com/lmshostingservices/moodle-local_aiconfig/issues)
- Email: support@lmshostingservices.com

## Licence

GNU GPL v3 or later.

## Bring your own AI (1.1.0)

Site administration > Plugins > Local plugins > **Bring your own AI**.

With a yearly LMS Labs "Bring your own AI" licence, every LMS Labs plugin can run its AI on your own
providers instead of LMS Labs credits. Pick a source for each kind of AI:

| Kind | LMS Labs credits | Moodle AI (4.5+) | Your own key |
|---|---|---|---|
| Text | Yes | Any provider with Generate text | Anthropic, OpenAI, Google Gemini |
| Images | Yes | OpenAI, Azure, Gemini, Bedrock | OpenAI |
| Voice | Yes | Not supported by Moodle | Google Chirp 3 HD, ElevenLabs, OpenAI |
| Reading images | Yes | Anthropic, OpenAI or Gemini provider | Anthropic, OpenAI, Google Gemini |
| Video | Yes | Not supported by Moodle | Not yet |

Own-key and Moodle AI calls go from your Moodle site straight to your provider. LMS Labs only sees the
daily licence check (site address, versions, settings summary, active learner count).

### For plugin developers

```php
use local_aiconfig\byok\ai;

if (class_exists(ai::class) && ai::uses_own_ai('mod_example', ai::TEXT)) {
    $json = ai::text('mod_example', $systemprompt, $userprompt, ['json' => true, 'maxtokens' => 4000]);
} else {
    // Existing LMS Labs credits call.
}
```

`ai::image()` returns PNG bytes, `ai::speech()` returns MP3 bytes, and `ai::text()` with
`options['images']` reads images. Calls throw `moodle_exception` on failure and never spend credits.
