/**
 * UI Initialization Engine for the Competency Widget.
 *
 * @module     block_competency_widget/init
 * @package    block_competency_widget
 */
define([], function() {
    return {
        init: function(uniqid) {
            var blockWrapper = document.getElementById(uniqid);
            if (!blockWrapper) {
                return;
            }
            
            var progressCircle = blockWrapper.querySelector('.cw-svg-progress');
            if (progressCircle) {
                // Read calculated target metrics from dataset attribute tags
                var targetOffset = progressCircle.getAttribute('data-offset');
                
                // Allow thread breathing space to guarantee transition rendering pipelines execute
                setTimeout(function() {
                    progressCircle.style.strokeDashoffset = targetOffset;
                }, 75);
            }
        }
    };
});