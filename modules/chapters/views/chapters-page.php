<?php

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['mdcat_message']) ? sanitize_key(wp_unslash($_GET['mdcat_message'])) : '';

$messages = [
    'created'         => __('Chapter created successfully.', 'mdcat-platform'),
    'updated'         => __('Chapter updated successfully.', 'mdcat-platform'),
    'deleted'         => __('Chapter deleted successfully.', 'mdcat-platform'),
    'missing_name'    => __('Chapter name is required.', 'mdcat-platform'),
    'invalid_subject' => __('Please select a valid subject.', 'mdcat-platform'),
];

$notice_class = in_array($message, ['missing_name', 'invalid_subject'], true) ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
?>

<div class="wrap">
    <?php /* Page heading and create action. */ ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Chapters', 'mdcat-platform'); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-chapters&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'mdcat-platform'); ?>
    </a>

    <hr class="wp-header-end">

    <?php /* Admin status messages after create, update, or delete actions. */ ?>
    <?php if (isset($messages[$message])) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <?php /* Chapters listing table with relational subject data. */ ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Chapter Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Subject Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Created Date', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'mdcat-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($chapters)) : ?>
                <?php foreach ($chapters as $chapter) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'       => 'mdcat-chapters',
                            'action'     => 'edit',
                            'chapter_id' => absint($chapter->id),
                        ],
                        admin_url('admin.php')
                    );

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'     => 'mdcat_delete_chapter',
                                'chapter_id' => absint($chapter->id),
                            ],
                            admin_url('admin-post.php')
                        ),
                        'mdcat_delete_chapter_' . absint($chapter->id)
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html($chapter->name); ?></td>
                        <td><?php echo esc_html($chapter->subject_name ? $chapter->subject_name : __('Subject unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html($chapter->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php esc_html_e('Edit', 'mdcat-platform'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this chapter?', 'mdcat-platform')); ?>');">
                                <?php esc_html_e('Delete', 'mdcat-platform'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No chapters found.', 'mdcat-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
