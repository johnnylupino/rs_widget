<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy API provider for block_progresswidget.
 *
 * This plugin only caches aggregated completion counts derived from
 * data that is already managed by the core completion subsystem.
 * It does not store any personal data of its own — the cache is
 * ephemeral (5-minute TTL, keyed by userid) and contains no
 * information beyond what the user can already see on their dashboard.
 *
 * Therefore this provider implements \core_privacy\local\metadata\null_provider.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_progresswidget\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * Returns the reason why this plugin stores no personal data.
     *
     * @return string Language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata:cache';
    }
}
