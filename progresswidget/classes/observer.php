<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observer for block_progresswidget.
 *
 * Purges the per-user MUC cache entry whenever a relevant completion
 * or enrolment event fires for that user, so the next page load
 * recalculates fresh progress data.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_progresswidget;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Invalidate cache when a full course completion is recorded.
     *
     * @param \core\event\course_completed $event
     */
    public static function course_completed(\core\event\course_completed $event): void {
        self::purge_user_cache($event->relateduserid);
    }

    /**
     * Invalidate cache when any activity completion state changes.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function completion_updated(
        \core\event\course_module_completion_updated $event
    ): void {
        self::purge_user_cache($event->userid);
    }

    /**
     * Invalidate cache when the user's enrolments change.
     *
     * @param \core\event\base $event  (user_enrolment_created or _deleted)
     */
    public static function enrolment_changed(\core\event\base $event): void {
        self::purge_user_cache($event->relateduserid);
    }

    /**
     * Delete a single user's progress cache entry.
     *
     * @param int $userid
     */
    private static function purge_user_cache(int $userid): void {
        if (!$userid) {
            return;
        }
        $cache = \cache::make('block_progresswidget', 'userprogress');
        $cache->delete('progress_' . $userid);
    }
}
