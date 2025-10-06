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
 * Library functions for Framez Webservice plugin
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Generate complete page content from markdown summary and H5P content
 *
 * @param array $summary Summary data with markdown content
 * @param string $h5pcontent H5P embed HTML content
 * @return string Generated HTML content
 */
function local_framez_webservice_get_markdown_page_content($summary, $h5pcontent = '') {
    require_once(__DIR__ . '/classes/page_renderer.php');
    
    return \local_framez_webservice\page_renderer::render_markdown_page_content($summary, $h5pcontent);
}

/**
 * Render markdown text to HTML
 *
 * @param string $markdown_text Markdown text to render
 * @return string Rendered HTML
 */
function local_framez_webservice_render_markdown($markdown_text) {
    require_once(__DIR__ . '/classes/markdown_renderer.php');
    
    return \local_framez_webservice\markdown_renderer::render_markdown($markdown_text);
}

/**
 * Validate markdown input data
 *
 * @param string $markdown_text Markdown text to validate
 * @return bool True if valid
 * @throws invalid_parameter_exception
 */
function local_framez_webservice_validate_markdown($markdown_text) {
    if (empty(trim($markdown_text))) {
        throw new \invalid_parameter_exception('Markdown text cannot be empty');
    }
    
    // Check if markdown contains at least one header
    if (!preg_match('/^#+\s+.+$/m', $markdown_text)) {
        throw new \invalid_parameter_exception('Markdown must contain at least one header');
    }
    
    return true;
}

/**
 * Get default page module settings
 *
 * @return array Default settings for page module
 */
function local_framez_webservice_get_default_page_settings() {
    return array(
        'display' => 5, // Display on course page
        'displayoptions' => 'a:1:{s:12:"printheading";i:1;}',
        'revision' => 1,
        'visible' => 1,
        'visibleoncoursepage' => 1,
        'availabilityconditionsjson' => '{}',
        'completionunlocked' => 1,
        'completionview' => 1,
        'completionexpected' => 0
    );
}

/**
 * Sanitize text content for HTML output
 *
 * @param string $text Text to sanitize
 * @return string Sanitized text
 */
function local_framez_webservice_sanitize_text($text) {
    return clean_param($text, PARAM_TEXT);
}

/**
 * Format date for display
 *
 * @param string $date Date string
 * @return string Formatted date
 */
function local_framez_webservice_format_date($date) {
    if (empty($date)) {
        return '';
    }
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format('M j, Y g:i A');
    } catch (Exception $e) {
        return $date; // Return original if parsing fails
    }
}

/**
 * Get difficulty level display name
 *
 * @param string $difficulty Difficulty level
 * @return string Display name
 */
function local_framez_webservice_get_difficulty_display($difficulty) {
    switch (strtolower($difficulty)) {
        case 'easy':
            return get_string('difficulty_easy', 'local_framez_webservice');
        case 'hard':
            return get_string('difficulty_hard', 'local_framez_webservice');
        case 'medium':
        default:
            return get_string('difficulty_medium', 'local_framez_webservice');
    }
}

/**
 * Check if user has permission to create pages in course
 *
 * @param int $courseid Course ID
 * @param int $userid User ID (optional, defaults to current user)
 * @return bool True if user has permission
 */
function local_framez_webservice_can_create_page($courseid, $userid = null) {
    global $USER;
    
    if ($userid === null) {
        $userid = $USER->id;
    }
    
    $context = context_course::instance($courseid);
    return has_capability('local/framez_webservice:create_course_page', $context, $userid);
}

/**
 * Generate H5P Dialog Cards from cuecards data
 *
 * @param array $cuecards Array of cue cards with question/answer pairs
 * @param string $title Title for the H5P content
 * @return string H5P embed HTML
 */
function local_framez_webservice_generate_h5p_dialog_cards($cuecards, $title) {
    require_once(__DIR__ . '/classes/h5p_generator.php');
    
    $context = context_system::instance();
    $h5pfile = \local_framez_webservice\h5p_generator::create_h5p_package_file($cuecards, $title, $context);
    
    return \local_framez_webservice\h5p_generator::get_h5p_display_html($h5pfile);
}

/**
 * Get H5P embed HTML from stored file
 *
 * @param stored_file $h5pfile H5P package file
 * @return string H5P embed HTML
 */
function local_framez_webservice_get_h5p_embed_html($h5pfile) {
    require_once(__DIR__ . '/classes/h5p_generator.php');
    
    return \local_framez_webservice\h5p_generator::get_h5p_display_html($h5pfile);
}

/**
 * Get plugin version information
 *
 * @return array Version information
 */
function local_framez_webservice_get_version_info() {
    global $CFG;
    
    $plugin = new stdClass();
    include($CFG->dirroot . '/local/framez_webservice/version.php');
    
    return array(
        'component' => $plugin->component,
        'version' => $plugin->version,
        'release' => $plugin->release,
        'maturity' => $plugin->maturity
    );
}




