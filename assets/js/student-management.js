/**
 * MDCAT Platform — Student Management Controller
 *
 * Manages the admin student management page: directory listing
 * with search, filter, pagination, and student profile views
 * with tabs for progress, analytics, activity, and enrollment.
 *
 * Only loaded on the student management admin page.
 */

(function () {

    'use strict';

    var config = window.MDCATStudentManagement || {};
    var i18n   = config.i18n || {};

    var MDCATStudentManager = {

        // State
        currentPage: 1,
        currentSearch: '',
        currentStatus: 'all',
        currentSort: 'registered',
        currentOrder: 'DESC',
        searchTimer: null,
        currentProfileData: null,
        attemptsPage: 1,

        /**
         * Initialize the controller based on the current view.
         */
        init: function () {

            var app = document.getElementById('mdcat-students-app');

            if (!app) {
                return;
            }

            var view = app.getAttribute('data-view');

            if (view === 'profile') {
                var studentId = parseInt(app.getAttribute('data-student-id'), 10);
                if (studentId) {
                    this.loadProfile(studentId);
                }
            } else {
                this.bindDirectoryEvents();
                this.loadDirectory();
            }
        },

        /* ============================================================
           Directory View
           ============================================================ */

        /**
         * Bind search, filter, and sort events.
         */
        bindDirectoryEvents: function () {

            var self = this;

            // Search with debounce.
            var searchInput = document.getElementById('mdcat-student-search');
            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(self.searchTimer);
                    self.searchTimer = setTimeout(function () {
                        self.currentSearch = searchInput.value.trim();
                        self.currentPage = 1;
                        self.loadDirectory();
                    }, 300);
                });
            }

            // Status filter.
            var statusFilter = document.getElementById('mdcat-student-status-filter');
            if (statusFilter) {
                statusFilter.addEventListener('change', function () {
                    self.currentStatus = statusFilter.value;
                    self.currentPage = 1;
                    self.loadDirectory();
                });
            }

            // Sort.
            var sortSelect = document.getElementById('mdcat-student-sort');
            if (sortSelect) {
                sortSelect.addEventListener('change', function () {
                    self.currentSort = sortSelect.value;
                    self.currentPage = 1;
                    self.loadDirectory();
                });
            }
        },

        /**
         * Load the student directory via AJAX.
         */
        loadDirectory: function () {

            var self      = this;
            var container = document.getElementById('mdcat-students-directory');

            if (!container) {
                return;
            }

            container.innerHTML = '<div class="mdcat-students__loading">' + (i18n.loading || 'Loading...') + '</div>';

            this.ajaxRequest('mdcat_admin_get_student_directory', {
                page:    this.currentPage,
                search:  this.currentSearch,
                status:  this.currentStatus,
                orderby: this.currentSort,
                order:   this.currentOrder,
            }, function (data) {
                self.renderDirectory(data.items || [], data.pagination || {});
            });
        },

        /**
         * Render the student directory table.
         */
        renderDirectory: function (students, pagination) {

            var container      = document.getElementById('mdcat-students-directory');
            var paginationEl   = document.getElementById('mdcat-students-pagination');

            if (!container) {
                return;
            }

            if (!students.length) {
                container.innerHTML = '<div class="mdcat-students__empty">' + this.escapeHtml(i18n.no_students || 'No students found.') + '</div>';
                if (paginationEl) paginationEl.innerHTML = '';
                return;
            }

            var html = '<div class="mdcat-students__table-wrap">';
            html += '<table class="mdcat-students__table">';
            html += '<thead><tr>';
            html += '<th>Student</th>';
            html += '<th>Email</th>';
            html += '<th>Status</th>';
            html += '<th>Attempts</th>';
            html += '<th>Last Activity</th>';
            html += '<th>Registered</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < students.length; i++) {
                var s = students[i];
                var profileUrl = config.students_url + '&student_id=' + s.user_id;
                var statusClass = s.account_status === 'suspended' ? 'suspended' : 'active';

                html += '<tr>';
                html += '<td><a href="' + this.escapeHtml(profileUrl) + '" class="mdcat-students__student-link">' + this.escapeHtml(s.display_name) + '</a></td>';
                html += '<td><span class="mdcat-students__email">' + this.escapeHtml(s.email) + '</span></td>';
                html += '<td><span class="mdcat-students__badge mdcat-students__badge--' + statusClass + '">' + this.escapeHtml(s.account_status) + '</span></td>';
                html += '<td>' + this.escapeHtml(String(s.attempt_count)) + '</td>';
                html += '<td><span class="mdcat-students__date">' + this.formatDate(s.last_activity_date) + '</span></td>';
                html += '<td><span class="mdcat-students__date">' + this.formatDate(s.registered) + '</span></td>';
                html += '<td>';
                html += '<a href="' + this.escapeHtml(profileUrl) + '" class="mdcat-students__btn mdcat-students__btn--view">View</a>';
                html += '</td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            container.innerHTML = html;

            // Render pagination.
            if (paginationEl) {
                this.renderPagination(paginationEl, pagination);
            }
        },

        /**
         * Render pagination controls.
         */
        renderPagination: function (container, pagination) {

            var self       = this;
            var totalPages = pagination.total_pages || 0;
            var page       = pagination.page || 1;
            var totalItems = pagination.total_items || 0;

            if (totalPages <= 1) {
                container.innerHTML = '<div class="mdcat-students__pagination-info">Showing ' + totalItems + ' student' + (totalItems !== 1 ? 's' : '') + '</div>';
                return;
            }

            var html = '<div class="mdcat-students__pagination-info">';
            html += 'Page ' + page + ' of ' + totalPages + ' (' + totalItems + ' students)';
            html += '</div>';

            html += '<div class="mdcat-students__pagination-buttons">';

            // Previous.
            html += '<button class="mdcat-students__page-btn" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

            // Page numbers (show up to 5 pages).
            var startPage = Math.max(1, page - 2);
            var endPage   = Math.min(totalPages, startPage + 4);
            startPage     = Math.max(1, endPage - 4);

            for (var p = startPage; p <= endPage; p++) {
                var activeClass = p === page ? ' mdcat-students__page-btn--active' : '';
                html += '<button class="mdcat-students__page-btn' + activeClass + '" data-page="' + p + '">' + p + '</button>';
            }

            // Next.
            html += '<button class="mdcat-students__page-btn" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>Next &raquo;</button>';

            html += '</div>';
            container.innerHTML = html;

            // Bind page buttons.
            container.querySelectorAll('[data-page]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetPage = parseInt(btn.getAttribute('data-page'), 10);
                    if (targetPage >= 1 && targetPage <= totalPages) {
                        self.currentPage = targetPage;
                        self.loadDirectory();
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                });
            });
        },

        /* ============================================================
           Profile View
           ============================================================ */

        /**
         * Load the full student profile via AJAX.
         */
        loadProfile: function (studentId) {

            var self = this;

            this.ajaxRequest('mdcat_admin_get_student_profile', {
                student_id: studentId,
            }, function (data) {
                self.currentProfileData = data;
                self.renderProfileOverview(data);
                self.renderQuickStats(data);
                self.bindTabs(data);
                self.showTab('progress', data);
            });
        },

        /**
         * Render the profile overview card.
         */
        renderProfileOverview: function (data) {

            var container = document.getElementById('mdcat-student-overview');

            if (!container || !data.overview) {
                return;
            }

            var o           = data.overview;
            var statusClass = o.account_status === 'suspended' ? 'suspended' : 'active';
            var studentId   = o.user_id;

            var html = '<div class="mdcat-students__overview-card">';
            html += '<div class="mdcat-students__overview-row">';

            // Main info.
            html += '<div class="mdcat-students__overview-main">';
            html += '<h2 class="mdcat-students__overview-name">' + this.escapeHtml(o.display_name) + '</h2>';
            html += '<p class="mdcat-students__overview-email">' + this.escapeHtml(o.email) + '</p>';

            html += '<div class="mdcat-students__overview-meta">';
            html += '<span class="mdcat-students__overview-meta-item"><strong>Status:</strong> <span class="mdcat-students__badge mdcat-students__badge--' + statusClass + '">' + this.escapeHtml(o.account_status) + '</span></span>';
            html += '<span class="mdcat-students__overview-meta-item"><strong>Registered:</strong> ' + this.formatDate(o.registered) + '</span>';
            html += '<span class="mdcat-students__overview-meta-item"><strong>Role:</strong> ' + this.escapeHtml(o.role) + '</span>';
            html += '</div>';

            html += '</div>';

            // Action buttons.
            html += '<div class="mdcat-students__overview-actions" id="mdcat-student-actions">';
            if (o.account_status === 'suspended') {
                html += '<button class="mdcat-students__btn mdcat-students__btn--activate" data-action="activate" data-student-id="' + studentId + '">Activate Student</button>';
            } else {
                html += '<button class="mdcat-students__btn mdcat-students__btn--suspend" data-action="suspend" data-student-id="' + studentId + '">Suspend Student</button>';
            }
            html += '</div>';

            html += '</div>';
            html += '</div>';

            container.innerHTML = html;

            // Bind action buttons.
            this.bindStatusActions(container);
        },

        /**
         * Render quick stats cards below the overview.
         */
        renderQuickStats: function (data) {

            var container = document.getElementById('mdcat-student-overview');

            if (!container) {
                return;
            }

            var progress    = data.progress ? data.progress.overall_completion : {};
            var streak      = data.streak || {};
            var analytics   = data.analytics || {};

            // Calculate overall accuracy from subject performance.
            var totalCorrect   = 0;
            var totalQuestions = 0;
            var subjects       = analytics.subject_performance || [];

            for (var i = 0; i < subjects.length; i++) {
                totalCorrect   += subjects[i].correct_answers || 0;
                totalQuestions += subjects[i].total_questions || 0;
            }

            var accuracy = totalQuestions > 0 ? Math.round((totalCorrect / totalQuestions) * 100 * 100) / 100 : 0;

            var stats = [
                { icon: '📊', value: (progress.completion_percentage || 0) + '%', label: 'Completion', modifier: 'progress' },
                { icon: '🔥', value: streak.current_streak || 0, label: 'Current Streak', modifier: 'streak' },
                { icon: '🎯', value: accuracy + '%', label: 'Accuracy', modifier: 'accuracy' },
                { icon: '✏️', value: streak.total_active_days || 0, label: 'Active Days', modifier: 'attempts' },
            ];

            var html = '<div class="mdcat-students__quick-stats">';

            for (var j = 0; j < stats.length; j++) {
                var s = stats[j];
                html += '<div class="mdcat-students__quick-stat mdcat-students__quick-stat--' + s.modifier + '">';
                html += '<span class="mdcat-students__quick-stat-icon">' + s.icon + '</span>';
                html += '<div class="mdcat-students__quick-stat-value">' + this.escapeHtml(String(s.value)) + '</div>';
                html += '<div class="mdcat-students__quick-stat-label">' + this.escapeHtml(s.label) + '</div>';
                html += '</div>';
            }

            html += '</div>';
            container.insertAdjacentHTML('beforeend', html);
        },

        /**
         * Bind tab click events.
         */
        bindTabs: function (data) {

            var self   = this;
            var tabsEl = document.getElementById('mdcat-student-tabs');

            if (!tabsEl) {
                return;
            }

            tabsEl.querySelectorAll('.mdcat-students__tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabsEl.querySelectorAll('.mdcat-students__tab').forEach(function (t) {
                        t.classList.remove('mdcat-students__tab--active');
                    });
                    tab.classList.add('mdcat-students__tab--active');
                    self.showTab(tab.getAttribute('data-tab'), data);
                });
            });
        },

        /**
         * Show a specific tab's content.
         */
        showTab: function (tabName, data) {

            var contentEl = document.getElementById('mdcat-student-tab-content');
            var paginationEl = document.getElementById('mdcat-student-attempts-pagination');

            if (!contentEl) {
                return;
            }

            // Hide attempts pagination by default.
            if (paginationEl) {
                paginationEl.style.display = 'none';
            }

            switch (tabName) {
                case 'progress':
                    this.renderProgressTab(contentEl, data);
                    break;
                case 'analytics':
                    this.renderAnalyticsTab(contentEl, data);
                    break;
                case 'activity':
                    this.renderActivityTab(contentEl, data);
                    break;
                case 'enrollment':
                    this.renderEnrollmentTab(contentEl, data);
                    break;
                default:
                    contentEl.innerHTML = '';
            }
        },

        /* ============================================================
           Progress Tab
           ============================================================ */

        renderProgressTab: function (container, data) {

            var progress = data.progress || {};
            var overall  = progress.overall_completion || {};
            var subjects = progress.subject_completion || [];

            var html = '<div class="mdcat-students__tab-panel">';

            // Overall completion.
            html += '<h3 class="mdcat-students__tab-panel-title">Overall Completion</h3>';
            html += this.renderProgressBar('Curriculum Progress', overall.completion_percentage || 0);
            html += '<p style="font-size:12px; color:var(--sm-text-secondary); margin-top:4px;">';
            html += (overall.completed_collections || 0) + ' of ' + (overall.total_collections || 0) + ' collections completed';
            html += '</p>';

            // Subject completion.
            if (subjects.length) {
                html += '<h3 class="mdcat-students__tab-panel-title" style="margin-top:24px;">Subject Completion</h3>';
                for (var i = 0; i < subjects.length; i++) {
                    var s = subjects[i];
                    html += this.renderProgressBar(
                        s.subject_name + ' (' + (s.completed_collections || 0) + '/' + (s.total_collections || 0) + ')',
                        s.completion_percentage || 0
                    );
                }
            }

            // Chapter completion.
            var chapters = progress.chapter_completion || [];
            if (chapters.length) {
                html += '<h3 class="mdcat-students__tab-panel-title" style="margin-top:24px;">Chapter Completion</h3>';
                for (var c = 0; c < chapters.length; c++) {
                    var ch = chapters[c];
                    html += this.renderProgressBar(
                        ch.chapter_name + ' (' + ch.subject_name + ')',
                        ch.completion_percentage || 0
                    );
                }
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /**
         * Render a single progress bar.
         */
        renderProgressBar: function (label, percentage) {

            percentage = parseFloat(percentage) || 0;

            var html = '<div class="mdcat-students__progress-item">';
            html += '<div class="mdcat-students__progress-label">';
            html += '<span class="mdcat-students__progress-name">' + this.escapeHtml(label) + '</span>';
            html += '<span class="mdcat-students__progress-value">' + percentage + '%</span>';
            html += '</div>';
            html += '<div class="mdcat-students__progress-bar">';
            html += '<div class="mdcat-students__progress-fill" style="width:' + Math.min(100, percentage) + '%"></div>';
            html += '</div>';
            html += '</div>';

            return html;
        },

        /* ============================================================
           Analytics Tab
           ============================================================ */

        renderAnalyticsTab: function (container, data) {

            var analytics = data.analytics || {};
            var subjects  = analytics.subject_performance || [];
            var chapters  = analytics.chapter_performance || [];

            var html = '<div class="mdcat-students__tab-panel">';

            // Subject performance.
            html += '<h3 class="mdcat-students__tab-panel-title">Subject Performance</h3>';

            if (!subjects.length) {
                html += '<div class="mdcat-students__empty">No performance data available yet.</div>';
            } else {
                html += '<div class="mdcat-students__table-wrap"><table class="mdcat-students__table">';
                html += '<thead><tr><th>Subject</th><th>Accuracy</th><th>Correct</th><th>Wrong</th><th>Total</th></tr></thead>';
                html += '<tbody>';

                for (var i = 0; i < subjects.length; i++) {
                    var s = subjects[i];
                    var accuracyClass = this.getAccuracyClass(s.accuracy_percentage);
                    html += '<tr>';
                    html += '<td>' + this.escapeHtml(s.subject_title) + '</td>';
                    html += '<td><span class="' + accuracyClass + '">' + s.accuracy_percentage + '%</span></td>';
                    html += '<td>' + s.correct_answers + '</td>';
                    html += '<td>' + s.wrong_answers + '</td>';
                    html += '<td>' + s.total_questions + '</td>';
                    html += '</tr>';
                }

                html += '</tbody></table></div>';
            }

            // Chapter performance.
            if (chapters.length) {
                html += '<h3 class="mdcat-students__tab-panel-title" style="margin-top:24px;">Chapter Performance</h3>';
                html += '<div class="mdcat-students__table-wrap"><table class="mdcat-students__table">';
                html += '<thead><tr><th>Chapter</th><th>Subject</th><th>Accuracy</th><th>Correct</th><th>Wrong</th><th>Performance</th></tr></thead>';
                html += '<tbody>';

                for (var c = 0; c < chapters.length; c++) {
                    var ch = chapters[c];
                    var chAccuracyClass = this.getAccuracyClass(ch.accuracy_percentage);
                    var labelClass = this.getLabelClass(ch.performance_label);

                    html += '<tr>';
                    html += '<td>' + this.escapeHtml(ch.chapter_title) + '</td>';
                    html += '<td>' + this.escapeHtml(ch.subject_title) + '</td>';
                    html += '<td><span class="' + chAccuracyClass + '">' + ch.accuracy_percentage + '%</span></td>';
                    html += '<td>' + ch.correct_answers + '</td>';
                    html += '<td>' + ch.wrong_answers + '</td>';
                    html += '<td><span class="mdcat-students__perf-label ' + labelClass + '">' + this.escapeHtml(ch.performance_label) + '</span></td>';
                    html += '</tr>';
                }

                html += '</tbody></table></div>';
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /* ============================================================
           Activity Tab
           ============================================================ */

        renderActivityTab: function (container, data) {

            var self = this;
            var studentId = data.overview ? data.overview.user_id : 0;
            this.attemptsPage = 1;

            container.innerHTML = '<div class="mdcat-students__loading">' + (i18n.loading || 'Loading...') + '</div>';

            this.loadAttempts(studentId, 1, function (result) {
                self.renderAttemptsList(container, result, studentId);
            });
        },

        /**
         * Load paginated attempts for a student.
         */
        loadAttempts: function (studentId, page, callback) {

            this.ajaxRequest('mdcat_admin_get_student_attempts', {
                student_id: studentId,
                page: page,
            }, callback);
        },

        /**
         * Render the attempts list table.
         */
        renderAttemptsList: function (container, result, studentId) {

            var self       = this;
            var items      = result.items || [];
            var pagination = result.pagination || {};

            var html = '<div class="mdcat-students__tab-panel">';
            html += '<h3 class="mdcat-students__tab-panel-title">Quiz Attempt History</h3>';

            if (!items.length) {
                html += '<div class="mdcat-students__empty">' + this.escapeHtml(i18n.no_attempts || 'No quiz attempts recorded yet.') + '</div>';
                html += '</div>';
                container.innerHTML = html;
                return;
            }

            html += '<div class="mdcat-students__table-wrap"><table class="mdcat-students__table">';
            html += '<thead><tr>';
            html += '<th>Subject</th>';
            html += '<th>Chapter</th>';
            html += '<th>Collection</th>';
            html += '<th>Score</th>';
            html += '<th>Correct</th>';
            html += '<th>Wrong</th>';
            html += '<th>Total</th>';
            html += '<th>Date</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            for (var i = 0; i < items.length; i++) {
                var a = items[i];
                html += '<tr>';
                html += '<td>' + this.escapeHtml(a.subject_title) + '</td>';
                html += '<td>' + this.escapeHtml(a.chapter_title) + '</td>';
                html += '<td>' + this.escapeHtml(a.collection_title) + '</td>';
                html += '<td><strong>' + a.score + '</strong></td>';
                html += '<td>' + a.correct_answers + '</td>';
                html += '<td>' + a.wrong_answers + '</td>';
                html += '<td>' + a.total_questions + '</td>';
                html += '<td><span class="mdcat-students__date">' + this.formatDate(a.completed_at) + '</span></td>';
                html += '</tr>';
            }

            html += '</tbody></table></div>';
            html += '</div>';

            container.innerHTML = html;

            // Render attempt pagination.
            var paginationEl = document.getElementById('mdcat-student-attempts-pagination');
            if (paginationEl && pagination.total_pages > 1) {
                paginationEl.style.display = '';
                this.renderAttemptsPagination(paginationEl, pagination, studentId, container);
            }
        },

        /**
         * Render pagination for the attempts list.
         */
        renderAttemptsPagination: function (container, pagination, studentId, contentContainer) {

            var self       = this;
            var totalPages = pagination.total_pages || 0;
            var page       = pagination.page || 1;

            var html = '<div class="mdcat-students__pagination">';
            html += '<div class="mdcat-students__pagination-info">Page ' + page + ' of ' + totalPages + '</div>';
            html += '<div class="mdcat-students__pagination-buttons">';

            html += '<button class="mdcat-students__page-btn" data-attempts-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>&laquo; Prev</button>';

            var startPage = Math.max(1, page - 2);
            var endPage   = Math.min(totalPages, startPage + 4);
            startPage     = Math.max(1, endPage - 4);

            for (var p = startPage; p <= endPage; p++) {
                var activeClass = p === page ? ' mdcat-students__page-btn--active' : '';
                html += '<button class="mdcat-students__page-btn' + activeClass + '" data-attempts-page="' + p + '">' + p + '</button>';
            }

            html += '<button class="mdcat-students__page-btn" data-attempts-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>Next &raquo;</button>';

            html += '</div></div>';
            container.innerHTML = html;

            container.querySelectorAll('[data-attempts-page]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var targetPage = parseInt(btn.getAttribute('data-attempts-page'), 10);
                    if (targetPage >= 1 && targetPage <= totalPages) {
                        contentContainer.innerHTML = '<div class="mdcat-students__loading">' + (i18n.loading || 'Loading...') + '</div>';
                        self.loadAttempts(studentId, targetPage, function (result) {
                            self.renderAttemptsList(contentContainer, result, studentId);
                        });
                    }
                });
            });
        },

        /* ============================================================
           Enrollment Tab
           ============================================================ */

        renderEnrollmentTab: function (container, data) {

            var enrollment = data.enrollment;

            var html = '<div class="mdcat-students__tab-panel">';
            html += '<h3 class="mdcat-students__tab-panel-title">Enrollment Information</h3>';

            if (!enrollment) {
                html += '<div class="mdcat-students__empty">' + this.escapeHtml(i18n.no_enrollment || 'No enrollment record found for this student.') + '</div>';
                html += '</div>';
                container.innerHTML = html;
                return;
            }

            html += '<div class="mdcat-students__enrollment-grid">';
            html += this.renderEnrollmentDetail('Full Name', enrollment.full_name);
            html += this.renderEnrollmentDetail('Email', enrollment.email);
            html += this.renderEnrollmentDetail('Phone', enrollment.phone);
            html += this.renderEnrollmentDetail('City', enrollment.city);
            html += this.renderEnrollmentDetail('Enrollment Status', '<span class="mdcat-students__badge mdcat-students__badge--' + (enrollment.status === 'approved' ? 'active' : enrollment.status) + '">' + this.escapeHtml(enrollment.status) + '</span>');
            html += this.renderEnrollmentDetail('Submitted', this.formatDate(enrollment.created_at));

            if (enrollment.reviewed_at) {
                html += this.renderEnrollmentDetail('Reviewed', this.formatDate(enrollment.reviewed_at));
            }

            if (enrollment.admin_notes) {
                html += this.renderEnrollmentDetail('Admin Notes', enrollment.admin_notes);
            }

            html += '</div>';

            // Payment screenshot.
            if (enrollment.screenshot_url) {
                html += '<div class="mdcat-students__screenshot-wrap">';
                html += '<div class="mdcat-students__screenshot-title">Payment Screenshot</div>';
                html += '<img src="' + this.escapeHtml(enrollment.screenshot_url) + '" alt="Payment Screenshot" class="mdcat-students__screenshot-img" />';
                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;
        },

        /**
         * Render a single enrollment detail row.
         */
        renderEnrollmentDetail: function (label, value) {

            return '<div class="mdcat-students__enrollment-detail">' +
                '<div class="mdcat-students__enrollment-label">' + this.escapeHtml(label) + '</div>' +
                '<div class="mdcat-students__enrollment-value">' + (value || '—') + '</div>' +
                '</div>';
        },

        /* ============================================================
           Status Management
           ============================================================ */

        /**
         * Bind suspend/activate buttons.
         */
        bindStatusActions: function (container) {

            var self = this;

            container.querySelectorAll('[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {

                    var action    = btn.getAttribute('data-action');
                    var studentId = parseInt(btn.getAttribute('data-student-id'), 10);

                    if (action === 'suspend') {
                        self.suspendStudent(studentId, btn);
                    } else if (action === 'activate') {
                        self.activateStudent(studentId, btn);
                    }
                });
            });
        },

        /**
         * Suspend a student.
         */
        suspendStudent: function (studentId, btn) {

            var self = this;

            if (!confirm(i18n.suspend_confirm || 'Suspend this student?')) {
                return;
            }

            btn.disabled    = true;
            btn.textContent = i18n.suspending || 'Suspending...';

            this.ajaxRequest('mdcat_admin_suspend_student', {
                student_id: studentId,
            }, function (data) {
                alert(data.message || 'Student suspended.');
                // Reload the profile page.
                window.location.reload();
            }, function (error) {
                btn.disabled    = false;
                btn.textContent = 'Suspend Student';
                alert(error || (i18n.error_generic || 'Error'));
            });
        },

        /**
         * Activate a student.
         */
        activateStudent: function (studentId, btn) {

            var self = this;

            if (!confirm(i18n.activate_confirm || 'Activate this student?')) {
                return;
            }

            btn.disabled    = true;
            btn.textContent = i18n.activating || 'Activating...';

            this.ajaxRequest('mdcat_admin_activate_student', {
                student_id: studentId,
            }, function (data) {
                alert(data.message || 'Student activated.');
                window.location.reload();
            }, function (error) {
                btn.disabled    = false;
                btn.textContent = 'Activate Student';
                alert(error || (i18n.error_generic || 'Error'));
            });
        },

        /* ============================================================
           Utilities
           ============================================================ */

        /**
         * Reusable AJAX POST helper.
         *
         * @param {string}   action        WordPress AJAX action name.
         * @param {object}   params        Additional parameters to send.
         * @param {function} onSuccess     Success callback receiving response data.
         * @param {function} [onError]     Error callback receiving error message.
         */
        ajaxRequest: function (action, params, onSuccess, onError) {

            var formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', config.nonce || '');

            if (params) {
                for (var key in params) {
                    if (params.hasOwnProperty(key)) {
                        formData.append(key, params[key]);
                    }
                }
            }

            fetch(config.ajax_url || '', {
                method: 'POST',
                body: formData,
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success && data.data) {
                    if (onSuccess) onSuccess(data.data);
                } else {
                    var msg = data.data && data.data.message
                        ? data.data.message
                        : (i18n.error_generic || 'Error');
                    if (onError) {
                        onError(msg);
                    }
                }
            })
            .catch(function () {
                if (onError) {
                    onError(i18n.error_generic || 'Error');
                }
            });
        },

        /**
         * Get CSS class for accuracy value coloring.
         */
        getAccuracyClass: function (accuracy) {

            accuracy = parseFloat(accuracy) || 0;

            if (accuracy >= 80) {
                return 'mdcat-students__accuracy--strong';
            }

            if (accuracy >= 60) {
                return 'mdcat-students__accuracy--average';
            }

            return 'mdcat-students__accuracy--weak';
        },

        /**
         * Get CSS class for performance label badge.
         */
        getLabelClass: function (label) {

            var normalized = (label || '').toLowerCase();

            if (normalized === 'strong') {
                return 'mdcat-students__perf-label--strong';
            }

            if (normalized === 'average') {
                return 'mdcat-students__perf-label--average';
            }

            return 'mdcat-students__perf-label--weak';
        },

        /**
         * Format a date string for display.
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
            MDCATStudentManager.init();
        });
    } else {
        MDCATStudentManager.init();
    }

})();
