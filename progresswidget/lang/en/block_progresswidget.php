<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * English language strings for block_progresswidget.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname']     = 'Progress widget';

// Chart legend / state labels.
$string['state_completed']  = 'Completed';
$string['state_inprogress'] = 'In progress';
$string['state_notstarted'] = 'Not started';

// Accessible labels.
$string['legend']       = 'Completion legend';
$string['chartlabel']   = 'Course completion chart';
$string['coursedetail'] = 'Course breakdown';

// Summary / counts.
$string['courses']  = 'courses';
$string['summary']  = '{$a->completed} of {$a->total} courses completed';

// Empty / disabled states.
$string['nocourses']  = 'You are not enrolled in any courses yet.';
$string['notracking'] = 'No completion tracking';

// Privacy (GDPR).
$string['privacy:metadata:cache'] = 'The Progress Widget block caches aggregated course-completion counts per user. No personal data beyond the user ID is stored in the cache.';
