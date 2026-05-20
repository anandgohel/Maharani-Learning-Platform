/**
 * MW LearnDash Analytics — Heartbeat Script
 * Pings the server every 30s while the user is actively viewing a lesson.
 * Tracks real time-on-lesson (pauses when tab is hidden).
 */
(function () {
    'use strict';

    if (typeof mwaData === 'undefined') return;

    var isActive = true;
    var intervalId = null;

    // Detect tab visibility changes
    document.addEventListener('visibilitychange', function () {
        isActive = !document.hidden;
    });

    // Also track focus/blur for extra reliability
    window.addEventListener('focus', function () { isActive = true; });
    window.addEventListener('blur', function () { isActive = false; });

    function sendHeartbeat() {
        if (!isActive) return;

        fetch(mwaData.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': mwaData.nonce,
            },
            body: JSON.stringify({
                postId: mwaData.postId,
                postType: mwaData.postType,
                userId: mwaData.userId,
                courseId: mwaData.courseId || 0,
            }),
            keepalive: true,
        }).catch(function () {
            // Silently fail — don't interrupt the user experience
        });
    }

    // Send initial heartbeat immediately (marks session start)
    sendHeartbeat();

    // Then send every N seconds (default 30)
    var interval = (mwaData.interval || 30) * 1000;
    intervalId = setInterval(sendHeartbeat, interval);

    // Cleanup on page unload
    window.addEventListener('beforeunload', function () {
        if (intervalId) clearInterval(intervalId);
        // Send final heartbeat
        if (navigator.sendBeacon && isActive) {
            var blob = new Blob([JSON.stringify({
                postId: mwaData.postId,
                postType: mwaData.postType,
                userId: mwaData.userId,
            })], { type: 'application/json' });
            navigator.sendBeacon(mwaData.ajaxUrl + '?_wpnonce=' + mwaData.nonce, blob);
        }
    });
})();
