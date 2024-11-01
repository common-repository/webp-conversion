<?php
/**
 * WebP Conversion Uninstall
 * Uninstalling WebP Conversion resets conversion settings
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

$options_to_delete = [
    'webpc_auto',
    'webpc_svg',
    'webpc_ico',
    'webpc_200kb',
    'webpc_1000kb',
    'webpc_2500kb',
    'webpc_more_2500kb'
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}