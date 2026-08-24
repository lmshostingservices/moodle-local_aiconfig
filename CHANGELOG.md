# AI Grader Central Config Changelog

## Version 1.0.16 (24 August 2026)

### Marketplace and documentation
- Replaced the placeholder README with accurate installation, credential-priority, compatibility,
  shared-credit and support guidance.
- Clarified that compatible integrations read Central Config when implemented; the plugin does not
  make every Moodle plugin inherit credentials automatically.
- Clarified that Central Config stores credentials and does not consume AI credits itself.
- Aligned public release metadata with Moodle 4.0 to 5.0 support.

## Version 1.0.2 (December 2025)

### Bug Fix
- Corrected ZIP folder structure (aiconfig/) for proper Moodle installation
- Previous versions had files at root level instead of inside plugin folder

## Version 1.0.1 (December 2025)

### Role-Based Access Control
- Added support for `lmshsadmin` role to manage Site ID and API Key
- Settings page now checks for `local/aiconfig:manage` capability in addition to site admin access
- Custom roles with this capability can now configure AI Grader credentials

## Version 1.0.0 (December 2025)

### Initial Release
- Centralized Site ID and API Key configuration for all AI Grader ecosystem plugins
- Helper functions: `local_aiconfig_get_siteid()` and `local_aiconfig_get_apikey()`
- `local_aiconfig_is_configured()` function to check configuration status
- Priority-based fallback pattern: Central Config > Plugin-specific settings
- GDPR-compliant privacy provider
- Full Moodle 4.0 - 5.x compatibility

### Initial integration list
The following plugins were documented as integrations at the time of the initial release:
- AI Essay Grader (quiz_aigrader)
- AI Essay Maker (local_essaymaker)
- AI Knowledge Check (mod_knowledgecheck)
- AI Quiz (mod_aiquiz)
- AI Content Creator (mod_contentcreator)
- AI Practical Assessment (mod_practicalassessment)
- AI Verify ID (mod_verifyid)
- AI Video Conference (mod_aivideoconf)
- AI Moodle Support (local_moodlesupport)
- RTO Compliance (local_rtocompliance)

### Installation
1. Download local_aiconfig_v1.0.2.zip
2. Install via Site Administration > Plugins > Install plugins
3. Configure Site ID and API Key in Local plugins > AI Grader Central Config
4. Compatible plugins can read these credentials when their integration is enabled

### Granting Access to Custom Roles
To allow a custom role (e.g., `lmshsadmin`) to manage AI Grader settings:
1. Go to Site Administration → Users → Permissions → Define roles
2. Edit the role (or create a new one)
3. Grant the capability: `local/aiconfig:manage`
4. Users with this role can now access Central Config settings
