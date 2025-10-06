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

use local_framez_webservice\external\create_course_page;

/**
 * Unit tests for Framez Webservice external API
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external_test extends \advanced_testcase {

    /**
     * Mock Framez API calls for testing
     *
     * @param string $session_id Session ID
     * @param int $course_id Course ID
     */
    private function mock_framez_api_calls($session_id, $course_id) {
        // This would normally be implemented with a proper mocking framework
        // For now, we'll assume the API calls work correctly
        // In a real implementation, you'd mock the curl calls or use a test double
    }

    /**
     * Test successful page creation
     */
    public function test_create_course_page_success() {
        global $DB;

        $this->resetAfterTest(true);

        // Create a course
        $course = $this->getDataGenerator()->create_course();

        // Create a user with editing teacher role
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        // Set user context
        $this->setUser($user);

        // Prepare test data
        $session_id = 'test-session-123';

        // Mock API responses
        $this->mock_framez_api_calls($session_id, $course->id);

        // Call the webservice
        $result = create_course_page::execute($course->id, $session_id);

        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('pageid', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertGreaterThan(0, $result['pageid']);
        $this->assertEmpty($result['warnings']);

        // Verify page was created in database
        $page = $DB->get_record('page', ['id' => $result['pageid']]);
        $this->assertNotFalse($page);
        $this->assertEquals('Test Page', $page->name);
    }

    /**
     * Test invalid course ID
     */
    public function test_create_course_page_invalid_course() {
        $this->resetAfterTest(true);

        // Create a user
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $session_id = 'test-session-123';

        // Test with non-existent course ID
        $this->expectException(\dml_missing_record_exception::class);
        create_course_page::execute(99999, $session_id);
    }

    /**
     * Test API failure scenarios
     */
    public function test_create_course_page_api_failure() {
        $this->resetAfterTest(true);

        // Create a course and user
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $session_id = 'invalid-session';

        // Test with invalid session ID (API failure)
        $this->expectException(\moodle_exception::class);
        create_course_page::execute($course->id, $session_id);
    }

    /**
     * Test invalid session ID parameter
     */
    public function test_create_course_page_invalid_session_id() {
        $this->resetAfterTest(true);

        // Create a course and user
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        // Test with empty session ID
        $this->expectException(\moodle_exception::class);
        create_course_page::execute($course->id, '');

        // Test with null session ID
        $this->expectException(\moodle_exception::class);
        create_course_page::execute($course->id, null);
    }

    /**
     * Test permission validation
     */
    public function test_create_course_page_no_permission() {
        $this->resetAfterTest(true);

        // Create a course and user without editing permissions
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $session_id = 'test-session-123';

        // This should fail due to missing capability
        $this->expectException(\required_capability_exception::class);
        create_course_page::execute($course->id, $session_id);
    }

    /**
     * Test with valid session ID
     */
    public function test_create_course_page_valid_session() {
        $this->resetAfterTest(true);

        // Create a course and user
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');
        $this->setUser($user);

        $session_id = 'valid-session-123';

        // Mock API responses
        $this->mock_framez_api_calls($session_id, $course->id);

        // Call with valid session ID
        $result = create_course_page::execute($course->id, $session_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pageid', $result);
        $this->assertGreaterThan(0, $result['pageid']);
    }

    /**
     * Test parameter validation
     */
    public function test_create_course_page_parameters() {
        $params = create_course_page::execute_parameters();
        $this->assertInstanceOf(\core_external\external_function_parameters::class, $params);
    }

    /**
     * Test return value structure
     */
    public function test_create_course_page_returns() {
        $returns = create_course_page::execute_returns();
        $this->assertInstanceOf(\core_external\external_single_structure::class, $returns);
    }
}




