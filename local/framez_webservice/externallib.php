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
 * External API for Framez Webservice plugin (Legacy support)
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/classes/external.php');

/**
 * External API class for legacy webservice support
 */
class local_framez_webservice_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function create_course_page_parameters() {
        return \local_framez_webservice\external\create_course_page::execute_parameters();
    }

    /**
     * Create a course page with summary and cue cards content
     *
     * @param int $courseid Course ID
     * @param string $summary Summary JSON data
     * @param string $cuecards Cue cards JSON data
     * @return array Result with page ID and warnings
     */
    public static function create_course_page($courseid, $summary, $cuecards) {
        return \local_framez_webservice\external\create_course_page::execute($courseid, $summary, $cuecards);
    }

    /**
     * Returns description of method result value
     *
     * @return external_single_structure
     */
    public static function create_course_page_returns() {
        return \local_framez_webservice\external\create_course_page::execute_returns();
    }
}




