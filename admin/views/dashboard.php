<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
global $wpdb;
$table_links = $wpdb->prefix . 'rg_links';
$table_logs  = $wpdb->prefix . 'rg_logs';

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$total_links = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_links );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$total_clicks = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_logs );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$top_ips = $wpdb->get_results( "SELECT ip_address, COUNT(id) as total_clicks FROM " . $table_logs . " GROUP BY ip_address ORDER BY total_clicks DESC LIMIT 20", ARRAY_A );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$top_links = $wpdb->get_results( "SELECT lk.name, COUNT(lg.id) as total_clicks FROM " . $table_logs . " lg LEFT JOIN " . $table_links . " lk ON lg.link_id = lk.id GROUP BY lg.link_id ORDER BY total_clicks DESC LIMIT 20", ARRAY_A );

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
$chart_data_raw = $wpdb->get_results( "SELECT HOUR(clicked_at) as click_hour, COUNT(id) as total_clicks FROM " . $table_logs . " GROUP BY HOUR(clicked_at) ORDER BY click_hour ASC", ARRAY_A );

$hours_array = array_fill(0, 24, 0);
if ( $chart_data_raw ) {
    foreach ( $chart_data_raw as $row ) {
        $hours_array[ intval( $row['click_hour'] ) ] = intval( $row['total_clicks'] );
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Overview Statistics (Dashboard)', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <div style="display: flex; gap: 20px; margin-bottom: 20px; margin-top: 20px;">
        <div style="flex: 1; background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e( 'Total Links Created', 'redirect-gateway-manager' ); ?></h3>
            <span style="font-size: 32px; font-weight: bold; color: #0073aa;"><?php echo number_format( intval( $total_links ) ); ?></span>
        </div>
        <div style="flex: 1; background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e( 'Total System Clicks', 'redirect-gateway-manager' ); ?></h3>
            <span style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo number_format( intval( $total_clicks ) ); ?></span>
        </div>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
        <h3><?php esc_html_e( 'Hourly Clicks Chart (Server Time)', 'redirect-gateway-manager' ); ?></h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="clicksChart" 
                data-chart="<?php echo esc_attr( wp_json_encode( array_values( $hours_array ) ) ); ?>" 
                data-label="<?php esc_attr_e( 'Number of link clicks', 'redirect-gateway-manager' ); ?>" 
                data-title="<?php esc_attr_e( 'Time slot: ', 'redirect-gateway-manager' ); ?>">
            </canvas>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4;">
            <h3 style="margin: 0; padding: 15px; background: #f9f9f9; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Top 20 Most Clicked Links', 'redirect-gateway-manager' ); ?></h3>
            <div style="padding: 15px;">
                <table class="wp-list-table widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Link Name', 'redirect-gateway-manager' ); ?></th><th><?php esc_html_e( 'Clicks Count', 'redirect-gateway-manager' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( empty($top_links) ) : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'No data available.', 'redirect-gateway-manager' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $top_links as $link ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $link['name'] ? $link['name'] : __( 'Deleted Link', 'redirect-gateway-manager' ) ); ?></strong></td>
                                    <td><?php echo number_format( intval( $link['total_clicks'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4;">
            <h3 style="margin: 0; padding: 15px; background: #f9f9f9; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Top 20 Most Active IPs', 'redirect-gateway-manager' ); ?></h3>
            <div style="padding: 15px;">
                <table class="wp-list-table widefat striped">
                    <thead><tr><th><?php esc_html_e( 'IP Address', 'redirect-gateway-manager' ); ?></th><th><?php esc_html_e( 'Clicks Count', 'redirect-gateway-manager' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( empty($top_ips) ) : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'No data available.', 'redirect-gateway-manager' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $top_ips as $ip ) : ?>
                                <tr>
                                    <td><code><?php echo esc_html( $ip['ip_address'] ); ?></code></td>
                                    <td><?php echo number_format( intval( $ip['total_clicks'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>