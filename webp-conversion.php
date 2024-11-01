<?php
/**
 * Plugin Name: WebP Conversion
 * Description: Plugin that converts your media images into .webp extension
 * Version: 1.1.2
 * Author: SheepFish
 * Author URI: https://sheep.fish/
 * Requires at least: 6.4
 * Requires PHP: 7.1
 * Text Domain: webp-conversion
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

require 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

if (!class_exists('WEBPCbySheepFish')) {

    class WEBPCbySheepFish
    {
        private $plugin_page;
        private $plugin_path;
        private $plugin_url;
        private $plugin_basename;

        private $webpc_auto;
        private $webpc_svg;
        private $webpc_ico;
        private $webpc_200kb;
        private $webpc_1000kb;
        private $webpc_2500kb;
        private $webpc_more_2500kb;

        public function __construct()
        {

            $this->plugin_page = admin_url('tools.php?page=webp-conversion');
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);
            $this->plugin_basename = plugin_basename(__FILE__);

            $this->webpc_auto = get_option('webpc_auto');
            $this->webpc_svg = get_option('webpc_svg');
            $this->webpc_ico = get_option('webpc_ico');
            $this->webpc_200kb = intval(get_option('webpc_200kb', 75));
            $this->webpc_1000kb = intval(get_option('webpc_1000kb', 70));
            $this->webpc_2500kb = intval(get_option('webpc_2500kb', 50));
            $this->webpc_more_2500kb = intval(get_option('webpc_more_2500kb', 45));

            register_activation_hook(__FILE__, [$this, 'webp_conversion_activate']);
            add_action('admin_init', [$this, 'webp_conversion_redirect']);
            register_deactivation_hook(__FILE__, [$this, 'webp_conversion_deactivate']);

            add_action('admin_menu', [$this, 'register_submenu_page']);
            add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'webpc_plugin_action_links']);
            add_action('admin_enqueue_scripts', [$this, 'webpc_enqueue_scripts']);
            add_action('admin_init', [$this, 'webpc_register_settings']);
            add_filter('attachment_fields_to_edit', [$this, 'add_custom_media_button'], 10, 2);

            add_action('wp_handle_upload', [$this, 'auto_convert']);

            add_action('wp_ajax_convert_single', [$this, 'convert_certain']);
            add_action('wp_ajax_nopriv_convert_single', [$this, 'convert_certain']);
            add_action('wp_ajax_convert_selected', [$this, 'convert_certain']);
            add_action('wp_ajax_nopriv_convert_selected', [$this, 'convert_certain']);

            add_action('post-upload-ui', [$this, 'add_select_button']);

            add_action('wp_ajax_update', [$this, 'update_settings']);
            add_action('wp_ajax_nopriv_update', [$this, 'update_settings']);

            add_filter('bulk_actions-upload', [$this, 'register_convert_selected_action']);
            add_filter('handle_bulk_actions-upload', [$this, 'convert_selected_handler'], 10, 3);
            add_action('admin_notices', [$this, 'convert_selected_admin_notice']);

            add_filter('upload_mimes', [$this, 'allow_svg_ico_mimes']);
            add_filter('wp_check_filetype_and_ext', [$this, 'return_mime_types'], 10, 4);

        }

        //Plugin Activation
        public function webp_conversion_activate(): void
        {
            set_transient('webpc_redirect', true, 30);
            if ($this->webpc_auto === false) {
                update_option('webpc_auto', 1);
            }
            if ($this->webpc_svg === false) {
                update_option('webpc_svg', 1);
            }
            if ($this->webpc_ico === false) {
                update_option('webpc_ico', 1);
            }
            flush_rewrite_rules();
        }

        //Redirection to plugin page after activation
        public function webp_conversion_redirect(): void
        {
            if (get_transient('webpc_redirect')) {

                delete_transient('webpc_redirect');
                wp_redirect($this->plugin_page);
                exit;

            }
        }

        //Plugin Deactivation
        public function webp_conversion_deactivate(): void
        {
            flush_rewrite_rules();
        }

        //Plugin Uninstall
        public function webp_conversion_uninstall(): void
        {
            include_once($this->plugin_path . 'uninstall.php');
        }

        //Plugin tab menu
        public function register_submenu_page(): void
        {

            add_submenu_page(
                'tools.php',
                __('WebP Conversion', 'webp-conversion'),
                __('WebP Conversion', 'webp-conversion'),
                'manage_options',
                'webp-conversion',
                [$this, 'webpc_plugin_page_content']
            );

        }

        //Plugin settings page content
        public function webpc_plugin_page_content(): void
        {
            require 'templates/webp-conversion-page.php';
        }

        //Plugin settings page button
        public function webpc_plugin_action_links($links): array
        {
            $custom_link = '<a href="' . $this->plugin_page . '">' . __('Settings', 'webp-conversion') . '</a>';
            array_push($links, $custom_link);
            return $links;
        }

        //Enqueueing plugin scripts and styles
        public function webpc_enqueue_scripts(): void
        {
            wp_enqueue_style('webpc_style', $this->plugin_url . 'assets/css/style.css', [], '1.0', 'all');
            wp_enqueue_script('webpc_ajax_script', $this->plugin_url . 'assets/js/ajax.js', ['jquery'], '1.0', true);
            wp_localize_script('webpc_ajax_script', 'webp_conversion', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('webpc_nonce')
            ]);
            wp_enqueue_script('webpc_main_script', $this->plugin_url . 'assets/js/main.js', ['jquery'], '1.0', true);
        }

        //Registration of plugin settings
        public function webpc_register_settings(): void
        {
            register_setting('webpc-settings-group', 'webpc_auto', 'intval');
            register_setting('webpc-settings-group', 'webpc_svg', 'intval');
            register_setting('webpc-settings-group', 'webpc_ico', 'intval');
            register_setting('webpc-settings-group', 'webpc_200kb', 'intval');
            register_setting('webpc-settings-group', 'webpc_1000kb', 'intval');
            register_setting('webpc-settings-group', 'webpc_2500kb', 'intval');
            register_setting('webpc-settings-group', 'webpc_more_2500kb', 'intval');
        }

        //Separate conversion button for png and jpeg images (Convert to WebP)
        public function add_custom_media_button($form_fields, $post): array
        {

            $image_path = wp_get_original_image_path($post->ID);
            $file_extension = pathinfo($image_path, PATHINFO_EXTENSION);

            $image_size = filesize($image_path);
            $image_weight = intval(round($image_size / 1000));

            if ($image_weight >= 10000) {
                $text = '<p class="description">' . __('Image is too big for conversion', 'webp-conversion') . '</p>';
            } else {
                $text = '
                        <button type="button" class="button button-primary webpc_convert_single" data-id="' . esc_html($post->ID) . '">' .
                            __('Convert to WebP', 'webp-conversion')
                        . '</button>
                        <div class="webpc-single-attach-spinner" style="display:none;"></div>
                    ';
            }

            if ($file_extension == 'png' || $file_extension == 'jpeg' || $file_extension == 'jpg') {
                $form_fields['convert_selected'] = [
                    'label' => __('Convert', 'webp-conversion'),
                    'input' => 'html',
                    'html' => $text,
                ];
            }

            return $form_fields;
        }

        //Auto-conversion of images while uploading
        public function auto_convert($file): array
        {

            if (!$this->webpc_auto || $file['type'] !== 'image/png' && $file['type'] !== 'image/jpeg') {
                return $file;
            }

            $image_path = $file['file'];

            $original_size = filesize($image_path);

            $original_weight = intval(round($original_size / 1000));

            if ($original_weight >= 10000) {
                return $file;
            }

            $manager = new ImageManager(new Driver());

            if ($this->weights_a_lot($image_path)) {
                $image = ImageManager::imagick()->read($image_path);
            } else {
                $image = $manager->read($image_path);
            }

            if (!is_object($image)) {
                return $file;
            }

            $file_name_with_extension = basename($image_path);
            $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);

            $path = wp_upload_dir()['path'] . '/' . $file_name . '.webp';
            $url = wp_upload_dir()['url'] . '/' . $file_name . '.webp';

            $counter = 1;
            while (file_exists($path)) {
                $path = wp_upload_dir()['path'] . '/' . $file_name . '-' . $counter . '.webp';
                $url = wp_upload_dir()['url'] . '/' . $file_name . '-' . $counter . '.webp';
                $counter++;
            }

            $this->convert_from_settings($original_weight, $image, $path);

            $img_array = [
                'file' => $path,
                'url' => $url,
                'type' => 'image/webp'
            ];

            $this->delete_file_directly($image_path);

            return $img_array;
        }

        //Image conversion by using buttons (Convert Selected, Convert to WebP)
        public function convert_certain($post_ids): ?int
        {

            if (!$post_ids) {

                check_ajax_referer('webpc_nonce', 'nonce');

                if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'webpc_nonce')) {
                    wp_send_json_error('Invalid nonce');
                    return null;
                }

                if (isset($_POST['image_id'])) {
                    $image_ids = [];
                    $image_ids[] = sanitize_text_field(wp_unslash($_POST['image_id']));
                    $single_image = true;
                } elseif (isset($_POST['image_ids'])) {
                    $image_ids = array_map('sanitize_text_field', wp_unslash($_POST['image_ids']));
                    $single_image = false;
                } else {
                    wp_send_json_error('No image ID provided');
                    return null;
                }

            } else {
                $image_ids = $post_ids;
                $single_image = false;
            }

            $count = 0;

            foreach ($image_ids as $image_id) {

                $mime_type = get_post_mime_type($image_id);
                if ($mime_type !== 'image/png' && $mime_type !== 'image/jpeg') {
                    continue;
                }

                $manager = new ImageManager(new Driver());
                $image_path = wp_get_original_image_path($image_id);

                $image_size = filesize($image_path);
                $image_weight = intval(round($image_size / 1000));

                if ($image_weight >= 10000) {
                    continue;
                }

                $this->weights_a_lot($image_path);

                if ($this->weights_a_lot($image_path)) {
                    $image = ImageManager::imagick()->read($image_path);
                } else {
                    $image = $manager->read($image_path);
                }

                if (!is_object($image)) {
                    continue;
                }

                $file_name_with_extension = basename($image_path);
                $file_name = pathinfo($file_name_with_extension, PATHINFO_FILENAME);
                $path = wp_upload_dir()['path'] . '/' . $file_name . '.webp';
                $url = wp_upload_dir()['url'] . '/' . $file_name . '.webp';
                $old_url = wp_get_attachment_url($image_id);

                $counter = 1;
                while (file_exists($path)) {
                    $path = wp_upload_dir()['path'] . '/' . $file_name . '-' . $counter . '.webp';
                    $url = wp_upload_dir()['url'] . '/' . $file_name . '-' . $counter . '.webp';
                    $counter++;
                }

                $this->convert_from_settings($image_weight, $image, $path);

                $file_type = wp_check_filetype($path, null);

                $attachment = [
                    'guid' => $url,
                    'post_mime_type' => $file_type['type'],
                    'post_title' => sanitize_file_name($file_name),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ];

                $attach_id = wp_insert_attachment($attachment, $path);

                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $path);
                wp_update_attachment_metadata($attach_id, $attach_data);

                $this->replace_meta_value($image_id, $attach_id, $old_url, $url);

                wp_delete_attachment($image_id, true);

                $count++;

                if ($single_image) {
                    $response = 'reload';

                    if (isset($_POST['current_page']) && $_POST['current_page'] === '/wp-admin/upload.php') {
                        $response = admin_url('upload.php?item=' . $attach_id);
                    }
                    if (wp_get_referer() == admin_url() . 'post.php?post=' . $image_id . '&action=edit') {
                        $response = admin_url('post.php?post=' . $attach_id . '&action=edit');
                    }

                    wp_send_json_success([
                        'url' => $response,
                        'converted' => $count
                    ]);
                    exit;
                }

            }

            if (!$post_ids) {

                wp_send_json_success([
                    'url' => admin_url('upload.php?conversion_done='),
                    'converted' => $count
                ]);

            }

            return $count;
        }

        //Deleting file after conversion
        public function delete_file_directly($file_path): bool
        {
            if (file_exists($file_path)) {
                wp_delete_file($file_path);
                return true;
            } else {
                return false;
            }
        }

        //Convert Selected and Select All/Unselect All buttons
        public function add_select_button(): void
        {
            $script = "
                jQuery(document).ready(function ($) {
                console.log('Script is running');
                    const convertSelected = $('<button type=\"button\" class=\"button media-button button-secondary button-large delete-selected-button webpc_convert_selected\" style=\"display: none;\">" . __('Convert Selected', 'webp-conversion') . "</button>');
                    $('.delete-selected-button').after(convertSelected);
            
                    const convertedCounterAndSpinner = $('<div class=\"webpc-counter-and-spinner media-button\" style=\"display: none;\"><div class=\"webpc-counter-and-spinner-inner\"><div class=\"webpc-converted-count-container\"><p>" . __('Images converted: ', 'webp-conversion') . "<span id=\"webpc-converted-count\">0</span></p></div><div class=\"webpc-convert-selected-spinner-container\"><div class=\"webpc-convert-selected-spinner\"></div></div></div></div>');
                    $('.webpc_convert_selected').after(convertedCounterAndSpinner);

                    const toggleSelect = $('<button type=\"button\" class=\"button media-button button-primary button-large delete-selected-button webpc_toggle_select\" style=\"display: none;\">" . __('Select All:', 'webp-conversion') . "</button>');
                    $('.webpc-counter-and-spinner').after(toggleSelect);
            
                    $('.select-mode-toggle-button').one('click', function () {
                        convertSelected.css('display', '');
                        toggleSelect.css('display', '');
                    });
            
                    function selectAllImages() {
                        $('.attachments-browser .attachments .attachment').each(function () {
                            if (!$(this).hasClass('selected')) {
                                $(this).find('.attachment-preview').click();
                            }
                        });
                    }
            
                    function deselectAllImages() {
                        $('.attachments-browser .attachments .attachment').each(function () {
                            if ($(this).hasClass('selected')) {
                                $(this).find('.attachment-preview').click();
                            }
                        });
                    }
            
                    $('.webpc_toggle_select').on('click', function () {
                        if ($(this).text() === '" . __('Select All:', 'webp-conversion') . "') {
                            selectAllImages();
                            $(this).text('" . __('Unselect All:', 'webp-conversion') . "');
                        } else {
                            deselectAllImages();
                            $(this).text('" . __('Select All:', 'webp-conversion') . "');
                        }
                    });
                });
            ";

            wp_add_inline_script('webpc_main_script', $script);
        }

        //Replacing original images with converted ones in wpdb
        public function replace_meta_value($old_value, $new_value, $old_url, $new_url): void
        {
            global $wpdb;

            // Escaping values
            $old_value_escaped = esc_sql($old_value);
            $new_value_escaped = esc_sql($new_value);
            $old_url_escaped = esc_sql($old_url);
            $new_url_escaped = esc_sql($new_url);

            $old_post = get_post($old_value_escaped);
            $old_post_parent = $old_post ? $old_post->post_parent : 0;

            $wpdb_metas = [$wpdb->postmeta, $wpdb->usermeta, $wpdb->termmeta];

            // Replacing images in postmeta, usermeta, and termmeta
            foreach ($wpdb_metas as $meta) {

                $cache_key = "{$meta}_meta_value_{$old_value_escaped}";
                $cached_meta = wp_cache_get($cache_key);

                if ($cached_meta === false) {
                    $wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $meta SET meta_value = %s WHERE meta_value = %s",
                            $new_value_escaped,
                            $old_value_escaped
                        )
                    );
                    wp_cache_set($cache_key, $new_value_escaped);
                }
            }

            // Replacing images in post content
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
                    $old_url_escaped,
                    $new_url_escaped,
                    '%' . $wpdb->esc_like($old_url_escaped) . '%'
                )
            );

            // Replacing images in WooCommerce gallery
            if (class_exists('WooCommerce')) {
                $products = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_product_image_gallery' AND meta_value LIKE %s",
                        '%' . $wpdb->esc_like($old_value_escaped) . '%'
                    )
                );

                foreach ($products as $product) {
                    $new_gallery = str_replace($old_value_escaped, $new_value_escaped, $product->meta_value);
                    $wpdb->update(
                        $wpdb->postmeta,
                        ['meta_value' => $new_gallery],
                        ['post_id' => $product->post_id, 'meta_key' => '_product_image_gallery']
                    );
                }
            }

            // Updating options table
            $options = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT option_id, option_value, option_name FROM {$wpdb->options} WHERE option_value LIKE %s",
                    '%' . $wpdb->esc_like($old_value_escaped) . '%'
                )
            );

            foreach ($options as $option) {
                if (strpos($option->option_name, 'options_') === 0) {
                    $new_option_value = str_replace($old_value_escaped, $new_value_escaped, $option->option_value);
                    $wpdb->update(
                        $wpdb->options,
                        ['option_value' => $new_option_value],
                        ['option_id' => $option->option_id]
                    );
                }
            }

            // Replacing post attachments
            wp_update_post([
                'ID' => $new_value_escaped,
                'post_parent' => $old_post_parent,
            ]);
            $attach_data = wp_generate_attachment_metadata($new_value_escaped, get_attached_file($new_value_escaped));
            wp_update_attachment_metadata($new_value_escaped, $attach_data);
        }

        //Saving user settings
        public function update_settings(): void
        {
            check_ajax_referer('webpc-settings-group-options');

            update_option('webpc_auto', isset($_POST['webpc_auto']) ? 1 : 0);
            update_option('webpc_svg', isset($_POST['webpc_svg']) ? 1 : 0);
            update_option('webpc_ico', isset($_POST['webpc_ico']) ? 1 : 0);

            $quality_settings = [
                '200kb' => 75,
                '1000kb' => 70,
                '2500kb' => 50,
                'more_2500kb' => 45
            ];

            foreach ($quality_settings as $key => $default_value) {
                if (isset($_POST['webpc_' . $key])) {
                    update_option('webpc_' . $key, intval(sanitize_text_field(wp_unslash($_POST['webpc_' . $key]))));
                }
            }

            wp_send_json_success();
        }

        //Convert Selected option in upload.php?mode=list
        public function register_convert_selected_action($bulk_actions): array
        {
            $bulk_actions['convert_selected'] = __('Convert Selected', 'webp-conversion');
            return $bulk_actions;
        }

        //Handler for "Convert Selected" option in upload.php?mode=list
        public function convert_selected_handler($redirect_to, $doaction, $post_ids): string
        {

            if ($doaction !== 'convert_selected') {
                return $redirect_to;
            }

            $num = $this->convert_certain($post_ids);

            $redirect_to = add_query_arg('conversion_done', $num, $redirect_to);
            return $redirect_to;
        }

        //Notice after conversion using button or option "Convert Selected"
        public function convert_selected_admin_notice(): void
        {
            if (!empty($_REQUEST['conversion_done'])) {

                $count = intval(sanitize_text_field(wp_unslash($_REQUEST['conversion_done'])));
                printf(
                    // translators: amount of converted images
                    '<div id="message" class="updated notice is-dismissible webpc-notice"><p>' . esc_html__('Conversion applied to %s media items.', 'webp-conversion') . '</p></div>',
                    esc_html($count)
                );
            }
        }

        //Conversion itself using user settings
        public function convert_from_settings($image_weight, $image, $path): void
        {

            if ($image_weight <= 200) {
                $image->toWebp($this->webpc_200kb)->save($path);
            } else if ($image_weight <= 1000) {
                $image->toWebp($this->webpc_1000kb)->save($path);
            } else if ($image_weight <= 2500) {
                $image->toWebp($this->webpc_2500kb)->save($path);
            } else {
                $image->toWebp($this->webpc_more_2500kb)->save($path);
            }

        }

        //Checks if image weight more than 1.6mb
        public function weights_a_lot($path): bool
        {

            $image_dimensions = getimagesize($path);
            $image_size = filesize($path);
            $image_weight = intval(round($image_size / 1000));

            if ($image_weight >= 1600) {
                return true;
            }

            if ($image_dimensions[0] > '2000' || $image_dimensions[1] > '2000') {
                return true;
            }

            return false;
        }

        //Allows svg and ico uploads
        public function allow_svg_ico_mimes($mimes): array
        {
            if ($this->webpc_svg == 1) {
                $mimes['svg'] = 'image/svg+xml';
            }
            if ($this->webpc_ico == 1) {
                $mimes['ico'] = 'image/x-icon';
            }

            return $mimes;
        }

        //Returns correct meme types
        public function return_mime_types($data, $file, $filename, $mimes): array
        {

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($this->webpc_svg == 1 && $ext === 'svg') {
                $data['type'] = 'image/svg+xml';
                $data['ext'] = 'svg';
            }

            if ($this->webpc_ico == 1 && $ext === 'ico') {
                $data['type'] = 'image/x-icon';
                $data['ext'] = 'ico';
            }

            return $data;
        }

    }

    $webpcsf = new WEBPCbySheepFish;

}