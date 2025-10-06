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
 * Settings for Framez Webservice plugin
 *
 * @package    local_framez_webservice
 * @copyright  2024 Framez
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_framez_webservice',
        get_string('pluginname', 'local_framez_webservice'),
        new moodle_url('/local/framez_webservice/settings.php'),
        'local/framez_webservice:create_course_page'
    ));

    $settings = new admin_settingpage('local_framez_webservice_settings',
        get_string('settings', 'local_framez_webservice'));

    if ($ADMIN->fulltree) {
        // Add settings here in the future if needed
        $settings->add(new admin_setting_heading('local_framez_webservice_general',
            get_string('settings', 'local_framez_webservice'),
            get_string('settings_desc', 'local_framez_webservice')));
    }

    $ADMIN->add('localplugins', $settings);
}




