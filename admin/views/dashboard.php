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
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Thống kê Tổng quan (Dashboard)', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <div style="display: flex; gap: 20px; margin-bottom: 20px; margin-top: 20px;">
        <div style="flex: 1; background: #fff; padding: 20px; border-left: 4px solid #0073aa; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e( 'Tổng số Link đã tạo', 'redirect-gateway-manager' ); ?></h3>
            <span style="font-size: 32px; font-weight: bold; color: #0073aa;"><?php echo number_format( intval( $total_links ) ); ?></span>
        </div>
        <div style="flex: 1; background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin: 0 0 10px 0; color: #666;"><?php esc_html_e( 'Tổng lượt Click hệ thống', 'redirect-gateway-manager' ); ?></h3>
            <span style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo number_format( intval( $total_clicks ) ); ?></span>
        </div>
    </div>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-bottom: 20px;">
        <h3><?php esc_html_e( 'Biểu đồ lượt Click theo giờ trong ngày (Giờ Server)', 'redirect-gateway-manager' ); ?></h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="clicksChart"></canvas>
        </div>
    </div>

    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4;">
            <h3 style="margin: 0; padding: 15px; background: #f9f9f9; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Top 20 Link nhấp nhiều nhất', 'redirect-gateway-manager' ); ?></h3>
            <div style="padding: 15px;">
                <table class="wp-list-table widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Tên Link', 'redirect-gateway-manager' ); ?></th><th><?php esc_html_e( 'Số lượt Click', 'redirect-gateway-manager' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( empty($top_links) ) : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'Chưa có dữ liệu.', 'redirect-gateway-manager' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $top_links as $link ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $link['name'] ? $link['name'] : __( 'Link đã xóa', 'redirect-gateway-manager' ) ); ?></strong></td>
                                    <td><?php echo number_format( intval( $link['total_clicks'] ) ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="flex: 1; min-width: 300px; background: #fff; border: 1px solid #ccd0d4;">
            <h3 style="margin: 0; padding: 15px; background: #f9f9f9; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Top 20 IP nhấp nhiều nhất', 'redirect-gateway-manager' ); ?></h3>
            <div style="padding: 15px;">
                <table class="wp-list-table widefat striped">
                    <thead><tr><th><?php esc_html_e( 'Địa chỉ IP', 'redirect-gateway-manager' ); ?></th><th><?php esc_html_e( 'Số lượt Click', 'redirect-gateway-manager' ); ?></th></tr></thead>
                    <tbody>
                        <?php if ( empty($top_ips) ) : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'Chưa có dữ liệu.', 'redirect-gateway-manager' ); ?></td></tr>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartData = <?php echo json_encode( array_values( $hours_array ) ); ?>;
    const chartLabels = ['00:00', '01:00', '02:00', '03:00', '04:00', '05:00', '06:00', '07:00', '08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00', '19:00', '20:00', '21:00', '22:00', '23:00'];

    const ctx = document.getElementById('clicksChart').getContext('2d');
    const clicksChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: '<?php echo esc_js( __( 'Số lượt nhấp link', 'redirect-gateway-manager' ) ); ?>',
                data: chartData,
                backgroundColor: 'rgba(0, 115, 170, 0.7)',
                borderColor: 'rgba(0, 115, 170, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 } 
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(context) { return '<?php echo esc_js( __( 'Khung giờ: ', 'redirect-gateway-manager' ) ); ?>' + context[0].label; }
                    }
                }
            }
        }
    });
});
</script>