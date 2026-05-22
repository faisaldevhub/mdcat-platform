<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_edit  = !empty($subject);
$form_url = admin_url('admin-post.php');
$is_missing_edit = isset($_GET['action']) && 'edit' === sanitize_key(wp_unslash($_GET['action'])) && !$is_edit;
?>

<div class="wrap">
    <?php /* Add/edit page heading. */ ?>
    <h1>
        <?php echo $is_edit ? esc_html__('Edit Subject', 'mdcat-platform') : esc_html__('Add New Subject', 'mdcat-platform'); ?>
    </h1>

    <?php if ($is_missing_edit) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Subject not found.', 'mdcat-platform'); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-subjects')); ?>">
                <?php esc_html_e('Back to Subjects', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php else : ?>

    <?php /* Subject create/update form. */ ?>
    <form method="post" action="<?php echo esc_url($form_url); ?>">
        <?php wp_nonce_field('mdcat_save_subject', 'mdcat_subject_nonce'); ?>

        <input type="hidden" name="action" value="mdcat_save_subject">
        <input type="hidden" name="subject_id" value="<?php echo esc_attr($is_edit ? absint($subject->id) : 0); ?>">

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="mdcat-subject-name"><?php esc_html_e('Name', 'mdcat-platform'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="mdcat-subject-name"
                            name="name"
                            class="regular-text"
                            value="<?php echo esc_attr($is_edit ? $subject->name : ''); ?>"
                            required
                        >
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mdcat-subject-slug"><?php esc_html_e('Slug', 'mdcat-platform'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="mdcat-subject-slug"
                            name="slug"
                            class="regular-text"
                            value="<?php echo esc_attr($is_edit ? $subject->slug : ''); ?>"
                        >
                        <p class="description">
                            <?php esc_html_e('Leave empty to generate from the subject name.', 'mdcat-platform'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button($is_edit ? __('Update Subject', 'mdcat-platform') : __('Create Subject', 'mdcat-platform')); ?>
    </form>

    <p>
        <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-subjects')); ?>">
            <?php esc_html_e('Back to Subjects', 'mdcat-platform'); ?>
        </a>
    </p>
    <?php endif; ?>
</div>
