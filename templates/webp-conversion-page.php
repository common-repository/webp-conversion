<?php

if (!defined('ABSPATH')) {
    exit;
}

?>
<div class="webpc-plugin-main-container">
    <div class="top-container">
        <h1><?php echo esc_html__('WebP Conversion', 'webp-conversion'); ?></h1>
        <p><?php echo esc_html__('Manage conversion settings', 'webp-conversion'); ?></p>
    </div>
    <div id="webpc-notice" class="notice notice-success" style="display:none;">
        <p><?php echo esc_html__('Changes have been saved!', 'webp-conversion'); ?></p>
    </div>
    <form method="post" action="options.php" id="webpc-settings-form">
        <?php settings_fields('webpc-settings-group'); ?>
        <div class="settings-container">
            <h2><?php echo esc_html__('Settings', 'webp-conversion'); ?></h2>
            <div class="input-field settings-block">
                <div class="settings-row">
                    <input type="checkbox" id="webpc_auto" name="webpc_auto"
                           value="1" <?php checked(1, get_option('webpc_auto', 1), true); ?> >
                    <?php echo esc_html__('Automatically convert images while uploading', 'webp-conversion'); ?>
                </div>
                <div class="settings-row">
                    <input type="checkbox" id="webpc_svg" name="webpc_svg"
                           value="1" <?php checked(1, get_option('webpc_svg', 1), true); ?> >
                    <?php echo esc_html__('Enable svg uploads', 'webp-conversion'); ?>
                </div>
                <div class="settings-row">
                    <input type="checkbox" id="webpc_ico" name="webpc_ico"
                           value="1" <?php checked(1, get_option('webpc_ico', 1), true); ?> >
                    <?php echo esc_html__('Enable ico uploads', 'webp-conversion'); ?>
                </div>
            </div>
            <h4><?php echo esc_html__('Conversion quality', 'webp-conversion'); ?></h4>
            <table class="input-table">
                <?php
                $quality_settings = [
                    '200kb' => ['label' => 'Less than 200kb', 'default' => 75],
                    '1000kb' => ['label' => 'Less than 1mb', 'default' => 70],
                    '2500kb' => ['label' => 'Less than 2.5mb', 'default' => 50],
                    'more_2500kb' => ['label' => 'More than 2.5mb', 'default' => 45]
                ];

                foreach ($quality_settings as $key => $settings) {
                    $label = $settings['label'];
                    $default_value = $settings['default'];
                    $value = esc_attr(get_option('webpc_' . $key, $default_value)); ?>
                    <tr>
                        <td><label for="quality"><?php echo esc_html($label) ?></label></td>
                        <td><input type="range" id="<?php echo 'webpc_' . esc_html($key) ?>"
                                   name="<?php echo 'webpc_' . esc_html($key) ?>" min="0" max="100"
                                   value="<?php echo esc_html($value) ?>"></td>
                        <td><input type="number" id="<?php echo 'webpc_' . esc_html($key) . '_value' ?>"
                                   name="<?php echo 'webpc_' . esc_html($key) . '_value' ?>" min="0" max="100"
                                   value="<?php echo esc_html($value) ?>"></td>
                    </tr>
                <?php } ?>
            </table>
        </div>
        <input type="submit" value="Save" class="button button-primary">
    </form>
</div>
