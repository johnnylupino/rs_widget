<?php
/**
 * Competency Widget Block Execution File.
 * Generates React-style interactive radial graphs and labels tracking skill completion.
 */

defined('MOODLE_INTERNAL') || die();

class block_competency_widget extends block_base {

    /**
     * Component initializer. Sets the visible title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_competency_widget');
    }

    /**
     * Generates and returns the visual content inside the block container.
     */
    public function get_content() {
        global $USER;

        // If content is already calculated, optimize by returning it directly
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Verification step: Ensure core tracking framework is enabled system-wide
        if (!get_config('core_competency', 'enabled')) {
            $this->content->text = html_writer::div(get_string('competenciesdisabled', 'core_competency'), 'alert alert-warning');
            return $this->content;
        }

        $userid = $USER->id;
        try {
            $usercompetencies = \core_competency\user_competency::get_records(['userid' => $userid]);
        } catch (Exception $e) {
            $usercompetencies = [];
        }

        $totalcompetencies = count($usercompetencies);
        $earnedcompetencies = 0;
        $listitemshtml = '';

        // Loop through metrics data to build React-like pill elements
        foreach ($usercompetencies as $uc) {
            $isproficient = $uc->get('proficiency');
            if ($isproficient) {
                $earnedcompetencies++;
            }

            try {
                $competency = new \core_competency\competency($uc->get('competencyid'));
                $name = format_string($competency->get('shortname'));
            } catch (Exception $e) {
                continue; // Skip execution if object instances are detached or missing
            }

            $badgeclass = $isproficient ? 'cw-badge-success' : 'cw-badge-muted';
            $badgetext = $isproficient ? get_string('proficient', 'block_competency_widget') : get_string('notproficient', 'block_competency_widget');

            $listitemshtml .= "
            <div class='cw-list-item'>
                <span class='cw-item-name' title='{$name}'>{$name}</span>
                <span class='cw-badge {$badgeclass}'>{$badgetext}</span>
            </div>";
        }

        // Percentage and SVG mathematical calculations
        $percentage = $totalcompetencies > 0 ? round(($earnedcompetencies / $totalcompetencies) * 100) : 0;
        $radius = 40;
        $circumference = 2 * M_PI * $radius;
        $strokeoffset = $circumference - ($percentage / 100) * $circumference;

        // Build object variables mapping localized information output
        $stringvars = new stdClass();
        $stringvars->earned = $earnedcompetencies;
        $stringvars->total = $totalcompetencies;
        $metastring = get_string('earnedof', 'block_competency_widget', $stringvars);

        // Core component UI HTML
        $html = "
        <div class='cw-wrapper'>
            <div class='cw-chart-container'>
                <div class='cw-radial-wrapper'>
                    <svg width='110' height='110' viewBox='0 0 100 100' style='transform: rotate(-90deg);'>
                        <circle class='cw-svg-bg' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'/>
                        <circle class='cw-svg-progress' cx='50' cy='50' r='{$radius}' stroke-width='8' fill='transparent'
                                stroke-dasharray='{$circumference}' 
                                stroke-dashoffset='{$strokeoffset}'/>
                    </svg>
                    <div class='cw-radial-value'>{$percentage}%</div>
                </div>
                <div class='cw-meta'>{$metastring}</div>
            </div>
            
            <div class='cw-list-container'>
                " . (!empty($listitemshtml) ? $listitemshtml : "<div class='text-muted text-center' style='font-size:13px;'>No tracked competencies assigned.</div>") . "
            </div>
        </div>
        ";

        // Modern React-Component Scoped Styling System (safely namespaced with .cw-)
        $css = "
        <style>
            .cw-wrapper {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                color: #1e293b;
            }
            .cw-chart-container {
                text-align: center;
                margin-bottom: 16px;
                padding-bottom: 16px;
                border-bottom: 1px solid #f1f5f9;
            }
            .cw-radial-wrapper {
                position: relative;
                width: 110px;
                height: 110px;
                margin: 0 auto 8px auto;
            }
            .cw-radial-value {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 20px;
                font-weight: 700;
                color: #0f172a;
            }
            .cw-svg-bg {
                stroke: #f1f5f9;
            }
            .cw-svg-progress {
                stroke: #2563eb; /* Tailwinds Royal Blue */
                stroke-linecap: round;
                transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .cw-meta {
                font-size: 13px;
                color: #64748b;
                font-weight: 500;
            }
            .cw-list-container {
                max-height: 220px;
                overflow-y: auto;
                padding-right: 4px;
            }
            .cw-list-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 12px;
                margin-bottom: 6px;
                background: #f8fafc;
                border-radius: 8px;
                border: 1px solid #e2e8f0;
                font-size: 12px;
            }
            .cw-list-item:hover {
                background: #f1f5f9;
            }
            .cw-item-name {
                font-weight: 500;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                margin-right: 12px;
            }
            .cw-badge {
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 10px;
                font-weight: 600;
                white-space: nowrap;
            }
            .cw-badge-success {
                background: #dcfce7;
                color: #15803d;
            }
            .cw-badge-muted {
                background: #e2e8f0;
                color: #475569;
            }
        </style>
        ";

        $this->content->text = $css . $html;
        return $this->content;
    }

    /**
     * Explicit layout definitions. Dictates where block instances can live.
     */
    public function applicable_formats() {
        return array('all' => true);
    }
}