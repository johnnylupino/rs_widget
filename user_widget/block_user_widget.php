<?php
/**
 * User Widget Core Block Logic.
 * Computes performance thresholds and renders a modern profile card interface.
 */

defined('MOODLE_INTERNAL') || die();

class block_user_widget extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_user_widget');
    }

    public function instance_allow_config() {
        return true;
    }

    public function get_content() {
        global $USER, $DB, $OUTPUT, $PAGE, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $userid = $USER->id;
        $courseid = $PAGE->course->id;

        // 1. Obliczanie statusu użytkownika: "On track" vs "Needs Attention"
        $statusisontrack = $this->calculate_user_status($userid, $courseid);
        
        $statusclass = $statusisontrack ? 'uw-status-ontrack' : 'uw-status-attention';
        $statustext = $statusisontrack ? get_string('ontrack', 'block_user_widget') : get_string('needsattention', 'block_user_widget');

        // 2. Pobieranie danych profilu (oraz pól niestandardowych)
        require_once($CFG->dirroot . '/user/profile/lib.php');
        profile_load_custom_fields($USER);

        $profilelines = [];
        for ($i = 1; $i <= 5; $i++) {
            $fieldname = 'field' . $i;
            if (!empty($this->config->$fieldname)) {
                $key = $this->config->$fieldname;
                
                if (str_starts_with($key, 'custom_field_')) {
                    $shortname = str_replace('custom_field_', '', $key);
                    if (isset($USER->profile[$shortname])) {
                        $profilelines[] = s($USER->profile[$shortname]);
                    }
                } else if (!empty($USER->$key)) {
                    if ($key === 'country') {
                        $profilelines[] = get_string_manager()->get_string($USER->country, 'countries');
                    } else {
                        $profilelines[] = s($USER->$key);
                    }
                }
            }
        }

        // Renderowanie linii podrzędnych (np. połączonych separatorem kropki jak na zrzucie ekranu)
        $subtextlineshtml = '';
        if (!empty($profilelines)) {
            // Grupowanie pierwszych trzech parametrów w linię z kropką, reszta poniżej
            $line1 = array_slice($profilelines, 0, 3);
            $subtextlineshtml .= "<div class='uw-profile-sub'>" . implode(' · ', $line1) . "</div>";
            if (count($profilelines) > 3) {
                $line2 = array_slice($profilelines, 3);
                $subtextlineshtml .= "<div class='uw-profile-sub'>" . implode(' · ', $line2) . "</div>";
            }
        }

        // 3. Pobieranie zdjęcia profilowego (Duży Avatar)
        $userpicture = $OUTPUT->user_picture($USER, ['size' => 100, 'link' => false, 'class' => 'uw-avatar-img']);

        // 4. Pobieranie prowadzącego / opiekuna (Academic Tutor)
        $tutorhtml = $this->get_tutor_card($courseid);

        // Odnośnik kalendarza i opisu "About Me"
        $calendarurl = new moodle_url('/calendar/view.php', ['view' => 'month']);
        $abouttitle = get_string('aboutuser', 'block_user_widget', format_string($USER->firstname));
        $usercontext = \context_user::instance($USER->id);

                // Pobieramy pełny opis i jego format bezpośrednio z tabeli użytkownika w bazie danych
        $userfields = $DB->get_record('user', ['id' => $userid], 'description, descriptionformat');

        if ($userfields && !empty($userfields->description)) {
            $userdescription = format_text($userfields->description, $userfields->descriptionformat);
        } else {
            $userdescription = get_string('nodescription', 'block_user_widget');
}

        // Renderowanie ostatecznego szablonu HTML
        $html = "
        <div class='uw-card-container'>
            <div class='uw-avatar-wrapper'>
                {$userpicture}
            </div>
            
            <h2 class='uw-user-name'>" . fullname($USER) . "</h2>
            {$subtextlineshtml}
            
            <div class='uw-status-badge-container'>
                <span class='uw-status-badge {$statusclass}'>{$statustext}</span>
            </div>
            
            <hr class='uw-divider' />
            
            <div class='uw-about-section'>
                <h4 class='uw-about-title'>{$abouttitle}</h4>
                <div class='uw-about-text'>{$userdescription}</div>
            </div>
            
            <a href='{$calendarurl}' class='uw-calendar-btn'>
                <span class='uw-icon'>📅</span> " . get_string('opencalendar', 'block_user_widget') . "
            </a>
            
            <div class='uw-email-line'>" . s($USER->email) . "</div>
            
            {$tutorhtml}
        </div>
        ";

        $this->content->text = $html;
        return $this->content;
    }

    /**
     * Oblicza status ukończenia zadań użytkownika.
     */
    private function calculate_user_status($userid, $courseid) {
        global $DB;
        
        // Zabezpieczenie: Jeśli jesteśmy poza kursem (np. na Kokpicie), pobierz pierwszy przypisany kurs
        if ($courseid == SITEID) {
            $enrolledcourses = enrol_get_users_courses($userid, true, 'id');
            if (empty($enrolledcourses)) {
                return true;
            }
            $courseid = array_key_first($enrolledcourses);
        }

        $now = time();

        // Sprawdzenie 1: Czy są jakieś zaległe zadania (overdue assignments)
        $sql = "SELECT a.id 
                FROM {assign} a
                JOIN {course_modules} cm ON cm.instance = a.id
                JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                WHERE cm.course = :courseid 
                  AND a.duedate > 0 
                  AND a.duedate < :now
                  AND NOT EXISTS (
                      SELECT asb.id FROM {assign_submission} asb 
                      WHERE asb.assignment = a.id 
                        AND asb.userid = :userid 
                        AND asb.status = 'submitted'
                  )";
        
        $overdue = $DB->get_records_sql($sql, ['courseid' => $courseid, 'now' => $now, 'userid' => $userid]);
        if (!empty($overdue)) {
            return false; // Flaga krytyczna: są zaległe zadania
        }

        // Sprawdzenie 2: Sprawdzenie progu ukończenia aktywności (wymagane min. 70%)
        $course = $DB->get_record('course', ['id' => $courseid]);
        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled()) {
            $activities = $completioninfo->get_activities();
            $totaltracked = 0;
            $completedcount = 0;

            foreach ($activities as $activity) {
                if ($activity->completion != COMPLETION_TRACKING_NONE) {
                    $totaltracked++;
                    $data = $completioninfo->get_data($activity, false, $userid);
                    if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                        $completedcount++;
                    }
                }
            }

            if ($totaltracked > 0) {
                $rate = ($completedcount / $totaltracked) * 100;
                if ($rate < 70) {
                    return false; // Wynik poniżej progu 70%
                }
            }
        }

        return true;
    }

    /**
     * Pobiera dane nauczyciela lub opiekuna kursu (Academic Tutor) do stopki.
     */
    private function get_tutor_card($courseid) {
        global $DB, $OUTPUT;

        if ($courseid == SITEID) {
            return '';
        }

        // Szukamy użytkownika z rolą 'editingteacher' (Nauczyciel) w kontekście kursu
        $context = context_course::instance($courseid);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        
        if ($teacherrole) {
            $teachers = get_role_users($teacherrole->id, $context, false, 'u.id, u.firstname, u.lastname, u.email, u.picture, u.imagealt', 'u.id ASC', null, '', 1);
            if (!empty($teachers)) {
                $tutor = reset($teachers);
                $tutorpic = $OUTPUT->user_picture($tutor, ['size' => 36, 'link' => false, 'class' => 'uw-tutor-img']);
                $tutorname = fullname($tutor);
                $tutorrolelabel = get_string('academictutor', 'block_user_widget');

                return "
                <div class='uw-tutor-card'>
                    <div class='uw-tutor-avatar'>{$tutorpic}</div>
                    <div class='uw-tutor-info'>
                        <div class='uw-tutor-name'>{$tutorname}</div>
                        <div class='uw-tutor-role'>{$tutorrolelabel}</div>
                    </div>
                </div>";
            }
        }
        return '';
    }
}