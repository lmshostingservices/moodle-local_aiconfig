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
$string['privacy:metadata'] = 'The AI Grader Central Config plugin does not store any personal data.';

$string['settings_header'] = 'Central Configuration';
$string['settings_desc'] = 'Configure your AI Grader credentials once here and every plugin in the AI Grader ecosystem will read them automatically — no need to enter the Site ID and API Key separately in each plugin\'s settings. Plugins that support Central Config include: AI Essay Grader, AI Content Creator, AI Quiz, AI Learning Activities, AI Video Activity, AI Knowledge Check, AI Practical Assessment, AI Video Conference, AI Verify ID, AI Slideshow with Voiceover, AI PDF Grader, Essay Guard, AI Quiz Remedial Learning, and Payment Unlock Assignment. If a plugin has its own Site ID and API Key fields, those fields will override Central Config for that specific plugin only.';

$string['siteid'] = 'Site ID';
$string['siteid_desc'] = 'Your unique Site ID from lms-labs.com. This identifies your Moodle site.';

$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'Your API key from lms-labs.com. Keep this secure and do not share it.';

$string['missing_config'] = 'AI Grader Central Config is not configured. Please set your Site ID and API Key in Site Administration > Plugins > Local plugins > AI Grader Central Config.';

$string['aiconfig:manage'] = 'Manage AI Grader Central Config settings';
