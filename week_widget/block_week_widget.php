<?php
/**
 * Week Widget Core Class.
 * Compiles a linear vertical timeline tracking user schedule items within the current week.
 * Links directly to course activities instead of calendar pages.
 */

defined('MOODLE_INTERNAL') || die();

class block_week_widget extends block_base {

    public function init() {
        $this->title = $this->get_local_string('pluginname', 'Week Widget');
    }

    /**
     * Zabezpieczenie przed brakiem pamięci cache tłumaczeń.
     */
    private function get_local_string($identifier, $defaulttext) {
        $string = get_string($identifier, 'block_week_widget');
        if (str_starts_with($string, '[[')) {
            return $defaulttext;
        }
        return $string;
    }

    public function get_content() {
        global $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $userid = $USER->id;

        // 1. OBLICZANIE RAM CZASOWYCH BIEŻĄCEGO TYGODNIA (Od teraz do niedzieli 23:59:59)
        $startofweek = time(); 
        $endofweek = strtotime('sunday this week 23:59:59');

        // Pobieramy ID wszystkich kursów, do których zapisany jest użytkownik
        $courseids = array_keys(enrol_get_users_courses($userid, true, 'id'));
        $courseids[] = SITEID; 

        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'crs');
        
        $params['userid'] = $userid;
        $params['startdate'] = $startofweek;
        $params['enddate'] = $endofweek;

        // 2. NOWE ZAPYTANIE SQL: Pobieramy cm.id (Course Module ID) potrzebne do bezpośredniego linku
        $sql = "SELECT e.id, e.name, e.timestart, e.timeduration, e.eventtype, e.location, e.modulename, cm.id AS cmid
                FROM {event} e
                LEFT JOIN {modules} md ON md.name = e.modulename
                LEFT JOIN {course_modules} cm ON cm.module = md.id AND cm.instance = e.instance
                WHERE (e.userid = :userid OR e.courseid {$insql} OR (e.courseid = 0 AND e.groupid = 0 AND e.userid = 0))
                  AND e.timestart >= :startdate 
                  AND e.timestart <= :enddate
                ORDER BY e.timestart ASC";

        try {
            $events = $DB->get_records_sql($sql, $params);
        } catch (Exception $e) {
            $events = [];
        }

        $timelinehtml = '';
        $colorcounter = 0; 

        if (!empty($events)) {
            foreach ($events as $event) {
                $name = format_string($event->name);
                $timeformat = get_string('strftimetime', 'langconfig');
                
                $dayname = userdate($event->timestart, '%a'); 
                $starttime = userdate($event->timestart, $timeformat);
                
                if ($event->timeduration > 0) {
                    $endtime = userdate($event->timestart + $event->timeduration, $timeformat);
                    $timespan = "{$starttime} – {$endtime}";
                } else {
                    $timespan = $starttime;
                }

                if (!empty($event->location)) {
                    $location = s($event->location);
                } else if ($event->modulename === 'assign') {
                    $location = $this->get_local_string('onlinesubmission', 'Online submission');
                } else {
                    $location = 'Room online';
                }

                // 3. GENEROWANIE LINKU: Bezpośrednio do aktywności lub fallback do kalendarza
                if (!empty($event->modulename) && !empty($event->cmid)) {
                    // LINK BEZPOŚREDNI DO AKTYWNOŚCI (np. /mod/assign/view.php?id=XX lub /mod/quiz/view.php?id=XX)
                    $eventurl = new moodle_url('/mod/' . $event->modulename . '/view.php', ['id' => $event->cmid]);
                } else {
                    // Ręczny wpis / Wydarzenie użytkownika -> Link do szczegółów kalendarza
                    $eventurl = new moodle_url('/calendar/view.php', ['view' => 'event', 'id' => $event->id]);
                }

                $namelink = html_writer::link($eventurl, $name, ['class' => 'ww-event-link']);


                // Rotacja kolorów kropek (od ww-dot-0 do ww-dot-2 -> Zielony, Pomarańczowy, Czerwony)
                $dotcolorclass = 'ww-dot-' . ($colorcounter % 3);
                $colorcounter++;

                $timelinehtml .= "
                <div class='ww-item'>
                    <div class='ww-dot {$dotcolorclass}'></div>
                    <div class='ww-event-link-container'>{$namelink}</div>
                    <div class='ww-metadata'>{$dayname} · {$timespan} · {$location}</div>
                </div>";
            }
        } else {
            $timelinehtml = html_writer::div($this->get_local_string('noevents', 'No events scheduled for this week.'), 'text-muted text-center py-2');
        }

        $calendarurl = new moodle_url('/calendar/view.php');
        $calendarlink = html_writer::link($calendarurl, $this->get_local_string('calendar', 'Calendar'), ['class' => 'ww-link']);
        $thisweekstr = $this->get_local_string('thisweek', 'This week');

        $html = "
        <div class='ww-container'>
            <div class='ww-header'>
                <h3 class='ww-title'>{$thisweekstr}</h3>
                {$calendarlink}
            </div>
            <div class='ww-timeline'>
                {$timelinehtml}
            </div>
        </div>
        ";

        $this->content->text = $html;
        return $this->content;
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}