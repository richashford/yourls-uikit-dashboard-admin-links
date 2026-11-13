<?php
/*
Plugin Name: UIkit Dashboard Admin Links
Plugin URI: https://github.com/richashford/yourls-uikit-dashboard-admin-links
Description: Companion to the UIkit Skin: makes the Dashboard's "Top performers" and "Recently created" short URLs open the admin Links list filtered to that keyword (where the row actions live), and restyles the per-link stats page (margins, card layout, pill tabs) to match the skin.
Version: 1.1
Author: Richard Ashford
Author URI: https://github.com/richashford
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

yourls_add_action( 'html_footer', 'rja_uikit_dash_admin_links' );
yourls_add_action( 'html_head', 'rja_uikit_infos_styles' );
yourls_add_action( 'pre_html_head', 'rja_uikit_infos_nocache' );

/**
 * Stats pages are plain 200 responses with no cache headers, so a CDN in
 * front of the site (20i StackCDN here) caches them and serves stale click
 * numbers and markup. Mark them uncacheable.
 */
function rja_uikit_infos_nocache( $args ) {
    $context = is_array( $args ) ? $args[0] : $args;
    if ( $context === 'infos' && !headers_sent() ) {
        header( 'Cache-Control: no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }
}

/**
 * On the per-link stats page (yourls-infos.php, body.infos), give the content
 * the same card treatment as the UIkit Skin: centred container with margins,
 * white card, pill-style tabs. Pure CSS, no markup or behaviour changes.
 */
function rja_uikit_infos_styles( $args ) {
    $context = is_array( $args ) ? $args[0] : $args;
    if ( $context !== 'infos' ) {
        return;
    }
    ?>
    <style>
    body.infos {
        background: var(--sb-bg, #f6f8fb);
    }
    body.infos #wrap {
        max-width: 1100px;
        margin: 28px auto 48px;
        padding: 28px 32px;
        background: var(--sb-card-bg, #ffffff);
        border: 1px solid var(--sb-border, #e5e9f0);
        border-radius: var(--sb-radius, 10px);
        box-sizing: border-box;
    }
    @media (max-width: 1160px) {
        body.infos #wrap { margin-left: 16px; margin-right: 16px; }
    }
    body.infos h2#informations {
        margin-top: 0;
        font-size: 1.1rem;
        line-height: 1.45;
        word-break: break-all;
        color: #333;
    }
    body.infos h3 .label { color: #666; font-weight: 600; }
    body.infos ul#headers.stat_tab {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        list-style: none;
        margin: 20px 0 0;
        padding: 0 0 14px;
        border-bottom: 1px solid var(--sb-border, #e5e9f0);
    }
    body.infos ul#headers.stat_tab li { margin: 0; padding: 0; background: none; }
    body.infos ul#headers.stat_tab li a {
        display: block;
        padding: 7px 16px;
        background: var(--sb-bg, #f6f8fb);
        border: 1px solid var(--sb-border, #e5e9f0);
        border-radius: 999px;
        text-decoration: none;
    }
    body.infos ul#headers.stat_tab li a h2 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 600;
        color: #444;
    }
    body.infos ul#headers.stat_tab li.selected a {
        background: var(--sb-accent, #1e87f0);
        border-color: var(--sb-accent, #1e87f0);
    }
    body.infos ul#headers.stat_tab li.selected a h2 { color: #fff; }
    body.infos .tab > h2 { font-size: 1.05rem; margin-top: 24px; }
    </style>
    <?php
}

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
