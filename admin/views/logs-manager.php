<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Hàm dịch User Agent thành loại Thiết bị giao diện thân thiện
if ( ! function_exists( 'wprg_get_device_type' ) ) {
    function wprg_get_device_type( $user_agent ) {
        $ua = strtolower( $user_agent );
        if ( empty( $ua ) || $ua === 'unknown' ) return __( '❓ Không rõ', 'wp-redirect-gateway' ); 
        
        if ( preg_match( '/bot|crawl|slurp|spider|mediapartners|google|bing|yandex|facebook/i', $ua ) ) {
            return __( '🤖 Robot (Bot)', 'wp-redirect-gateway' ); 
        }
        if ( preg_match( '/ipad|tablet|kindle|playbook|silk/i', $ua ) ) {
            return __( '💊 Máy tính bảng', 'wp-redirect-gateway' ); 
        }
        if ( preg_match( '/mobile|android|iphone|ipod|blackberry|windows phone/i', $ua ) ) {
            return __( '📱 Điện thoại', 'wp-redirect-gateway' ); 
        }
        return __( '💻 Máy tính', 'wp-redirect-gateway' ); 
    }
}

global $wpdb;
$table_logs  = $wpdb->prefix . 'rg_logs';
$table_links = $wpdb->prefix . 'rg_links';

// --- XỬ LÝ XÓA LOG ---
if ( isset( $_POST['wprg_delete_logs'] ) && check_admin_referer( 'wprg_delete_logs_nonce' ) ) {
    $delete_type = sanitize_text_field( $_POST['delete_type'] );
    
    if ( $delete_type === 'all' ) {
        $wpdb->query( "TRUNCATE TABLE $table_logs" );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Đã xóa sạch toàn bộ Log!', 'wp-redirect-gateway' ) . '</p></div>';
    } elseif ( $delete_type === 'month' && !empty($_POST['filter_month']) ) {
        $month_year = sanitize_text_field( $_POST['filter_month'] ); 
        $wpdb->query( $wpdb->prepare( "DELETE FROM $table_logs WHERE DATE_FORMAT(clicked_at, '%%Y-%%m') = %s", $month_year ) );
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Đã xóa log của tháng %s thành công.', 'wp-redirect-gateway' ), esc_html( $month_year ) ) . '</p></div>';
    }
}

// --- XỬ LÝ LỌC & QUERY DỮ LIỆU ---
$selected_month = isset( $_GET['filter_month'] ) ? sanitize_text_field( $_GET['filter_month'] ) : '';
$where_clause = "";
if ( ! empty( $selected_month ) ) {
    $where_clause = $wpdb->prepare( "WHERE DATE_FORMAT(lg.clicked_at, '%%Y-%%m') = %s", $selected_month );
}

$query = "
    SELECT lg.*, lk.name as link_name 
    FROM $table_logs lg
    LEFT JOIN $table_links lk ON lg.link_id = lk.id
    $where_clause
    ORDER BY lg.clicked_at DESC
    LIMIT 500
";
$logs = $wpdb->get_results( $query, ARRAY_A );

$months_query = "SELECT DISTINCT DATE_FORMAT(clicked_at, '%Y-%m') as month_year FROM $table_logs ORDER BY month_year DESC";
$available_months = $wpdb->get_col( $months_query );

// --- TRUY VẤN THỐNG KÊ NHANH ---
$total_clicks = $wpdb->get_var( "SELECT COUNT(id) FROM $table_logs lg $where_clause" );
$where_completed = !empty($where_clause) ? $where_clause . " AND status = 'completed'" : "WHERE status = 'completed'";
$completed_clicks = $wpdb->get_var( "SELECT COUNT(id) FROM $table_logs lg $where_completed" );
$conversion_rate = ($total_clicks > 0) ? round(($completed_clicks / $total_clicks) * 100, 2) : 0;

$top_link = $wpdb->get_row( "
    SELECT lk.name, COUNT(lg.id) as clicks 
    FROM $table_logs lg 
    LEFT JOIN $table_links lk ON lg.link_id = lk.id 
    $where_clause 
    GROUP BY lg.link_id 
    ORDER BY clicks DESC 
    LIMIT 1
" );

$top_referrers = $wpdb->get_results( "
    SELECT referrer, COUNT(id) as clicks 
    FROM $table_logs lg 
    WHERE referrer != 'Direct / None' AND referrer != 'Trực tiếp' AND referrer IS NOT NULL AND referrer != ''
    " . ( !empty($selected_month) ? " AND " . str_replace("WHERE ", "", $where_clause) : "" ) . "
    GROUP BY referrer 
    ORDER BY clicks DESC 
    LIMIT 3
" );
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Thống kê & Lịch sử Click', 'wp-redirect-gateway' ); ?></h1>
    <hr class="wp-header-end">

    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px;">
        <div class="wprg-stat-card success">
            <h4>
                <?php esc_html_e( 'Tổng Truy Cập / Hoàn Thành', 'wp-redirect-gateway' ); ?>
                <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Số lượt click vào link / Số lượt xem xong quảng cáo để lấy link đích.', 'wp-redirect-gateway' ); ?></span></span>
            </h4>
            <span style="font-size: 32px; font-weight: bold; color: #0073aa; line-height: 1;"><?php echo number_format_i18n( $total_clicks ?: 0 ); ?></span>
            <span style="font-size: 18px; color: #00a32a; font-weight: bold;"> / <?php echo number_format_i18n( $completed_clicks ?: 0 ); ?></span>
            <span style="font-size: 14px; color: #d63638; font-weight: bold; margin-left: 5px;" title="<?php esc_attr_e( 'Conversion Rate (Tỉ lệ chuyển đổi)', 'wp-redirect-gateway' ); ?>">(CR: <?php echo $conversion_rate; ?>%)</span>
        </div>
        
        <div class="wprg-stat-card">
            <h4>
                <?php esc_html_e( 'Link Nhấp Nhiều Nhất', 'wp-redirect-gateway' ); ?>
            </h4>
            <?php if ( $top_link ) : ?>
                <span style="font-size: 16px; font-weight: bold; display: block; margin-bottom: 5px;"><?php echo esc_html( $top_link->name ? $top_link->name : __( 'Link đã xóa', 'wp-redirect-gateway' ) ); ?></span>
                <span style="color: #00a32a; font-weight: bold;"><?php printf( esc_html__( '%s click', 'wp-redirect-gateway' ), number_format_i18n( $top_link->clicks ) ); ?></span>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e( 'Chưa có dữ liệu', 'wp-redirect-gateway' ); ?></span>
            <?php endif; ?>
        </div>

        <div class="wprg-stat-card warning">
            <h4>
                <?php esc_html_e( 'Top 3 Nguồn Truy Cập (Referrer)', 'wp-redirect-gateway' ); ?>
                <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Nơi người dùng nhấp vào link của bạn (Facebook, Youtube, Website khác...).', 'wp-redirect-gateway' ); ?></span></span>
            </h4>
            <?php if ( ! empty( $top_referrers ) ) : ?>
                <ul style="margin: 0; padding: 0; list-style: none;">
                    <?php foreach ( $top_referrers as $ref ) : ?>
                        <li style="margin-bottom: 5px; display: flex; justify-content: space-between; max-width: 400px;">
                            <a href="<?php echo esc_url( 'http://' . $ref->referrer ); ?>" target="_blank" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80%;"><?php echo esc_html( $ref->referrer ); ?></a>
                            <span style="background: #e5e5e5; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php echo number_format_i18n( $ref->clicks ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e( 'Chưa có nguồn truy cập ngoại', 'wp-redirect-gateway' ); ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 20px; position: relative; z-index: 1;">
        <div class="wprg-form-container" style="flex: 1; min-width: 300px;">
            <h3>🔍 <?php esc_html_e( 'Lọc hiển thị', 'wp-redirect-gateway' ); ?></h3>
            <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="wprg-logs">
                <select name="filter_month" style="width: 60%;">
                    <option value=""><?php esc_html_e( '-- Tất cả các tháng --', 'wp-redirect-gateway' ); ?></option>
                    <?php foreach ( $available_months as $m ) : ?>
                        <option value="<?php echo esc_attr($m); ?>" <?php selected( $selected_month, $m ); ?>>
                            <?php echo esc_html__( 'Tháng ', 'wp-redirect-gateway' ) . esc_html($m); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" class="button" value="<?php esc_attr_e( 'Lọc Log', 'wp-redirect-gateway' ); ?>">
            </form>
        </div>

        <div class="wprg-form-container" style="flex: 1; min-width: 300px; border-left: 4px solid #d63638;">
            <h3 style="color: #d63638;">🗑️ <?php esc_html_e( 'Dọn dẹp Database', 'wp-redirect-gateway' ); ?></h3>
            <form method="post" action="" onsubmit="return confirm('<?php esc_js( esc_html_e( 'Hành động này không thể hoàn tác. Bạn chắc chắn chứ?', 'wp-redirect-gateway' ) ); ?>');" style="display: flex; gap: 10px; align-items: center;">
                <?php wp_nonce_field( 'wprg_delete_logs_nonce' ); ?>
                <select name="delete_type" required style="width: 60%;">
                    <option value=""><?php esc_html_e( '-- Chọn kiểu xóa --', 'wp-redirect-gateway' ); ?></option>
                    <?php if ( ! empty( $selected_month ) ) : ?>
                        <option value="month"><?php printf( esc_html__( 'Xóa Log tháng %s', 'wp-redirect-gateway' ), esc_html( $selected_month ) ); ?></option>
                    <?php endif; ?>
                    <option value="all"><?php esc_html_e( 'Xóa sạch toàn bộ Log', 'wp-redirect-gateway' ); ?></option>
                </select>
                
                <?php if ( ! empty( $selected_month ) ) : ?>
                    <input type="hidden" name="filter_month" value="<?php echo esc_attr($selected_month); ?>">
                <?php endif; ?>

                <input type="submit" name="wprg_delete_logs" class="button" style="color: #d63638; border-color: #d63638;" value="<?php esc_attr_e( 'Thực thi Xóa', 'wp-redirect-gateway' ); ?>">
            </form>
        </div>

        <div class="wprg-form-container" style="flex: 1; min-width: 300px; border-left: 4px solid #00a32a;">
            <h3 style="color: #00a32a;">📊 <?php esc_html_e( 'Xuất Dữ Liệu (Excel)', 'wp-redirect-gateway' ); ?></h3>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="action" value="wprg_export_logs_csv">
                <?php wp_nonce_field( 'wprg_export_logs_action', 'wprg_export_logs_nonce' ); ?>
                
                <div style="width: 60%; font-size: 13px; color: #555;">
                    <?php if ( ! empty( $selected_month ) ) : ?>
                        <input type="hidden" name="filter_month" value="<?php echo esc_attr($selected_month); ?>">
                        <?php printf( esc_html__( 'Đang xuất tháng: %s', 'wp-redirect-gateway' ), '<strong>' . esc_html($selected_month) . '</strong>' ); ?>
                    <?php else: ?>
                        <?php printf( esc_html__( 'Xuất %s (Có thể nặng)', 'wp-redirect-gateway' ), '<strong>' . esc_html__( 'Tất cả', 'wp-redirect-gateway' ) . '</strong>' ); ?>
                    <?php endif; ?>
                </div>

                <button type="submit" class="button" style="color: #00a32a; border-color: #00a32a;"><span class="dashicons dashicons-download" style="margin-top: 3px;"></span> <?php esc_attr_e( 'Tải file CSV', 'wp-redirect-gateway' ); ?></button>
            </form>
        </div>
    </div>

    <div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); position: relative; z-index: 10;">
        <table class="wp-list-table widefat fixed striped" style="border: none; border-top-left-radius: 8px; border-top-right-radius: 8px;">
            <thead>
                <tr>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Thời gian', 'wp-redirect-gateway' ); ?></th>
                    <th style="width: 10%; border-bottom: 2px solid #ccd0d4;">
                        <?php esc_html_e( 'Trạng thái', 'wp-redirect-gateway' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Truy cập: Mới bấm vào link. Hoàn thành: Đã xem xong quảng cáo.', 'wp-redirect-gateway' ); ?></span></span>
                    </th>
                    <th style="width: 16%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Tên Link', 'wp-redirect-gateway' ); ?></th>
                    
                    <th style="width: 10%; border-bottom: 2px solid #ccd0d4; text-align: center;">
                        <?php esc_html_e( 'Sub-ID', 'wp-redirect-gateway' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'ID chiến dịch theo dõi (ví dụ: ?subid=facebook_ads).', 'wp-redirect-gateway' ); ?></span></span>
                    </th>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4; text-align: center;">
                        <?php esc_html_e( 'Tham số', 'wp-redirect-gateway' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Các đuôi tracking UTM gắn kèm sau link.', 'wp-redirect-gateway' ); ?></span></span>
                    </th>
                    
                    <th style="width: 11%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Địa chỉ IP', 'wp-redirect-gateway' ); ?></th>
                    <th style="width: 17%; border-bottom: 2px solid #ccd0d4;">
                        <?php esc_html_e( 'Nguồn (Referrer)', 'wp-redirect-gateway' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Trang web mà khách hàng đang đứng trước khi click.', 'wp-redirect-gateway' ); ?></span></span>
                    </th>
                    <th style="width: 12%; border-bottom: 2px solid #ccd0d4;"><?php esc_html_e( 'Thiết bị', 'wp-redirect-gateway' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $logs ) ) : ?>
                    <tr><td colspan="8" style="padding: 20px; text-align: center; color: #666;"><?php esc_html_e( 'Chưa có dữ liệu log nào.', 'wp-redirect-gateway' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $logs as $log ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( date('d/m/Y H:i', strtotime($log['clicked_at'])) ); ?></strong></td>

                            <td>
                                <?php if ( isset($log['status']) && $log['status'] === 'completed' ) : ?>
                                    <span style="background: #e1fbd6; color: #00a32a; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php esc_html_e( 'Hoàn thành', 'wp-redirect-gateway' ); ?></span>
                                <?php else : ?>
                                    <span style="background: #f0f0f1; color: #666; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;"><?php esc_html_e( 'Truy cập', 'wp-redirect-gateway' ); ?></span>
                                <?php endif; ?>
                            </td>

                            <td><span style="color:#0073aa; font-weight:bold;"><?php echo esc_html( $log['link_name'] ? $log['link_name'] : __( 'Link đã xóa', 'wp-redirect-gateway' ) ); ?></span></td>
                            
                            <td style="text-align: center;">
                                <?php if ( !empty($log['sub_id']) ) : ?>
                                    <input type="text" readonly value="<?php echo esc_attr( $log['sub_id'] ); ?>" title="<?php esc_attr_e( 'Click để bôi đen', 'wp-redirect-gateway' ); ?>" onfocus="this.select();" style="width: 100%; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #d63638; font-weight: bold; font-family: monospace; cursor: copy; text-align: center; box-shadow: none;" />
                                <?php else: ?>
                                    <span style="color: #ccc;">-</span>
                                <?php endif; ?>
                            </td>
                            
                            <td style="text-align: center;">
                                <?php if ( !empty($log['url_params']) ) : ?>
                                    <input type="text" readonly value="?<?php echo esc_attr( $log['url_params'] ); ?>" title="<?php esc_attr_e( 'Click để bôi đen', 'wp-redirect-gateway' ); ?>" onfocus="this.select();" style="width: 100%; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #666; font-family: monospace; cursor: copy; box-shadow: none;" />
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
                                    <em style="color:#999;"><?php esc_html_e( 'Trực tiếp', 'wp-redirect-gateway' ); ?></em>
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
    <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Đang hiển thị tối đa 500 click mới nhất.', 'wp-redirect-gateway' ); ?></p>
</div>