<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_edit         = !empty($chapter);
$form_url        = admin_url('admin-post.php');
$is_missing_edit = isset($_GET['action']) && 'edit' === sanitize_key(wp_unslash($_GET['action'])) && !$is_edit;
?>

<div class="wrap">
    <?php /* Add/edit page heading. */ ?>
    <h1>
        <?php echo $is_edit ? esc_html__('Edit Chapter', 'mdcat-platform') : esc_html__('Add New Chapter', 'mdcat-platform'); ?>
    </h1>

    <?php if ($is_missing_edit) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Chapter not found.', 'mdcat-platform'); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-chapters')); ?>">
                <?php esc_html_e('Back to Chapters', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php else : ?>

        <?php if (empty($subjects)) : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Please create a subject before adding chapters.', 'mdcat-platform'); ?></p>
            </div>
        <?php endif; ?>

        <?php /* Chapter create/update form with relational subject dropdown. */ ?>
        <form method="post" action="<?php echo esc_url($form_url); ?>">
            <?php wp_nonce_field('mdcat_save_chapter', 'mdcat_chapter_nonce'); ?>

            <input type="hidden" name="action" value="mdcat_save_chapter">
            <input type="hidden" name="chapter_id" value="<?php echo esc_attr($is_edit ? absint($chapter->id) : 0); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-chapter-subject"><?php esc_html_e('Subject', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-chapter-subject" name="subject_id" required>
                                <option value=""><?php esc_html_e('Select Subject', 'mdcat-platform'); ?></option>
                                <?php foreach ($subjects as $subject) : ?>
                                    <option value="<?php echo esc_attr(absint($subject->id)); ?>" <?php selected($is_edit ? absint($chapter->subject_id) : 0, absint($subject->id)); ?>>
                                        <?php echo esc_html($subject->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-chapter-name"><?php esc_html_e('Chapter Name', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="mdcat-chapter-name"
                                name="name"
                                class="regular-text"
                                value="<?php echo esc_attr($is_edit ? $chapter->name : ''); ?>"
                                required
                            >
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button($is_edit ? __('Update Chapter', 'mdcat-platform') : __('Create Chapter', 'mdcat-platform'), 'primary', 'submit', true, empty($subjects) ? ['disabled' => 'disabled'] : []); ?>
        </form>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-chapters')); ?>">
                <?php esc_html_e('Back to Chapters', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
