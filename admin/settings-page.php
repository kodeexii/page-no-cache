<?php
// Elak akses terus
if (!defined('ABSPATH')) {
    exit;
}

// 1. Tambah menu baru di bawah 'Settings'
add_action('admin_menu', function() {
    add_options_page(
        'Page Cache Excluder Settings',
        'Page Cache Excluder',
        'manage_options',
        'pce-settings',
        'pce_render_settings_page'
    );
});

// 2. Daftarkan semua setting kita
add_action('admin_init', function() {
    register_setting('pce_settings_group', 'pce_excluded_items', array('type' => 'array', 'sanitize_callback' => 'pce_sanitize_excluded_items'));
    register_setting('pce_settings_group', 'pce_update_source', array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'));
});

// Fungsi sanitization untuk senarai ID
function pce_sanitize_excluded_items($posted_data) {
    if (empty($posted_data) || !is_array($posted_data)) return array();
    return array_map('absint', $posted_data);
}

// 3. Fungsi utama untuk render keseluruhan HTML page setting
function pce_render_settings_page() {
    if (!current_user_can('manage_options')) return;
    
    $post_types = get_post_types(array('public' => true), 'objects');
    unset($post_types['attachment']);

    $all_items_by_type = array();
    foreach ($post_types as $post_type) {
        $all_items_by_type[$post_type->name] = get_posts(array('post_type' => $post_type->name, 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Pilih mana-mana content di bawah untuk menghalangnya daripada disimpan di dalam page cache.</p>
        
        <h2 class="nav-tab-wrapper">
            <?php
            $first_tab = true;
            foreach ($post_types as $post_type) {
                $class = $first_tab ? ' nav-tab-active' : '';
                echo '<a href="#' . $post_type->name . '" class="nav-tab' . $class . '" data-tab="' . $post_type->name . '">' . esc_html($post_type->labels->name) . '</a>';
                $first_tab = false;
            }
            ?>
        </h2>

        <form action="options.php" method="post">
            <?php
            settings_fields('pce_settings_group');
            $excluded_items = (array) get_option('pce_excluded_items', array());

            $first_tab = true;
            foreach ($post_types as $post_type) {
                $style = $first_tab ? '' : 'style="display:none;"';
                echo '<div id="tab-content-' . $post_type->name . '" class="tab-content" ' . $style . '>';
                
                // [BARU] Kotak Carian untuk setiap tab
                echo '<p><input type="search" class="pce-search-input" placeholder="Cari ' . esc_attr($post_type->labels->name) . '..." style="width: 100%; padding: 8px;"></p>';

                $items = $all_items_by_type[$post_type->name];
                if ($items) {
                    echo '<div class="list-container" style="max-height: 400px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 1rem; border-radius: 4px;">';
                    foreach ($items as $item) {
                        $checked = in_array($item->ID, $excluded_items) ? 'checked="checked"' : '';
                        echo "<label style='display: block; margin-bottom: 0.5rem;'><input type='checkbox' name='pce_excluded_items[]' value='{$item->ID}' {$checked}> " . esc_html($item->post_title) . "</label>";
                    }
                    echo '</div>';
                } else {
                    echo "<p>Tiada content jenis '" . esc_html($post_type->labels->name) . "' yang dijumpai.</p>";
                }
                echo '</div>';
                $first_tab = false;
            }
            ?>
            <hr>
            <h2>Sumber Kemaskini (Update Source)</h2>
            <p>Pilih dari mana plugin ini akan menerima notifikasi kemaskini.</p>
            <?php $current_source = get_option('pce_update_source', 'wordpress'); ?>
            <fieldset>
                <label><input type="radio" name="pce_update_source" value="wordpress" <?php checked($current_source, 'wordpress'); ?>> WordPress.org (Versi Stabil & Rasmi)</label><br>
                <label><input type="radio" name="pce_update_source" value="github" <?php checked($current_source, 'github'); ?>> GitHub (Versi Pembangunan & Ciri-ciri Terkini)</label>
            </fieldset>
            <?php submit_button('Simpan Pilihan'); ?>
        </form>
    </div>
    
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Logik untuk kawal tab
            $('.nav-tab-wrapper a').on('click', function(e) {
                e.preventDefault();
                var tab_id = $(this).data('tab');
                $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.tab-content').hide();
                $('#tab-content-' + tab_id).show();
            });

            // [BARU] Logik untuk carian real-time
            $('.pce-search-input').on('keyup', function() {
                var searchTerm = $(this).val().toLowerCase();
                // Cari senarai label dalam container yang sama dengan kotak carian
                $(this).closest('.tab-content').find('.list-container label').each(function() {
                    var postTitle = $(this).text().toLowerCase();
                    if (postTitle.includes(searchTerm)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
        });
    </script>
    <?php
}
