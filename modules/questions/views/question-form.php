<?php

if (!defined('ABSPATH')) {
    exit;
}

$is_edit         = !empty($question);
$form_url        = admin_url('admin-post.php');
$is_missing_edit = isset($_GET['action']) && 'edit' === sanitize_key(wp_unslash($_GET['action'])) && !$is_edit;
?>

<div class="wrap">
    <?php /* Add/edit page heading. */ ?>
    <h1>
        <?php echo $is_edit ? esc_html__('Edit Question', 'mdcat-platform') : esc_html__('Add New Question', 'mdcat-platform'); ?>
    </h1>

    <?php if ($is_missing_edit) : ?>
        <div class="notice notice-error">
            <p><?php esc_html_e('Question not found.', 'mdcat-platform'); ?></p>
        </div>
        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-questions')); ?>">
                <?php esc_html_e('Back to Questions', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php else : ?>

        <?php if (empty($collections)) : ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Please create a collection before adding questions.', 'mdcat-platform'); ?></p>
            </div>
        <?php endif; ?>

        <?php /* Question create/update form with deep relational collection dropdown. */ ?>
        <form method="post" action="<?php echo esc_url($form_url); ?>">
            <?php wp_nonce_field('mdcat_save_question', 'mdcat_question_nonce'); ?>

            <input type="hidden" name="action" value="mdcat_save_question">
            <input type="hidden" name="question_id" value="<?php echo esc_attr($is_edit ? absint($question->id) : 0); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-collection"><?php esc_html_e('Collection', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-question-collection" name="collection_id" required>
                                <option value=""><?php esc_html_e('Select Collection', 'mdcat-platform'); ?></option>
                                <?php foreach ($collections as $collection) : ?>
                                    <?php
                                    $collection_label = sprintf(
                                        '%s - %s - %s',
                                        $collection->subject_name ? $collection->subject_name : __('Subject unavailable', 'mdcat-platform'),
                                        $collection->chapter_name ? $collection->chapter_name : __('Chapter unavailable', 'mdcat-platform'),
                                        $collection->collection_title
                                    );
                                    ?>
                                    <option value="<?php echo esc_attr(absint($collection->id)); ?>" <?php selected($is_edit ? absint($question->collection_id) : 0, absint($collection->id)); ?>>
                                        <?php echo esc_html($collection_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-text"><?php esc_html_e('Question', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <textarea
                                id="mdcat-question-text"
                                name="question"
                                class="large-text"
                                rows="5"
                                required
                            ><?php echo esc_textarea($is_edit ? $question->question : ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-option-a"><?php esc_html_e('Option A', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mdcat-question-option-a" name="option_a" class="regular-text" value="<?php echo esc_attr($is_edit ? $question->option_a : ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-option-b"><?php esc_html_e('Option B', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mdcat-question-option-b" name="option_b" class="regular-text" value="<?php echo esc_attr($is_edit ? $question->option_b : ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-option-c"><?php esc_html_e('Option C', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mdcat-question-option-c" name="option_c" class="regular-text" value="<?php echo esc_attr($is_edit ? $question->option_c : ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-option-d"><?php esc_html_e('Option D', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="mdcat-question-option-d" name="option_d" class="regular-text" value="<?php echo esc_attr($is_edit ? $question->option_d : ''); ?>" required>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-correct-option"><?php esc_html_e('Correct Option', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-question-correct-option" name="correct_option" required>
                                <option value=""><?php esc_html_e('Select Correct Option', 'mdcat-platform'); ?></option>
                                <?php foreach ($correct_options as $option_key => $option_label) : ?>
                                    <option value="<?php echo esc_attr($option_key); ?>" <?php selected($is_edit ? $question->correct_option : '', $option_key); ?>>
                                        <?php echo esc_html($option_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-explanation"><?php esc_html_e('Explanation', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <textarea
                                id="mdcat-question-explanation"
                                name="explanation"
                                class="large-text"
                                rows="5"
                            ><?php echo esc_textarea($is_edit ? $question->explanation : ''); ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-difficulty"><?php esc_html_e('Difficulty', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-question-difficulty" name="difficulty" required>
                                <?php foreach ($difficulties as $difficulty_key => $difficulty_label) : ?>
                                    <option value="<?php echo esc_attr($difficulty_key); ?>" <?php selected($is_edit ? $question->difficulty : 'easy', $difficulty_key); ?>>
                                        <?php echo esc_html($difficulty_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-marks"><?php esc_html_e('Marks', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="mdcat-question-marks"
                                name="marks"
                                class="small-text"
                                min="0"
                                step="0.01"
                                value="<?php echo esc_attr($is_edit ? $question->marks : '1.00'); ?>"
                                required
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-sort-order"><?php esc_html_e('Sort Order', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="mdcat-question-sort-order"
                                name="sort_order"
                                class="small-text"
                                min="0"
                                step="1"
                                value="<?php echo esc_attr($is_edit ? absint($question->sort_order) : 0); ?>"
                            >
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="mdcat-question-status"><?php esc_html_e('Status', 'mdcat-platform'); ?></label>
                        </th>
                        <td>
                            <select id="mdcat-question-status" name="status" required>
                                <?php foreach ($statuses as $status_key => $status_label) : ?>
                                    <option value="<?php echo esc_attr($status_key); ?>" <?php selected($is_edit ? $question->status : 'active', $status_key); ?>>
                                        <?php echo esc_html($status_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php submit_button($is_edit ? __('Update Question', 'mdcat-platform') : __('Create Question', 'mdcat-platform'), 'primary', 'submit', true, empty($collections) ? ['disabled' => 'disabled'] : []); ?>
        </form>

        <p>
            <a href="<?php echo esc_url(admin_url('admin.php?page=mdcat-questions')); ?>">
                <?php esc_html_e('Back to Questions', 'mdcat-platform'); ?>
            </a>
        </p>
    <?php endif; ?>
</div>
