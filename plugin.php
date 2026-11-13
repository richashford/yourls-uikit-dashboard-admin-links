<?php
/*
Plugin Name: UIkit Dashboard Admin Links
Plugin URI: https://github.com/richashford/yourls-uikit-dashboard-admin-links
Description: Companion to the UIkit Skin: makes the Dashboard's "Top performers" and "Recently created" short URLs open the admin Links list filtered to that keyword (where the row actions live), instead of following the redirect.
Version: 1.0
Author: Richard Ashford
Author URI: https://github.com/richashford
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

yourls_add_action( 'html_footer', 'rja_uikit_dash_admin_links' );

/**
 * On the UIkit Skin dashboard page only, rewrite the short-URL anchors in the
 * "Top performers" and "Recently created" cards so they point at the admin
 * Links list filtered to that keyword. The keyword is taken from the anchor's
 * href (the short URL), whose last path segment is the keyword; the anchor
 * text can be truncated by CSS so it is not used.
 */
function rja_uikit_dash_admin_links() {
    // Only on admin plugin pages (the dashboard is a plugin page)
    if ( !yourls_is_admin() ) {
        return;
    }
    $admin_index = yourls_admin_url( 'index.php' );
    ?>
    <script>
    (function () {
        var anchors = document.querySelectorAll('.sb-list-card table a[target="_blank"]');
        if (!anchors.length) return;
        var adminIndex = <?php echo json_encode( $admin_index ); ?>;
        anchors.forEach(function (a) {
            var href = a.getAttribute('href');
            if (!href) return;
            var keyword;
            try {
                var path = new URL(href, window.location.href).pathname;
                keyword = decodeURIComponent(path.replace(/\/+$/, '').split('/').pop());
            } catch (e) {
                return;
            }
            if (!keyword) return;
            a.href = adminIndex + '?search=' + encodeURIComponent(keyword) + '&search_in=keyword';
            a.removeAttribute('target');
            a.title = 'Show "' + keyword + '" in the Links list';
        });
    })();
    </script>
    <?php
}
