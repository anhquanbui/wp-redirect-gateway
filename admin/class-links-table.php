<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class WPRG_Links_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'link',
            'plural'   => 'links',
            'ajax'     => false
        ) );
    }

    public function get_columns() {
        return array(
            'cb'               => '<input type="checkbox" />',
            'name'             => __( 'Tên Link', 'wp-redirect-gateway' ),
            'original_url'     => __( 'Link Gốc', 'wp-redirect-gateway' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'Đường dẫn đích mà khách sẽ được chuyển hướng tới.', 'wp-redirect-gateway' ) . '</span></span>',
            'slug'             => __( 'Link Chia sẻ', 'wp-redirect-gateway' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'Đây là link redirect của bạn, sẽ mở ra cổng Gateway. Định dạng đầy đủ khi Copy: yourdomain.com/go/[slug]', 'wp-redirect-gateway' ) . '</span></span>',
            'inline_code'      => __( 'Mã chèn (Nút)', 'wp-redirect-gateway' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'Đây là mã shortcode dùng để chèn vào page / post. Định dạng đầy đủ khi Copy: [wprg_inline_button slug="[slug]"]', 'wp-redirect-gateway' ) . '</span></span>', 
            'ad_count'         => __( 'Số QC', 'wp-redirect-gateway' ),
            'wait_time'        => __( 'Thời gian', 'wp-redirect-gateway' ),
            'completed_clicks' => __( 'Thành công', 'wp-redirect-gateway' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'Số lượt khách xem xong quảng cáo và lấy link thành công.', 'wp-redirect-gateway' ) . '</span></span>',
            'created_at'       => __( 'Ngày tạo', 'wp-redirect-gateway' )
        );
    }

    // [MỚI] Khai báo các cột cho phép Click để sắp xếp
    protected function get_sortable_columns() {
        return array(
            'name'             => array('name', false),
            'completed_clicks' => array('completed_clicks', false), // Cột đếm click có thể sắp xếp
            'created_at'       => array('created_at', false)
        );
    }

    protected function get_bulk_actions() {
        return array(
            'bulk-delete' => __( 'Xóa', 'wp-redirect-gateway' )
        );
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rg_links';

        $ad_counts = $wpdb->get_col( "SELECT DISTINCT ad_count FROM $table_name ORDER BY ad_count ASC" );
        $months = $wpdb->get_results( "SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month FROM $table_name ORDER BY created_at DESC" );

        $selected_ad = isset( $_REQUEST['filter_ad_count'] ) ? sanitize_text_field( $_REQUEST['filter_ad_count'] ) : '';
        $selected_month = isset( $_REQUEST['filter_month'] ) ? sanitize_text_field( $_REQUEST['filter_month'] ) : '';

        echo '<div class="alignleft actions">';

        echo '<select name="filter_ad_count">';
        echo '<option value="">' . __( 'Tất cả số QC', 'wp-redirect-gateway' ) . '</option>';
        if ( ! empty( $ad_counts ) ) {
            foreach ( $ad_counts as $count ) {
                printf( '<option value="%s" %s>%s</option>', esc_attr( $count ), selected( $selected_ad, $count, false ), sprintf( esc_html__( '%s QC', 'wp-redirect-gateway' ), esc_html( $count ) ) );
            }
        }
        echo '</select>';

        echo '<select name="filter_month">';
        echo '<option value="">' . __( 'Tất cả các tháng', 'wp-redirect-gateway' ) . '</option>';
        if ( ! empty( $months ) ) {
            foreach ( $months as $m ) {
                $val = sprintf( '%04d-%02d', $m->year, $m->month );
                $label = sprintf( '%02d/%04d', $m->month, $m->year );
                printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $selected_month, $val, false ), esc_html( $label ) );
            }
        }
        echo '</select>';

        submit_button( __( 'Lọc', 'wp-redirect-gateway' ), 'button', 'filter_action', false );
        echo '</div>';
    }

    protected function column_name( $item ) {
        $edit_nonce = wp_create_nonce( 'wprg_edit_link' );
        $delete_nonce = wp_create_nonce( 'wprg_delete_link' );
        
        $actions = array(
            'edit'      => sprintf( '<a href="?page=%s&action=%s&link_id=%s&_wpnonce=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'edit', absint( $item['id'] ), $edit_nonce, __( 'Sửa', 'wp-redirect-gateway' ) ),
            'duplicate' => sprintf( '<a href="?page=%s&action=%s&link_id=%s">%s</a>', esc_attr( $_REQUEST['page'] ), 'duplicate', absint( $item['id'] ), __( 'Nhân bản', 'wp-redirect-gateway' ) ),
            'delete'    => sprintf( '<a href="?page=%s&action=%s&link_id=%s&_wpnonce=%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>', esc_attr( $_REQUEST['page'] ), 'delete', absint( $item['id'] ), $delete_nonce, esc_js( __( 'Bạn có chắc chắn muốn xóa link này?', 'wp-redirect-gateway' ) ), __( 'Xóa', 'wp-redirect-gateway' ) ),
        );

        $lock_icon = !empty($item['password']) ? ' <span title="' . esc_attr__( 'Có mật khẩu bảo vệ', 'wp-redirect-gateway' ) . ': ' . esc_attr($item['password']) . '" style="color: #d63638; font-size: 14px;">🔒</span>' : '';
        $display_name = mb_strimwidth( $item['name'], 0, 80, '...' );

        return sprintf( '%1$s%2$s %3$s', '<strong style="font-size:14px; color:#2271b1; display:block; word-wrap:break-word; white-space:normal; line-height:1.4;">' . esc_html( $display_name ) . '</strong>', $lock_icon, $this->row_actions( $actions ) );
    }

    protected function column_cb( $item ) {
        return sprintf( '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id'] );
    }

    protected function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'original_url':
                return '<div style="display:flex; align-items:center; gap:4px; max-width: 100%;">
                            <div style="flex: 1; min-width: 0; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 4px 8px;">
                                <a href="' . esc_url( $item['original_url'] ) . '" target="_blank" style="display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-decoration:none; font-size: 12px; color: #666;" title="' . esc_attr( $item['original_url'] ) . '">' . esc_html( $item['original_url'] ) . '</a>
                            </div>
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $item['original_url'] ) . '" title="' . esc_attr__( 'Copy Link gốc', 'wp-redirect-gateway' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'slug':
                $redirect_url = site_url( '/go/' . $item['slug'] );
                return '<div style="display:flex; align-items:center; gap:4px;">
                            <input type="text" readonly value="' . esc_attr( $item['slug'] ) . '" title="' . esc_attr__( 'Hiện Slug rút gọn - Bấm Copy để lấy Full Link', 'wp-redirect-gateway' ) . '" onfocus="this.select();" style="flex: 1; min-width: 40px; border: 1px solid #85c2e1; background: #f0f8ff; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #0073aa; font-weight: bold; font-family: monospace; text-align: center; box-shadow: none;" />
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $redirect_url ) . '" title="' . esc_attr__( 'Copy Full Link', 'wp-redirect-gateway' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'inline_code': 
                $inline_code = '[wprg_inline_button slug="' . esc_attr( $item['slug'] ) . '"]';
                return '<div style="display:flex; align-items:center; gap:4px;">
                            <input type="text" readonly value="' . esc_attr( $item['slug'] ) . '" title="' . esc_attr__( 'Hiện Slug rút gọn - Bấm Copy để lấy Full Mã chèn', 'wp-redirect-gateway' ) . '" onfocus="this.select();" style="flex: 1; min-width: 40px; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #d63638; font-weight: bold; font-family: monospace; text-align: center; box-shadow: none;" />
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $inline_code ) . '" title="' . esc_attr__( 'Copy Full Mã chèn', 'wp-redirect-gateway' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'ad_count':
                return '<span class="badge" style="background:#0073aa;color:#fff;padding:3px 8px;border-radius:10px;">' . esc_html( $item[ $column_name ] ) . '</span>';
            
            case 'wait_time': 
                if ( ! empty( $item['wait_time'] ) ) {
                    return '<span style="color: #d63638; font-weight: bold;">' . esc_html( $item['wait_time'] ) . 's</span>';
                } else {
                    return '<span style="color: #999; font-style: italic;">' . esc_html__( 'Mặc định', 'wp-redirect-gateway' ) . '</span>';
                }
            
            // [MỚI] Hiển thị số click lấy link thành công
            case 'completed_clicks':
                $clicks = intval( $item['completed_clicks'] );
                $color = $clicks > 0 ? '#00a32a' : '#999';
                $bg = $clicks > 0 ? '#e1fbd6' : '#f0f0f1';
                return '<span style="color: ' . $color . '; font-weight: bold; background: ' . $bg . '; padding: 3px 8px; border-radius: 10px; display: inline-block;">' . number_format_i18n( $clicks ) . '</span>';

            case 'created_at':
                return esc_html( date( 'd/m/Y', strtotime( $item[ $column_name ] ) ) );
            default:
                return print_r( $item, true );
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'rg_links';
        $table_logs = $wpdb->prefix . 'rg_logs';

        $per_page = 20; 
        $current_page = $this->get_pagenum();

        $where_clauses = array("1=1"); 
        $query_args = array();

        if ( isset( $_REQUEST['s'] ) && ! empty( trim( $_REQUEST['s'] ) ) ) {
            $search_term = sanitize_text_field( trim( $_REQUEST['s'] ) );
            $where_clauses[] = "(name LIKE %s OR original_url LIKE %s)";
            $query_args[] = '%' . $wpdb->esc_like( $search_term ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $search_term ) . '%';
        }

        if ( isset( $_REQUEST['filter_ad_count'] ) && $_REQUEST['filter_ad_count'] !== '' ) {
            $where_clauses[] = "ad_count = %d";
            $query_args[] = intval( $_REQUEST['filter_ad_count'] );
        }

        if ( isset( $_REQUEST['filter_month'] ) && ! empty( $_REQUEST['filter_month'] ) ) {
            $month_val = sanitize_text_field( $_REQUEST['filter_month'] );
            $where_clauses[] = "DATE_FORMAT(created_at, '%%Y-%%m') = %s"; 
            $query_args[] = $month_val;
        }

        $where_sql = implode( ' AND ', $where_clauses );

        $sql_count = "SELECT COUNT(id) FROM $table_name WHERE $where_sql";
        $total_items = empty( $query_args ) ? $wpdb->get_var( $sql_count ) : $wpdb->get_var( $wpdb->prepare( $sql_count, $query_args ) );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns(); // Đã bật tính năng Sort
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // [MỚI] Bắt sự kiện Click tiêu đề để sắp xếp (ASC / DESC)
        $orderby = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'name', 'created_at', 'completed_clicks', 'id' ) ) ) ? $_REQUEST['orderby'] : 'id';
        $order = ( isset( $_REQUEST['order'] ) && in_array( strtoupper( $_REQUEST['order'] ), array( 'ASC', 'DESC' ) ) ) ? strtoupper( $_REQUEST['order'] ) : 'DESC';

        $offset = ( $current_page - 1 ) * $per_page;
        
        // [MỚI] SubQuery: Gắn kèm logic đếm số click thành công từ bảng Log cho từng Link
        $sql_data = "
            SELECT *, 
            (SELECT COUNT(id) FROM $table_logs WHERE link_id = $table_name.id AND status = 'completed') as completed_clicks
            FROM $table_name 
            WHERE $where_sql 
            ORDER BY $orderby $order 
            LIMIT %d OFFSET %d
        ";
        
        $query_args[] = $per_page;
        $query_args[] = $offset;

        $query = $wpdb->prepare( $sql_data, $query_args );
        
        $this->items = $wpdb->get_results( $query, ARRAY_A );
    }

    public function display() {
        // Đã canh lại tỷ lệ % độ rộng các cột để chèn cột "Thành công" vào cho đẹp
        echo '<style>
            .wp-list-table .column-cb { width: 3%; }
            .wp-list-table .column-name { width: 17%; }
            .wp-list-table .column-original_url { width: 23%; } 
            .wp-list-table .column-slug { width: 12%; }
            .wp-list-table .column-inline_code { width: 12%; }
            .wp-list-table .column-ad_count { width: 6%; text-align: center; }
            .wp-list-table .column-wait_time { width: 7%; text-align: center; }
            .wp-list-table .column-completed_clicks { width: 10%; text-align: center; }
            .wp-list-table .column-created_at { width: 10%; }
            .wp-list-table th { border-bottom: 2px solid #ccd0d4; }
        </style>';
        
        parent::display();

        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                document.body.addEventListener("click", function(e) {
                    let btn = e.target.closest(".wprg-btn-copy");
                    if (btn) {
                        e.preventDefault();
                        let textToCopy = btn.getAttribute("data-copy");
                        if (textToCopy) {
                            navigator.clipboard.writeText(textToCopy).then(() => {
                                let icon = btn.querySelector(".dashicons");
                                if (icon) {
                                    icon.classList.remove("dashicons-admin-page");
                                    icon.classList.add("dashicons-saved");
                                    icon.style.color = "#00a32a";
                                    setTimeout(() => {
                                        icon.classList.add("dashicons-admin-page");
                                        icon.classList.remove("dashicons-saved");
                                        icon.style.color = "";
                                    }, 1500);
                                }
                            });
                        }
                    }
                });
            });
        </script>';
    }
}