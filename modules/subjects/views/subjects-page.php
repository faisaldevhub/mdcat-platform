<?php

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['mdcat_message']) ? sanitize_key(wp_unslash($_GET['mdcat_message'])) : '';

$messages = [
    'created'      => __('Subject created successfully.', 'mdcat-platform'),
    'updated'      => __('Subject updated successfully.', 'mdcat-platform'),
    'deleted'      => __('Subject deleted successfully.', 'mdcat-platform'),
    'missing_name' => __('Subject name is required.', 'mdcat-platform'),
];

$notice_class = 'missing_name' === $message ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
?>

<div class="wrap">
    <?php /* Page heading and create action. */ ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Subjects', 'mdcat-platform'); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-subjects&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'mdcat-platform'); ?>
    </a>

    <hr class="wp-header-end">

    <?php /* Admin status messages after create, update, or delete actions. */ ?>
    <?php if (isset($messages[$message])) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <?php /* Subjects listing table. */ ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('ID', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Slug', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Created At', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'mdcat-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($subjects)) : ?>
                <?php foreach ($subjects as $subject) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'       => 'mdcat-subjects',
                            'action'     => 'edit',
                            'subject_id' => absint($subject->id),
                        ],
                        admin_url('admin.php')
                    );

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'     => 'mdcat_delete_subject',
                                'subject_id' => absint($subject->id),
                            ],
                            admin_url('admin-post.php')
                        ),
                        'mdcat_delete_subject_' . absint($subject->id)
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html(absint($subject->id)); ?></td>
                        <td><?php echo esc_html($subject->name); ?></td>
                        <td><?php echo esc_html($subject->slug); ?></td>
                        <td><?php echo esc_html($subject->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php esc_html_e('Edit', 'mdcat-platform'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this subject?', 'mdcat-platform')); ?>');">
                                <?php esc_html_e('Delete', 'mdcat-platform'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e('No subjects found.', 'mdcat-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
