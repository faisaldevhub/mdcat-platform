/**
 * MDCAT Platform — Enrollment Form Controller
 *
 * Handles the public enrollment form: field validation, screenshot
 * preview, drag-and-drop upload, honeypot check, and AJAX submission.
 */
(function () {

    'use strict';

    const form      = document.getElementById('mdcat-enrollment-form');
    const container = document.getElementById('mdcat-enrollment-form-container');

    if (!form || !container) return;

    const submitBtn       = document.getElementById('mdcat-enrollment-submit');
    const messageEl       = document.getElementById('mdcat-enrollment-message');
    const successEl       = document.getElementById('mdcat-enrollment-success');
    const fileInput       = document.getElementById('mdcat-enrollment-screenshot');
    const uploadZone      = document.getElementById('mdcat-enrollment-upload-zone');
    const previewEl       = document.getElementById('mdcat-enrollment-preview');
    const previewImg      = document.getElementById('mdcat-enrollment-preview-img');
    const removeBtn       = document.getElementById('mdcat-enrollment-remove');
    const config          = window.MDCATEnrollment || {};
    const maxFileSize     = 5 * 1024 * 1024;
    const allowedTypes    = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Screenshot preview on file selection.
     */
    fileInput.addEventListener('change', function () {
        handleFilePreview(this.files[0]);
    });

    /**
     * Drag-and-drop support on the upload zone.
     */
    uploadZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.classList.add('mdcat-enrollment__upload-zone--dragover');
    });

    uploadZone.addEventListener('dragleave', function () {
        this.classList.remove('mdcat-enrollment__upload-zone--dragover');
    });

    uploadZone.addEventListener('drop', function (e) {
        e.preventDefault();
        this.classList.remove('mdcat-enrollment__upload-zone--dragover');

        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            handleFilePreview(e.dataTransfer.files[0]);
        }
    });

    /**
     * Remove button clears the preview and file input.
     */
    removeBtn.addEventListener('click', function () {
        fileInput.value = '';
        previewEl.style.display = 'none';
        uploadZone.style.display = '';
    });

    /**
     * Form submission via AJAX.
     */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        clearMessage();

        // Honeypot check.
        var honeypot = form.querySelector('[name="website_url"]');
        if (honeypot && honeypot.value) {
            return;
        }

        // Client-side validation.
        var name  = form.querySelector('[name="full_name"]').value.trim();
        var email = form.querySelector('[name="email"]').value.trim();
        var phone = form.querySelector('[name="phone"]').value.trim();
        var city  = form.querySelector('[name="city"]').value.trim();
        var file  = fileInput.files[0];

        var errors = [];

        if (!name)  errors.push('Full name is required.');
        if (!email) errors.push('Email address is required.');
        if (!phone) errors.push('Phone number is required.');
        if (!city)  errors.push('City is required.');

        if (!file) {
            errors.push('Payment screenshot is required.');
        } else {
            if (!allowedTypes.includes(file.type)) {
                errors.push(config.i18n ? config.i18n.invalid_type : 'Only JPG, PNG, and WebP images are accepted.');
            }
            if (file.size > maxFileSize) {
                errors.push(config.i18n ? config.i18n.file_too_large : 'File size exceeds 5MB limit.');
            }
        }

        if (errors.length) {
            showMessage(errors.join(' '), 'error');
            return;
        }

        // Build FormData for multipart submission.
        var formData = new FormData();
        formData.append('action', 'mdcat_enrollment_submit');
        formData.append('nonce', config.nonce || '');
        formData.append('full_name', name);
        formData.append('email', email);
        formData.append('phone', phone);
        formData.append('city', city);
        formData.append('payment_screenshot', file);

        setLoading(true);

        fetch(config.ajax_url || '', {
            method: 'POST',
            body: formData,
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {

            setLoading(false);

            if (data.success) {
                form.style.display = 'none';
                successEl.style.display = '';
            } else {
                var msg = '';

                if (data.data && data.data.errors) {
                    msg = data.data.errors.join(' ');
                } else if (data.data && data.data.message) {
                    msg = data.data.message;
                } else {
                    msg = 'Something went wrong. Please try again.';
                }

                showMessage(msg, 'error');
            }
        })
        .catch(function () {

            setLoading(false);
            showMessage('Network error. Please check your connection and try again.', 'error');
        });
    });

    /**
     * Show a file preview thumbnail.
     */
    function handleFilePreview(file) {

        if (!file) return;

        if (!allowedTypes.includes(file.type)) {
            showMessage(config.i18n ? config.i18n.invalid_type : 'Invalid file type.', 'error');
            return;
        }

        if (file.size > maxFileSize) {
            showMessage(config.i18n ? config.i18n.file_too_large : 'File too large.', 'error');
            return;
        }

        var reader = new FileReader();

        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewEl.style.display = '';
            uploadZone.style.display = 'none';
            clearMessage();
        };

        reader.readAsDataURL(file);
    }

    /**
     * Show a validation/error message.
     */
    function showMessage(text, type) {

        messageEl.textContent = text;
        messageEl.className   = 'mdcat-enrollment__message mdcat-enrollment__message--' + type;
    }

    function clearMessage() {

        messageEl.textContent = '';
        messageEl.className   = 'mdcat-enrollment__message';
    }

    /**
     * Toggle loading state on the submit button.
     */
    function setLoading(loading) {

        submitBtn.disabled    = loading;
        submitBtn.textContent = loading
            ? (config.i18n ? config.i18n.submitting : 'Submitting...')
            : (config.i18n ? config.i18n.submit : 'Submit Enrollment Request');
    }

})();
