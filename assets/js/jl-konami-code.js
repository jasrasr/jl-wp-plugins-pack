(function () {
    'use strict';
    var target = window.jlKonamiTarget;
    if (!target) return;

    var keyboard = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
    var touch = ['up', 'up', 'down', 'down', 'left', 'right', 'left', 'right', 'tap', 'tap'];
    var keys = [];
    var gestures = [];
    var start = null;

    function advance(history, sequence, value) {
        history.push(value);
        if (history.length > sequence.length) history.shift();
        if (history.length === sequence.length && history.every(function (item, index) { return item === sequence[index]; })) {
            window.location.assign(target);
        }
    }

    document.addEventListener('keydown', function (event) {
        var element = event.target;
        if (element && (element.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(element.tagName))) return;
        var key = event.key.length === 1 ? event.key.toLowerCase() : event.key;
        advance(keys, keyboard, key);
    });

    document.addEventListener('touchstart', function (event) {
        if (event.touches.length !== 1) return;
        start = { x: event.touches[0].clientX, y: event.touches[0].clientY };
    }, { passive: true });

    document.addEventListener('touchend', function (event) {
        if (!start || event.changedTouches.length !== 1) return;
        var dx = event.changedTouches[0].clientX - start.x;
        var dy = event.changedTouches[0].clientY - start.y;
        start = null;
        var gesture = 'tap';
        if (Math.max(Math.abs(dx), Math.abs(dy)) > 35) gesture = Math.abs(dx) > Math.abs(dy) ? (dx > 0 ? 'right' : 'left') : (dy > 0 ? 'down' : 'up');
        advance(gestures, touch, gesture);
    }, { passive: true });
}());
