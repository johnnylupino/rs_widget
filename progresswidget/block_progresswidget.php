<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Progress Widget block for Moodle dashboard.
 * Unified Light Theme Framework for Moodle 4.5+ (PHP 8.1+)
 *
 * @package    block_progresswidget
 * @copyright  2026 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');

class block_progresswidget extends block_base {

    public function init() {
        $this->title = $this->get_local_string('pluginname', 'Your Progress');
    }

    /**
     * Zabezpieczenie przed zablokowanym cache paczek językowych Moodle.
     */
    private function get_local_string($identifier, $defaulttext) {
        $string = get_string($identifier, 'block_progresswidget');
        if (str_starts_with($string, '[[')) {
            return $defaulttext;
        }
        return $string;
    }

    public function applicable_formats() {
        return [
            'my'          => true,
            'site'        => true,
            'course-view' => true
        ];
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return false;
    }

    public function get_content() {
        global $USER, $PAGE, $CFG;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content         = new stdClass();
        $this->content->footer = '';
        $this->content->text   = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        // Rejestracja zewnętrznego pliku stylów CSS
        $PAGE->requires->css(new moodle_url('/blocks/progresswidget/styles.css'));

        // Pobieranie i buforowanie danych progresu użytkownika via MUC
        $cache    = cache::make('block_progresswidget', 'userprogress');
        $cachekey = 'progress_' . $USER->id;
        $data     = $cache->get($cachekey);

        if ($data === false) {
            $data = $this->calculate_progress($USER->id);
            $cache->set($cachekey, $data);
        }

        // Generowanie wierszy kursów z bezpośrednimi linkami
        $courseshtml = '';
        if (!empty($data['courses'])) {
            foreach ($data['courses'] as $course) {
                $courseurl = new moodle_url('/course/view.php', ['id' => $course['id']]);
                
                $courseshtml .= "
                <a href='{$courseurl}' class='pw-course-row' data-state='{$course['state']}'>
                    <div class='pw-course-icon pw-icon-{$course['state']}'>
                        " . strtoupper(substr($course['shortname'], 0, 2)) . "
                    </div>
                    <div class='pw-course-info'>
                        <div class='pw-course-name'>{$course['fullname']}</div>
                        <div class='pw-course-track'>
                            <div class='pw-course-fill pw-bar-{$course['state']}' style='width: {$course['pct']}%'></div>
                        </div>
                    </div>
                    <span class='pw-course-badge pw-badge-{$course['state']}'>{$course['pct']}%</span>
                </a>";
            }
        }

        // Obliczenia matematyczne dla głównego błękitnego okręgu SVG
        $radius = 40;
        $circumference = 2 * M_PI * $radius;
        $strokeoffset = $circumference - ($data['overall_progress'] / 100) * $circumference;

        // Budowanie interfejsu komponentu
        $html = "
        <div class='block-progresswidget-container'>
            <div class='pw-header'>
                <span class='pw-title'>" . $this->get_local_string('thisweek', 'OVERALL SEMESTER STATUS') . "</span>
                <span class='pw-pill'>" . $data['overall_progress'] . "% " . $this->get_local_string('complete', 'Done') . "</span>
            </div>

            <div class='pw-chart-target'>
                <div class='pw-chart-wrap'>
                    <svg width='110' height='110' viewBox='0 0 100 100' style='transform: rotate(-90deg);'>
                        <circle class='pw-svg-bg' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'/>
                        <circle class='pw-svg-progress' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'
                                stroke-dasharray='{$circumference}' 
                                stroke-dashoffset='{$strokeoffset}'/>
                    </svg>
                    <div class='pw-centre'>
                        <span class='pw-pct'>{$data['overall_progress']}%</span>
                        <span class='pw-sub'>" . $this->get_local_string('complete', 'Progress') . "</span>
                    </div>
                </div>
            </div>

            <div class='pw-legend'>
                <div class='pw-legend-row' data-filter='inprogress' title='Kliknij, aby pofiltrować listę'>
                    <div class='pw-legend-left'>
                        <span class='pw-dot pw-dot-inprogress'></span>
                        <span class='pw-legend-label'>" . $this->get_local_string('state_inprogress', 'In progress') . "</span>
                    </div>
                    <span class='pw-legend-count'>{$data['inprogress']} <span class='pw-of'>of {$data['total']}</span></span>
                </div>
                
                <div class='pw-legend-row' data-filter='notstarted' title='Kliknij, aby pofiltrować listę'>
                    <div class='pw-legend-left'>
                        <span class='pw-dot pw-dot-notstarted'></span>
                        <span class='pw-legend-label'>" . $this->get_local_string('state_notstarted', 'Not started') . "</span>
                    </div>
                    <span class='pw-legend-count'>{$data['notstarted']} <span class='pw-of'>of {$data['total']}</span></span>
                </div>

                <div class='pw-legend-row' data-filter='completed' title='Kliknij, aby pofiltrować listę'>
                    <div class='pw-legend-left'>
                        <span class='pw-dot pw-dot-completed'></span>
                        <span class='pw-legend-label'>" . $this->get_local_string('state_completed', 'Completed') . "</span>
                    </div>
                    <span class='pw-legend-count'>{$data['completed']} <span class='pw-of'>of {$data['total']}</span></span>
                </div>
            </div>

            <div class='pw-courses-section'>
                <button id='pw-toggle-btn' class='pw-toggle-btn'>
                    👁️ " . $this->get_local_string('showcourses', 'Pokaż listę kursów') . " (" . $data['total'] . ")
                </button>
                
                <div id='pw-courses-wrapper' class='pw-courses-wrapper pw-hidden'>
                    <div class='pw-courses'>
                        {$courseshtml}
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggleBtn = document.getElementById('pw-toggle-btn');
            const wrapper = document.getElementById('pw-courses-wrapper');
            const legendRows = document.querySelectorAll('.pw-legend-row');
            const courseRows = document.querySelectorAll('.pw-course-row');

            // 1. Akcja przycisku Pokaż/Ukryj listę kursów
            toggleBtn.addEventListener('click', function() {
                if (wrapper.classList.contains('pw-hidden')) {
                    wrapper.classList.remove('pw-hidden');
                    toggleBtn.innerHTML = '🙈 Ukryj listę kursów';
                } else {
                    wrapper.classList.add('pw-hidden');
                    toggleBtn.innerHTML = '👁️ Pokaż listę kursów (' + courseRows.length + ')';
                    // Reset ewentualnych filtrów przy zamykaniu
                    courseRows.forEach(row => row.style.display = 'flex');
                    legendRows.forEach(r => r.classList.remove('pw-active-filter'));
                }
            });

            // 2. Akcja interaktywnego klikania na cyfry w legendzie (Filtrowanie)
            legendRows.forEach(row => {
                row.addEventListener('click', function() {
                    const targetState = this.getAttribute('data-filter');
                    
                    // Upewniamy się, że lista kursów jest rozwinięta
                    wrapper.classList.remove('pw-hidden');
                    toggleBtn.innerHTML = '🙈 Ukryj listę kursów';

                    if (this.classList.contains('pw-active-filter')) {
                        // Jeśli ten filtr był już aktywny - wyłączamy go (pokazujemy wszystkie)
                        this.classList.remove('pw-active-filter');
                        courseRows.forEach(cRow => cRow.style.display = 'flex');
                    } else {
                        // Włączamy nowy filtr
                        legendRows.forEach(r => r.classList.remove('pw-active-filter'));
                        this.classList.add('pw-active-filter');
                        
                        courseRows.forEach(cRow => {
                            if (cRow.getAttribute('data-state') === targetState) {
                                cRow.style.display = 'flex';
                            } else {
                                cRow.style.display = 'none';
                            }
                        });
                    }
                });
            });
        });
        </script>
        ";

        $this->content->text = $html;
        return $this->content;
    }

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

            if (!$info->is_enabled()) {
                $notstarted++;
                $courserows[] = [
                    'id'            => $course->id,
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'notstarted',
                    'pct'           => 0,
                ];
                continue;
            }

            if ($info->is_course_complete($userid)) {
                $completed++;
                $courserows[] = [
                    'id'            => $course->id,
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'completed',
                    'pct'           => 100,
                ];
                continue;
            }

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
                $courserows[] = [
                    'id'            => $course->id,
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'inprogress',
                    'pct'           => $pct,
                ];
            } else {
                $notstarted++;
                $courserows[] = [
                    'id'            => $course->id,
                    'fullname'      => $course->fullname,
                    'shortname'     => $course->shortname,
                    'state'         => 'notstarted',
                    'pct'           => 0,
                ];
            }
        }

        usort($courserows, function ($a, $b) {
            $order = ['inprogress' => 0, 'notstarted' => 1, 'completed' => 2];
            return ($order[$a['state']] ?? 9) <=> ($order[$b['state']] ?? 9);
        });

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
            'overall_progress'   => $overall_progress,
            'courses'            => $courserows,
        ];
    }
}