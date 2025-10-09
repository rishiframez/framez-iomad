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

namespace local_framez_webservice\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;
use core_external\util;
use stdClass;

/**
 * External API for creating course pages with summary and cue cards
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_course_page extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_TEXT, 'Course ID where the page will be created'),
                'namespace_id' => new external_value(PARAM_TEXT, 'Session ID to retrieve summary and flashcards from API')
            )
        );
    }

    /**
     * Create a course page with summary and cue cards content from session ID
     *
     * @param int $courseid Course ID
     * @param string $namespace_id Session ID to retrieve data from API
     * @return array Result with page ID and warnings
     */
    public static function execute($courseid, $namespace_id) {
        global $DB, $CFG;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), array(
            'courseid' => $courseid,
            'namespace_id' => $namespace_id
        ));

        $warnings = array();

        // Validate course access
        $course = $DB->get_record('course', array('id' => $params['courseid']), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        self::validate_context($context);

        // Check capability
        require_capability('local/framez_webservice:create_course_page', $context);

        // Fetch session data from API
        $sessiondata = self::fetch_session_data($params['namespace_id'], $params['courseid']);
        
        // Validate markdown summary data
        $summarydata = self::validate_markdown_summary($sessiondata['summary']);
        $flashcardsdata = self::validate_flashcards_data($sessiondata['flashcards']);

        // Create or update the page module
        $result = self::create_page_module($course, $summarydata, $flashcardsdata, $sessiondata['namespace_name'], $params['namespace_id']);

        if (!$result || $result === false) {
            $warnings[] = array(
                'item' => 'page_creation',
                'itemid' => 0,
                'warningcode' => 'page_creation_failed',
                'message' => 'Failed to create or update page module'
            );
            $pageid = 0;
            $action = 'failed';
        } else {
            $pageid = is_array($result) ? $result['pageid'] : $result;
            $action = is_array($result) ? $result['action'] : 'created';
        }

        $result = array(
            'pageid' => $pageid,
            'action' => $action, // 'created', 'updated', or 'failed'
            'warnings' => $warnings
        );

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure(
            array(
                'pageid' => new external_value(PARAM_INT, 'Created or updated page ID'),
                'action' => new external_value(PARAM_TEXT, 'Action performed: created, updated, or failed'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Validate markdown summary data from API
     *
     * @param string $summary_text Summary text from API
     * @return array Validated summary data with title and content
     * @throws invalid_parameter_exception
     */
    private static function validate_markdown_summary($summary_text) {
        if (empty(trim($summary_text))) {
            throw new \invalid_parameter_exception('Summary text cannot be empty');
        }

        $summary_text = clean_param($summary_text, PARAM_RAW);
        
        // Render markdown to HTML
        $rendered_content = \local_framez_webservice\markdown_renderer::render_markdown($summary_text);

        return array(
            'content' => $rendered_content,
            'markdown_text' => $summary_text
        );
    }

    /**
     * Validate flashcards data from API
     *
     * @param array $flashcards Array of flashcards from API
     * @return array Validated flashcards data
     * @throws invalid_parameter_exception
     */
    private static function validate_flashcards_data($flashcards) {
        if (!is_array($flashcards)) {
            throw new \invalid_parameter_exception('Flashcards must be an array');
        }

        // Validate each flashcard
        foreach ($flashcards as $index => $card) {
            if (!is_array($card)) {
                throw new \invalid_parameter_exception("Flashcard at index {$index} must be an object");
            }

            // Validate required fields (question and answer)
            $required_fields = array('question', 'answer');
            foreach ($required_fields as $field) {
                if (!isset($card[$field]) || empty($card[$field])) {
                    throw new \invalid_parameter_exception("Flashcard at index {$index} must contain '{$field}' field");
                }
            }

            // Sanitize string fields
            $string_fields = array('question', 'answer');
            foreach ($string_fields as $field) {
                if (isset($card[$field])) {
                    $card[$field] = clean_param($card[$field], PARAM_TEXT);
                }
            }

            $flashcards[$index] = $card;
        }

        return $flashcards;
    }

    private static function get_namespace_name($namespaces, $namespace_id) {
        foreach ($namespaces as $ns) {
            if (isset($ns['guid']) && $ns['guid'] === $namespace_id) {
                return $ns['name'];
            }
        }
        return null; // not found
    }

    /**
     * Fetch session data from Framez API
     *
     * @param string $namespace_id Session ID
     * @param int $course_id Course ID
     * @return array Session data with summary and flashcards
     * @throws moodle_exception
     */
    private static function fetch_session_data($namespace_id, $course_id) {
        global $CFG;
        
        // Get JWT token for authentication
        $clientid = $CFG->framez_tool_client;
        $jwt = self::get_signed_jwt($course_id, $clientid);
        
        // Fetch summary and flashcards from API
        $summary = self::fetch_summary_from_api($namespace_id, $course_id, $jwt);
        $flashcards = self::fetch_flashcards_from_api($namespace_id, $course_id, $jwt);

        $namespaces = self::fetch_namespaces_from_api($jwt, $course_id);

        $namespace_name = self::get_namespace_name($namespaces, $namespace_id);

        if (!$namespace_name) {
            throw new \moodle_exception('namespace_not_found in fetch_session_data', 'local_framez_webservice', '', 'Namespace not found in API response');
        }

        return array(
            'summary' => $summary,
            'flashcards' => $flashcards,
            'namespace_name' => $namespace_name
        );
    }

    /**
     * Generate signed JWT for API authentication
     *
     * @param int $courseId Course ID
     * @param string $clientid Client ID
     * @return string JWT token
     * @throws moodle_exception
     */
    private static function get_signed_jwt($courseId, $clientid): string {
        global $CFG;
        
        $privateKeyFile = $CFG->dataroot . '/framez_private.pem';
        $privateKey = file_get_contents($privateKeyFile);
        if (!$privateKey) {
            throw new \moodle_exception("framez - missing private key {$privateKeyFile}");
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

        // Try to use JWT if available, otherwise fall back to manual JWT creation
        if (class_exists('\Firebase\JWT\JWT')) {
            return \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
        }
        
        // Fallback: create a simple JWT manually (basic implementation)
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $payload_json = json_encode($payload);
        
        $base64_header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64_payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_json));
        
        $signature = '';
        if (openssl_sign($base64_header . '.' . $base64_payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            $base64_signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
            return $base64_header . '.' . $base64_payload . '.' . $base64_signature;
        }
        
        throw new \moodle_exception('jwt_creation_failed', 'local_framez_webservice', '', 'Failed to create JWT signature');
    }

    /**
     * Fetch flashcards from Framez API
     *
     * @param string $namespace_id Session ID
     * @param int $course_id Course ID
     * @param string $jwt JWT token
     * @return array Flashcards data
     * @throws moodle_exception
     */
    private static function fetch_flashcards_from_api($namespace_id, $course_id, $jwt) {
        global $CFG;
        
        $url = "https://{$CFG->framez_api_server}/api/v1/lti/session/{$namespace_id}/flashcards";
        
        $response = self::make_framez_api_call($url, $jwt);
        
        if (!isset($response['payload']['flashcards'])) {
            throw new \moodle_exception('flashcards_not_found', 'local_framez_webservice', '', 'Flashcards not found in API response');
        }
        
        return $response['payload']['flashcards'];
    }

    /**
     * Fetch summary from Framez API
     *
     * @param string $namespace_id Session ID
     * @param int $course_id Course ID
     * @param string $jwt JWT token
     * @return string Summary data
     * @throws moodle_exception
     */
    private static function fetch_summary_from_api($namespace_id, $course_id, $jwt) {
        global $CFG;
        
        $url = "https://{$CFG->framez_api_server}/api/v1/lti/session/{$namespace_id}/summary";
        
        $response = self::make_framez_api_call($url, $jwt);
        
        if (!isset($response['payload']['summary'])) {
            throw new \moodle_exception('summary_not_found', 'local_framez_webservice', '', 'Summary not found in API response');
        }
        
        return $response['payload']['summary'];
    }

    /**
     * Make API call to Framez server
     *
     * @param string $url API endpoint URL
     * @param string $jwt JWT token
     * @return array API response
     * @throws moodle_exception
     */
    private static function make_framez_api_call($url, $jwt) {
        $headers = [
            'Authorization: Bearer ' . $jwt,
            'Content-Type: application/json'
        ];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerr = curl_error($curl);
        curl_close($curl);

        if ($status !== 200 || !$response) {
            throw new \moodle_exception('api_call_failed', 'local_framez_webservice', '', "HTTP $status. Curl error: $curlerr");
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('invalid_api_response', 'local_framez_webservice', '', 'API response is not valid JSON');
        }

        if (!isset($decoded['status']) || $decoded['status'] !== 'SUCCESS') {
            throw new \moodle_exception('api_error', 'local_framez_webservice', '', 'API returned status: ' . ($decoded['status'] ?? 'MISSING'));
        }

        if (!isset($decoded['payload']) || !is_array($decoded['payload'])) {
            throw new \moodle_exception('api_payload_missing', 'local_framez_webservice', '', 'API response missing valid payload');
        }

        return $decoded;
    }

    private static function fetch_namespaces_from_api($jwt, $courseid): array 
    {
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

    /**
     * Get or create LTI tool instance (copied from block_framez)
     *
     * @param string $toolurl Tool URL
     * @param int $courseid Course ID
     * @return int Course module ID
     */
    private static function get_or_create_lti_tool($toolurl, $courseid): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/lti/lib.php');

        // Check if already exists
        $instances = get_coursemodules_in_course('lti', $courseid);
        foreach ($instances as $cm) {
            if ($cm->modname === 'lti' && $cm->name === 'Framez') {
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

        return $cmid;
    }

    /**
     * Create or update page module in course based on namespace_id
     *
     * @param stdClass $course Course object
     * @param array $summary Summary data
     * @param array $flashcards Flashcards data
     * @param string $namespace_name Namespace name for the page
     * @param string $namespace_id Namespace ID for generating AI links
     * @return int|false Page ID or false on failure
     */
    private static function create_page_module($course, $summary, $flashcards, $namespace_name, $namespace_id) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/local/framez_webservice/lib.php');

        // Check if a page with this namespace_id already exists
        $result = self::find_existing_page_by_namespace($course->id, $namespace_id);
        
        if ($result) {
            list($existing_page, $cmid) = $result;
            // Update existing page
            debugging("Framez Debug: Updating existing page with namespace_id: {$namespace_id}", DEBUG_DEVELOPER);
            return self::update_existing_page($existing_page, $summary, $flashcards, $namespace_name, $namespace_id, $cmid);
        } else {
            // Create new page
            debugging("Framez Debug: Creating new page with namespace_id: {$namespace_id}", DEBUG_DEVELOPER);
            return self::create_new_page($course, $summary, $flashcards, $namespace_name, $namespace_id);
        }
    }

    /**
     * Find existing page by namespace_id using Moodle's first-class APIs
     *
     * @param int $course_id Course ID
     * @param string $namespace_id Namespace ID
     * @return stdClass|false Existing page record or false if not found
     */
    private static function find_existing_page_by_namespace($course_id, $namespace_id) {
        global $DB;
        
        // Use Moodle's proper course module APIs to get all page modules in the course
        $course_modules = get_coursemodules_in_course('page', $course_id);
        
        if (empty($course_modules)) {
            debugging("Framez Debug: No page modules found in course {$course_id}", DEBUG_DEVELOPER);
            return false;
        }
        
        // Search through each page module for the namespace_id
        foreach ($course_modules as $cm) {
            // Get the page instance
            if ($cm->deletioninprogress) {
                debugging("Framez Debug: Page instance with id {$cm->id} is in deletion progress", DEBUG_DEVELOPER);
                continue;
            }
            $page = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
            
            if (!$page) {
                debugging("Framez Debug: Page instance not found for cmid {$cm->id}", DEBUG_DEVELOPER);
                continue;
            }
            
            // Check if this page contains our namespace_id in content or intro
            $search_pattern = 'namespace_id:' . $namespace_id;
            if (strpos($page->content, $search_pattern) !== false || strpos($page->intro, $search_pattern) !== false) {
                debugging("Framez Debug: Found existing page ID {$page->id} (cmid: {$cm->id}) for namespace {$namespace_id}", DEBUG_DEVELOPER);
                return [$page, $cm->id];
            }
        }
        
        debugging("Framez Debug: No existing page found for namespace {$namespace_id} in course {$course_id}", DEBUG_DEVELOPER);
        return false;
    }

    /**
     * Update existing page with new content
     *
     * @param stdClass $existing_page Existing page record
     * @param array $summary Summary data
     * @param array $flashcards Flashcards data
     * @param string $namespace_name Namespace name
     * @param string $namespace_id Namespace ID
     * @return int|false Page ID or false on failure
     */
    private static function update_existing_page($existing_page, $summary, $flashcards, $namespace_name, $namespace_id, $cmid) {
        global $CFG, $DB;
        
        try {
            // Generate H5P content if flashcards are provided
            $h5pcontent = '';
            $h5pstoredfile = null;
            $h5pfilename = '';
            $coursecontext = \context_course::instance($existing_page->course);
            
            if (!empty($flashcards)) {
                require_once(__DIR__ . '/h5p_generator.php');
                $title = 'Dialog Cards - ' . $namespace_name;
                $h5pstoredfile = \local_framez_webservice\h5p_generator::create_h5p_package_file($flashcards, $title, $coursecontext);
                $content_id = \local_framez_webservice\h5p_content_manager::add_to_content_bank($h5pstoredfile, $existing_page->course);
                $h5pfilename = $h5pstoredfile->get_filename();
                $h5pcontent = '<div class="h5p-placeholder" contenteditable="false">@@PLUGINFILE@@/' . $h5pfilename . '</div>';
            }

            // Generate AI navigation links
            global $USER;
            // Generate new page content
            $pagecontent = local_framez_webservice_get_markdown_page_content($summary, $h5pcontent);
            
            // Update the page record
            $existing_page->name = $namespace_name;
            $existing_page->content = $pagecontent;
            $existing_page->timemodified = time();
            
            // Add namespace_id metadata to the content for future lookups
            $existing_page->content = "<!-- namespace_id:{$namespace_id} -->\n" . $existing_page->content;
            
            $DB->update_record('page', $existing_page);
            
            // Update course module
            $cm = $DB->get_record('course_modules', ['instance' => $existing_page->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'page'])]);
            if ($cm) {
                $cm->timemodified = time();
                $DB->update_record('course_modules', $cm);
            }
            
            // If H5P file was generated, copy it to the page's file area
            if (!empty($h5pfilename) && isset($h5pstoredfile)) {
                $fs = get_file_storage();
                $contextmodule = \context_module::instance($cm->id);
                
                // Remove old H5P files from this page
                $old_files = $fs->get_area_files($contextmodule->id, 'mod_page', 'content', 0);
                foreach ($old_files as $file) {
                    if (pathinfo($file->get_filename(), PATHINFO_EXTENSION) === 'h5p') {
                        $file->delete();
                    }
                }
                
                // Add new H5P file
                $filerecord = [
                    'contextid' => $contextmodule->id,
                    'component' => 'mod_page',
                    'filearea' => 'content',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $h5pfilename,
                    'userid' => $USER->id,
                    'timecreated' => time(),
                    'timemodified' => time()
                ];
                $fs->create_file_from_storedfile($filerecord, $h5pstoredfile);
            }
            
            debugging("LONDON cmidis: " . print_r($cm, true));
            // Ensure page-specific block instance exists with namespace_id
            self::create_page_specific_block_instance($cmid, $namespace_id, $namespace_name, $existing_page->course);
            
            debugging("Framez Debug: Successfully updated page ID {$existing_page->id} for namespace {$namespace_id}", DEBUG_DEVELOPER);
            return ['pageid' => $cmid, 'action' => 'updated'];
            
        } catch (Exception $e) {
            debugging("Framez Debug: Error updating page: " . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Create new page module in course
     *
     * @param stdClass $course Course object
     * @param array $summary Summary data
     * @param array $flashcards Flashcards data
     * @param string $namespace_name Namespace name for the page
     * @param string $namespace_id Namespace ID for generating AI links
     * @return int|false Page ID or false on failure
     */
    private static function create_new_page($course, $summary, $flashcards, $namespace_name, $namespace_id) {
        global $CFG, $DB;

        // Get the page module ID
        $module = $DB->get_record('modules', array('name' => 'page'), 'id', MUST_EXIST);
        if (!$module) {
            throw new \moodle_exception('Page module not found');
        }

        // Generate H5P package if flashcards are provided (will be embedded inline via @@PLUGINFILE@@ placeholder)
        $h5pcontent = '';
        $h5pstoredfile = null;
        $h5pfilename = '';
        $coursecontext = \context_course::instance($course->id);
        if (!empty($flashcards)) {
            require_once(__DIR__ . '/h5p_generator.php');
            $title = 'Dialog Cards - ' . $namespace_name;
            $h5pstoredfile = \local_framez_webservice\h5p_generator::create_h5p_package_file($flashcards, $title, $coursecontext);
            $content_id = \local_framez_webservice\h5p_content_manager::add_to_content_bank($h5pstoredfile, $course->id);
            $h5pfilename = $h5pstoredfile->get_filename();
            // Placeholder consumed by filter_displayh5p within page rendering
            $h5pcontent = '<div class="h5p-placeholder" contenteditable="false">@@PLUGINFILE@@/' . $h5pfilename . '</div>';
        }

        // Generate AI navigation links
        global $USER;
        
        // Generate page content with AI navigation at the top
        $pagecontent = local_framez_webservice_get_markdown_page_content($summary, $h5pcontent);
        
        // Add namespace_id metadata to the content for future lookups
        $pagecontent = "<!-- namespace_id:{$namespace_id} -->\n" . $pagecontent;
        // Framez Magic GUID for framez generated content
        $pagecontent = "<!-- e9f4b5a1-13c5-4278-824c-05a8ac066e07 -->\n" . $pagecontent;
        
        // Sanitize and truncate page title to fit DB column (typically 255 chars)
        $pagetitle = $namespace_name;

        // Prepare module data
        $moduleinfo = new stdClass();
        $moduleinfo->modulename = 'page';
        $moduleinfo->module = $module->id;  // Add the module ID
        $moduleinfo->course = $course->id;
        $moduleinfo->name = $pagetitle;
        $moduleinfo->intro = '';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->content = $pagecontent;
        $moduleinfo->contentformat = FORMAT_HTML;
        $moduleinfo->display = 5; // Display on course page
        // Ensure required display options are set to avoid warnings and match inline example
        $moduleinfo->printintro = 0;
        $moduleinfo->printlastmodified = 1;
        $moduleinfo->displayoptions = 'a:2:{s:10:"printintro";s:1:"0";s:17:"printlastmodified";s:1:"1";}';
        $moduleinfo->revision = 1;
        $moduleinfo->cmidnumber = '';
        $moduleinfo->section = 0; // Will be added to first available section
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->availabilityconditionsjson = '{"op":"&","c":[],"showc":[]}';
        $moduleinfo->completionunlocked = 1;
        $moduleinfo->completionview = 1;
        $moduleinfo->completionexpected = 0;
        $moduleinfo->tags = array();

        try {
            $moduleinfo = add_moduleinfo($moduleinfo, $course);

            // If we created an H5P file, copy it into the page module file area so the placeholder resolves.
            if ($h5pstoredfile) {
                $cmid = $moduleinfo->coursemodule;
                $cmcontext = \context_module::instance($cmid);
                $fs = get_file_storage();
                $filerecord = [
                    'contextid' => $cmcontext->id,
                    'component' => 'mod_page',
                    'filearea' => 'content',
                    'itemid' => 0,
                    'filepath' => '/',
                    'filename' => $h5pfilename
                ];
                // Create file in the page content area from the generated stored file
                $fs->create_file_from_storedfile($filerecord, $h5pstoredfile);
                // Optionally delete the temporary/local copy to avoid duplicates
                try { $h5pstoredfile->delete(); } catch (\Throwable $t) {}
            }

            debugging("LONDON cmid is: " . $moduleinfo->coursemodule);

            // Create page-specific block instance with namespace_id
            self::create_page_specific_block_instance($moduleinfo->coursemodule, $namespace_id, $namespace_name, $course->id);

            return ['pageid' => $moduleinfo->coursemodule, 'action' => 'created'];
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create page-specific block instance with namespace_id
     *
     * @param int $cmid Course module ID
     * @param string $namespace_id Namespace ID
     * @param string $namespace_name Namespace name
     * @param int $course_id Course ID
     * @return void
     */
    private static function create_page_specific_block_instance($cmid, $namespace_id, $namespace_name, $course_id) {


        $page_context = \context_module::instance($cmid);
        /**** . AI Generated Code */
        global $DB;
        
        // Check if block instance already exists for this page
        $exists = $DB->record_exists('block_instances', [
            'blockname' => 'framez',
            'parentcontextid' => $page_context->id,
            'pagetypepattern' => 'mod-page-view',
            'subpagepattern' => (string)$cmid
        ]);
        
        if ($exists) {
            // Update existing block instance with new namespace data
            $block_instance = $DB->get_record('block_instances', [
                'blockname' => 'framez',
                'parentcontextid' => $page_context->id,
                'pagetypepattern' => 'mod-page-view',
                'subpagepattern' => (string)$cmid
            ]);
            
            $config_data = [
                'namespace_id' => $namespace_id,
                'namespace_name' => $namespace_name,
                'created_by' => 'framez_webservice',
                'page_id' => $cmid,
                'course_id' => $course_id
            ];
            
            $block_instance->configdata = base64_encode(serialize($config_data));
            $block_instance->timemodified = time();
            $DB->update_record('block_instances', $block_instance);
            
            return;
        }
        
        // Create new block instance
        $block_instance = new stdClass();
        $block_instance->blockname = 'framez';
        $block_instance->parentcontextid = $page_context->id;
        $block_instance->showinsubcontexts = 1;
        $block_instance->pagetypepattern = 'mod-page-view';
        $block_instance->subpagepattern = null;
        $block_instance->defaultregion = 'side-pre';
        $block_instance->defaultweight = 0;
        
        $config_data = [
            'namespace_id' => $namespace_id,
            'namespace_name' => $namespace_name,
            'created_by' => 'framez_webservice',
            'page_id' => $cmid,
            'course_id' => $course_id
        ];
        
        $block_instance->configdata = base64_encode(serialize($config_data));
        $block_instance->timecreated = time();
        $block_instance->timemodified = time();
        
        $block_instance->id = $DB->insert_record('block_instances', $block_instance);
        
        // Ensure block is visible
        $DB->insert_record('block_positions', [
            'blockinstanceid' => $block_instance->id,
            'contextid' => $page_context->id,
            'pagetype' => 'mod-page-view',
            'subpage' => '',
            'visible' => 1,
            'region' => 'side-pre',
            'weight' => 0
        ]);
        
        // Clear block cache to ensure the new instance is recognized
        purge_all_caches();
    }
    
    // Removed iframe/embed-based H5P generation. We now embed via @@PLUGINFILE@@ placeholder for inline rendering.
}

