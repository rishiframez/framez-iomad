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

use core_contentbank\contentbank;
use stored_file;

/**
 * H5P content manager for Framez Webservice
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_content_manager {

    /**
     * Add H5P content to course content bank using proper Moodle workflow
     *
     * @param stored_file $h5pfile H5P package file
     * @param int $courseid Course ID
     * @return int Content bank content ID
     */
    public static function add_to_content_bank(stored_file $h5pfile, int $courseid): int {
        global $USER;

        debugging("H5P Debug: Adding H5P to content bank using proper Moodle workflow", DEBUG_DEVELOPER);
        debugging("H5P Debug: Course ID: " . $courseid, DEBUG_DEVELOPER);
        debugging("H5P Debug: H5P file ID: " . $h5pfile->get_id(), DEBUG_DEVELOPER);

        // Get course context
        $context = \context_course::instance($courseid);
        debugging("H5P Debug: Course context ID: " . $context->id, DEBUG_DEVELOPER);
        debugging("H5P Debug: Course context level: " . $context->contextlevel, DEBUG_DEVELOPER);
        debugging("H5P Debug: Course context path: " . $context->path, DEBUG_DEVELOPER);
        
        // First, save the H5P content using Moodle's helper
        debugging("H5P Debug: About to save H5P content using helper", DEBUG_DEVELOPER);
        $factory = new \core_h5p\factory();
        $config = (object)[
            'frame' => 1,
            'export' => 1,
            'embed' => 1,  // Allow embedding
            'copyright' => 1,
        ];
        
        $h5pid = \core_h5p\helper::save_h5p($factory, $h5pfile, $config);
        debugging("H5P Debug: H5P content saved with ID: " . $h5pid, DEBUG_DEVELOPER);
        
        if (!$h5pid) {
            throw new \moodle_exception('h5p_save_failed', 'local_framez_webservice');
        }
        
        // Now create content bank entry manually using proper workflow
        debugging("H5P Debug: About to create content bank entry manually", DEBUG_DEVELOPER);
        
        // Create content bank entry manually
        $record = new \stdClass();
        $record->name = $h5pfile->get_filename();
        $record->contenttype = 'contenttype_h5p';
        $record->contextid = $context->id;
        $record->usercreated = $USER->id;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->configdata = json_encode(['h5p_id' => $h5pid]); // Store the H5P ID for easy retrieval
        $record->instanceid = 0;
        
        global $DB;
        $contentid = $DB->insert_record('contentbank_content', $record);
        debugging("H5P Debug: Content bank entry created with ID: " . $contentid, DEBUG_DEVELOPER);
        
        // Create the H5P file in content bank
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'contentbank',
            'filearea' => 'public',
            'itemid' => $contentid,
            'filepath' => '/',
            'filename' => $h5pfile->get_filename(),
            'timecreated' => time(),
            'timemodified' => time(),
            'userid' => $USER->id
        ];
        
        $contentfile = $fs->create_file_from_storedfile($filerecord, $h5pfile);
        debugging("H5P Debug: Content bank file created with ID: " . $contentfile->get_id(), DEBUG_DEVELOPER);
        
        debugging("H5P Debug: Content bank integration completed successfully", DEBUG_DEVELOPER);
        return $contentid;
    }

    /**
     * Get content bank URL for H5P content
     *
     * @param int $contentid Content bank content ID
     * @return string URL for content bank H5P
     */
    public static function get_content_bank_url(int $contentid): string {
        global $CFG;
        
        return $CFG->wwwroot . '/contentbank/view.php?id=' . $contentid;
    }

    /**
     * Get H5P embed URL from content bank
     *
     * @param int $contentid Content bank content ID
     * @return string H5P embed URL
     */
    public static function get_h5p_embed_url(int $contentid): string {
        global $CFG, $DB;
        
        debugging("H5P Debug: Getting embed URL for content ID: " . $contentid, DEBUG_DEVELOPER);
        
        // Get the H5P file from content bank
        $content = $DB->get_record('contentbank_content', ['id' => $contentid]);
        if (!$content) {
            debugging("H5P Debug: Content bank entry not found for ID: " . $contentid, DEBUG_DEVELOPER);
            throw new \moodle_exception('content_not_found', 'local_framez_webservice');
        }
        
        debugging("H5P Debug: Found content bank entry: " . $content->name, DEBUG_DEVELOPER);
        
        // Try to get the H5P ID from the stored configdata first
        $h5pid = null;
        if (!empty($content->configdata)) {
            $config = json_decode($content->configdata, true);
            if (isset($config['h5p_id'])) {
                $h5pid = $config['h5p_id'];
                debugging("H5P Debug: Found stored H5P ID in configdata: " . $h5pid, DEBUG_DEVELOPER);
            }
        }
        
        // If we have the H5P ID, use it directly
        if ($h5pid) {
            // Use the content bank URL which was working
            $embedurl = $CFG->wwwroot . '/contentbank/view.php?id=' . $contentid;
            debugging("H5P Debug: Generated content bank URL using stored H5P ID: " . $embedurl, DEBUG_DEVELOPER);
            return $embedurl;
        }
        
        // Fallback: try to find H5P content by filename (for backward compatibility)
        debugging("H5P Debug: No stored H5P ID found, trying fallback method", DEBUG_DEVELOPER);
        
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $content->contextid,
            'contentbank',
            'public',
            $contentid,
            'sortorder, itemid, filepath, filename',
            false
        );
        
        if (empty($files)) {
            debugging("H5P Debug: No files found in content bank for content ID: " . $contentid, DEBUG_DEVELOPER);
            throw new \moodle_exception('h5p_file_not_found', 'local_framez_webservice');
        }
        
        $file = reset($files);
        debugging("H5P Debug: Found content bank file: " . $file->get_filename(), DEBUG_DEVELOPER);
        
        // Get the most recent H5P content as fallback
        $h5precord = $DB->get_record_sql(
            "SELECT * FROM {h5p} ORDER BY id DESC LIMIT 1"
        );
        if (!$h5precord) {
            debugging("H5P Debug: No H5P content found at all", DEBUG_DEVELOPER);
            throw new \moodle_exception('h5p_content_not_found', 'local_framez_webservice');
        }
        debugging("H5P Debug: Using most recent H5P content with ID: " . $h5precord->id, DEBUG_DEVELOPER);
        
        $embedurl = $CFG->wwwroot . '/contentbank/view.php?id=' . $contentid;
        debugging("H5P Debug: Generated content bank URL using fallback method: " . $embedurl, DEBUG_DEVELOPER);
        
        return $embedurl;
    }

    /**
     * Clean up orphaned H5P content from content bank
     *
     * @param int $courseid Course ID
     */
    public static function cleanup_orphaned_h5p(int $courseid): void {
        global $DB;
        
        // Get course context
        $context = \context_course::instance($courseid);
        
        // Find content bank H5P content that might be orphaned
        // This is a placeholder for future cleanup logic
        // For now, we'll leave cleanup to manual processes
        
        // Log cleanup attempt
        debugging('H5P cleanup requested for course ' . $courseid, DEBUG_DEVELOPER);
    }

    /**
     * Check if content bank H5P content exists and is accessible
     *
     * @param int $contentid Content bank content ID
     * @return bool True if content exists and is accessible
     */
    public static function is_content_accessible(int $contentid): bool {
        global $DB;
        
        try {
            // Check if content exists
            $content = $DB->get_record('contentbank_content', ['id' => $contentid]);
            if (!$content) {
                return false;
            }
            
            // Check if it's H5P content
            if ($content->contenttype !== 'h5p') {
                return false;
            }
            
            // Check if file exists
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $content->contextid,
                'contentbank',
                'public',
                $contentid,
                'sortorder, itemid, filepath, filename',
                false
            );
            
            return !empty($files);
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get content bank content information
     *
     * @param int $contentid Content bank content ID
     * @return \stdClass Content information
     */
    public static function get_content_info(int $contentid): \stdClass {
        global $DB;
        
        $content = $DB->get_record('contentbank_content', ['id' => $contentid]);
        if (!$content) {
            throw new \moodle_exception('content_not_found', 'local_framez_webservice');
        }
        
        return $content;
    }
}
