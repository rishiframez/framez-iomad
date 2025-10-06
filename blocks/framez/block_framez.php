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

/**
 * Block framez is defined here.
 *
 * @package     block_framez
 * @copyright   2025 Faster Frames Corporation <contact@example.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use Firebase\JWT\JWT;
use moodle_exception;
use context_course;
use moodle_url;

require_once($CFG->dirroot . '/mod/lti/locallib.php');

function get_signed_jwt($courseId, $clientid): string 
{
    global $CFG;
    
    $privateKeyFile = $CFG->dataroot . '/framez_private.pem';
    $privateKey = file_get_contents($privateKeyFile);
    if (!$privateKey) {
        throw new moodle_exception("framez - missing private key {$privateKeyFile}");
    }

    $payload = [
        'iss' => $CFG->wwwroot,
        'signer' => 'issuer_url',
        'iat' => time(),
        'exp' => time() + 300,
        'scope' => 'course:list',
        'course_id' => $courseId,
        'client_id' => $clientid
    ];

    return JWT::encode($payload, $privateKey, 'RS256');
}

function fetch_namespaces_from_api($jwt, $courseid): array {
    global $CFG;
    $url = "https://{$CFG->framez_api_server}/api/v1/jwt/course/{$courseid}/namespaces";

    $headers = [
        'Authorization: Bearer ' . $jwt,
        'Content-Type: application/json'
    ];

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlerr = curl_error($curl);
    curl_close($curl);

    if ($status !== 200 || !$response) {
        debugging("Framez API call failed: HTTP $status. Curl error: $curlerr", DEBUG_DEVELOPER);
        return [];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        debugging("Framez API response is not valid JSON", DEBUG_DEVELOPER);
        return [];
    }

    if (!isset($decoded['status']) || $decoded['status'] !== 'SUCCESS') {
        debugging("Framez API returned status: " . ($decoded['status'] ?? 'MISSING'), DEBUG_DEVELOPER);
        return [];
    }

    if (!isset($decoded['payload']) || !is_array($decoded['payload'])) {
        debugging("Framez API response missing valid 'payload'", DEBUG_DEVELOPER);
        return [];
    }

    return $decoded['payload'];
}

function get_or_create_lti_tool($toolurl, $courseid): int {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->dirroot . '/mod/lti/lib.php');

    // Check if already exists
    $instances = get_coursemodules_in_course('lti', $courseid);
    foreach ($instances as $cm) {
        if ($cm->modname === 'lti' && $cm->name === 'Framez') {
            // debugging("Framez Tool already exists: {$cm->id}", DEBUG_DEVELOPER);
            return $cm->id;
        }
    }

    // Get the lti module id
    $module = $DB->get_record('modules', ['name' => 'lti'], '*', MUST_EXIST);

    // Step 1: Prepare course module without the instance
    $cm = new stdClass();
    $cm->course = $courseid;
    $cm->module = $module->id;
    $cm->instance = 0; // placeholder, will update after lti_add_instance
    $cm->section = 0;
    $cm->visible = 1;
    $cmid = add_course_module($cm);

    // Step 2: Create the LTI instance
    $lti = new stdClass();
    $lti->typeid = 0;
    $lti->toolurl = $toolurl;
    $lti->name = 'Framez';
    $lti->course = $courseid;
    $lti->intro = 'Framez tool for lecture viewing';
    $lti->introformat = FORMAT_HTML;
    $lti->visible = 1;
    $lti->coursemodule = $cmid;  // ⚠️ This is important
    $lti->launchcontainer = LTI_LAUNCH_CONTAINER_EMBED;

    $ltiid = lti_add_instance($lti, null); // Now this works without warning

    // Step 3: Update the course module to point to the instance
    $DB->set_field('course_modules', 'instance', $ltiid, ['id' => $cmid]);

    // Step 4: Add to section and rebuild course cache
    course_add_cm_to_section($courseid, $cmid, 0);
    rebuild_course_cache($courseid, true);

    debugging("Created Framez LTI instance and course module: {$cmid}", DEBUG_DEVELOPER);
    return $cmid;
}

class block_framez extends block_base {

    /**
     * Initializes class member variables.
     */

    public function instance_allow_multiple() 
    {
        return false;
    }

    public function instance_can_be_hidden() 
    {
        return false;
    }

    public function instance_can_be_docked() 
    {
        return false;
    }

    public function instance_can_be_deleted() 
    {
        return false; // ← this makes it non-removable
    }

    public function init() {
        // Needed by Moodle to differentiate between blocks.
        $this->title = get_string('pluginname', 'block_framez');
    }

    public function get_content() {
        global $CFG, $COURSE, $USER, $PAGE;
        
        if ($this->content !== null) {
            return $this->content;
        }
        
        debugging("Framez Block Debug: get_content() called. Page type: " . $PAGE->pagetype . ", Subpage: " . ($PAGE->subpage ?? 'none'), DEBUG_DEVELOPER);

        $this->content = new stdClass();
        $this->content->items = [];
        $this->content->icons = [];
        $this->content->footer = '';
        
        $courseId = $COURSE->id ?? null;
        if (!$courseId) {
            throw new moodle_exception('framez - could not determine current course ID');
        }

        $context = context_course::instance($COURSE->id);
        $is_teacher = has_capability('moodle/course:update', $context, $USER);
        $is_student = has_capability('mod/resource:view', $context, $USER);

        // Check if this is a page-specific block instance (has namespace_id)
        $namespace_id = null;
        $namespace_name = null;
        if ($this->config && isset($this->config->namespace_id)) {
            $namespace_id = $this->config->namespace_id;
            $namespace_name = $this->config->namespace_name ?? 'Framez Session';
            debugging("Framez Block Debug: Page view detected with namespace_id: {$namespace_id}", DEBUG_DEVELOPER);
        } else {
            debugging("Framez Block Debug: Course view detected (no namespace_id)", DEBUG_DEVELOPER);
        }
        
        debugging("Framez Block Debug: Block config: " . print_r($this->config, true), DEBUG_DEVELOPER);
        
        // Alternative approach: Check if we're on a page view and try to detect namespace from page content
        if (!$namespace_id && $PAGE->pagetype === 'mod-page-view') {
            debugging("Framez Block Debug: On page view, attempting to detect namespace from page content", DEBUG_DEVELOPER);
            $namespace_id = self::detect_namespace_from_page_content();
            if ($namespace_id) {
                debugging("Framez Block Debug: Detected namespace from page content: {$namespace_id}", DEBUG_DEVELOPER);
            }
        }

        if ($namespace_id) {
            // Page view with specific namespace - render fancy namespace-specific UI
            $this->content->text = $this->render_namespace_links($namespace_id, $namespace_name, $is_teacher, $is_student, $courseId);
        } else {
            // Course view - render general course links
            $this->content->text = $this->render_course_links($is_teacher, $is_student, $courseId);
        }

        return $this->content;
    }


    /**
     * Defines configuration data.
     *
     * The function is called immediately after init().
     */
    public function specialization() {
        debugging("Framez Block Debug: specialization() called. Config: " . print_r($this->config, true), DEBUG_DEVELOPER);

        // Load user defined title and make sure it's never empty.
        if (empty($this->config->title)) {
            $this->title = get_string('pluginname', 'block_framez');
        } else {
            $this->title = $this->config->title;
        }
    }

    /**
     * Render namespace-specific links for page view
     *
     * @param string $namespace_id Namespace ID
     * @param string $namespace_name Namespace name
     * @param bool $is_teacher Whether user is a teacher
     * @param bool $is_student Whether user is a student
     * @param int $courseId Course ID
     * @return string HTML content
     */
    private function render_namespace_links($namespace_id, $namespace_name, $is_teacher, $is_student, $courseId) {
        global $CFG;
        
        $toolurl = $CFG->framez_tool_url;
        $cmid = get_or_create_lti_tool($toolurl, $courseId);
        
        $html = '<div class="framez-block framez-block--page-view">';
        $html .= '<div class="framez-block__header">';
        $html .= '<div class="framez-block__icon">🤖</div>';
        $html .= '<h3 class="framez-block__title">AI Learning Tools</h3>';
        $html .= '<div class="framez-block__subtitle">' . htmlspecialchars($namespace_name) . '</div>';
        $html .= '</div>';
        
        $html .= '<div class="framez-block__links">';
        
        if ($is_teacher) {
            // Teacher links with full text
            $teacher_links = [
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=summary"]), 'text' => 'Summary', 'icon' => '📖'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=flashcards"]), 'text' => 'FlashCards', 'icon' => '🃏'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=chat"]), 'text' => 'AI Teacher', 'icon' => '💬'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=assessments"]), 'text' => 'Assessments', 'icon' => '📝'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=citations"]), 'text' => 'Citations', 'icon' => '📚']
            ];
            
            foreach ($teacher_links as $link) {
                $html .= '<a href="' . $link['url']->out(false) . '" target="_blank" class="framez-link framez-link--teacher">';
                $html .= '<span class="framez-link__icon">' . $link['icon'] . '</span>';
                $html .= '<span class="framez-link__text">' . $link['text'] . '</span>';
                $html .= '</a>';
            }
        } else if ($is_student) {
            // Student links with full text
            $student_links = [
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=summary"]), 'text' => 'Summary', 'icon' => '📖'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=flashcards"]), 'text' => 'FlashCards', 'icon' => '🃏'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=chat"]), 'text' => 'AI Teacher', 'icon' => '💬'],
                ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$namespace_id}\raction=self-assessments"]), 'text' => 'Self-Assessments', 'icon' => '📝']
            ];
            
            foreach ($student_links as $link) {
                $html .= '<a href="' . $link['url']->out(false) . '" target="_blank" class="framez-link framez-link--student">';
                $html .= '<span class="framez-link__icon">' . $link['icon'] . '</span>';
                $html .= '<span class="framez-link__text">' . $link['text'] . '</span>';
                $html .= '</a>';
            }
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html . $this->get_fancy_styles();
    }

    /**
     * Render general course links for course view
     *
     * @param bool $is_teacher Whether user is a teacher
     * @param bool $is_student Whether user is a student
     * @param int $courseId Course ID
     * @return string HTML content
     */
    private function render_course_links($is_teacher, $is_student, $courseId) {
        global $CFG;
        
        $toolurl = $CFG->framez_tool_url;
        $clientid = $CFG->framez_tool_client;
        $cmid = get_or_create_lti_tool($toolurl, $courseId);
        
        $jwt = get_signed_jwt($courseId, $clientid);
        $lectures = fetch_namespaces_from_api($jwt, $courseId);
        
        $html = '<div class="framez-block framez-block--course-view">';
        $html .= '<div class="framez-block__header">';
        $html .= '<div class="framez-block__icon">🏠</div>';
        $html .= '<h3 class="framez-block__title">Framez Tools</h3>';
        $html .= '</div>';
        
        $html .= '<div class="framez-block__content">';
        
        if (!empty($lectures)) {
            $html .= '<div class="framez-lectures">';
            foreach ($lectures as $lecture) {
                $guid = $lecture['guid'];
                $name = $lecture['name'];
                
                $html .= '<div class="framez-lecture-item">';
                $html .= '<div class="framez-lecture-name">' . htmlspecialchars($name) . '</div>';
                $html .= '<div class="framez-lecture-links">';
                
                if ($is_teacher) {
                    // Teacher links with icons only
                    $teacher_links = [
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=summary"]), 'icon' => '📖', 'title' => 'Summary'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=flashcards"]), 'icon' => '🃏', 'title' => 'FlashCards'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=chat"]), 'icon' => '💬', 'title' => 'AI Teacher'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=assessments"]), 'icon' => '📝', 'title' => 'Assessments'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=citations"]), 'icon' => '📚', 'title' => 'Citations']
                    ];
                    
                    foreach ($teacher_links as $link) {
                        $html .= '<a href="' . $link['url']->out(false) . '" target="_blank" class="framez-link framez-link--icon-only" title="' . $link['title'] . '">';
                        $html .= '<span class="framez-link__icon">' . $link['icon'] . '</span>';
                        $html .= '</a>';
                    }
                } else if ($is_student) {
                    // Student links with icons only
                    $student_links = [
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=summary"]), 'icon' => '📖', 'title' => 'Summary'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=flashcards"]), 'icon' => '🃏', 'title' => 'FlashCards'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=chat"]), 'icon' => '💬', 'title' => 'AI Teacher'],
                        ['url' => new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "namespace={$guid}\raction=self-assessments"]), 'icon' => '📝', 'title' => 'Self-Assessments']
                    ];
                    
                    foreach ($student_links as $link) {
                        $html .= '<a href="' . $link['url']->out(false) . '" target="_blank" class="framez-link framez-link--icon-only" title="' . $link['title'] . '">';
                        $html .= '<span class="framez-link__icon">' . $link['icon'] . '</span>';
                        $html .= '</a>';
                    }
                }
                
                $html .= '</div>';
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="framez-empty">No Framez sessions available</div>';
        }
        
        if ($is_teacher) {
            $html .= '<div class="framez-admin-links">';
            $settings_url = new moodle_url('/mod/lti/launch.php', ['id' => $cmid, 'action' => "action=settings"]);
            $html .= '<a href="' . $settings_url->out(false) . '" target="_blank" class="framez-link framez-link--admin">⚙️ Settings</a>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        return $html . $this->get_fancy_styles();
    }

    /**
     * Detect namespace_id from page content
     *
     * @return string|null Namespace ID if found, null otherwise
     */
    private static function detect_namespace_from_page_content() {
        global $DB, $PAGE;
        
        // Get the current page module
        if (!$PAGE->cm) {
            return null;
        }
        
        $page = $DB->get_record('page', ['id' => $PAGE->cm->instance], 'content');
        if (!$page) {
            return null;
        }
        
        // Look for namespace_id comment in page content
        if (preg_match('/<!-- namespace_id:([a-f0-9-]+) -->/', $page->content, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Get fancy CSS styles for the block
     *
     * @return string CSS styles
     */
    private function get_fancy_styles() {
        return '<style>
        .framez-block {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            color: white;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        .framez-block--course-view {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            box-shadow: 0 8px 32px rgba(240, 147, 251, 0.3);
        }
        
        .framez-block__header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .framez-block__icon {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }
        
        .framez-block__title {
            margin: 0;
            font-size: 1.4em;
            font-weight: 600;
        }
        
        .framez-block__subtitle {
            font-size: 0.9em;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .framez-block__links {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .framez-link {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .framez-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            text-decoration: none;
            color: white;
        }
        
        .framez-link--teacher {
            background: rgba(255, 193, 7, 0.2);
            border-color: rgba(255, 193, 7, 0.3);
        }
        
        .framez-link--teacher:hover {
            background: rgba(255, 193, 7, 0.3);
        }
        
        .framez-link--small {
            padding: 8px 12px;
            font-size: 0.9em;
        }
        
        .framez-link--icon-only {
            padding: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            position: relative;
        }
        
        .framez-link--icon-only .framez-link__icon {
            font-size: 18px;
        }
        
        .framez-link--icon-only:hover::after {
            content: attr(title);
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            pointer-events: none;
        }
        
        .framez-link--icon-only:hover::before {
            content: "";
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-bottom-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            pointer-events: none;
        }
        
        .framez-link--admin {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .framez-lecture-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        
        .framez-lecture-name {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .framez-lecture-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .framez-empty {
            text-align: center;
            opacity: 0.7;
            font-style: italic;
        }
        
        .framez-admin-links {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        @media (max-width: 768px) {
            .framez-block {
                padding: 15px;
            }
            
            .framez-block__links {
                gap: 8px;
            }
            
            .framez-link {
                padding: 10px 12px;
            }
        }
        </style>';
    }

    /**
     * Sets the applicable formats for the block.
     *
     * @return string[] Array of pages and permissions.
     */
    public function applicable_formats() {
        return array(
                'course-view' => true, // Allow block on course pages
                'mod-page-view' => true, // Allow block on page module views
                'mod-*' => true, // Allow block on all module pages
                'site' => false        // Optional: disallow on site homepage
            );
    }
}
