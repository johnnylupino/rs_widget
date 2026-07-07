<?php
/**
 * Grades Widget Block.
 * Fetches and presents recent gradebook tracking values cleanly without core charts.
 */

defined('MOODLE_INTERNAL') || die();

class block_grades_widget extends block_base {

    /**
     * Initialize block configuration properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_grades_widget');
    }

    /**
     * Compiles and outputs structural layout logic.
     */
    public function get_content() {
        global $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $userid = $USER->id;

        // Zapytanie SQL wyciągające 3 ostatnie oceny użytkownika z modułów/aktywności kursów
        $sql = "SELECT gg.id, gi.itemname, c.fullname AS coursename, gg.finalgrade, gi.grademax
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gg.itemid = gi.id
                JOIN {course} c ON gi.courseid = c.id
                WHERE gg.userid = :userid
                  AND gi.itemtype = 'mod'
                  AND gg.finalgrade IS NOT NULL
                ORDER BY gg.timemodified DESC";

        try {
            $grades = $DB->get_records_sql($sql, ['userid' => $userid], 0, 3);
        } catch (Exception $e) {
            $grades = [];
        }

        $listitemshtml = '';

        if (!empty($grades)) {
            foreach ($grades as $grade) {
                $itemname = format_string($grade->itemname);
                $coursename = format_string($grade->coursename);
                
                $finalgrade = (float)$grade->finalgrade;
                $grademax = (float)$grade->grademax > 0 ? (float)$grade->grademax : 100;
                
                // Obliczanie wartości procentowej, by dynamicznie nadać styl kolorystyczny z pliku CSS
                $percentage = ($finalgrade / $grademax) * 100;

                if ($percentage >= 85) {
                    $badgeclass = 'gw-badge-high';   // Zielony badge (np. 92, 88)
                } else if ($percentage >= 50) {
                    $badgeclass = 'gw-badge-medium'; // Pomarańczowy/żółty badge (np. 74)
                } else {
                    $badgeclass = 'gw-badge-low';    // Czerwony badge
                }

                $displaygrade = round($finalgrade);

                $listitemshtml .= "
                <div class='gw-item'>
                    <div class='gw-info'>
                        <span class='gw-itemname' title='{$itemname}'>{$itemname}</span>
                        <span class='gw-coursename' title='{$coursename}'>{$coursename}</span>
                    </div>
                    <div class='gw-badge {$badgeclass}'>{$displaygrade}</div>
                </div>";
            }
        } else {
            $listitemshtml = html_writer::div(get_string('nogrades', 'block_grades_widget'), 'text-muted text-center py-3');
        }

        // Adres URL kierujący bezpośrednio do raportu ocen (Gradebook) w profilu użytkownika
        $gradebookurl = new moodle_url('/grade/report/overview/index.php');
        $gradebooklink = html_writer::link($gradebookurl, get_string('gradebook', 'block_grades_widget'), ['class' => 'gw-link']);

        $recentgradesstr = get_string('recentgrades', 'block_grades_widget');

        // Render struktury HTML elementu
        $html = "
        <div class='gw-container'>
            <div class='gw-header'>
                <h3 class='gw-title'>{$recentgradesstr}</h3>
                {$gradebooklink}
            </div>
            <div class='gw-list'>
                {$listitemshtml}
            </div>
        </div>
        ";

        $this->content->text = $html;
        return $this->content;
    }

    /**
     * Defines global allocation formats permissions.
     */
    public function applicable_formats() {
        return array('all' => true);
    }
}