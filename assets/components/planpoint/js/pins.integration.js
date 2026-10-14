/* Optional site integration: replace old pin_center() calls, don't run both. */
(function(root){
'use strict';
var scheduled=false;
function update(){scheduled=false;document.querySelectorAll('.plan-svg').forEach(function(block){
    var rect=block.getBoundingClientRect();if(!rect.width||!rect.height)return;
    block.querySelectorAll('circle[data-pin]').forEach(function(dot){
        var svg=dot.ownerSVGElement,m=dot.getScreenCTM();if(!svg||!m)return;
        var p=svg.createSVGPoint();p.x=dot.cx.baseVal.value;p.y=dot.cy.baseVal.value;p=p.matrixTransform(m);
        block.querySelectorAll('.pin-item[data-i]').forEach(function(pin){
            if(pin.getAttribute('data-i')!==dot.getAttribute('data-pin'))return;
            var parent=pin.offsetParent;if(!parent)return;var r=parent.getBoundingClientRect();
            pin.style.left=(p.x-r.left-parent.clientLeft+parent.scrollLeft)+'px';
            pin.style.top=(p.y-r.top-parent.clientTop+parent.scrollTop)+'px';pin.style.visibility='visible';
        });
    });
});}
function schedule(){if(!scheduled){scheduled=true;requestAnimationFrame(update);}}
function init(){document.querySelectorAll('.plan-svg').forEach(function(block){
    block.querySelectorAll('img').forEach(function(img){img.addEventListener('load',schedule);});
    if(root.ResizeObserver)new ResizeObserver(schedule).observe(block);
});root.addEventListener('resize',schedule);schedule();}
root.PlanPointRepositionPins=schedule;
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
}(window));
