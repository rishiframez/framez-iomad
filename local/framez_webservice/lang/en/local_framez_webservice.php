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
 * Language strings for Framez Webservice plugin
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Framez Webservice';
$string['privacy:metadata'] = 'The Framez Webservice plugin does not store any personal data.';

// Capabilities
$string['framez_webservice:create_course_page'] = 'Create course pages with summary and cue cards';

// Webservice function
$string['create_course_page'] = 'Create course page';
$string['create_course_page_desc'] = 'Create a course page with markdown summary and cue cards content';

// Page content labels
$string['author'] = 'Author';
$string['subject'] = 'Subject';
$string['level'] = 'Level';
$string['tags'] = 'Tags';
$string['created'] = 'Created';
$string['lastmodified'] = 'Last modified';
$string['cuecards'] = 'Cue Cards';
$string['clicktoflip'] = 'Click to flip';

// Difficulty levels
$string['difficulty_easy'] = 'Easy';
$string['difficulty_medium'] = 'Medium';
$string['difficulty_hard'] = 'Hard';

// Error messages
$string['error_invalid_course'] = 'Invalid course ID';
$string['error_invalid_markdown'] = 'Invalid markdown format';
$string['error_markdown_parse_failed'] = 'Failed to parse markdown content';
$string['error_no_title_in_markdown'] = 'Markdown must contain a title (first header)';
$string['error_missing_front'] = 'Cue card must contain front content';
$string['error_missing_back'] = 'Cue card must contain back content';
$string['error_page_creation_failed'] = 'Failed to create page module';
$string['error_no_permission'] = 'You do not have permission to create pages in this course';

// Success messages
$string['success_page_created'] = 'Page created successfully';
$string['success_page_id'] = 'Page ID: {$a}';

// Markdown content
$string['summary_markdown'] = 'Summary (Markdown)';
$string['markdown_rendered'] = 'Markdown Content';

// Settings
$string['settings'] = 'Framez Webservice Settings';
$string['settings_desc'] = 'Configure settings for the Framez Webservice plugin';




