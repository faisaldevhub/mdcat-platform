<?php

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['mdcat_message']) ? sanitize_key(wp_unslash($_GET['mdcat_message'])) : '';

$messages = [
    'created'                => __('Question created successfully.', 'mdcat-platform'),
    'updated'                => __('Question updated successfully.', 'mdcat-platform'),
    'deleted'                => __('Question deleted successfully.', 'mdcat-platform'),
    'missing_required'       => __('Please complete all required question fields.', 'mdcat-platform'),
    'invalid_collection'     => __('Please select a valid collection.', 'mdcat-platform'),
    'invalid_correct_option' => __('Please select a valid correct option.', 'mdcat-platform'),
    'invalid_difficulty'     => __('Please select a valid difficulty.', 'mdcat-platform'),
    'invalid_marks'          => __('Marks must be numeric.', 'mdcat-platform'),
    'invalid_status'         => __('Please select a valid status.', 'mdcat-platform'),
];

$error_messages = ['missing_required', 'invalid_collection', 'invalid_correct_option', 'invalid_difficulty', 'invalid_marks', 'invalid_status'];
$notice_class   = in_array($message, $error_messages, true) ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
?>

<div class="wrap">
    <?php /* Page heading and create action. */ ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Questions', 'mdcat-platform'); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-questions&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'mdcat-platform'); ?>
    </a>

    <hr class="wp-header-end">

    <?php /* Admin status messages after create, update, or delete actions. */ ?>
    <?php if (isset($messages[$message])) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <?php /* Questions listing table with deep collection, chapter, and subject data. */ ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Question', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Collection Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Chapter Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Subject Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Correct Option', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Difficulty', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Created Date', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'mdcat-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($questions)) : ?>
                <?php foreach ($questions as $question_item) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'        => 'mdcat-questions',
                            'action'      => 'edit',
                            'question_id' => absint($question_item->id),
                        ],
                        admin_url('admin.php')
                    );

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'      => 'mdcat_delete_question',
                                'question_id' => absint($question_item->id),
                            ],
                            admin_url('admin-post.php')
                        ),
                        'mdcat_delete_question_' . absint($question_item->id)
                    );

                    $question_text = wp_trim_words(wp_strip_all_tags($question_item->question), 12);
                    ?>
                    <tr>
                        <td><?php echo esc_html($question_text); ?></td>
                        <td><?php echo esc_html($question_item->collection_title ? $question_item->collection_title : __('Collection unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html($question_item->chapter_name ? $question_item->chapter_name : __('Chapter unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html($question_item->subject_name ? $question_item->subject_name : __('Subject unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html(isset($correct_options[$question_item->correct_option]) ? $correct_options[$question_item->correct_option] : $question_item->correct_option); ?></td>
                        <td><?php echo esc_html(isset($difficulties[$question_item->difficulty]) ? $difficulties[$question_item->difficulty] : $question_item->difficulty); ?></td>
                        <td><?php echo esc_html(isset($statuses[$question_item->status]) ? $statuses[$question_item->status] : $question_item->status); ?></td>
                        <td><?php echo esc_html($question_item->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php esc_html_e('Edit', 'mdcat-platform'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this question?', 'mdcat-platform')); ?>');">
                                <?php esc_html_e('Delete', 'mdcat-platform'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="9"><?php esc_html_e('No questions found.', 'mdcat-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
