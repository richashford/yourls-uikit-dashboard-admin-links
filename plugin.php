<?php
/*
Plugin Name: UIkit Dashboard Admin Links
Plugin URI: https://github.com/richashford/yourls-uikit-dashboard-admin-links
Description: Companion to the UIkit Skin: makes the Dashboard's "Top performers" and "Recently created" short URLs open the admin Links list filtered to that keyword (where the row actions live), and restyles the per-link stats page (margins, card layout, pill tabs) to match the skin.
Version: 1.2
Author: Richard Ashford
Author URI: https://github.com/richashford
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

yourls_add_action( 'html_footer', 'rja_uikit_dash_admin_links' );
yourls_add_action( 'html_head', 'rja_uikit_infos_styles' );
yourls_add_action( 'pre_html_head', 'rja_uikit_infos_nocache' );
yourls_add_action( 'html_footer', 'rja_uikit_infos_nav_unwrap' );
yourls_add_action( 'html_footer', 'rja_uikit_filter_to_top' );
yourls_add_action( 'yourls_ajax_rja_dashboard', 'rja_uikit_dashboard_data' );
yourls_add_action( 'html_footer', 'rja_uikit_dashboard_periods' );

/**
 * admin-ajax.php?action=rja_dashboard&period=all|24h|7d|30d
 * Returns {top: [...], recent: [...]} for the dashboard cards, 10 rows each.
 * "all" reads url.clicks; the periods count rows in the log table.
 */
function rja_uikit_dashboard_data() {
    $ydb       = yourls_get_db();
    $url_table = YOURLS_DB_TABLE_URL;
    $log_table = YOURLS_DB_TABLE_LOG;
    $limit     = 10;

    $period    = isset( $_GET['period'] ) ? $_GET['period'] : 'all';
    $intervals = array( '24h' => '24 HOUR', '7d' => '7 DAY', '30d' => '30 DAY' );

    if ( $period === 'all' ) {
        $top = $ydb->fetchObjects(
            "SELECT `keyword`, `title`, `url`, `clicks`
             FROM `$url_table` ORDER BY `clicks` DESC LIMIT $limit" );
    } elseif ( isset( $intervals[ $period ] ) ) {
        $iv  = $intervals[ $period ];
        $top = $ydb->fetchObjects(
            "SELECT u.`keyword`, u.`title`, u.`url`, COUNT(*) AS `clicks`
             FROM `$log_table` l
             JOIN `$url_table` u ON u.`keyword` = l.`shorturl`
             WHERE l.`click_time` >= NOW() - INTERVAL $iv
             GROUP BY l.`shorturl`
             ORDER BY `clicks` DESC LIMIT $limit" );
    } else {
        header( 'Content-Type: application/json' );
        echo json_encode( array( 'error' => 'bad period' ) );
        die();
    }

    $recent = $ydb->fetchObjects(
        "SELECT `keyword`, `title`, `url`, `timestamp`
         FROM `$url_table` ORDER BY `timestamp` DESC LIMIT $limit" );

    header( 'Content-Type: application/json' );
    echo json_encode( array( 'top' => $top, 'recent' => $recent ) );
    die();
}

/**
 * On the UIkit Skin dashboard: add period badges (24 hours / Week / Month /
 * All time) to the "Top performers" card and grow both lists to 10 rows,
 * fed by the rja_dashboard ajax action. No skin or core files touched.
 */
function rja_uikit_dashboard_periods() {
    if ( !yourls_is_admin() ) {
        return;
    }
    $ajax_url    = yourls_add_query_arg( array( 'action' => 'rja_dashboard' ), yourls_admin_url( 'admin-ajax.php' ) );
    $admin_index = yourls_admin_url( 'index.php' );
    ?>
    <style>
    .rja-periods { display: inline-flex; gap: 6px; margin-left: 12px; vertical-align: middle; }
    .rja-periods button {
        border: 1px solid var(--sb-border, #e5e9f0);
        background: var(--sb-bg, #f6f8fb);
        color: #444;
        border-radius: 999px;
        padding: 3px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .rja-periods button.rja-active {
        background: var(--sb-accent, #1e87f0);
        border-color: var(--sb-accent, #1e87f0);
        color: #fff;
    }
    </style>
    <script>
    (function () {
        var cards = document.querySelectorAll('.sb-list-card');
        if (!cards.length) return;
        var topCard = null, recentCard = null;
        cards.forEach(function (c) {
            var t = (c.querySelector('.uk-card-title') || {}).textContent || '';
            if (t.indexOf('Top performers') !== -1) topCard = c;
            if (t.indexOf('Recently created') !== -1) recentCard = c;
        });
        if (!topCard || !recentCard) return;

        var ajaxUrl = <?php echo json_encode( $ajax_url ); ?>;
        var adminIndex = <?php echo json_encode( $admin_index ); ?>;

        function keywordCell(row) {
            var td = document.createElement('td');
            var a = document.createElement('a');
            a.href = adminIndex + '?search=' + encodeURIComponent(row.keyword) + '&search_in=keyword';
            a.textContent = row.keyword;
            a.title = 'Show "' + row.keyword + '" in the Links list';
            td.appendChild(a);
            return td;
        }
        function destCell(row) {
            var td = document.createElement('td');
            td.className = 'sb-truncate';
            if (row.title) {
                var strong = document.createElement('strong');
                strong.className = 'sb-list-title';
                strong.textContent = row.title;
                td.appendChild(strong);
                td.appendChild(document.createElement('br'));
            }
            var small = document.createElement('small');
            small.className = 'uk-text-muted';
            small.textContent = row.url;
            td.appendChild(small);
            return td;
        }
        function fillTop(rows) {
            var tbody = topCard.querySelector('tbody');
            if (!tbody) return;
            tbody.textContent = '';
            rows.forEach(function (row) {
                var tr = document.createElement('tr');
                tr.appendChild(keywordCell(row));
                tr.appendChild(destCell(row));
                var td = document.createElement('td');
                td.className = 'uk-text-right';
                var span = document.createElement('span');
                span.className = 'uk-label sb-clicks-badge';
                span.textContent = Number(row.clicks).toLocaleString();
                td.appendChild(span);
                tr.appendChild(td);
                tbody.appendChild(tr);
            });
        }
        function fillRecent(rows) {
            var tbody = recentCard.querySelector('tbody');
            if (!tbody) return;
            tbody.textContent = '';
            rows.forEach(function (row) {
                var tr = document.createElement('tr');
                var kc = keywordCell(row);
                kc.className = 'sb-truncate';
                tr.appendChild(kc);
                tr.appendChild(destCell(row));
                var td = document.createElement('td');
                td.className = 'uk-text-right';
                var small = document.createElement('small');
                small.className = 'uk-text-muted';
                var d = new Date((row.timestamp || '').replace(' ', 'T'));
                small.textContent = isNaN(d) ? (row.timestamp || '') :
                    d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ', ' +
                    d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', hour12: false });
                td.appendChild(small);
                tr.appendChild(td);
                tbody.appendChild(tr);
            });
        }
        function load(period, withRecent) {
            fetch(ajaxUrl + '&period=' + period, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.top) fillTop(data.top);
                    if (withRecent && data.recent) fillRecent(data.recent);
                });
        }

        // Period badges on the Top performers title
        var title = topCard.querySelector('.uk-card-title');
        var holder = document.createElement('span');
        holder.className = 'rja-periods';
        var periods = [ ['24h', '24 hours'], ['7d', 'Week'], ['30d', 'Month'], ['all', 'All time'] ];
        periods.forEach(function (p) {
            var b = document.createElement('button');
            b.type = 'button';
            b.dataset.period = p[0];
            b.textContent = p[1];
            if (p[0] === 'all') b.classList.add('rja-active');
            b.addEventListener('click', function () {
                holder.querySelectorAll('button').forEach(function (x) { x.classList.remove('rja-active'); });
                b.classList.add('rja-active');
                load(p[0], false);
            });
            holder.appendChild(b);
        });
        title.appendChild(holder);

        // Initial load: default All time, 10 rows in both cards
        load('all', true);
    })();
    </script>
    <?php
}

/**
 * The admin Links page renders the search/filter form inside the table's
 * <tfoot>, below all rows. Move it above the table so filtering does not
 * require scrolling past the whole list. Pagination stays at the bottom.
 */
function rja_uikit_filter_to_top() {
    if ( !yourls_is_admin() ) {
        return;
    }
    ?>
    <style>
    #rja-filter-top { margin: 0 0 18px; }
    /* The skin gives #filter_form a card look (border, 16px padding); make it
       span the full table width and keep even spacing inside. */
    #rja-filter-top #filter_form {
        width: 100% !important;
        max-width: none !important;
        box-sizing: border-box !important;
        padding: 18px 20px !important;
        margin: 0 !important;
    }
    #rja-filter-top #filter_form form { margin: 0 !important; }
    /* Keep the form's natural inline flow, just give the wrapped lines and
       controls room to breathe. */
    #rja-filter-top #filter_options { line-height: 2.8; }
    #rja-filter-top #filter_options input,
    #rja-filter-top #filter_options select { margin: 0 4px; }
    </style>
    <script>
    (function () {
        var table  = document.getElementById('main_table');
        var filter = document.getElementById('filter_form');
        if (!table || !filter) return;
        var holder = document.createElement('div');
        holder.id = 'rja-filter-top';
        holder.appendChild(filter);
        table.parentNode.insertBefore(holder, table);
    })();
    </script>
    <?php
}

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
    body.infos h3 .label { color: #666; font-weight: 600; white-space: nowrap; }
    body.infos h3 { display: flex !important; align-items: center; gap: 6px; }
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
