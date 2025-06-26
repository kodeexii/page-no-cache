<?php
/**
 * Plugin Name:       Page Cache Excluder
 * Plugin URI:        https://hadeeroslan.my/plugins/page-cache-excluder
 * Description:       Membolehkan anda memilih mana-mana content untuk dikecualikan dari sebarang jenis page cache server-side, dengan pilihan sumber kemaskini.
 * Version:           2.3.1
 * Author:            Al-Hadee Mohd Roslan & Mat Gem
 * Author URI:        https://hadeeroslan.my
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Fungsi cleanup apabila plugin dipadam.
 * Ia akan memadam option dari database untuk kebersihan.
 */
function pce_cleanup_on_uninstall() {
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        die;
    }
    delete_option('pce_excluded_items');
    delete_option('pce_update_source');
}
register_uninstall_hook(__FILE__, 'pce_cleanup_on_uninstall');


// Sertakan fail yang menguruskan halaman tetapan admin & updater.
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

/**
 * Memulakan pemeriksa kemaskini berdasarkan pilihan pengguna.
 */
function pce_initialize_updater() {
    $update_source = get_option('pce_update_source', 'wordpress');

    // Hanya aktifkan pemeriksa kemaskini dari GitHub JIKA pengguna memilihnya.
    if ($update_source === 'github') {
        // Muatkan fail enjin updater kita
        require_once plugin_dir_path(__FILE__) . 'admin/updater.php';
        // Mulakan enjin updater
        new PCE_GitHub_Updater(__FILE__);
    }
}
add_action('plugins_loaded', 'pce_initialize_updater');


// --- LOGIK META BOX DALAM EDITOR ---

/**
 * 1. Tambah Meta Box ke semua jenis post type yang public.
 */
add_action('add_meta_boxes', function() {
    $post_types = get_post_types(array('public' => true));
    foreach ($post_types as $post_type) {
        add_meta_box(
            'pce_meta_box',
            'Page Cache Excluder',
            'pce_render_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
});

/**
 * 2. Fungsi untuk render HTML content dalam Meta Box.
 */
function pce_render_meta_box($post) {
    wp_nonce_field('pce_save_meta_box_data', 'pce_meta_box_nonce');
    $excluded_items = (array) get_option('pce_excluded_items', array());
    $is_checked = in_array($post->ID, $excluded_items);
    
    echo '<label><input type="checkbox" name="pce_exclude_checkbox" value="1" ' . checked($is_checked, true, false) . ' /> Exclude this item from cache</label>';
}

/**
 * 3. Simpan data dari Meta Box apabila post disimpan.
 */
add_action('save_post', function($post_id) {
    if (!isset($_POST['pce_meta_box_nonce']) || !wp_verify_nonce($_POST['pce_meta_box_nonce'], 'pce_save_meta_box_data')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $excluded_items = (array) get_option('pce_excluded_items', array());

    if (isset($_POST['pce_exclude_checkbox'])) {
        if (!in_array($post_id, $excluded_items)) $excluded_items[] = $post_id;
    } else {
        $excluded_items = array_diff($excluded_items, array($post_id));
    }
    update_option('pce_excluded_items', array_unique($excluded_items));
});


// --- FUNGSI TERAS PLUGIN ---

/**
 * Fungsi utama: menghantar no-cache header jika content dipilih.
 */
add_action('template_redirect', function() {
    $excluded_items = get_option('pce_excluded_items', array());
    if (empty($excluded_items)) return;

    $current_item_id = get_queried_object_id();
    if ($current_item_id && in_array($current_item_id, $excluded_items)) {
        header('X-Accel-Expires: 0');
        header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    }
});
