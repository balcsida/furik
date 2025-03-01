<?php
add_action('init', 'furik_localization_init', 0);

function furik_localization_init() {
    $path = dirname(plugin_basename( __FILE__ )) . '/lang/';
    $loaded = load_plugin_textdomain('furik', false, $path);
}