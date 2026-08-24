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
