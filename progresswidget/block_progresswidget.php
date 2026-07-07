<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Progress Widget block for Moodle dashboard.
 *
 * @package    block_progresswidget
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

class block_progresswidget extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_progresswidget');
    }

    public function applicable_formats() {
        return [
            'my'               => true,
            'site'       => true,
            'course-view'      => true,
            'local-enocustompages-view'=> true
        ];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return false;
    }

    public function get_content() {
        global $USER, $OUTPUT, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';
        $this->content->text   = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // ── Load CSS via Moodle URL handler ────────────────────────────────
        $this->page->requires->css(new moodle_url('/blocks/progresswidget/styles.css'));

        // ── MUC cache ─────────────────────────────────────────────────────
        $cache    = cache::make('block_progresswidget', 'userprogress');
        $cachekey = 'progress_' . $USER->id;
        $data     = $cache->get($cachekey);

        if ($data === false) {
            $data = $this->calculate_progress($USER->id);
            $cache->set($cachekey, $data);
        }

        // ── Render template ────────────────────────────────────────────────
        $this->content->text = $OUTPUT->render_from_template(
            'block_progresswidget/main',
            $data
        );

        return $this->content;
    }

    /**
     * Calculate progress for $userid using the Moodle completion API.
     * Returns an array ready to pass directly to the Mustache template.
     */
    private function calculate_progress(int $userid): array {
        $courses    = enrol_get_my_courses('id, fullname, shortname, enablecompletion');
        $total      = 0;
        $completed  = 0;
        $inprogress = 0;
        $notstarted = 0;
        $courserows = [];

        foreach ($courses as $course) {
            if ($course->id == SITEID) {
                continue;
            }

            $total++;
            $info = new completion_info($course);

            // ── Completion tracking disabled on this course ────────────────
            if (!$info->is_enabled()) {
                $notstarted++;
                $courserows[] = [
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'notstarted',
                    'state_label'   => get_string('state_notstarted', 'block_progresswidget'),
                    'is_completed'  => false,
                    'is_inprogress' => false,
                    'is_notstarted' => true,
                    'pct'           => 0,
                    'bar_color'     => '#B4B2A9',
                    'no_tracking'   => true,
                ];
                continue;
            }

            // ── Whole-course complete ──────────────────────────────────────
            if ($info->is_course_complete($userid)) {
                $completed++;
                $courserows[] = [
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'completed',
                    'state_label'   => get_string('state_completed', 'block_progresswidget'),
                    'is_completed'  => true,
                    'is_inprogress' => false,
                    'is_notstarted' => false,
                    'pct'           => 100,
                    'bar_color'     => '#1D9E75',
                    'no_tracking'   => false,
                ];
                continue;
            }

            // ── Count activity completions ─────────────────────────────────
            $activities = $info->get_activities();
            $act_total  = count($activities);
            $done       = 0;

            foreach ($activities as $cm) {
                $cmdata = $info->get_data($cm, false, $userid);
                if ($cmdata->completionstate >= COMPLETION_COMPLETE) {
                    $done++;
                }
            }

            if ($act_total > 0 && $done > 0) {
                $inprogress++;
                $pct   = (int) round(($done / $act_total) * 100);
                $state = 'inprogress';
                $courserows[] = [
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => $state,
                    'state_label'   => get_string('state_inprogress', 'block_progresswidget'),
                    'is_completed'  => false,
                    'is_inprogress' => true,
                    'is_notstarted' => false,
                    'pct'           => $pct,
                    'bar_color'     => '#EF9F27',
                    'no_tracking'   => false,
                ];
            } else {
                $notstarted++;
                $courserows[] = [
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'notstarted',
                    'state_label'   => get_string('state_notstarted', 'block_progresswidget'),
                    'is_completed'  => false,
                    'is_inprogress' => false,
                    'is_notstarted' => true,
                    'pct'           => 0,
                    'bar_color'     => '#B4B2A9',
                    'no_tracking'   => false,
                ];
            }
        }

        // Sort: in-progress first, then not-started, completed last
        usort($courserows, function ($a, $b) {
            $order = ['inprogress' => 0, 'notstarted' => 1, 'completed' => 2];
            return ($order[$a['state']] ?? 9) <=> ($order[$b['state']] ?? 9);
        });

        $safe = $total > 0 ? $total : 1;

        // ── Calculate overall average progress across all courses ─────────
        $total_progress = 0;
        foreach ($courserows as $course) {
            $total_progress += $course['pct'];
        }
        $overall_progress = $total > 0 ? (int) round($total_progress / $total) : 0;

        return [
            'total'              => $total,
            'completed'          => $completed,
            'inprogress'         => $inprogress,
            'notstarted'         => $notstarted,
            'pct_completed'      => (int) round(($completed  / $safe) * 100),
            'pct_inprogress'     => (int) round(($inprogress / $safe) * 100),
            'pct_notstarted'     => (int) round(($notstarted / $safe) * 100),
            'overall_progress'   => $overall_progress,
            'has_courses'        => $total > 0,
            'courses'            => $courserows,
        ];
    }
}
