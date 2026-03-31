<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Hàm dịch User Agent thành loại Thiết bị giao diện thân thiện
if ( ! function_exists( 'wprg_get_device_type' ) ) {
    function wprg_get_device_type( $user_agent ) {
        $ua = strtolower( $user_agent );
        if ( empty( $ua ) || $ua === 'unknown' ) return __( '❓ Unknown', 'redirect-gateway-manager' ); 
        
        if ( preg_match( '/bot|crawl|slurp|spider|mediapartners|google|bing|yandex|facebook/i', $ua ) ) {
            return __( '🤖 Robot (Bot)', 'redirect-gateway-manager' ); 
        }
        if ( preg_match( '/ipad|tablet|kindle|playbook|silk/i', $ua ) ) {
            return __( '💊 Tablet', 'redirect-gateway-manager' ); 
        }
        if ( preg_match( '/mobile|android|iphone|ipod|blackberry|windows phone/i', $ua ) ) {
            return __( '📱 Mobile', 'redirect-gateway-manager' ); 
        }
        return __( '💻 Computer', 'redirect-gateway-manager' ); 
    }
}

global $wpdb;
$table_logs  = $wpdb->prefix . 'rg_logs';
$table_links = $wpdb->prefix . 'rg_links';

// --- XỬ LÝ XÓA LOG ---
if ( isset( $_POST['wprg_delete_logs'] ) && check_admin_referer( 'wprg_delete_logs_nonce' ) ) {
    $delete_type = isset( $_POST['delete_type'] ) ? sanitize_text_field( wp_unslash( $_POST['delete_type'] ) ) : '';
    
    if ( $delete_type === 'all' ) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter        
        $wpdb->query( "TRUNCATE TABLE {$table_logs}" );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All logs have been completely deleted!', 'redirect-gateway-manager' ) . '</p></div>';
    } elseif ( $delete_type === 'month' && !empty($_POST['filter_month']) ) {
        $month_year = sanitize_text_field( wp_unslash( $_POST['filter_month'] ) ); 
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query( $wpdb->prepare( "DELETE FROM " . $table_logs . " WHERE DATE_FORMAT(clicked_at, '%%Y-%%m') = %s", $month_year ) );
        /* translators: %s: Month and year */
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Logs for month %s deleted successfully.', 'redirect-gateway-manager' ), esc_html( $month_year ) ) . '</p></div>';
    }
}

// --- XỬ LÝ LỌC & QUERY DỮ LIỆU ---
$selected_month = isset( $_GET['filter_month'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_month'] ) ) : '';

// 1. Query Lấy Danh Sách Logs
if ( ! empty( $selected_month ) ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $logs = $wpdb->get_results( $wpdb->prepare( "SELECT lg.*, lk.name as link_name FROM " . $table_logs . " lg LEFT JOIN " . $table_links . " lk ON lg.link_id = lk.id WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s ORDER BY lg.clicked_at DESC LIMIT 500", $selected_month ), ARRAY_A );
} else {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $logs = $wpdb->get_results( "SELECT lg.*, lk.name as link_name FROM " . $table_logs . " lg LEFT JOIN " . $table_links . " lk ON lg.link_id = lk.id ORDER BY lg.clicked_at DESC LIMIT 500", ARRAY_A );
}

// 2. Query Lấy Danh Sách Tháng
// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$available_months = $wpdb->get_col( "SELECT DISTINCT DATE_FORMAT(clicked_at, '%Y-%m') as month_year FROM " . $table_logs . " ORDER BY month_year DESC" );

// --- TRUY VẤN THỐNG KÊ NHANH ---
if ( ! empty( $selected_month ) ) {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $total_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . $table_logs . " lg WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s", $selected_month ) );
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $completed_clicks = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . $table_logs . " lg WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s AND status = 'completed'", $selected_month ) );
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $top_link = $wpdb->get_row( $wpdb->prepare( "SELECT lk.name, COUNT(lg.id) as clicks FROM " . $table_logs . " lg LEFT JOIN " . $table_links . " lk ON lg.link_id = lk.id WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s GROUP BY lg.link_id ORDER BY clicks DESC LIMIT 1", $selected_month ) );

    // 3. Query Top Referrers
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $top_referrers = $wpdb->get_results( $wpdb->prepare( "SELECT referrer, COUNT(id) as clicks FROM " . $table_logs . " lg WHERE referrer != 'Direct / None' AND referrer != 'Trực tiếp' AND referrer IS NOT NULL AND referrer != '' AND DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s GROUP BY referrer ORDER BY clicks DESC LIMIT 3", $selected_month ) );
} else {
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $total_clicks = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_logs . " lg" );
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $completed_clicks = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_logs . " lg WHERE status = 'completed'" );
    
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $top_link = $wpdb->get_row( "SELECT lk.name, COUNT(lg.id) as clicks FROM " . $table_logs . " lg LEFT JOIN " . $table_links . " lk ON lg.link_id = lk.id GROUP BY lg.link_id ORDER BY clicks DESC LIMIT 1" );

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $top_referrers = $wpdb->get_results( "SELECT referrer, COUNT(id) as clicks FROM " . $table_logs . " lg WHERE referrer != 'Direct / None' AND referrer != 'Trực tiếp' AND referrer IS NOT NULL AND referrer != '' GROUP BY referrer ORDER BY clicks DESC LIMIT 3" );
}

$conversion_rate = ($total_clicks > 0) ? round(($completed_clicks / $total_clicks) * 100, 2) : 0;
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Statistics & Click History', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
        <div class="wprg-stat-card success">
            <h4>
                <?php esc_html_e( 'Total Access / Completed', 'redirect-gateway-manager' ); ?>
                <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Number of clicks on the link / Number of times the ad was fully watched to get the destination link.', 'redirect-gateway-manager' ); ?></span></span>
            </h4>
            <span style="font-size: 32px; font-weight: bold; color: #0073aa; line-height: 1;"><?php echo esc_html( number_format_i18n( $total_clicks ?: 0 ) ); ?></span>
            <span style="font-size: 18px; color: #00a32a; font-weight: bold;"> / <?php echo esc_html( number_format_i18n( $completed_clicks ?: 0 ) ); ?></span>
            <span style="font-size: 14px; color: #d63638; font-weight: bold; margin-left: 5px;" title="<?php esc_attr_e( 'Conversion Rate', 'redirect-gateway-manager' ); ?>">(CR: <?php echo esc_html( $conversion_rate ); ?>%)</span>
        </div>
        
        <div class="wprg-stat-card">
            <h4>
                <?php esc_html_e( 'Most Clicked Link', 'redirect-gateway-manager' ); ?>
            </h4>
            <?php if ( $top_link ) : ?>
                <span style="font-size: 16px; font-weight: bold; display: block; margin-bottom: 5px;"><?php echo esc_html( $top_link->name ? $top_link->name : __( 'Deleted Link', 'redirect-gateway-manager' ) ); ?></span>
                <span style="color: #00a32a; font-weight: bold;">
                <?php 
                    /* translators: %s: Number of clicks */
                    printf( esc_html__( '%s clicks', 'redirect-gateway-manager' ), esc_html( number_format_i18n( $top_link->clicks ) ) ); 
                ?>
                </span>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e( 'No data', 'redirect-gateway-manager' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="wprg-stat-card warning">
            <h4>
                <?php esc_html_e( 'Top 3 Traffic Sources (Referrer)', 'redirect-gateway-manager' ); ?>
                <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Where users clicked your link (Facebook, Youtube, other websites...).', 'redirect-gateway-manager' ); ?></span></span>
            </h4>
            <?php if ( ! empty( $top_referrers ) ) : ?>
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <?php foreach ( $top_referrers as $ref ) : ?>
                        <li style="margin-bottom: 5px; display: flex; justify-content: space-between; max-width: 400px;">
                            <a href="<?php echo esc_url( 'http://' . $ref->referrer ); ?>" target="_blank" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80%;"><?php echo esc_html( $ref->referrer ); ?></a>
                            <span style="background: #e5e5e5; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php echo esc_html( number_format_i18n( $ref->clicks ) ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e( 'No external traffic sources yet', 'redirect-gateway-manager' ); ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; position: relative; z-index: 1;">
        <div class="wprg-form-container" style="flex: 1; min-width: 300px;">
            <h3>🔍 <?php esc_html_e( 'Filter Display', 'redirect-gateway-manager' ); ?></h3>
            <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="wprg-logs">
                <select name="filter_month" style="width: 60%;">
                    <option value=""><?php esc_html_e( '-- All months --', 'redirect-gateway-manager' ); ?></option>
                    <?php foreach ( $available_months as $m ) : ?>
                        <option value="<?php echo esc_attr($m); ?>" <?php selected( $selected_month, $m ); ?>>
                            <?php echo esc_html__( 'Month ', 'redirect-gateway-manager' ) . esc_html($m); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter Logs', 'redirect-gateway-manager' ); ?>">
            </form>
        </div>

        <div class="wprg-form-container" style="flex: 1; min-width: 300px; border-left: 4px solid #d63638;">
            <h3 style="color: #d63638;">🗑️ <?php esc_html_e( 'Clean Database', 'redirect-gateway-manager' ); ?></h3>
            <form method="post" action="" onsubmit="return confirm('<?php esc_js( esc_html_e( 'This action cannot be undone. Are you sure?', 'redirect-gateway-manager' ) ); ?>');" style="display: flex; gap: 10px; align-items: center;">
                <?php wp_nonce_field( 'wprg_delete_logs_nonce' ); ?>
                <select name="delete_type" required style="width: 60%;">
                    <option value=""><?php esc_html_e( '-- Select deletion type --', 'redirect-gateway-manager' ); ?></option>
                    <?php if ( ! empty( $selected_month ) ) : ?>
                        <option value="month">
                            <?php 
                            /* translators: %s: Month and year */
                            printf( esc_html__( 'Delete logs for %s', 'redirect-gateway-manager' ), esc_html( $selected_month ) ); 
                            ?>
                        </option>
                    <?php endif; ?>
                    <option value="all"><?php esc_html_e( 'Delete all logs', 'redirect-gateway-manager' ); ?></option>
                </select>
                
                <?php if ( ! empty( $selected_month ) ) : ?>
                    <input type="hidden" name="filter_month" value="<?php echo esc_attr($selected_month); ?>">
                <?php endif; ?>

                <input type="submit" name="wprg_delete_logs" class="button" style="color: #d63638; border-color: #d63638;" value="<?php esc_attr_e( 'Execute Deletion', 'redirect-gateway-manager' ); ?>">
            </form>
        </div>

        <div class="wprg-form-container" style="flex: 1; min-width: 300px; border-left: 4px solid #00a32a;">
            <h3 style="color: #00a32a;">📊 <?php esc_html_e( 'Export Data (Excel)', 'redirect-gateway-manager' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="action" value="wprg_export_logs_csv">
                <?php wp_nonce_field( 'wprg_export_logs_action', 'wprg_export_logs_nonce' ); ?>
                
                <div style="width: 60%; font-size: 13px; color: #555;">
                    <?php if ( ! empty( $selected_month ) ) : ?>
                        <input type="hidden" name="filter_month" value="<?php echo esc_attr($selected_month); ?>">
                        <?php 
                            /* translators: %s: Month and year */
                            printf( esc_html__( 'Exporting month: %s', 'redirect-gateway-manager' ), '<strong>' . esc_html($selected_month) . '</strong>' ); 
                        ?>
                    <?php else: ?>
                        <?php 
                            /* translators: %s: Type of export (All) */
                            printf( esc_html__( 'Export %s (May be heavy)', 'redirect-gateway-manager' ), '<strong>' . esc_html__( 'All', 'redirect-gateway-manager' ) . '</strong>' ); 
                        ?>
                    <?php endif; ?>
                </div>

                <button type="submit" class="button" style="color: #00a32a; border-color: #00a32a;"><span class="dashicons dashicons-download" style="margin-top: 3px;"></span> <?php esc_attr_e( 'Download CSV', 'redirect-gateway-manager' ); ?></button>
            </form>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); position: relative; z-index: 10;">
        <table class="wp-list-table widefat fixed striped" style="border: none; border-top-left-radius: 8px; border-top-right-radius: 8px;">
            <thead>
                <tr>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Time', 'redirect-gateway-manager' ); ?></th>
                    <th style="width: 10%; border-bottom: 2px solid #ccd0d4;">
                        <?php esc_html_e( 'Status', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Accessed: Just clicked the link. Completed: Finished viewing the ad.', 'redirect-gateway-manager' ); ?></span></span>
                    </th>
                    <th style="width: 16%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Link Name', 'redirect-gateway-manager' ); ?></th>
                    
                    <th style="width: 10%; border-bottom: 2px solid #ccd0d4; text-align: center;">
                        <?php esc_html_e( 'Sub-ID', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Tracking campaign ID (e.g., ?subid=facebook_ads).', 'redirect-gateway-manager' ); ?></span></span>
                    </th>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4; text-align: center;">
                        <?php esc_html_e( 'Parameters', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'UTM tracking parameters attached to the link.', 'redirect-gateway-manager' ); ?></span></span>
                    </th>
                    
                    <th style="width: 11%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'IP Address', 'redirect-gateway-manager' ); ?></th>
                    <th style="width: 17%; border-bottom: 2px solid #ccd0d4;">
                        <?php esc_html_e( 'Source (Referrer)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'The website the user was on before clicking.', 'redirect-gateway-manager' ); ?></span></span>
                    </th>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Device', 'redirect-gateway-manager' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="8" style="padding: 20px; text-align: center; color: #666;"><?php esc_html_e( 'No log data available yet.', 'redirect-gateway-manager' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( wp_date('d/m/Y H:i', strtotime($log['clicked_at'])) ); ?></strong></td>

                            <td>
                                <?php if ( isset($log['status']) && $log['status'] === 'completed' ) : ?>
                                    <span style="background: #e1fbd6; color: #00a32a; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php esc_html_e( 'Completed', 'redirect-gateway-manager' ); ?></span>
                                <?php else : ?>
                                    <span style="background: #f0f0f1; color: #666; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php esc_html_e( 'Accessed', 'redirect-gateway-manager' ); ?></span>
                                <?php endif; ?>
                            </td>

                            <td><span style="color:#0073aa; font-weight:bold;"><?php echo esc_html( $log['link_name'] ? $log['link_name'] : __( 'Deleted Link', 'redirect-gateway-manager' ) ); ?></span></td>
                            
                            <td style="text-align: center;">
                                <?php if ( !empty($log['sub_id']) ) : ?>
                                    <input type="text" readonly value="<?php echo esc_attr( $log['sub_id'] ); ?>" title="<?php esc_attr_e( 'Click to highlight', 'redirect-gateway-manager' ); ?>" onfocus="this.select();" style="width: 100%; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #d63638; font-weight: bold; font-family: monospace; cursor: copy; text-align: center; box-shadow: none;" />
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if ( !empty($log['url_params']) ) : ?>
                                    <input type="text" readonly value="?<?php echo esc_attr( $log['url_params'] ); ?>" title="<?php esc_attr_e( 'Click to highlight', 'redirect-gateway-manager' ); ?>" onfocus="this.select();" style="width: 100%; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #666; font-family: monospace; cursor: copy; box-shadow: none;" />
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>

                            <td><code><?php echo esc_html( $log['ip_address'] ); ?></code></td>
                            <td>
                                <?php if ( $log['referrer'] && $log['referrer'] !== 'Direct / None' && $log['referrer'] !== 'Trực tiếp' ) : ?>
                                    <a href="<?php echo esc_url( 'http://' . $log['referrer'] ); ?>" target="_blank" style="display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none;">
                                        <?php echo esc_html( $log['referrer'] ); ?>
                                    </a>
                                <?php else: ?>
                                    <em style="color:#999;"><?php esc_html_e( 'Direct', 'redirect-gateway-manager' ); ?></em>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <span style="display: inline-block; padding: 4px 8px; background: #f0f0f1; border-radius: 4px; font-size: 12px; font-weight: 500; color: #444; cursor: help; white-space: nowrap;" title="<?php echo esc_attr( $log['user_agent'] ); ?>">
                                    <?php echo esc_html( wprg_get_device_type( $log['user_agent'] ) ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Showing up to 500 latest clicks.', 'redirect-gateway-manager' ); ?></p>
</div>