<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Event observer registrations for block_progresswidget.
 *
 * Listens for completion-related events so the MUC cache can be
 * invalidated the moment a user's progress changes — ensuring the
 * dashboard always reflects real-time state after the next page load.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Fires when an entire course is marked complete for a user.
    [
        'eventname' => '\core\event\course_completed',
        'callback'  => '\block_progresswidget\observer::course_completed',
    ],
    // Fires when a single activity/resource completion state changes.
    [
        'eventname' => '\core\event\course_module_completion_updated',
        'callback'  => '\block_progresswidget\observer::completion_updated',
    ],
    // Fires when a user enrols — their course list has changed.
    [
        'eventname' => '\core\event\user_enrolment_created',
        'callback'  => '\block_progresswidget\observer::enrolment_changed',
    ],
    // Fires when an enrolment is deleted or suspended.
    [
        'eventname' => '\core\event\user_enrolment_deleted',
        'callback'  => '\block_progresswidget\observer::enrolment_changed',
    ],
];
