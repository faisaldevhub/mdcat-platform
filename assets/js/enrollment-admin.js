/**
 * MDCAT Platform — Enrollment Admin Controller
 *
 * Manages the admin enrollment requests page: loading requests,
 * filtering by status, viewing details with screenshot, approving,
 * rejecting with reason, and deleting requests.
 */
(function () {

    'use strict';

    var config = window.MDCATEnrollmentAdmin || {};
    var i18n   = config.i18n || {};

    var listEl              = document.getElementById('mdcat-enrollment-list');
    var tabsEl              = document.getElementById('mdcat-enrollment-tabs');
    var modal               = document.getElementById('mdcat-enrollment-modal');
    var modalOverlay        = document.getElementById('mdcat-enrollment-modal-overlay');
    var modalClose          = document.getElementById('mdcat-enrollment-modal-close');
    var modalBody           = document.getElementById('mdcat-enrollment-modal-body');
    var modalActions        = document.getElementById('mdcat-enrollment-modal-actions');
    var rejectModal         = document.getElementById('mdcat-enrollment-reject-modal');
    var rejectOverlay       = document.getElementById('mdcat-enrollment-reject-overlay');
    var rejectClose         = document.getElementById('mdcat-enrollment-reject-close');
    var rejectCancel        = document.getElementById('mdcat-enrollment-reject-cancel');
    var rejectConfirm       = document.getElementById('mdcat-enrollment-reject-confirm');
    var rejectReasonInput   = document.getElementById('mdcat-enrollment-reject-reason');

    if (!listEl) return;

    var currentStatus = 'all';
    var currentRejectId = null;

    /**
     * Initialize: load requests and bind events.
     */
    loadRequests();
    bindTabs();
    bindModalClose();
    bindRejectModal();

    /**
     * Load enrollment requests from the server.
     */
    function loadRequests() {

        listEl.innerHTML = '<div class="mdcat-enrollment-admin__loading">Loading...</div>';

        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_get_requests');
        formData.append('nonce', config.nonce || '');
        formData.append('status', currentStatus);

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                renderRequests(data.data.requests);
                updateCounts(data.data.counts);
            } else {
                listEl.innerHTML = '<div class="mdcat-enrollment-admin__empty">' +
                    (i18n.error_generic || 'Error loading requests.') + '</div>';
            }
        })
        .catch(function () {
            listEl.innerHTML = '<div class="mdcat-enrollment-admin__empty">' +
                (i18n.error_generic || 'Error loading requests.') + '</div>';
        });
    }

    /**
     * Render the requests list as a table.
     */
    function renderRequests(requests) {

        if (!requests || !requests.length) {
            listEl.innerHTML = '<div class="mdcat-enrollment-admin__empty">' +
                (i18n.no_requests || 'No enrollment requests found.') + '</div>';
            return;
        }

        var html = '<table class="mdcat-enrollment-admin__table">';
        html += '<thead><tr>';
        html += '<th>Name</th>';
        html += '<th>Email</th>';
        html += '<th>Phone</th>';
        html += '<th>City</th>';
        html += '<th>Status</th>';
        html += '<th>Date</th>';
        html += '<th>Actions</th>';
        html += '</tr></thead><tbody>';

        requests.forEach(function (req) {

            html += '<tr>';
            html += '<td>' + req.full_name + '</td>';
            html += '<td>' + req.email + '</td>';
            html += '<td>' + req.phone + '</td>';
            html += '<td>' + req.city + '</td>';
            html += '<td><span class="mdcat-enrollment-admin__badge mdcat-enrollment-admin__badge--' + req.status + '">' + req.status + '</span></td>';
            html += '<td>' + formatDate(req.created_at) + '</td>';
            html += '<td>';
            html += '<button class="mdcat-enrollment-admin__action-btn mdcat-enrollment-admin__action-btn--view" data-action="view" data-id="' + req.id + '">View</button>';

            if (req.status === 'pending') {
                html += '<button class="mdcat-enrollment-admin__action-btn mdcat-enrollment-admin__action-btn--approve" data-action="approve" data-id="' + req.id + '">Approve</button>';
                html += '<button class="mdcat-enrollment-admin__action-btn mdcat-enrollment-admin__action-btn--reject" data-action="reject" data-id="' + req.id + '">Reject</button>';
            }

            html += '<button class="mdcat-enrollment-admin__action-btn mdcat-enrollment-admin__action-btn--delete" data-action="delete" data-id="' + req.id + '">Delete</button>';
            html += '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        listEl.innerHTML = html;

        // Bind action buttons.
        listEl.querySelectorAll('[data-action]').forEach(function (btn) {
            btn.addEventListener('click', handleAction);
        });
    }

    /**
     * Handle action button clicks (view, approve, reject, delete).
     */
    function handleAction(e) {

        var btn    = e.currentTarget;
        var action = btn.getAttribute('data-action');
        var id     = parseInt(btn.getAttribute('data-id'), 10);

        switch (action) {
            case 'view':
                viewRequest(id);
                break;
            case 'approve':
                approveRequest(id);
                break;
            case 'reject':
                openRejectModal(id);
                break;
            case 'delete':
                deleteRequest(id);
                break;
        }
    }

    /**
     * View request details in the modal.
     */
    function viewRequest(id) {

        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_get_requests');
        formData.append('nonce', config.nonce || '');
        formData.append('status', 'all');

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data.success) return;

            var request = null;
            data.data.requests.forEach(function (r) {
                if (r.id === id) request = r;
            });

            if (!request) return;

            var html = '';
            html += renderDetail('Full Name', request.full_name);
            html += renderDetail('Email', request.email);
            html += renderDetail('Phone', request.phone);
            html += renderDetail('City', request.city);
            html += renderDetail('Status', '<span class="mdcat-enrollment-admin__badge mdcat-enrollment-admin__badge--' + request.status + '">' + request.status + '</span>');
            html += renderDetail('Submitted', formatDate(request.created_at));

            if (request.reviewed_at) {
                html += renderDetail('Reviewed', formatDate(request.reviewed_at));
            }

            if (request.admin_notes) {
                html += renderDetail('Admin Notes', request.admin_notes);
            }

            if (request.wp_user_id) {
                html += renderDetail('WordPress User ID', request.wp_user_id);
            }

            if (request.screenshot_url) {
                html += '<div class="mdcat-enrollment-admin__screenshot">';
                html += '<div class="mdcat-enrollment-admin__detail-label">Payment Screenshot</div>';
                html += '<img src="' + request.screenshot_url + '" alt="Payment Screenshot">';
                html += '</div>';
            }

            modalBody.innerHTML = html;

            // Actions.
            var actionsHtml = '';

            if (request.status === 'pending') {
                actionsHtml += '<button class="button button-primary" onclick="window._mdcatApprove(' + request.id + ')">Approve</button> ';
                actionsHtml += '<button class="button mdcat-enrollment-admin__btn--reject" onclick="window._mdcatRejectFromModal(' + request.id + ')">Reject</button>';
            }

            modalActions.innerHTML = actionsHtml;
            modal.style.display = '';
        });
    }

    // Expose approve/reject from modal to global scope.
    window._mdcatApprove = function (id) {
        closeModal();
        approveRequest(id);
    };

    window._mdcatRejectFromModal = function (id) {
        closeModal();
        openRejectModal(id);
    };

    /**
     * Approve an enrollment request.
     */
    function approveRequest(id) {

        if (!confirm(i18n.approve_confirm || 'Approve this enrollment?')) return;

        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_approve');
        formData.append('nonce', config.nonce || '');
        formData.append('request_id', id);

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                var msg = data.data.message;

                // If email failed, show password to admin.
                if (data.data.password) {
                    msg += '\n\nGenerated Password: ' + data.data.password;
                }

                alert(msg);
                loadRequests();
            } else {
                alert(data.data ? data.data.message : (i18n.error_generic || 'Error'));
            }
        })
        .catch(function () {
            alert(i18n.error_generic || 'Error');
        });
    }

    /**
     * Open the rejection reason modal.
     */
    function openRejectModal(id) {

        currentRejectId = id;
        rejectReasonInput.value = '';
        rejectModal.style.display = '';
    }

    /**
     * Confirm rejection with optional reason.
     */
    rejectConfirm.addEventListener('click', function () {

        if (!currentRejectId) return;

        var reason   = rejectReasonInput.value.trim();
        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_reject');
        formData.append('nonce', config.nonce || '');
        formData.append('request_id', currentRejectId);
        formData.append('reason', reason);

        rejectConfirm.disabled    = true;
        rejectConfirm.textContent = i18n.rejecting || 'Rejecting...';

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            rejectConfirm.disabled    = false;
            rejectConfirm.textContent = 'Confirm Rejection';

            if (data.success) {
                closeRejectModal();
                loadRequests();
            } else {
                alert(data.data ? data.data.message : (i18n.error_generic || 'Error'));
            }
        })
        .catch(function () {
            rejectConfirm.disabled    = false;
            rejectConfirm.textContent = 'Confirm Rejection';
            alert(i18n.error_generic || 'Error');
        });
    });

    /**
     * Delete an enrollment request.
     */
    function deleteRequest(id) {

        if (!confirm(i18n.delete_confirm || 'Delete this request?')) return;

        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_delete');
        formData.append('nonce', config.nonce || '');
        formData.append('request_id', id);

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                loadRequests();
            } else {
                alert(data.data ? data.data.message : (i18n.error_generic || 'Error'));
            }
        })
        .catch(function () {
            alert(i18n.error_generic || 'Error');
        });
    }

    /**
     * Bind status filter tab clicks.
     */
    function bindTabs() {

        if (!tabsEl) return;

        tabsEl.querySelectorAll('.mdcat-enrollment-admin__tab').forEach(function (tab) {
            tab.addEventListener('click', function () {

                tabsEl.querySelectorAll('.mdcat-enrollment-admin__tab').forEach(function (t) {
                    t.classList.remove('mdcat-enrollment-admin__tab--active');
                });

                this.classList.add('mdcat-enrollment-admin__tab--active');
                currentStatus = this.getAttribute('data-status');
                loadRequests();
            });
        });
    }

    /**
     * Update the tab count badges.
     */
    function updateCounts(counts) {

        setText('mdcat-count-total', counts.total || 0);
        setText('mdcat-count-pending', counts.pending || 0);
        setText('mdcat-count-approved', counts.approved || 0);
        setText('mdcat-count-rejected', counts.rejected || 0);
    }

    /**
     * Bind modal close buttons.
     */
    function bindModalClose() {

        if (modalClose)   modalClose.addEventListener('click', closeModal);
        if (modalOverlay) modalOverlay.addEventListener('click', closeModal);
    }

    function bindRejectModal() {

        if (rejectClose)   rejectClose.addEventListener('click', closeRejectModal);
        if (rejectOverlay) rejectOverlay.addEventListener('click', closeRejectModal);
        if (rejectCancel)  rejectCancel.addEventListener('click', closeRejectModal);
    }

    function closeModal() {
        if (modal) modal.style.display = 'none';
    }

    function closeRejectModal() {
        if (rejectModal) rejectModal.style.display = 'none';
        currentRejectId = null;
    }

    /**
     * Render a detail label/value pair for the modal.
     */
    function renderDetail(label, value) {

        return '<div class="mdcat-enrollment-admin__detail">' +
            '<div class="mdcat-enrollment-admin__detail-label">' + label + '</div>' +
            '<div class="mdcat-enrollment-admin__detail-value">' + (value || '—') + '</div>' +
            '</div>';
    }

    /**
     * Format a datetime string for display.
     */
    function formatDate(dateStr) {

        if (!dateStr) return '—';

        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;

        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    /**
     * Set text content of an element by ID.
     */
    function setText(id, text) {

        var el = document.getElementById(id);
        if (el) el.textContent = text;
    }

})();
