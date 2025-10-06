<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // This setting defines the default external tool URL used by the block.
    $settings->add(new admin_setting_configtext(
        'block_framez_toolurl',
        get_string('toolurl', 'block_framez'),
        get_string('toolurl_desc', 'block_framez'),
        '',
        PARAM_URL
    ));
}

