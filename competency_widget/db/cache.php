<?php
/**
 * Cache definitions for the Competency Widget block.
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    'competency_metrics' => [
        'mode' => cache_store::MODE_REQUEST, // Keeps data alive for the current page request lifecycle
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true
    ]
];