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
yourls_add_action( 'html_footer', 'rja_uikit_infos_nav_unwrap' );

/**
 * The skin renders its navbar inside #wrap. On the stats page #wrap becomes a
 * centred card (see rja_uikit_infos_styles), which would trap the navbar in
 * the card; move it out so it spans the full width like on the admin pages.
 */
function rja_uikit_infos_nav_unwrap() {
    ?>
    <script>
    (function () {
        if (!document.body.classList.contains('infos')) return;
        var nav = document.querySelector('#wrap > nav.sb-navbar');
        if (nav) document.body.insertBefore(nav, document.body.firstChild);
    })();
    </script>
    <?php
}

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
    /* The UIkit Skin resets #wrap and backgrounds with !important, so these
       rules (loaded before skin.css) must use !important too. */
    body.infos {
        background: var(--sb-bg, #f6f8fb) !important;
    }
    body.infos #wrap {
        max-width: 1100px !important;
        margin: 28px auto 48px !important;
        padding: 28px 32px !important;
        background: var(--sb-card-bg, #ffffff) !important;
        border: 1px solid var(--sb-border, #e5e9f0) !important;
        border-radius: var(--sb-radius, 10px) !important;
        box-sizing: border-box !important;
    }
    @media (max-width: 1160px) {
        body.infos #wrap { margin-left: 16px !important; margin-right: 16px !important; }
    }
    /* Core YOURLS logo header; redundant with the skin navbar */
    body.infos header[role="banner"] { display: none !important; }
    body.infos h2#informations {
        margin-top: 0 !important;
        font-size: 1.1rem !important;
        line-height: 1.45 !important;
        word-break: break-all;
        color: #333 !important;
    }
    body.infos h3 .label { color: #666; font-weight: 600; }
    body.infos ul#headers.stat_tab {
        display: flex !important;
        flex-wrap: wrap;
        gap: 8px;
        list-style: none !important;
        margin: 20px 0 0 !important;
        padding: 0 0 14px !important;
        border-bottom: 1px solid var(--sb-border, #e5e9f0) !important;
    }
    body.infos ul#headers.stat_tab li {
        margin: 0 !important;
        padding: 0 !important;
        background: none !important;
        float: none !important;
        display: block !important;
    }
    body.infos ul#headers.stat_tab li a {
        display: block !important;
        padding: 7px 16px !important;
        background: var(--sb-bg, #f6f8fb) !important;
        border: 1px solid var(--sb-border, #e5e9f0) !important;
        border-radius: 999px !important;
        text-decoration: none !important;
    }
    body.infos ul#headers.stat_tab li a h2 {
        margin: 0 !important;
        padding: 0 !important;
        font-size: 0.95rem !important;
        font-weight: 600 !important;
        color: #444 !important;
        display: inline !important;
        border: 0 !important;
    }
    body.infos ul#headers.stat_tab li.selected a {
        background: var(--sb-accent, #1e87f0) !important;
        border-color: var(--sb-accent, #1e87f0) !important;
    }
    body.infos ul#headers.stat_tab li.selected a h2 { color: #fff !important; }
    body.infos .tab > h2 { font-size: 1.05rem !important; margin-top: 24px !important; }
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
