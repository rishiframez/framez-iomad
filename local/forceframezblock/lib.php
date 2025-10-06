<?php

defined('MOODLE_INTERNAL') || die();

class local_forceframezblock_observer {
    public static function course_viewed(\core\event\course_viewed $event) {
        global $DB;

        $courseid = $event->courseid;

        // Skip front page.
        if ($courseid == SITEID) {
            return true;
        }

        $context = context_course::instance($courseid);

        // Check if block already added.
        $exists = $DB->record_exists('block_instances', [
            'blockname' => 'framez',
            'parentcontextid' => $context->id
        ]);

        if ($exists) {
            return true;
        }

        // Default values for a block.
        $blockinstance = new stdClass();
        $blockinstance->blockname = 'framez';
        $blockinstance->parentcontextid = $context->id;
        $blockinstance->showinsubcontexts = 0;
        $blockinstance->pagetypepattern = 'course-view-*';
        $blockinstance->subpagepattern = null;
        $blockinstance->defaultregion = 'side-pre';
        $blockinstance->defaultweight = 0;
        $blockinstance->configdata = base64_encode(serialize([]));
        $blockinstance->timecreated = time();
        $blockinstance->timemodified = time();

        // Insert the block instance.
        $blockinstance->id = $DB->insert_record('block_instances', $blockinstance);

        // Ensure block is visible.
        $DB->insert_record('block_positions', [
            'blockinstanceid' => $blockinstance->id,
            'contextid' => $context->id,
            'pagetype' => 'course-view-*',
            'subpage' => '', // ✅ Fix: empty string instead of null
            'visible' => 1,
            'region' => 'side-pre',
            'weight' => 0
        ]);

        return true;
    }
}

