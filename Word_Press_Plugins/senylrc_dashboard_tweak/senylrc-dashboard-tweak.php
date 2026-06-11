<?php
/**
 * Plugin Name: SENYLRC Dashboard Tweak
 * Description: Removes default dashboard widgets and adds Quick Links (admin bar + dashboard widget) for profiles, accounts, requests, and stats.
 * Version: 1.3.0
 * Author: SENYLRC
 */

// ------------------------------
// Remove unwanted dashboard boxes
// ------------------------------
function senylrc_remove_dashboard_widgets() {
    remove_meta_box('dashboard_activity', 'dashboard', 'normal'); // Activity
    remove_meta_box('dashboard_primary',  'dashboard', 'side');   // WP Events & News
    remove_meta_box('themeisle',          'dashboard', 'normal'); // Guides/Tutorials (from your HTML)
}
add_action('wp_dashboard_setup', 'senylrc_remove_dashboard_widgets', 999);

// ------------------------------
// Central link registry (easy to maintain)
// ------------------------------
function senylrc_link_sections() {
    return [
        'Profiles' => [
            [ 'label' => 'My Profile',              'href' => admin_url('/illuser') ],
            [ 'label' => 'Library Profile',         'href' => home_url('/libprofile') ],
            [ 'label' => 'View Library Accounts',   'href' => home_url('/libstaffaccount') ],
        ],
        'Request Status' => [
            [ 'label' => 'Borrowing',               'href' => home_url('/requesthistory') ],
            [ 'label' => 'Lending',                 'href' => home_url('/lender-history') ],
        ],
        'Statistics' => [
            [ 'label' => 'Borrowing',               'href' => home_url('/libstats') ],
            [ 'label' => 'Lending',                 'href' => home_url('/liblenderstat') ],
        ],
    ];
}

// ------------------------------
// Admin bar links (under a single parent to avoid clutter)
// ------------------------------
function senylrc_add_adminbar_links( $admin_bar ) {
    if ( ! is_user_logged_in() ) return;

    // Parent menu
    $admin_bar->add_node([
        'id'    => 'senylrc-quick-links',
        'title' => 'Library Quick Links',
        'href'  => home_url('/libprofile'),
        'meta'  => [ 'title' => 'Library Quick Links' ],
    ]);

    // Children
    foreach ( senylrc_link_sections() as $section => $links ) {
        // Section headers as non-clickable separators
        $section_id = 'senylrc-ql-' . sanitize_title( $section );
        $admin_bar->add_node([
            'id'     => $section_id,
            'parent' => 'senylrc-quick-links',
            'title'  => '— ' . esc_html( $section ) . ' —',
            'href'   => false,
            'meta'   => [ 'class' => 'ab-submenu' ],
        ]);

        foreach ( $links as $idx => $link ) {
            $admin_bar->add_node([
                'id'     => $section_id . '-' . $idx,
                'parent' => $section_id,
                'title'  => esc_html( $link['label'] ),
                'href'   => esc_url( $link['href'] ),
            ]);
        }
    }
}
add_action('admin_bar_menu', 'senylrc_add_adminbar_links', 100);

// ------------------------------
// Dashboard widget with grouped buttons
// ------------------------------
function senylrc_dashboard_widget_content() {
    $sections = senylrc_link_sections();
    ?>
    <div style="padding:10px 5px;">
        <?php foreach ( $sections as $section => $links ): ?>
            <h3 style="margin:12px 0 6px;"><?php echo esc_html( $section ); ?></h3>
            <p style="display:flex; flex-wrap:wrap; gap:8px;">
                <?php foreach ( $links as $link ): ?>
                    <a class="button<?php echo (stripos($link['label'], 'Profile') !== false ? ' button-primary' : ''); ?>"
                       href="<?php echo esc_url( $link['href'] ); ?>">
                        <?php echo esc_html( $link['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </p>
        <?php endforeach; ?>
    </div>
    <?php
}

function senylrc_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'senylrc_profile_links',
        'Quick Links',
        'senylrc_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'senylrc_add_dashboard_widget');
