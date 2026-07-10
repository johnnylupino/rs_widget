<?php
/**
 * Competency Widget Block Controller.
 * Fully refactored to support Mustache layouts, AMD UI, MUC caching, and deep context awareness.
 */

defined('MOODLE_INTERNAL') || die();

class block_competency_widget extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_competency_widget');
    }

    public function get_content() {
        global $USER, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!get_config('core_competency', 'enabled')) {
            $this->content->text = html_writer::div(get_string('competenciesdisabled', 'core_competency'), 'alert alert-warning');
            return $this->content;
        }

        // Context Awareness & Teacher Previews
        $userid = $USER->id;
        $pagecontext = $this->page->context;
        if ($pagecontext->contextlevel == CONTEXT_USER) {
            $userid = $pagecontext->instanceid;
        }

        // Performance MUC Cache Pipeline Optimization (Ad-hoc)
        $cache = cache::make_from_params(cache_store::MODE_REQUEST, 'block_competency_widget', 'competency_metrics');
        $cachekey = 'user_metrics_' . $userid . '_course_' . $this->page->course->id;
        $cacheddata = $cache->get($cachekey);

        if ($cacheddata !== false) {
            $this->render_widget_ui($cacheddata);
            return $this->content;
        }

        $all_tracked_competencies = [];
        $current_course_id = $this->page->course->id;
        $is_course_context = ($current_course_id && $current_course_id != SITEID);

        // --- PIPELINE 1: Global Learning Plans ---
        if (!$is_course_context) {
            try {
                $usercompetencies = \core_competency\user_competency::get_records(['userid' => $userid]);
                foreach ($usercompetencies as $uc) {
                    try {
                        $competency = new \core_competency\competency($uc->get('competencyid'));
                        $compid = $competency->get('id');
                        
                        $deeplink = new moodle_url('/competency/user_competency.php', [
                            'userid' => $userid, 
                            'competencyid' => $compid
                        ]);

                        $all_tracked_competencies[$compid . '_lp'] = [
                            'name' => format_string($competency->get('shortname')),
                            'proficient' => (bool)$uc->get('proficiency'),
                            'context' => 'Learning Plan',
                            'context_class' => 'cw-ctx-lp',
                            'url' => $deeplink->out(false)
                        ];
                    } catch (Exception $e) {
                        continue;
                    }
                }
            } catch (Exception $e) {}
        }

        // --- PIPELINE 2: Course Configurations ---
        try {
            $courses_to_scan = [];
            if ($is_course_context) {
                $courses_to_scan[] = $this->page->course;
            } else {
                $courses_to_scan = enrol_get_users_courses($userid, true, 'id, shortname');
            }

            foreach ($courses_to_scan as $course) {
                $course_comps = \core_competency\api::list_course_competencies($course->id);
                foreach ($course_comps as $cc_map) {
                    // FIXED: Defensive object/array check to prevent data loading dropouts
                    $competency = is_object($cc_map) ? $cc_map->competency : $cc_map['competency'];
                    if (!$competency) {
                        continue;
                    }
                    
                    $compid = $competency->get('id');
                    $user_course_comp = \core_competency\api::get_user_competency_in_course($course->id, $userid, $compid);
                    $is_proficient = $user_course_comp ? (bool)$user_course_comp->get('proficiency') : false;

                    $deeplink = new moodle_url('/competency/user_competency_in_course.php', [
                        'userid' => $userid, 
                        'competencyid' => $compid,
                        'courseid' => $course->id
                    ]);

                    $all_tracked_competencies[$compid . '_c_' . $course->id] = [
                        'name' => format_string($competency->get('shortname')),
                        'proficient' => $is_proficient,
                        'context' => format_string($course->shortname),
                        'context_class' => 'cw-ctx-course',
                        'url' => $deeplink->out(false)
                    ];
                }
            }
        } catch (Exception $e) {}

        // --- METRICS PROCESSING LOGIC ---
        $totalcompetencies = count($all_tracked_competencies);
        $earnedcompetencies = 0;
        $itemsdata = [];

        foreach ($all_tracked_competencies as $item) {
            if ($item['proficient']) {
                $earnedcompetencies++;
            }

            $status_text = $item['proficient'] 
                ? get_string('proficient', 'block_competency_widget') 
                : get_string('notproficient', 'block_competency_widget');

            $itemsdata[] = [
                'name' => $item['name'],
                'url' => $item['url'],
                'context' => $item['context'],
                'context_class' => $item['context_class'],
                'status_class' => $item['proficient'] ? 'cw-badge-success' : 'cw-badge-warning',
                'status_text' => $status_text
            ];
        }

        $percentage = $totalcompetencies > 0 ? round(($earnedcompetencies / $totalcompetencies) * 100) : 0;
        $radius = 40;
        $circumference = 2 * M_PI * $radius;
        $strokeoffset = $circumference - ($percentage / 100) * $circumference;

        $stringvars = new stdClass();
        $stringvars->earned = $earnedcompetencies;
        $stringvars->total = $totalcompetencies;
        $metastring = get_string('earnedof', 'block_competency_widget', $stringvars);

        $templatevars = [
            'uniqid' => uniqid('cw-widget-'),
            'radius' => $radius,
            'circumference' => $circumference,
            'strokeoffset' => $strokeoffset,
            'percentage' => $percentage,
            'metastring' => $metastring,
            'items' => $itemsdata
        ];

        $cache->set($cachekey, $templatevars);

        $this->render_widget_ui($templatevars);
        return $this->content;
    }

    /**
     * Executes the Mustache template engine renderer natively.
     */
    private function render_widget_ui($templatevars) {
        global $OUTPUT;
        
        // Render structure directly onto the output layer
        $this->content->text = $OUTPUT->render_from_template('block_competency_widget/widget_content', $templatevars);
    }

    public function applicable_formats() {
        return array('all' => true);
    }
}