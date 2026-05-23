<?php

if (!defined('ABSPATH')) {
    exit;
}

$message = isset($_GET['mdcat_message']) ? sanitize_key(wp_unslash($_GET['mdcat_message'])) : '';

$messages = [
    'created'         => __('Collection created successfully.', 'mdcat-platform'),
    'updated'         => __('Collection updated successfully.', 'mdcat-platform'),
    'deleted'         => __('Collection deleted successfully.', 'mdcat-platform'),
    'missing_title'   => __('Collection title is required.', 'mdcat-platform'),
    'invalid_chapter' => __('Please select a valid chapter.', 'mdcat-platform'),
    'invalid_type'    => __('Please select a valid collection type.', 'mdcat-platform'),
    'invalid_status'  => __('Please select a valid status.', 'mdcat-platform'),
];

$error_messages = ['missing_title', 'invalid_chapter', 'invalid_type', 'invalid_status'];
$notice_class   = in_array($message, $error_messages, true) ? 'notice notice-error is-dismissible' : 'notice notice-success is-dismissible';
?>

<div class="wrap">
    <?php /* Page heading and create action. */ ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Collections', 'mdcat-platform'); ?></h1>

    <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-collections&action=add')); ?>" class="page-title-action">
        <?php esc_html_e('Add New', 'mdcat-platform'); ?>
    </a>

    <hr class="wp-header-end">

    <?php /* Admin status messages after create, update, or delete actions. */ ?>
    <?php if (isset($messages[$message])) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>">
            <p><?php echo esc_html($messages[$message]); ?></p>
        </div>
    <?php endif; ?>

    <?php /* Collections listing table with nested chapter and subject data. */ ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Collection Title', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Type', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Chapter Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Subject Name', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Sort Order', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Status', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Created Date', 'mdcat-platform'); ?></th>
                <th scope="col"><?php esc_html_e('Actions', 'mdcat-platform'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($collections)) : ?>
                <?php foreach ($collections as $collection) : ?>
                    <?php
                    $edit_url = add_query_arg(
                        [
                            'page'          => 'mdcat-collections',
                            'action'        => 'edit',
                            'collection_id' => absint($collection->id),
                        ],
                        admin_url('admin.php')
                    );

                    $delete_url = wp_nonce_url(
                        add_query_arg(
                            [
                                'action'        => 'mdcat_delete_collection',
                                'collection_id' => absint($collection->id),
                            ],
                            admin_url('admin-post.php')
                        ),
                        'mdcat_delete_collection_' . absint($collection->id)
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html($collection->title); ?></td>
                        <td><?php echo esc_html(isset($types[$collection->type]) ? $types[$collection->type] : $collection->type); ?></td>
                        <td><?php echo esc_html($collection->chapter_name ? $collection->chapter_name : __('Chapter unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html($collection->subject_name ? $collection->subject_name : __('Subject unavailable', 'mdcat-platform')); ?></td>
                        <td><?php echo esc_html(absint($collection->sort_order)); ?></td>
                        <td><?php echo esc_html(isset($statuses[$collection->status]) ? $statuses[$collection->status] : $collection->status); ?></td>
                        <td><?php echo esc_html($collection->created_at); ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit_url); ?>">
                                <?php esc_html_e('Edit', 'mdcat-platform'); ?>
                            </a>
                            |
                            <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this collection?', 'mdcat-platform')); ?>');">
                                <?php esc_html_e('Delete', 'mdcat-platform'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="8"><?php esc_html_e('No collections found.', 'mdcat-platform'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
