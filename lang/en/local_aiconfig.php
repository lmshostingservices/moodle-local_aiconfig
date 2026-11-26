<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for AI Grader Central Config.
 *
 * @package    local_aiconfig
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'AI Grader Central Config';

$string['settings_header'] = 'Central Configuration';
$string['settings_desc'] = 'Configure your AI Grader credentials once here and every plugin in the AI Grader ecosystem will read them automatically — no need to enter the Site ID and API Key separately in each plugin\'s settings. Plugins that support Central Config include: AI Essay Grader, AI Content Creator, AI Quiz, AI Learning Activities, AI Video Activity, AI Knowledge Check, AI Practical Assessment, AI Video Conference, AI Verify ID, AI Slideshow with Voiceover, AI PDF Grader, Essay Guard, AI Quiz Remedial Learning, and Payment Unlock Assignment. If a plugin has its own Site ID and API Key fields, those fields will override Central Config for that specific plugin only.';

$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your unique Site ID from lms-labs.com. This identifies your Moodle site.';

$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API key from lms-labs.com. Keep this secure and do not share it.';

$string['missing_config'] = 'AI Grader Central Config is not configured. Please set your Site ID and API Key in Site Administration > Plugins > Local plugins > AI Grader Central Config.';

$string['aiconfig:manage'] = 'Manage AI Grader Central Config settings';

// Bring your own AI.
$string['byok_allplugins'] = 'every LMS Labs plugin';
$string['byok_callfailed'] = '{$a->vendor} could not complete the AI request: {$a->detail}';
$string['byok_checked'] = 'Licence checked with LMS Labs.';
$string['byok_checkfailed'] = 'LMS Labs could not be reached. Your licence keeps working from its last known state.';
$string['byok_checknow'] = 'Check now';
$string['byok_claimed'] = 'Payment received. Your licence is installed and bring your own AI is on.';
$string['byok_claimpending'] = 'We have not received your licence yet. If you have just paid, wait a minute and press Check now.';
$string['byok_creditsonly'] = 'No AI provider can make video on a Moodle site yet, so video always uses LMS Labs credits.';
$string['byok_detail_credits'] = 'Runs on LMS Labs servers and uses your credits.';
$string['byok_detail_moodle'] = 'Uses the provider set up in Moodle AI.';
$string['byok_detail_moodle_missing_image'] = 'Turn on Generate image with OpenAI, Azure, Gemini or Bedrock in Moodle AI.';
$string['byok_detail_moodle_missing_text'] = 'Set up a provider with Generate text in Moodle AI.';
$string['byok_detail_moodle_missing_vision'] = 'Needs an Anthropic, OpenAI or Gemini provider in Moodle AI.';
$string['byok_detail_moodle_native'] = 'Uses your "{$a}" provider in Moodle AI.';
$string['byok_detail_moodle_old'] = 'Needs Moodle 4.5 or later.';
$string['byok_detail_ownkey'] = 'Uses your {$a} key, straight from this site.';
$string['byok_detail_ownkey_missing'] = 'Add a key below to use this.';
$string['byok_effective_credits'] = 'Using LMS Labs credits';
$string['byok_effective_moodle'] = 'Using Moodle AI';
$string['byok_effective_ownkey'] = 'Using your own key';
$string['byok_eyebrow'] = 'Bring your own AI';
$string['byok_grace_banner'] = 'Your LMS Labs Bring your own AI subscription needs attention. AI keeps working until {$a}.';
$string['byok_grace_link'] = 'Review subscription';
$string['byok_grace_title'] = 'Your subscription needs attention';
$string['byok_key_clear'] = 'Remove saved key';
$string['byok_key_placeholder'] = 'Paste a key';
$string['byok_keyhint'] = 'Saved key ending {$a}';
$string['byok_keykeep'] = 'Leave blank to keep the saved key.';
$string['byok_keyrefused'] = 'That licence key is not valid for this site.';
$string['byok_keyremoved'] = 'Licence key removed. Every plugin now uses LMS Labs credits.';
$string['byok_keys_desc'] = 'Enter each key once. Every LMS Labs plugin on this site uses them, and calls go from this site straight to the provider, never through LMS Labs.';
$string['byok_keys_heading'] = 'Your AI keys';
$string['byok_keysaved'] = 'Licence key installed.';
$string['byok_lastcheck'] = 'Last checked';
$string['byok_licence_heading'] = 'Run LMS Labs plugins on your own AI';
$string['byok_manual_heading'] = 'Have a licence key from LMS Labs support?';
$string['byok_manual_label'] = 'Licence key';
$string['byok_manual_save'] = 'Install key';
$string['byok_moodleai_link'] = 'Set up Moodle AI providers';
$string['byok_notavailable'] = 'Bring your own AI is not set up for {$a} on this site.';
$string['byok_paiduntil'] = 'Paid until';
$string['byok_plan'] = 'Plan';
$string['byok_removekey'] = 'Remove licence key';
$string['byok_renews'] = 'Renews';
$string['byok_service_image'] = 'Images';
$string['byok_service_image_help'] = 'Illustrations, banners and scene images.';
$string['byok_service_text'] = 'Text';
$string['byok_service_text_help'] = 'Writing, marking, feedback, chat and diagrams.';
$string['byok_service_video'] = 'Video';
$string['byok_service_video_help'] = 'Generated video clips.';
$string['byok_service_vision'] = 'Reading images';
$string['byok_service_vision_help'] = 'Marking photos and scans, and identity checks.';
$string['byok_service_voice'] = 'Voice';
$string['byok_service_voice_help'] = 'Narration and spoken feedback.';
$string['byok_services_desc'] = 'Choose where each kind of AI runs. You can mix them, for example text on Moodle AI and voice on LMS Labs credits.';
$string['byok_services_heading'] = 'Where each kind of AI runs';
$string['byok_source_credits'] = 'LMS Labs credits';
$string['byok_source_moodle'] = 'Moodle AI';
$string['byok_source_ownkey'] = 'My own key';
$string['byok_state_active'] = 'Active';
$string['byok_state_expired'] = 'Expired';
$string['byok_state_grace'] = 'Payment due';
$string['byok_state_invalid'] = 'Invalid key';
$string['byok_state_nobyok'] = 'Not included';
$string['byok_state_none'] = 'Not subscribed';
$string['byok_state_notcovered'] = 'Not included';
$string['byok_state_revoked'] = 'Cancelled';
$string['byok_state_wrongsite'] = 'Wrong site';
$string['byok_statedesc_active'] = 'LMS Labs plugins on this site can use your own AI. Your data goes from this site straight to your provider.';
$string['byok_statedesc_expired'] = 'Your subscription ended on {$a->exp}. Plugins are back on LMS Labs credits until you renew.';
$string['byok_statedesc_grace'] = 'Your payment did not go through or your subscription has ended. Your own AI keeps working until {$a->grace}, then plugins switch back to LMS Labs credits.';
$string['byok_statedesc_invalid'] = 'The licence key saved here could not be verified. Subscribe again or contact LMS Labs support.';
$string['byok_statedesc_nobyok'] = 'Your LMS Labs licence does not include Bring your own AI. Subscribe to add it.';
$string['byok_statedesc_none'] = 'Use your own AI with every LMS Labs plugin: Moodle AI or your own Anthropic, OpenAI, Google or ElevenLabs keys. You pay your provider for AI, and the yearly subscription covers the plugins, updates and support.';
$string['byok_statedesc_notcovered'] = 'Your licence covers other LMS Labs plugins only. The All plugins plan covers every one.';
$string['byok_statedesc_revoked'] = 'This licence was cancelled by LMS Labs. Plugins use LMS Labs credits. Contact support if this is a mistake.';
$string['byok_statedesc_wrongsite'] = 'This licence belongs to another site, not {$a->site}. Subscribe for this site or contact LMS Labs support.';
$string['byok_subscribe'] = 'Subscribe yearly';
$string['byok_title'] = 'Bring your own AI';
$string['byok_usage_calls'] = 'Calls';
$string['byok_usage_desc'] = 'Calls made on your own AI in the last 30 days. Your provider bills you for these, not LMS Labs.';
$string['byok_usage_empty'] = 'No calls on your own AI yet.';
$string['byok_usage_heading'] = 'Your AI usage';
$string['byok_usage_plugin'] = 'Plugin';
$string['byok_usage_service'] = 'Kind';
$string['byok_usage_source'] = 'Runs on';
$string['byok_vendor_label'] = 'Own key provider';
$string['byok_voice_elevenlabs'] = 'Default ElevenLabs voice ID';
$string['byok_voice_googletts'] = 'Default Google voice';
$string['byok_voice_openai'] = 'Default OpenAI voice';
$string['licenceurl'] = 'LMS Labs licence server';
$string['licenceurl_desc'] = 'Where Bring your own AI licences are checked. Only change this if LMS Labs support asks you to.';
$string['privacy:metadata:aiprovider'] = 'When an administrator turns on Bring your own AI, LMS Labs plugins send the content needed for each AI request from this site to the AI provider the administrator chose.';
$string['privacy:metadata:aiprovider:content'] = 'Text, images or prompts sent for the AI request, as decided by the calling plugin.';
$string['privacy:metadata:lmslabs'] = 'The daily licence check sends this site\'s address, Moodle version, plugin settings summary and a count of active learners to LMS Labs. No personal data is sent.';
$string['privacy:metadata:lmslabs:site'] = 'The site address.';
$string['task_licencecheck'] = 'Check the Bring your own AI licence';
