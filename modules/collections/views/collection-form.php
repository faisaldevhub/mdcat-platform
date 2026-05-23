<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_edit         = !empty($collection);
$form_url        = admin_url('admin-post.php');
$is_missing_edit = isset($_GET['action']) && 'edit' === sanitize_key(wp_unslash($_GET['action'])) && !$is_edit;
?>

<div class="wrap">
    <?php /* Add/edit page heading. */ ?>
    <h1>
        <?php echo $is_edit ? esc_html__('Edit Collection', 'mdcat-platform') : esc_html__('Add New Collection', 'mdcat-platform'); ?>
    </h1>

    <?php if ($is_missing_edit) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Collection not found.', 'mdcat-platform'); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-collections')); ?>">
                <?php esc_html_e('Back to Collections', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php else : ?>

        <?php if (empty($chapters)) : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Please create a chapter before adding collections.', 'mdcat-platform'); ?></p>
            </div>
        <?php endif; ?>

        <?php /* Collection create/update form with nested chapter dropdown. */ ?>
        <form method="post" action="<?php echo esc_url($form_url); ?>">
            <?php wp_nonce_field('mdcat_save_collection', 'mdcat_collection_nonce'); ?>

            <input type="hidden" name="action" value="mdcat_save_collection">
            <input type="hidden" name="collection_id" value="<?php echo esc_attr($is_edit ? absint($collection->id) : 0); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-chapter"><?php esc_html_e('Chapter', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-collection-chapter" name="chapter_id" required>
                                <option value=""><?php esc_html_e('Select Chapter', 'mdcat-platform'); ?></option>
                                <?php foreach ($chapters as $chapter) : ?>
                                    <?php
                                    $chapter_label = sprintf(
                                        '%s - %s',
                                        $chapter->subject_name ? $chapter->subject_name : __('Subject unavailable', 'mdcat-platform'),
                                        $chapter->chapter_name
                                    );
                                    ?>
                                    <option value="<?php echo esc_attr(absint($chapter->id)); ?>" <?php selected($is_edit ? absint($collection->chapter_id) : 0, absint($chapter->id)); ?>>
                                        <?php echo esc_html($chapter_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-title"><?php esc_html_e('Collection Title', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="mdcat-collection-title"
                                name="title"
                                class="regular-text"
                                value="<?php echo esc_attr($is_edit ? $collection->title : ''); ?>"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-type"><?php esc_html_e('Collection Type', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-collection-type" name="type" required>
                                <option value=""><?php esc_html_e('Select Type', 'mdcat-platform'); ?></option>
                                <?php foreach ($types as $type_key => $type_label) : ?>
                                    <option value="<?php echo esc_attr($type_key); ?>" <?php selected($is_edit ? $collection->type : '', $type_key); ?>>
                                        <?php echo esc_html($type_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-description"><?php esc_html_e('Description', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <textarea
                                id="mdcat-collection-description"
                                name="description"
                                class="large-text"
                                rows="5"
                            ><?php echo esc_textarea($is_edit ? $collection->description : ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-sort-order"><?php esc_html_e('Sort Order', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="mdcat-collection-sort-order"
                                name="sort_order"
                                class="small-text"
                                min="0"
                                step="1"
                                value="<?php echo esc_attr($is_edit ? absint($collection->sort_order) : 0); ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-collection-status"><?php esc_html_e('Status', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-collection-status" name="status" required>
                                <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($is_edit ? $collection->status : 'active', $status_key); ?>>
                                        <?php echo esc_html($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button($is_edit ? __('Update Collection', 'mdcat-platform') : __('Create Collection', 'mdcat-platform'), 'primary', 'submit', true, empty($chapters) ? ['disabled' => 'disabled'] : []); ?>
        </form>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-collections')); ?>">
                <?php esc_html_e('Back to Collections', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
