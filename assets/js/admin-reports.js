/**
 * MDCAT Platform — Admin Reports Dashboard Controller
 *
 * Fetches platform-wide reporting data via AJAX and renders
 * it into the HTML shell provided by the admin view.
 *
 * Only loaded on the admin dashboard page.
 */

(function () {

    'use strict';

    var MDCATAdminDashboard = {

        /**
         * Initialize the admin dashboard controller.
         * Begins parallel data fetching once the DOM is ready.
         */
        init: function () {

            if (typeof MDCATAdminReports === 'undefined') {
                return;
            }

            this.fetchAll();
        },

        /**
         * Fetch all dashboard sections in parallel.
         * Each fetch is independent so one failure doesn't block others.
         */
        fetchAll: function () {

            this.fetchOverview();
            this.fetchStudents();
            this.fetchPerformance();
            this.fetchActivity();
        },

        /* ============================================================
           Overview Statistics
           ============================================================ */

        fetchOverview: function () {

            var self = this;

            this.ajaxRequest('mdcat_admin_get_overview', function (data) {
                self.renderStats(data);
            });
        },

        renderStats: function (data) {

            var container = document.querySelector('.mdcat-admin-reports__stats-grid');

            if (!container) {
                return;
            }

            var cards = [
                { key: 'total_students',      label: 'Total Students',      icon: '👥', modifier: 'students' },
                { key: 'total_subjects',      label: 'Total Subjects',      icon: '📚', modifier: 'subjects' },
                { key: 'total_chapters',      label: 'Total Chapters',      icon: '📖', modifier: 'chapters' },
                { key: 'total_collections',   label: 'Total Collections',   icon: '📋', modifier: 'collections' },
                { key: 'total_questions',     label: 'Total Questions',     icon: '❓', modifier: 'questions' },
                { key: 'total_attempts',      label: 'Total Attempts',      icon: '✏️', modifier: 'attempts' },
                { key: 'average_accuracy',    label: 'Average Accuracy',    icon: '🎯', modifier: 'accuracy', suffix: '%' },
                { key: 'active_streak_users', label: 'Active Today',        icon: '🔥', modifier: 'streak' },
            ];

            var html = '';

            for (var i = 0; i < cards.length; i++) {
                var card  = cards[i];
                var value = data[card.key] !== undefined ? data[card.key] : 0;
                var display = card.suffix ? value + card.suffix : value;

                html += '<div class="mdcat-admin-reports__stat-card mdcat-admin-reports__stat-card--' + card.modifier + '">';
                html += '<span class="mdcat-admin-reports__stat-icon">' + card.icon + '</span>';
                html += '<div class="mdcat-admin-reports__stat-value">' + this.escapeHtml(String(display)) + '</div>';
                html += '<div class="mdcat-admin-reports__stat-label">' + this.escapeHtml(card.label) + '</div>';
                html += '</div>';
            }

            container.innerHTML = html;
        },

        /* ============================================================
           Student Reporting
           ============================================================ */

        fetchStudents: function () {

            var self = this;

            this.ajaxRequest('mdcat_admin_get_students', function (data) {
                self.renderMostActive(data.most_active || []);
                self.renderTopPerformers(data.top_performers || []);
            });
        },

        renderMostActive: function (students) {

            var container = document.querySelector('.mdcat-admin-reports__most-active');

            if (!container) {
                return;
            }

            if (!students.length) {
                container.innerHTML = '<div class="mdcat-admin-reports__empty">No student activity recorded yet.</div>';
                return;
            }

            var html = '<div class="mdcat-admin-reports__table-wrap">';
            html += '<table class="mdcat-admin-reports__table">';
            html += '<thead><tr>';
            html += '<th>#</th>';
            html += '<th>Student</th>';
            html += '<th>Attempts</th>';
            html += '<th>Last Active</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < students.length; i++) {
                var s = students[i];
                html += '<tr>';
                html += '<td><span class="mdcat-admin-reports__rank">' + (i + 1) + '</span></td>';
                html += '<td>' + this.escapeHtml(s.display_name) + '</td>';
                html += '<td>' + this.escapeHtml(String(s.attempt_count)) + '</td>';
                html += '<td><span class="mdcat-admin-reports__date">' + this.formatDate(s.last_attempt_date) + '</span></td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        renderTopPerformers: function (students) {

            var container = document.querySelector('.mdcat-admin-reports__top-performers');

            if (!container) {
                return;
            }

            if (!students.length) {
                container.innerHTML = '<div class="mdcat-admin-reports__empty">No performance data available yet.</div>';
                return;
            }

            var html = '<div class="mdcat-admin-reports__table-wrap">';
            html += '<table class="mdcat-admin-reports__table">';
            html += '<thead><tr>';
            html += '<th>#</th>';
            html += '<th>Student</th>';
            html += '<th>Accuracy</th>';
            html += '<th>Correct</th>';
            html += '<th>Wrong</th>';
            html += '<th>Attempts</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < students.length; i++) {
                var s = students[i];
                var accuracyClass = this.getAccuracyClass(s.accuracy);

                html += '<tr>';
                html += '<td><span class="mdcat-admin-reports__rank">' + (i + 1) + '</span></td>';
                html += '<td>' + this.escapeHtml(s.display_name) + '</td>';
                html += '<td><span class="' + accuracyClass + '">' + this.escapeHtml(String(s.accuracy)) + '%</span></td>';
                html += '<td>' + this.escapeHtml(String(s.total_correct)) + '</td>';
                html += '<td>' + this.escapeHtml(String(s.total_wrong)) + '</td>';
                html += '<td>' + this.escapeHtml(String(s.total_attempts)) + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        /* ============================================================
           Performance Reporting
           ============================================================ */

        fetchPerformance: function () {

            var self = this;

            this.ajaxRequest('mdcat_admin_get_performance', function (data) {
                self.renderPerformanceReport(data.report || []);
                self.renderHighlightList('.mdcat-admin-reports__strongest', data.strongest || [], 'strong');
                self.renderHighlightList('.mdcat-admin-reports__weakest', data.weakest || [], 'weak');
            });
        },

        renderPerformanceReport: function (report) {

            var container = document.querySelector('.mdcat-admin-reports__performance-report');

            if (!container) {
                return;
            }

            if (!report.length) {
                container.innerHTML = '<div class="mdcat-admin-reports__empty">No performance data available yet.</div>';
                return;
            }

            var html = '<div class="mdcat-admin-reports__table-wrap">';
            html += '<table class="mdcat-admin-reports__table">';
            html += '<thead><tr>';
            html += '<th>Subject</th>';
            html += '<th>Accuracy</th>';
            html += '<th>Correct</th>';
            html += '<th>Wrong</th>';
            html += '<th>Total</th>';
            html += '<th>Performance</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < report.length; i++) {
                var r = report[i];
                var accuracyClass = this.getAccuracyClass(r.accuracy_percentage);
                var labelClass = this.getLabelClass(r.performance_label);

                html += '<tr>';
                html += '<td>' + this.escapeHtml(r.subject_title) + '</td>';
                html += '<td><span class="' + accuracyClass + '">' + this.escapeHtml(String(r.accuracy_percentage)) + '%</span></td>';
                html += '<td>' + this.escapeHtml(String(r.correct_answers)) + '</td>';
                html += '<td>' + this.escapeHtml(String(r.wrong_answers)) + '</td>';
                html += '<td>' + this.escapeHtml(String(r.total_questions)) + '</td>';
                html += '<td><span class="mdcat-admin-reports__label ' + labelClass + '">' + this.escapeHtml(r.performance_label) + '</span></td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        renderHighlightList: function (selector, items, type) {

            var container = document.querySelector(selector);

            if (!container) {
                return;
            }

            if (!items.length) {
                var emptyMsg = type === 'strong'
                    ? 'No strong subjects detected yet.'
                    : 'No weak subjects detected yet.';
                container.innerHTML = '<div class="mdcat-admin-reports__empty">' + emptyMsg + '</div>';
                return;
            }

            var html = '<ul class="mdcat-admin-reports__highlight-list">';

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var accuracyClass = this.getAccuracyClass(item.accuracy_percentage);

                html += '<li class="mdcat-admin-reports__highlight-item">';
                html += '<span class="mdcat-admin-reports__highlight-name">';
                html += '<span class="mdcat-admin-reports__rank">' + (i + 1) + '</span>';
                html += this.escapeHtml(item.subject_title);
                html += '</span>';
                html += '<span class="mdcat-admin-reports__highlight-accuracy ' + accuracyClass + '">';
                html += this.escapeHtml(String(item.accuracy_percentage)) + '%';
                html += '</span>';
                html += '</li>';
            }

            html += '</ul>';
            container.innerHTML = html;
        },

        /* ============================================================
           Activity Feed
           ============================================================ */

        fetchActivity: function () {

            var self = this;

            this.ajaxRequest('mdcat_admin_get_activity', function (data) {
                self.renderActivityFeed(data);
            });
        },

        renderActivityFeed: function (activity) {

            var container = document.querySelector('.mdcat-admin-reports__activity-feed');

            if (!container) {
                return;
            }

            if (!activity || !activity.length) {
                container.innerHTML = '<div class="mdcat-admin-reports__empty">No recent activity recorded yet.</div>';
                return;
            }

            var html = '<div class="mdcat-admin-reports__table-wrap">';
            html += '<table class="mdcat-admin-reports__table">';
            html += '<thead><tr>';
            html += '<th>Student</th>';
            html += '<th>Subject</th>';
            html += '<th>Chapter</th>';
            html += '<th>Collection</th>';
            html += '<th>Score</th>';
            html += '<th>Correct</th>';
            html += '<th>Wrong</th>';
            html += '<th>Date</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < activity.length; i++) {
                var a = activity[i];
                html += '<tr>';
                html += '<td>' + this.escapeHtml(a.student_name) + '</td>';
                html += '<td>' + this.escapeHtml(a.subject_title) + '</td>';
                html += '<td>' + this.escapeHtml(a.chapter_title) + '</td>';
                html += '<td>' + this.escapeHtml(a.collection_title) + '</td>';
                html += '<td><span class="mdcat-admin-reports__score">' + this.escapeHtml(String(a.score)) + '</span></td>';
                html += '<td>' + this.escapeHtml(String(a.correct_answers)) + '</td>';
                html += '<td>' + this.escapeHtml(String(a.wrong_answers)) + '</td>';
                html += '<td><span class="mdcat-admin-reports__date">' + this.formatDate(a.completed_at) + '</span></td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;
        },

        /* ============================================================
           Utilities
           ============================================================ */

        /**
         * Reusable AJAX POST helper.
         *
         * @param {string}   action   WordPress AJAX action name.
         * @param {function} callback Success callback receiving response data.
         */
        ajaxRequest: function (action, callback) {

            var xhr = new XMLHttpRequest();

            xhr.open('POST', MDCATAdminReports.ajax_url, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function () {

                if (xhr.readyState !== 4) {
                    return;
                }

                if (xhr.status !== 200) {
                    return;
                }

                try {
                    var response = JSON.parse(xhr.responseText);

                    if (response.success && response.data) {
                        callback(response.data);
                    }
                } catch (e) {
                    // Silently fail — loading states remain visible
                }
            };

            xhr.send(
                'action=' + encodeURIComponent(action) +
                '&nonce=' + encodeURIComponent(MDCATAdminReports.nonce)
            );
        },

        /**
         * Get CSS class for accuracy value coloring.
         *
         * @param {number} accuracy Accuracy percentage.
         * @return {string}
         */
        getAccuracyClass: function (accuracy) {

            accuracy = parseFloat(accuracy) || 0;

            if (accuracy >= 80) {
                return 'mdcat-admin-reports__accuracy--strong';
            }

            if (accuracy >= 60) {
                return 'mdcat-admin-reports__accuracy--average';
            }

            return 'mdcat-admin-reports__accuracy--weak';
        },

        /**
         * Get CSS class for performance label badge.
         *
         * @param {string} label Performance label.
         * @return {string}
         */
        getLabelClass: function (label) {

            var normalized = (label || '').toLowerCase();

            if (normalized === 'strong') {
                return 'mdcat-admin-reports__label--strong';
            }

            if (normalized === 'average') {
                return 'mdcat-admin-reports__label--average';
            }

            return 'mdcat-admin-reports__label--weak';
        },

        /**
         * Format a date string for display.
         *
         * @param {string} dateStr Date string from the server.
         * @return {string}
         */
        formatDate: function (dateStr) {

            if (!dateStr) {
                return '—';
            }

            try {
                var date = new Date(dateStr.replace(' ', 'T'));

                if (isNaN(date.getTime())) {
                    return dateStr;
                }

                var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                var day    = date.getDate();
                var month  = months[date.getMonth()];
                var year   = date.getFullYear();
                var hours  = date.getHours();
                var mins   = date.getMinutes();
                var ampm   = hours >= 12 ? 'PM' : 'AM';

                hours = hours % 12;
                hours = hours ? hours : 12;
                mins  = mins < 10 ? '0' + mins : mins;

                return month + ' ' + day + ', ' + year + ' ' + hours + ':' + mins + ' ' + ampm;
            } catch (e) {
                return dateStr;
            }
        },

        /**
         * Escape HTML to prevent XSS in rendered output.
         *
         * @param {string} str Raw string.
         * @return {string}
         */
        escapeHtml: function (str) {

            if (!str) {
                return '';
            }

            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    /* ============================================================
       Boot
       ============================================================ */

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            MDCATAdminDashboard.init();
        });
    } else {
        MDCATAdminDashboard.init();
    }

})();
