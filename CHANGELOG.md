# AI Grader Central Config Changelog

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

### Supported Plugins
All AI Grader ecosystem plugins automatically detect and use Central Config:
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
4. All other AI plugins will automatically use these credentials

### Granting Access to Custom Roles
To allow a custom role (e.g., `lmshsadmin`) to manage AI Grader settings:
1. Go to Site Administration → Users → Permissions → Define roles
2. Edit the role (or create a new one)
3. Grant the capability: `local/aiconfig:manage`
4. Users with this role can now access Central Config settings
