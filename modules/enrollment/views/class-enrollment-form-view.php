<?php

if (!defined('ABSPATH')) {
    exit;
}

class MDCAT_Platform_Enrollment_Form_View {

    /**
     * Render the public enrollment form.
     *
     * Called by the [mdcat_enrollment_form] shortcode. Shows
     * the form for guests, or a message for logged-in users.
     *
     * @return string HTML output.
     */
    public static function render() {

        // Logged-in users don't need to enroll.
        if (is_user_logged_in()) {
            return self::render_already_enrolled();
        }

        self::enqueue_assets();

        ob_start();
        ?>
        <div class="mdcat-enrollment" id="mdcat-enrollment-form-container">

            <div class="mdcat-enrollment__header">
                <div class="mdcat-enrollment__icon">📝</div>
                <h2 class="mdcat-enrollment__title">
                    <?php esc_html_e('Student Enrollment', 'mdcat-platform'); ?>
                </h2>
                <p class="mdcat-enrollment__subtitle">
                    <?php esc_html_e('Fill in your details and upload your payment screenshot to request access to the MDCAT Platform.', 'mdcat-platform'); ?>
                </p>
            </div>

            <form class="mdcat-enrollment__form" id="mdcat-enrollment-form" enctype="multipart/form-data">

                <div class="mdcat-enrollment__field">
                    <label for="mdcat-enrollment-name" class="mdcat-enrollment__label">
                        <?php esc_html_e('Full Name', 'mdcat-platform'); ?>
                        <span class="mdcat-enrollment__required">*</span>
                    </label>
                    <input
                        type="text"
                        id="mdcat-enrollment-name"
                        name="full_name"
                        class="mdcat-enrollment__input"
                        placeholder="<?php esc_attr_e('Enter your full name', 'mdcat-platform'); ?>"
                        required
                    >
                </div>

                <div class="mdcat-enrollment__field">
                    <label for="mdcat-enrollment-email" class="mdcat-enrollment__label">
                        <?php esc_html_e('Email Address', 'mdcat-platform'); ?>
                        <span class="mdcat-enrollment__required">*</span>
                    </label>
                    <input
                        type="email"
                        id="mdcat-enrollment-email"
                        name="email"
                        class="mdcat-enrollment__input"
                        placeholder="<?php esc_attr_e('Enter your email address', 'mdcat-platform'); ?>"
                        required
                    >
                    <p class="mdcat-enrollment__hint">
                        <?php esc_html_e('This email will be used as your login username.', 'mdcat-platform'); ?>
                    </p>
                </div>

                <div class="mdcat-enrollment__field">
                    <label for="mdcat-enrollment-phone" class="mdcat-enrollment__label">
                        <?php esc_html_e('Phone Number', 'mdcat-platform'); ?>
                        <span class="mdcat-enrollment__required">*</span>
                    </label>
                    <input
                        type="tel"
                        id="mdcat-enrollment-phone"
                        name="phone"
                        class="mdcat-enrollment__input"
                        placeholder="<?php esc_attr_e('03XX-XXXXXXX', 'mdcat-platform'); ?>"
                        required
                    >
                </div>

                <div class="mdcat-enrollment__field">
                    <label for="mdcat-enrollment-city" class="mdcat-enrollment__label">
                        <?php esc_html_e('City', 'mdcat-platform'); ?>
                        <span class="mdcat-enrollment__required">*</span>
                    </label>
                    <input
                        type="text"
                        id="mdcat-enrollment-city"
                        name="city"
                        class="mdcat-enrollment__input"
                        placeholder="<?php esc_attr_e('Enter your city', 'mdcat-platform'); ?>"
                        required
                    >
                </div>

                <div class="mdcat-enrollment__field">
                    <label for="mdcat-enrollment-screenshot" class="mdcat-enrollment__label">
                        <?php esc_html_e('Payment Screenshot', 'mdcat-platform'); ?>
                        <span class="mdcat-enrollment__required">*</span>
                    </label>
                    <div class="mdcat-enrollment__upload-zone" id="mdcat-enrollment-upload-zone">
                        <div class="mdcat-enrollment__upload-icon">📷</div>
                        <p class="mdcat-enrollment__upload-text">
                            <?php esc_html_e('Click to upload or drag and drop', 'mdcat-platform'); ?>
                        </p>
                        <p class="mdcat-enrollment__upload-hint">
                            <?php esc_html_e('JPG, PNG, or WebP (max 5MB)', 'mdcat-platform'); ?>
                        </p>
                        <input
                            type="file"
                            id="mdcat-enrollment-screenshot"
                            name="payment_screenshot"
                            class="mdcat-enrollment__file-input"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >
                    </div>
                    <div class="mdcat-enrollment__preview" id="mdcat-enrollment-preview" style="display:none;">
                        <img id="mdcat-enrollment-preview-img" src="" alt="">
                        <button type="button" class="mdcat-enrollment__preview-remove" id="mdcat-enrollment-remove">
                            <?php esc_html_e('Remove', 'mdcat-platform'); ?>
                        </button>
                    </div>
                </div>

                <!-- Honeypot field for spam bots -->
                <div class="mdcat-enrollment__hp" aria-hidden="true">
                    <input type="text" name="website_url" tabindex="-1" autocomplete="off">
                </div>

                <div class="mdcat-enrollment__message" id="mdcat-enrollment-message" role="alert"></div>

                <button type="submit" class="mdcat-enrollment__submit" id="mdcat-enrollment-submit">
                    <?php esc_html_e('Submit Enrollment Request', 'mdcat-platform'); ?>
                </button>

            </form>

            <div class="mdcat-enrollment__success" id="mdcat-enrollment-success" style="display:none;">
                <div class="mdcat-enrollment__success-icon">✅</div>
                <h3 class="mdcat-enrollment__success-title">
                    <?php esc_html_e('Request Submitted!', 'mdcat-platform'); ?>
                </h3>
                <p class="mdcat-enrollment__success-message">
                    <?php esc_html_e('Your enrollment request has been submitted successfully. You will receive an email with your login credentials once your request is approved.', 'mdcat-platform'); ?>
                </p>
            </div>

        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render the message shown to already logged-in users.
     *
     * @return string HTML output.
     */
    private static function render_already_enrolled() {

        ob_start();
        ?>
        <div class="mdcat-enrollment mdcat-enrollment--enrolled">
            <div class="mdcat-enrollment__icon">✅</div>
            <h3 class="mdcat-enrollment__title">
                <?php esc_html_e('You Already Have an Account', 'mdcat-platform'); ?>
            </h3>
            <p class="mdcat-enrollment__message">
                <?php esc_html_e('You are already logged in. No need to enroll again.', 'mdcat-platform'); ?>
            </p>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Enqueue frontend enrollment form assets.
     */
    private static function enqueue_assets() {

        wp_enqueue_style(
            'mdcat-enrollment',
            MDCAT_PLATFORM_URL . 'assets/css/enrollment.css',
            [],
            MDCAT_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'mdcat-enrollment',
            MDCAT_PLATFORM_URL . 'assets/js/enrollment.js',
            [],
            MDCAT_PLATFORM_VERSION,
            true
        );

        wp_localize_script(
            'mdcat-enrollment',
            'MDCATEnrollment',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('mdcat_enrollment_nonce'),
                'i18n'     => [
                    'submitting'     => __('Submitting...', 'mdcat-platform'),
                    'submit'         => __('Submit Enrollment Request', 'mdcat-platform'),
                    'file_too_large' => __('File size exceeds 5MB limit.', 'mdcat-platform'),
                    'invalid_type'   => __('Only JPG, PNG, and WebP images are accepted.', 'mdcat-platform'),
                ],
            ]
        );
    }
}
