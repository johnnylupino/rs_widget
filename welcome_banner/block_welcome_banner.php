<?php
/**
 * Welcome Banner Block Core Logic.
 * Fetches real-time activity tracking analytics with smart language fallback engines.
 */

defined('MOODLE_INTERNAL') || die();

class block_welcome_banner extends block_base {

    public function init() {
        $this->title = $this->get_local_string('pluginname', 'Welcome Banner');
    }

    /**
     * Inteligentny pomocnik językowy. Jeśli Moodle zablokuje pamięć cache i zwróci [[klucz]],
     * funkcja automatycznie wstawi poprawny tekst tekstowy jako fallback.
     */
    private function get_local_string($identifier, $defaulttext, $a = null) {
        $string = get_string($identifier, 'block_welcome_banner', $a);
        if (str_starts_with($string, '[[')) {
            if ($identifier === 'welcomeback') {
                return "Welcome back, " . ($a ?? 'User') . " 👋";
            }
            if ($identifier === 'progressdesc' && is_object($a)) {
                return "You've completed {$a->completed} of {$a->total} activities this week. {$a->upcoming} assignments are due within the next few days — keep the streak going.";
            }
            if ($identifier === 'progressdescnoupcoming' && is_object($a)) {
                return "You've completed {$a->completed} of {$a->total} activities. No assignments are due this week — brilliant job!";
            }
            return $defaulttext;
        }
        return $string;
    }

    public function get_content() {
        global $USER, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $userid = $USER->id;
        $courseid = $PAGE->course->id;

        // Określamy kontekst kursu (jeśli Kokpit, bierzemy pierwszy aktywny kurs użytkownika)
        if ($courseid == SITEID) {
            $enrolledcourses = enrol_get_users_courses($userid, true, 'id');
            if (empty($enrolledcourses)) {
                $this->content->text = $this->render_empty_banner(fullname($USER));
                return $this->content;
            }
            $courseid = array_key_first($enrolledcourses);
        }

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return $this->content;
        }

        // 1. ANALIZA UKOŃCZENIA AKTYWNOŚCI VIA COMPLETION API
        $totalactivities = 0;
        $completedactivities = 0;

        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled()) {
            $activities = $completioninfo->get_activities();
            foreach ($activities as $activity) {
                if ($activity->completion != COMPLETION_TRACKING_NONE) {
                    $totalactivities++;
                    $data = $completioninfo->get_data($activity, false, $userid);
                    if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                        $completedactivities++;
                    }
                }
            }
        }

        $percentage = $totalactivities > 0 ? round(($completedactivities / $totalactivities) * 100) : 0;

        // 2. ANALIZA NADCHODZĄCYCH ZADAŃ W NAJBLIŻSZYCH DNIACH (KOLEJNE 4 DNI)
        $now = time();
        $upcominglimit = $now + (4 * 86400);

        $sql = "SELECT a.id 
                FROM {assign} a
                JOIN {course_modules} cm ON cm.instance = a.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                WHERE cm.course = :courseid 
                  AND a.duedate > :now 
                  AND a.duedate <= :upcominglimit
                  AND NOT EXISTS (
                      SELECT asb.id FROM {assign_submission} asb 
                      WHERE asb.assignment = a.id 
                        AND asb.userid = :userid 
                        AND asb.status = 'submitted'
                  )";

        try {
            $upcomingassignments = $DB->get_records_sql($sql, [
                'courseid' => $courseid,
                'now' => $now,
                'upcominglimit' => $upcominglimit,
                'userid' => $userid
            ]);
            $upcomingcount = count($upcomingassignments);
        } catch (Exception $e) {
            $upcomingcount = 0;
        }

        // 3. BUDOWANIE DYNAMICZNEGO ZDANIA PODSUMOWUJĄCEGO
        $stringdata = new stdClass();
        $stringdata->completed = $completedactivities;
        $stringdata->total = $totalactivities;
        $stringdata->upcoming = $upcomingcount;

        if ($upcomingcount > 0) {
            $descriptiontext = $this->get_local_string('progressdesc', 'Progress stats updated.', $stringdata);
        } else {
            $descriptiontext = $this->get_local_string('progressdescnoupcoming', 'Progress stats updated.', $stringdata);
        }

        // 4. MATEMATYKA DLA PIERŚCIENIA SVG
        $radius = 40;
        $circumference = 2 * M_PI * $radius;
        $strokeoffset = $circumference - ($percentage / 100) * $circumference;

        $semesterprogressstr = $this->get_local_string('semesterprogress', 'SEMESTER PROGRESS');
        $welcomebackstr = $this->get_local_string('welcomeback', 'Welcome back!', format_string($USER->firstname));
        $completestr = $this->get_local_string('complete', 'complete');

        // Struktura komponentu HTML
        $html = "
        <div class='wb-banner'>
            <div class='wb-content-left'>
                <div class='wb-tag'>{$semesterprogressstr}</div>
                <h1 class='wb-title'>{$welcomebackstr}</h1>
                <p class='wb-description'>{$descriptiontext}</p>
            </div>
            
            <div class='wb-chart-right'>
                <div class='wb-radial-wrapper'>
                    <svg width='120' height='120' viewBox='0 0 100 100' style='transform: rotate(-90deg);'>
                        <circle class='wb-svg-bg' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'/>
                        <circle class='wb-svg-progress' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'
                                stroke-dasharray='{$circumference}' 
                                stroke-dashoffset='{$strokeoffset}'/>
                    </svg>
                    <div class='wb-radial-text-box'>
                        <span class='wb-percentage'>{$percentage}%</span>
                        <span class='wb-label'>{$completestr}</span>
                    </div>
                </div>
            </div>
        </div>
        ";

        $this->content->text = $html;
        return $this->content;
    }

    private function render_empty_banner($username) {
        return "
        <div class='wb-banner'>
            <div class='wb-content-left'>
                <div class='wb-tag'>SEMESTER PROGRESS</div>
                <h1 class='wb-title'>Welcome back, {$username} 👋</h1>
                <p class='wb-description'>You are not currently enrolled in any active courses.</p>
            </div>
        </div>";
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}