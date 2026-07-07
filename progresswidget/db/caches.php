<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * MUC (Moodle Universal Cache) definitions for block_progresswidget.
 *
 * The 'userprogress' store is an application-level cache (shared across
 * requests for the same user) with a 5-minute TTL.  It is invalidated
 * explicitly by the completion observer in classes/observer.php whenever
 * a course_completed or course_module_completion_updated event fires.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'userprogress' => [
        'mode'                  => cache_store::MODE_APPLICATION,
        'simplekeys'            => true,
        'simpledata'            => false,
        'ttl'                   => 300,   // 5 minutes in seconds.
        'invalidationevents'    => [],    // Manual invalidation via observer.
        'staticacceleration'    => true,
        'staticaccelerationsize' => 1,   // Only one entry needed per request.
    ],
];
