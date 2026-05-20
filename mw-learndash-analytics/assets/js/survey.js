/**
 * MW LearnDash Analytics — Survey JS
 * Star rating widget, NPS slider, AJAX submission for lesson + course surveys.
 */
(function () {
    'use strict';
    if (typeof mwaSurvey === 'undefined') return;

    // ── Lesson Micro-Survey ──
    var lessonSurvey = document.getElementById('mwa-lesson-survey');
    if (lessonSurvey) {
        initLessonSurvey(lessonSurvey);
    }

    // ── Course Survey (if container exists) ──
    var courseContainer = document.getElementById('mwa-course-survey-container');
    if (courseContainer) {
        buildCourseSurvey(courseContainer);
    }

    // ── Check for pending course survey ──
    if (mwaSurvey.postType === 'sfwd-courses') {
        checkPendingCourseSurvey();
    }

    function initLessonSurvey(el) {
        var stars = el.querySelectorAll('.mwa-star');
        var starsContainer = el.querySelector('.mwa-stars');
        var ratingText = el.querySelector('.mwa-rating-text');
        var submitBtn = el.querySelector('.mwa-submit-btn');
        var textarea = el.querySelector('.mwa-comment');
        var thanks = el.querySelector('.mwa-survey-thanks');
        var currentRating = parseInt(starsContainer.dataset.rating) || 0;

        var labels = ['', 'Not useful', 'Somewhat useful', 'Useful', 'Very useful', 'Extremely useful'];

        // Set initial state
        highlightStars(currentRating);

        stars.forEach(function (star) {
            star.addEventListener('mouseenter', function () {
                highlightStars(parseInt(this.dataset.value));
                ratingText.textContent = labels[parseInt(this.dataset.value)];
            });

            star.addEventListener('mouseleave', function () {
                highlightStars(currentRating);
                ratingText.textContent = currentRating ? labels[currentRating] : '';
            });

            star.addEventListener('click', function () {
                currentRating = parseInt(this.dataset.value);
                highlightStars(currentRating);
                ratingText.textContent = labels[currentRating];
                submitBtn.classList.add('mwa-btn-ready');
            });
        });

        submitBtn.addEventListener('click', function () {
            if (currentRating === 0) {
                ratingText.textContent = 'Please select a rating';
                ratingText.classList.add('mwa-error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';

            fetch(mwaSurvey.restUrl + 'submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mwaSurvey.nonce,
                },
                body: JSON.stringify({
                    surveyType: 'lesson',
                    postId: mwaSurvey.postId,
                    courseId: mwaSurvey.courseId,
                    rating: currentRating,
                    comment: textarea.value.trim(),
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        thanks.style.display = 'block';
                        thanks.textContent = data.updated ? '✓ Feedback updated!' : '✓ Thanks for your feedback!';
                        submitBtn.textContent = 'Update Feedback';
                        submitBtn.disabled = false;
                        el.classList.add('mwa-survey-submitted');
                    }
                })
                .catch(function () {
                    submitBtn.textContent = 'Error — try again';
                    submitBtn.disabled = false;
                });
        });

        function highlightStars(rating) {
            stars.forEach(function (s) {
                var val = parseInt(s.dataset.value);
                s.classList.toggle('mwa-star-active', val <= rating);
            });
        }
    }

    function buildCourseSurvey(container) {
        var lessons = mwaSurvey.lessons || [];
        var optionsHtml = '<option value="">— Select a lesson —</option>';
        lessons.forEach(function (l) {
            optionsHtml += '<option value="' + l.id + '">' + escHtml(l.title) + '</option>';
        });

        container.innerHTML = [
            '<div class="mwa-course-survey">',
            '  <h3 class="mwa-cs-title">🎓 Course Feedback Survey</h3>',
            '  <p class="mwa-cs-subtitle">Help us improve! Your feedback shapes the next version of this course.</p>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>1. How would you rate this course overall?</label>',
            '    <div class="mwa-stars mwa-cs-stars" id="mwa-cs-rating">',
            '      <span class="mwa-star" data-value="1">★</span>',
            '      <span class="mwa-star" data-value="2">★</span>',
            '      <span class="mwa-star" data-value="3">★</span>',
            '      <span class="mwa-star" data-value="4">★</span>',
            '      <span class="mwa-star" data-value="5">★</span>',
            '      <span class="mwa-rating-text"></span>',
            '    </div>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>2. Which lesson was most valuable to you?</label>',
            '    <select id="mwa-cs-most-valuable" class="mwa-cs-select">' + optionsHtml + '</select>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>3. Which lesson was least valuable?</label>',
            '    <select id="mwa-cs-least-valuable" class="mwa-cs-select">' + optionsHtml + '</select>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>4. How confident do you feel working with South Asian couples now?</label>',
            '    <div class="mwa-confidence-scale">',
            '      <span class="mwa-scale-label">Not at all</span>',
            '      <div class="mwa-scale-btns" id="mwa-cs-confidence">',
            '        <button type="button" data-value="1">1</button>',
            '        <button type="button" data-value="2">2</button>',
            '        <button type="button" data-value="3">3</button>',
            '        <button type="button" data-value="4">4</button>',
            '        <button type="button" data-value="5">5</button>',
            '      </div>',
            '      <span class="mwa-scale-label">Very confident</span>',
            '    </div>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>5. How likely are you to recommend this course to a colleague?</label>',
            '    <div class="mwa-nps-wrap">',
            '      <input type="range" id="mwa-cs-nps" min="0" max="10" value="5" class="mwa-nps-slider" />',
            '      <div class="mwa-nps-labels">',
            '        <span>0 - Not likely</span>',
            '        <span class="mwa-nps-value">5</span>',
            '        <span>10 - Very likely</span>',
            '      </div>',
            '    </div>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>6. What topic would you like us to add?</label>',
            '    <textarea id="mwa-cs-comment" class="mwa-cs-textarea" placeholder="E.g., Decor trends, vendor coordination..." maxlength="500"></textarea>',
            '  </div>',
            '',
            '  <div class="mwa-cs-question">',
            '    <label>7. Any other feedback?</label>',
            '    <textarea id="mwa-cs-additional" class="mwa-cs-textarea" placeholder="We appreciate your thoughts..." maxlength="500"></textarea>',
            '  </div>',
            '',
            '  <button type="button" id="mwa-cs-submit" class="mwa-submit-btn mwa-cs-submit-btn">Submit Course Feedback</button>',
            '  <div id="mwa-cs-thanks" class="mwa-survey-thanks" style="display:none;"></div>',
            '</div>'
        ].join('\n');

        // Wire up interactions
        initCourseStars();
        initConfidenceScale();
        initNPSSlider();
        initCourseSubmit();
    }

    function initCourseStars() {
        var container = document.getElementById('mwa-cs-rating');
        if (!container) return;
        var stars = container.querySelectorAll('.mwa-star');
        var ratingText = container.querySelector('.mwa-rating-text');
        var labels = ['', 'Poor', 'Below Average', 'Good', 'Very Good', 'Excellent'];
        window._mwaCourseRating = 0;

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                window._mwaCourseRating = parseInt(this.dataset.value);
                stars.forEach(function (s) {
                    s.classList.toggle('mwa-star-active', parseInt(s.dataset.value) <= window._mwaCourseRating);
                });
                ratingText.textContent = labels[window._mwaCourseRating];
            });
        });
    }

    function initConfidenceScale() {
        var container = document.getElementById('mwa-cs-confidence');
        if (!container) return;
        var btns = container.querySelectorAll('button');
        window._mwaConfidence = 0;

        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                window._mwaConfidence = parseInt(this.dataset.value);
                btns.forEach(function (b) {
                    b.classList.toggle('mwa-scale-active', parseInt(b.dataset.value) <= window._mwaConfidence);
                });
            });
        });
    }

    function initNPSSlider() {
        var slider = document.getElementById('mwa-cs-nps');
        if (!slider) return;
        var valueDisplay = document.querySelector('.mwa-nps-value');

        slider.addEventListener('input', function () {
            valueDisplay.textContent = this.value;
            var pct = (this.value / 10) * 100;
            this.style.background = 'linear-gradient(90deg, #6366f1 ' + pct + '%, #e2e8f0 ' + pct + '%)';
        });
    }

    function initCourseSubmit() {
        var btn = document.getElementById('mwa-cs-submit');
        if (!btn) return;

        btn.addEventListener('click', function () {
            if (!window._mwaCourseRating) {
                alert('Please provide an overall course rating.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Submitting...';

            fetch(mwaSurvey.restUrl + 'submit', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': mwaSurvey.nonce,
                },
                body: JSON.stringify({
                    surveyType: 'course',
                    postId: mwaSurvey.courseId || mwaSurvey.postId,
                    courseId: mwaSurvey.courseId,
                    rating: window._mwaCourseRating,
                    npsScore: parseInt(document.getElementById('mwa-cs-nps').value),
                    confidenceScore: window._mwaConfidence,
                    mostValuableLesson: parseInt(document.getElementById('mwa-cs-most-valuable').value) || 0,
                    leastValuableLesson: parseInt(document.getElementById('mwa-cs-least-valuable').value) || 0,
                    comment: document.getElementById('mwa-cs-comment').value.trim(),
                    additionalFeedback: document.getElementById('mwa-cs-additional').value.trim(),
                }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var thanks = document.getElementById('mwa-cs-thanks');
                        thanks.style.display = 'block';
                        thanks.textContent = '🎉 Thank you for your feedback! Your input helps us improve the course.';
                        btn.style.display = 'none';
                    }
                })
                .catch(function () {
                    btn.textContent = 'Error — try again';
                    btn.disabled = false;
                });
        });
    }

    function checkPendingCourseSurvey() {
        // If on a course page, check if user has a pending survey
        fetch(mwaSurvey.restUrl + 'status?postId=' + mwaSurvey.postId + '&surveyType=course', {
            headers: { 'X-WP-Nonce': mwaSurvey.nonce },
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.submitted) {
                    // Insert course survey after the course content
                    var content = document.querySelector('.learndash-wrapper') || document.querySelector('.entry-content');
                    if (content) {
                        var div = document.createElement('div');
                        div.id = 'mwa-course-survey-container';
                        div.dataset.courseId = mwaSurvey.postId;
                        content.appendChild(div);
                        buildCourseSurvey(div);
                    }
                }
            })
            .catch(function () { /* silently fail */ });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
})();
