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

namespace local_framez_webservice;

// No longer using Moodle's renderer_base in webservice context

/**
 * Page content renderer for Framez Webservice
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_renderer {

    /**
     * Render the complete page content from markdown
     *
     * @param array $summary Summary data with markdown content
     * @param string $h5pcontent H5P embed HTML content
     * @return string Rendered HTML content
     */
    public static function render_markdown_page_content($summary, $h5pcontent = '') {
        debugging("Page Renderer Debug: H5P content length: " . strlen($h5pcontent), DEBUG_DEVELOPER);
        debugging("Page Renderer Debug: H5P content preview: " . substr($h5pcontent, 0, 100), DEBUG_DEVELOPER);
        
        $data = array(
            'markdown_content' => $summary['content'],
            'h5p_content' => $h5pcontent,
            'has_h5p_content' => !empty($h5pcontent),
            'has_metadata' => false,
            'author' => '',
            'subject' => '',
            'level' => '',
            'tags' => array(),
            'created_date' => '',
            'last_modified' => ''
        );
        
        debugging("Page Renderer Debug: has_h5p_content: " . ($data['has_h5p_content'] ? 'true' : 'false'), DEBUG_DEVELOPER);

        // Use Mustache directly instead of Moodle's renderer
        $mustache = new \Mustache_Engine();
        $template_path = __DIR__ . '/../templates/h5p_page_content.mustache';
        
        if (!file_exists($template_path)) {
            throw new \moodle_exception('Template file not found: ' . $template_path);
        }
        
        $template = file_get_contents($template_path);
        $rendered = $mustache->render($template, $data);
        
        debugging("Page Renderer Debug: Rendered content length: " . strlen($rendered), DEBUG_DEVELOPER);
        debugging("Page Renderer Debug: Rendered content contains H5P: " . (strpos($rendered, 'framez-h5p-section') !== false ? 'yes' : 'no'), DEBUG_DEVELOPER);
        debugging("Page data is: " . print_r($data, true), DEBUG_DEVELOPER);
        debugging("Rendered content is: " . $rendered, DEBUG_DEVELOPER);
        
        return $rendered;
    }

    /**
     * Prepare markdown data for template
     *
     * @param string $markdown_text Raw markdown text
     * @return array Prepared markdown data
     */
    private static function prepare_markdown_data($markdown_text) {
        $title = \local_framez_webservice\markdown_renderer::extract_title_from_markdown($markdown_text);
        $content = \local_framez_webservice\markdown_renderer::render_markdown($markdown_text);
        
        return array(
            'title' => $title,
            'content' => $content,
            'markdown_text' => $markdown_text
        );
    }

    /**
     * Render page content with H5P integration
     *
     * @param array $summary Summary data with markdown content
     * @param string $h5pembedhtml H5P embed HTML
     * @return string Rendered HTML content
     */
    public static function render_h5p_page_content($summary, $h5pembedhtml) {
        return self::render_markdown_page_content($summary, $h5pembedhtml);
    }

    /**
     * Get CSS class for difficulty level
     *
     * @param string $difficulty Difficulty level
     * @return string CSS class
     */
    private static function get_difficulty_class($difficulty) {
        switch (strtolower($difficulty)) {
            case 'easy':
                return 'difficulty-easy';
            case 'hard':
                return 'difficulty-hard';
            case 'medium':
            default:
                return 'difficulty-medium';
        }
    }

    /**
     * Generate page title from markdown text
     *
     * @param string $markdown_text Markdown text
     * @return string Page title
     */
    public static function get_page_title_from_markdown($markdown_text) {
        $title = \local_framez_webservice\markdown_renderer::extract_title_from_markdown($markdown_text);
        return !empty($title) ? $title : 'Untitled Page';
    }
}

