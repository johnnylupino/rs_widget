// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AMD module for block_progresswidget.
 *
 * Renders a pure-SVG animated doughnut chart.
 * No external dependencies — works in Moodle 4.x production mode
 * without requiring grunt/npm build step, because the minified build
 * file (amd/build/chart.min.js) is shipped pre-built in this package.
 *
 * @module     block_progresswidget/chart
 * @copyright  2024 Your Organisation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function () {
    'use strict';

    var COLORS = {
        completed:  '#1D9E75',
        inprogress: '#EF9F27',
        notstarted: '#B4B2A9'
    };

    var RADIUS = 75;
    var CX     = 100;
    var CY     = 100;
    var CIRC   = 2 * Math.PI * RADIUS; // ≈ 471.24

    /**
     * Build one SVG <circle> arc segment.
     */
    function buildArc(startFrac, endFrac, color, ariaLabel) {
        var arcLen  = (endFrac - startFrac) * CIRC;
        var offset  = CIRC - arcLen;          // final stroke-dashoffset value
        var rotate  = startFrac * 360 - 90;   // rotate so arcs begin at 12-o'clock

        return '<circle'
            + ' cx="' + CX + '" cy="' + CY + '" r="' + RADIUS + '"'
            + ' fill="none"'
            + ' stroke="' + color + '"'
            + ' stroke-width="18"'
            + ' stroke-linecap="butt"'
            + ' stroke-dasharray="' + CIRC.toFixed(3) + '"'
            + ' stroke-dashoffset="' + CIRC.toFixed(3) + '"'
            + ' data-target="' + offset.toFixed(4) + '"'
            + ' transform="rotate(' + rotate.toFixed(2) + ' ' + CX + ' ' + CY + ')"'
            + ' aria-label="' + ariaLabel + '"'
            + ' style="transition:stroke-dashoffset 0.9s cubic-bezier(0.4,0,0.2,1)"/>';
    }

    /**
     * Inject SVG doughnut into .pw-chart-target and start animations.
     */
    function renderDoughnut(target, data) {
        var total      = data.total      > 0 ? data.total      : 1;
        var completed  = data.completed  || 0;
        var inprogress = data.inprogress || 0;
        var notstarted = data.notstarted || 0;
        var pct        = Math.round((completed / total) * 100);

        var GAP = (total > 1) ? 0.008 : 0;
        var cur = 0;
        var fC  = completed  / total;
        var fI  = inprogress / total;
        var fN  = notstarted / total;
        var arcs = '';

        if (fC > 0) {
            arcs += buildArc(cur, cur + fC - (fI > 0 || fN > 0 ? GAP : 0),
                COLORS.completed, completed + ' completed');
        }
        cur += fC;
        if (fI > 0) {
            arcs += buildArc(cur, cur + fI - (fN > 0 ? GAP : 0),
                COLORS.inprogress, inprogress + ' in progress');
        }
        cur += fI;
        if (fN > 0) {
            arcs += buildArc(cur, cur + fN,
                COLORS.notstarted, notstarted + ' not started');
        }

        target.innerHTML =
            '<div class="pw-chart-wrap">'
          +   '<svg viewBox="0 0 200 200" width="200" height="200"'
          +       ' role="img" aria-label="' + pct + '% of courses completed">'
          +     '<circle cx="' + CX + '" cy="' + CY + '" r="' + RADIUS + '"'
          +         ' fill="none" stroke="#e9e9e7" stroke-width="18"/>'
          +     arcs
          +   '</svg>'
          +   '<div class="pw-centre" aria-hidden="true">'
          +     '<span class="pw-pct">0%</span>'
          +     '<span class="pw-sub">done</span>'
          +   '</div>'
          + '</div>';

        // Trigger animations on next paint (transitions need one tick to register)
        requestAnimationFrame(function () {
            var arcsEls = target.querySelectorAll('circle[data-target]');
            for (var i = 0; i < arcsEls.length; i++) {
                arcsEls[i].style.strokeDashoffset = arcsEls[i].getAttribute('data-target');
            }

            // Count-up number inside hole
            var pctEl = target.querySelector('.pw-pct');
            var n = 0;
            var timer = setInterval(function () {
                n = Math.min(n + 2, pct);
                pctEl.textContent = n + '%';
                if (n >= pct) { clearInterval(timer); }
            }, 16);
        });
    }

    /**
     * Animate the thin legend bars and per-course bars (start at 0, expand to data-width).
     */
    function animateBars(container) {
        requestAnimationFrame(function () {
            var fills = container.querySelectorAll('[data-width]');
            for (var i = 0; i < fills.length; i++) {
                fills[i].style.width = fills[i].getAttribute('data-width') + '%';
            }
        });
    }

    return {
        /**
         * Entry point called by $PAGE->requires->js_call_amd().
         * data = {elementid, total, completed, inprogress, notstarted, …}
         */
        init: function (data) {
            // elementid points to the hidden <span> anchor inside the block
            var anchor = document.getElementById(data.elementid);
            if (!anchor) { return; }

            // Walk up to the .block-progresswidget wrapper
            var container = anchor.closest
                ? anchor.closest('.block-progresswidget')
                : (function (el) {
                    while (el && !el.classList.contains('block-progresswidget')) {
                        el = el.parentNode;
                    }
                    return el;
                }(anchor));

            if (!container) { return; }
            if (!data.total || data.total === 0) { return; }

            var chartTarget = container.querySelector('.pw-chart-target');
            if (chartTarget) {
                renderDoughnut(chartTarget, data);
            }

            animateBars(container);
        }
    };
});
