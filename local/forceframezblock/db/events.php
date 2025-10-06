<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\course_viewed',
        'callback'    => 'local_forceframezblock_observer::course_viewed',
        'includefile' => '/local/forceframezblock/lib.php',
        'internal'    => false,
        'priority'    => 1000,
    ],
];

