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

use core_h5p\factory;
use core_h5p\local\library\autoloader;
use stdClass;
use stored_file;

/**
 * H5P Dialog Cards generator for Framez Webservice
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class h5p_generator {

    /**
     * Generate H5P Dialog Cards package content
     *
     * @param array $cuecards Array of cue cards with question/answer pairs
     * @param string $title Title for the H5P content
     * @return array H5P package data
     */
    public static function generate_dialog_cards_h5p(array $cuecards, string $title): array {
        // Validate H5P libraries are available
        if (!self::validate_h5p_libraries()) {
            throw new \moodle_exception('h5p_library_missing', 'local_framez_webservice');
        }

        // Prepare dialog cards content
        $dialogcards = [];
        foreach ($cuecards as $index => $card) {
            $dialogcards[] = [
                'text' => $card['question'] ?? '',
                'answer' => $card['answer'] ?? ''
            ];
        }

        // Create H5P package structure
        $h5pdata = [
            'h5p.json' => [
                'title' => $title,
                'language' => 'en',
                'mainLibrary' => 'H5P.Dialogcards',
                'embedTypes' => ['iframe'],
                'license' => 'U',
                'preloadedDependencies' => [
                    [
                        'machineName' => 'H5P.Dialogcards',
                        'majorVersion' => '1',
                        'minorVersion' => '9'
                    ]
                ]
            ],
            'content/content.json' => [
                'dialogs' => $dialogcards,
                'behaviour' => [
                    'useCardName' => false,
                    'disableBackwardsNavigation' => false,
                    'randomCards' => false,
                    'autoAdvance' => false,
                    'showSolutions' => true,
                    'textualButton' => true,
                    'separateDialogs' => false,
                    'showSolutionsRequiresInput' => true,
                    'caseSensitive' => true
                ]
            ]
        ];

        return $h5pdata;
    }

    /**
     * Create H5P package file in Moodle file system
     *
     * @param array $cuecards Array of cue cards with question/answer pairs
     * @param string $title Title for the H5P content
     * @param \context $context Moodle context
     * @return stored_file Created H5P file
     */
    public static function create_h5p_package_file(array $cuecards, string $title, \context $context): stored_file {
        global $CFG;

        debugging("H5P Debug: Starting H5P package creation", DEBUG_DEVELOPER);
        debugging("H5P Debug: Cuecards count: " . count($cuecards), DEBUG_DEVELOPER);
        debugging("H5P Debug: Title: " . $title, DEBUG_DEVELOPER);
        debugging("H5P Debug: Context ID: " . $context->id, DEBUG_DEVELOPER);

        // Generate H5P package data
        $h5pdata = self::generate_dialog_cards_h5p($cuecards, $title);
        debugging("H5P Debug: H5P package data generated successfully", DEBUG_DEVELOPER);

        // Create temporary directory for H5P package
        $tempdir = $CFG->tempdir . '/framez_h5p_' . uniqid();
        if (!mkdir($tempdir, 0777, true)) {
            throw new \moodle_exception('temp_dir_creation_failed', 'local_framez_webservice');
        }

        try {
            // Create h5p.json file
            $h5pjson = json_encode($h5pdata['h5p.json'], JSON_PRETTY_PRINT);
            file_put_contents($tempdir . '/h5p.json', $h5pjson);

            // Create content directory and content.json
            if (!mkdir($tempdir . '/content', 0777, true)) {
                throw new \moodle_exception('content_dir_creation_failed', 'local_framez_webservice');
            }

            $contentjson = json_encode($h5pdata['content/content.json'], JSON_PRETTY_PRINT);
            file_put_contents($tempdir . '/content/content.json', $contentjson);

            // Create the H5P package (ZIP file)
            $zipfile = $tempdir . '/' . self::sanitize_filename($title) . '.h5p';
            $zip = new \ZipArchive();
            if ($zip->open($zipfile, \ZipArchive::CREATE) !== TRUE) {
                throw new \moodle_exception('zip_creation_failed', 'local_framez_webservice');
            }

            $zip->addFile($tempdir . '/h5p.json', 'h5p.json');
            $zip->addFile($tempdir . '/content/content.json', 'content/content.json');
            $zip->close();

            // Create stored file from ZIP - use temporary component for now
            global $USER;
            
            // Generate unique filename with timestamp to avoid conflicts
            $unique_filename = self::sanitize_filename($title) . '_' . time() . '_' . uniqid() . '.h5p';
            
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'local_framez_webservice',
                'filearea' => 'h5p_packages',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => $unique_filename,
                'userid' => $USER->id,
                'timecreated' => time(),
                'timemodified' => time()
            ];

            $fs = get_file_storage();
            $storedfile = $fs->create_file_from_pathname($filerecord, $zipfile);
            
            debugging("H5P Debug: Created unique H5P file: " . $unique_filename, DEBUG_DEVELOPER);

            return $storedfile;

        } finally {
            // Clean up temporary directory
            self::cleanup_temp_directory($tempdir);
        }
    }

    /**
     * Validate that H5P Dialog Cards library is available
     *
     * @return bool True if library is available
     */
    public static function validate_h5p_libraries(): bool {
        global $CFG;

        try {
            // Check if H5P is enabled
            if (!file_exists($CFG->dirroot . '/h5p/classes/factory.php')) {
                debugging("H5P Debug: Factory file not found at " . $CFG->dirroot . '/h5p/classes/factory.php', DEBUG_DEVELOPER);
                return false;
            }

            // Load H5P autoloader
            require_once($CFG->dirroot . '/h5p/classes/local/library/autoloader.php');
            autoloader::register();
            debugging("H5P Debug: Autoloader registered successfully", DEBUG_DEVELOPER);

            // Check if Dialog Cards library exists
            $factory = new factory();
            $core = $factory->get_core();
            debugging("H5P Debug: Factory and core created successfully", DEBUG_DEVELOPER);
            
            // Try to load the Dialog Cards library
            $library = $core->loadLibrary('H5P.Dialogcards', 1, 9);
            debugging("H5P Debug: loadLibrary result: " . (empty($library) ? 'EMPTY' : 'SUCCESS'), DEBUG_DEVELOPER);
            
            if (!empty($library)) {
                debugging("H5P Debug: Library loaded - " . json_encode($library), DEBUG_DEVELOPER);
            }
            
            return !empty($library);
        } catch (\Exception $e) {
            debugging("H5P Debug: Exception in validate_h5p_libraries: " . $e->getMessage(), DEBUG_DEVELOPER);
            debugging("H5P Debug: Exception trace: " . $e->getTraceAsString(), DEBUG_DEVELOPER);
            return false;
        }
    }

    /**
     * Get available H5P content types
     *
     * @return array List of available H5P content types
     */
    public static function get_available_h5p_types(): array {
        global $CFG, $DB;

        try {
            debugging("H5P Debug: Checking available H5P content types", DEBUG_DEVELOPER);
            
            if (!file_exists($CFG->dirroot . '/h5p/classes/factory.php')) {
                debugging("H5P Debug: Factory file not found", DEBUG_DEVELOPER);
                return [];
            }

            require_once($CFG->dirroot . '/h5p/classes/local/library/autoloader.php');
            autoloader::register();

            $factory = new factory();
            $core = $factory->get_core();
            
            // Check what libraries are actually installed in the database
            try {
                $sql = "SELECT machine_name, major_version, minor_version, title 
                        FROM {h5p_libraries} 
                        WHERE enabled = 1 
                        ORDER BY machine_name, major_version DESC, minor_version DESC";
                $libraries = $DB->get_records_sql($sql);
                
                debugging("H5P Debug: Found " . count($libraries) . " H5P libraries in database", DEBUG_DEVELOPER);
                foreach ($libraries as $lib) {
                    debugging("H5P Debug: Library - " . $lib->machine_name . " v" . $lib->major_version . "." . $lib->minor_version . " (" . $lib->title . ")", DEBUG_DEVELOPER);
                }
            } catch (\Exception $db_error) {
                debugging("H5P Debug: Database query failed, trying alternative approach: " . $db_error->getMessage(), DEBUG_DEVELOPER);
                // Try alternative column names
                try {
                    $sql = "SELECT name, major_version, minor_version, title 
                            FROM {h5p_libraries} 
                            WHERE enabled = 1 
                            ORDER BY name, major_version DESC, minor_version DESC";
                    $libraries = $DB->get_records_sql($sql);
                    debugging("H5P Debug: Found " . count($libraries) . " H5P libraries with alternative query", DEBUG_DEVELOPER);
                } catch (\Exception $alt_error) {
                    debugging("H5P Debug: Alternative query also failed: " . $alt_error->getMessage(), DEBUG_DEVELOPER);
                }
            }
            
            // Get all available libraries
            $available_types = [];
            
            // Try to get Dialog Cards
            $library = $core->loadLibrary('H5P.Dialogcards', 1, 9);
            if (!empty($library)) {
                $available_types[] = 'dialogcards';
                debugging("H5P Debug: Dialog Cards library is available", DEBUG_DEVELOPER);
            } else {
                debugging("H5P Debug: Dialog Cards library is NOT available", DEBUG_DEVELOPER);
            }
            
            return $available_types;
        } catch (\Exception $e) {
            debugging("H5P Debug: Exception in get_available_h5p_types: " . $e->getMessage(), DEBUG_DEVELOPER);
            return [];
        }
    }

    /**
     * Get H5P display HTML for inline embedding using content bank URL
     *
     * @param stored_file $h5pfile H5P package file
     * @return string HTML for H5P display
     */
    public static function get_h5p_display_html(stored_file $h5pfile): string {
        try {
            // For now, create a simple iframe that will be updated with the content bank URL
            $html = '<div class="framez-h5p-container">
                <div class="h5p-iframe-wrapper">
                    <iframe id="h5p-iframe-' . uniqid() . '" 
                            class="h5p-iframe" 
                            src="" 
                            width="100%" 
                            height="400" 
                            frameborder="0" 
                            allowfullscreen="allowfullscreen">
                    </iframe>
                </div>
            </div>';

            return $html;

        } catch (\Exception $e) {
            return '<div class="framez-h5p-error">Error loading H5P content: ' . $e->getMessage() . '</div>';
        }
    }

    /**
     * Sanitize filename for H5P package
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private static function sanitize_filename(string $filename): string {
        // Remove special characters and replace with underscores
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        // Ensure filename is not empty
        if (empty($filename)) {
            $filename = 'h5p_content';
        }
        
        return $filename;
    }

    /**
     * Clean up temporary directory
     *
     * @param string $tempdir Directory to clean up
     */
    private static function cleanup_temp_directory(string $tempdir): void {
        if (is_dir($tempdir)) {
            $files = glob($tempdir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                } elseif (is_dir($file)) {
                    self::cleanup_temp_directory($file);
                }
            }
            rmdir($tempdir);
        }
    }

}
