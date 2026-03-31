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
            'name'             => __( 'Link Name', 'redirect-gateway-manager' ),
            'tag'              => __( 'Tag', 'redirect-gateway-manager' ),
            'original_url'     => __( 'Original Link', 'redirect-gateway-manager' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'The destination URL the visitor will be redirected to.', 'redirect-gateway-manager' ) . '</span></span>',
            'slug'             => __( 'Shareable Link', 'redirect-gateway-manager' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'This is your redirect link that opens the Gateway. Full format when copied: yourdomain.com/go/[slug]', 'redirect-gateway-manager' ) . '</span></span>',
            'inline_code'      => __( 'Inline Code (Button)', 'redirect-gateway-manager' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'This is the shortcode used to insert into a page / post. Full format when copied: [wprg_inline_button slug="[slug]"]', 'redirect-gateway-manager' ) . '</span></span>', 
            'ad_count'         => __( 'Ads Count', 'redirect-gateway-manager' ),
            'wait_time'        => __( 'Wait Time', 'redirect-gateway-manager' ),
            'completed_clicks' => __( 'Completed', 'redirect-gateway-manager' ) . ' <span class="wprg-tooltip-icon" style="margin-top:-2px;">?<span class="wprg-tooltip-text">' . esc_attr__( 'Number of visitors who finished watching the ads and successfully got the link.', 'redirect-gateway-manager' ) . '</span></span>',
            'created_at'       => __( 'Created At', 'redirect-gateway-manager' )
        );
    }

    protected function get_sortable_columns() {
        return array(
            'name'             => array('name', false),
            'tag'              => array('tag', false),
            'completed_clicks' => array('completed_clicks', false), 
            'created_at'       => array('created_at', false)
        );
    }

    protected function get_bulk_actions() {
        return array(
            'bulk-delete' => __( 'Delete', 'redirect-gateway-manager' )
        );
    }

    protected function extra_tablenav( $which ) {
        if ( $which !== 'top' ) return;
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'rg_links';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $ad_counts = $wpdb->get_col( "SELECT DISTINCT ad_count FROM {$table_name} ORDER BY ad_count ASC" );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $months = $wpdb->get_results( "SELECT DISTINCT YEAR(created_at) AS year, MONTH(created_at) AS month FROM {$table_name} ORDER BY created_at DESC" );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_ad = isset( $_REQUEST['filter_ad_count'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_ad_count'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $selected_month = isset( $_REQUEST['filter_month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_month'] ) ) : '';

        echo '<div class="alignleft actions">';

        echo '<select name="filter_ad_count">';
        echo '<option value="">' . esc_html__( 'All Ad Counts', 'redirect-gateway-manager' ) . '</option>';
        if ( ! empty( $ad_counts ) ) {
            foreach ( $ad_counts as $count ) {
                /* translators: %s: Number of ads (QC) */
                $label = sprintf( esc_html__( '%s Ads', 'redirect-gateway-manager' ), esc_html( $count ) );
                
                printf( '<option value="%s" %s>%s</option>', esc_attr( $count ), selected( $selected_ad, $count, false ), esc_html( $label ) );
            }
        }
        echo '</select>';

        echo '<select name="filter_month">';
        echo '<option value="">' . esc_html__( 'All months', 'redirect-gateway-manager' ) . '</option>';
        if ( ! empty( $months ) ) {
            foreach ( $months as $m ) {
                $val = sprintf( '%04d-%02d', $m->year, $m->month );
                $label = sprintf( '%02d/%04d', $m->month, $m->year );
                printf( '<option value="%s" %s>%s</option>', esc_attr( $val ), selected( $selected_month, $val, false ), esc_html( $label ) );
            }
        }
        echo '</select>';

        submit_button( __( 'Filter', 'redirect-gateway-manager' ), 'button', 'filter_action', false );
        echo '</div>';
    }

    protected function column_name( $item ) {
        $edit_nonce = wp_create_nonce( 'wprg_edit_link' );
        $delete_nonce = wp_create_nonce( 'wprg_delete_link' );
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $req_page = isset( $_REQUEST['page'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['page'] ) ) : '';
        
        $actions = array(
            'edit'      => sprintf( '<a href="?page=%s&action=%s&link_id=%s&_wpnonce=%s">%s</a>', esc_attr( $req_page ), 'edit', absint( $item['id'] ), $edit_nonce, __( 'Edit', 'redirect-gateway-manager' ) ),
            'duplicate' => sprintf( '<a href="?page=%s&action=%s&link_id=%s">%s</a>', esc_attr( $req_page ), 'duplicate', absint( $item['id'] ), __( 'Duplicate', 'redirect-gateway-manager' ) ),
            'delete'    => sprintf( '<a href="?page=%s&action=%s&link_id=%s&_wpnonce=%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>', esc_attr( $req_page ), 'delete', absint( $item['id'] ), $delete_nonce, esc_js( __( 'Are you sure you want to delete this link?', 'redirect-gateway-manager' ) ), __( 'Delete', 'redirect-gateway-manager' ) ),
        );

        $lock_icon = !empty($item['password']) ? ' <span title="' . esc_attr__( 'Password protected', 'redirect-gateway-manager' ) . ': ' . esc_attr($item['password']) . '" style="color: #d63638; font-size: 14px;">🔒</span>' : '';
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
                            <input type="text" readonly value="' . esc_attr( $item['original_url'] ) . '" title="' . esc_attr( $item['original_url'] ) . '" onfocus="this.select();" style="flex: 1; min-width: 40px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #666; box-shadow: none; font-family: monospace; cursor: text;" />
                            <a href="' . esc_url( $item['original_url'] ) . '" target="_blank" class="button button-small" title="' . esc_attr__( 'Open Destination Link', 'redirect-gateway-manager' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-external" style="font-size: 14px; margin-top: 3px;"></span></a>
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $item['original_url'] ) . '" title="' . esc_attr__( 'Copy Original Link', 'redirect-gateway-manager' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'tag':
                if ( !empty($item['tag']) ) {
                return '<span style="background: #f0f0f1; border: 1px solid #c3c4c7; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 500; display: inline-block;">' . esc_html( $item['tag'] ) . '</span>';
                }
                return '<span style="color: #ccc;">-</span>';        

            case 'slug':
                $redirect_url = site_url( '/go/' . $item['slug'] );
                return '<div style="display:flex; align-items:center; gap:4px;">
                            <input type="text" readonly value="' . esc_attr( $item['slug'] ) . '" title="' . esc_attr__( 'Showing short Slug - Click Copy to get Full Link', 'redirect-gateway-manager' ) . '" onfocus="this.select();" style="flex: 1; min-width: 40px; border: 1px solid #85c2e1; background: #f0f8ff; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #0073aa; font-weight: bold; font-family: monospace; text-align: center; box-shadow: none;" />
                            <a href="' . esc_url( $redirect_url ) . '" target="_blank" class="button button-small" title="' . esc_attr__( 'Open Shareable Link', 'redirect-gateway-manager' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-external" style="font-size: 14px; margin-top: 3px;"></span></a>
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $redirect_url ) . '" title="' . esc_attr__( 'Copy Full Link', 'redirect-gateway-manager' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'inline_code': 
                $inline_code = '[wprg_inline_button slug="' . esc_attr( $item['slug'] ) . '"]';
                return '<div style="display:flex; align-items:center; gap:4px;">
                            <input type="text" readonly value="' . esc_attr( $item['slug'] ) . '" title="' . esc_attr__( 'Showing short Slug - Click Copy to get Full Inline Code', 'redirect-gateway-manager' ) . '" onfocus="this.select();" style="flex: 1; min-width: 40px; border: 1px solid #ddd; background: #fafafa; border-radius: 4px; padding: 4px 6px; font-size: 11px; color: #d63638; font-weight: bold; font-family: monospace; text-align: center; box-shadow: none;" />
                            <button type="button" class="button button-small wprg-btn-copy" data-copy="' . esc_attr( $inline_code ) . '" title="' . esc_attr__( 'Copy Full Inline Code', 'redirect-gateway-manager' ) . '" style="padding: 0 4px; flex-shrink: 0;"><span class="dashicons dashicons-admin-page" style="font-size: 14px; margin-top: 3px;"></span></button>
                        </div>';
            
            case 'ad_count':
                return '<span class="badge" style="background:#0073aa;color:#fff;padding:3px 8px;border-radius:10px;">' . esc_html( $item[ $column_name ] ) . '</span>';
            
            case 'wait_time': 
                if ( ! empty( $item['wait_time'] ) ) {
                    return '<span style="color: #d63638; font-weight: bold;">' . esc_html( $item['wait_time'] ) . 's</span>';
                } else {
                    return '<span style="color: #999; font-style: italic;">' . esc_html__( 'Default', 'redirect-gateway-manager' ) . '</span>';
                }
            
            case 'completed_clicks':
                $clicks = intval( $item['completed_clicks'] );
                $color = $clicks > 0 ? '#00a32a' : '#999';
                $bg = $clicks > 0 ? '#e1fbd6' : '#f0f0f1';
                return '<span style="color: ' . $color . '; font-weight: bold; background: ' . $bg . '; padding: 3px 8px; border-radius: 10px; display: inline-block;">' . number_format_i18n( $clicks ) . '</span>';

            case 'created_at':
                return esc_html( wp_date( 'd/m/Y', strtotime( $item[ $column_name ] ) ) );
            default:
                return ''; // Đã xóa hàm print_r() debug ở đây để tuân thủ bảo mật
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

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search_req = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
        if ( ! empty( $search_req ) ) {
            $where_clauses[] = "(name LIKE %s OR original_url LIKE %s OR tag LIKE %s)";
            $query_args[] = '%' . $wpdb->esc_like( $search_req ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $search_req ) . '%';
            $query_args[] = '%' . $wpdb->esc_like( $search_req ) . '%';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_ad_count = isset( $_REQUEST['filter_ad_count'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_ad_count'] ) ) : '';
        if ( $filter_ad_count !== '' ) {
            $where_clauses[] = "ad_count = %d";
            $query_args[] = intval( $filter_ad_count );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $filter_month = isset( $_REQUEST['filter_month'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['filter_month'] ) ) : '';
        if ( ! empty( $filter_month ) ) {
            $where_clauses[] = "DATE_FORMAT(created_at, '%%Y-%%m') = %s"; 
            $query_args[] = $filter_month;
        }

        $where_sql = implode( ' AND ', $where_clauses );

        if ( empty( $query_args ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.PlaceholdersReplacements, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM " . $table_name . " WHERE " . $where_sql );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.PlaceholdersReplacements, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $total_items = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM " . $table_name . " WHERE " . $where_sql, $query_args ) );
        }

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );

        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns(); 
        $this->_column_headers = array( $columns, $hidden, $sortable );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $req_orderby = isset( $_REQUEST['orderby'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $req_order = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : '';

        $orderby = ( ! empty( $req_orderby ) && in_array( $req_orderby, array( 'name', 'tag', 'created_at', 'completed_clicks', 'id' ) ) ) ? $req_orderby : 'id';
        $order = ( ! empty( $req_order ) && in_array( strtoupper( $req_order ), array( 'ASC', 'DESC' ) ) ) ? strtoupper( $req_order ) : 'DESC';

        $offset = ( $current_page - 1 ) * $per_page;
        
        $query_args[] = $per_page;
        $query_args[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.PlaceholdersReplacements, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
        $this->items = $wpdb->get_results( $wpdb->prepare( "SELECT *, (SELECT COUNT(id) FROM " . $table_logs . " WHERE link_id = " . $table_name . ".id AND status = 'completed') as completed_clicks FROM " . $table_name . " WHERE " . $where_sql . " ORDER BY " . $orderby . " " . $order . " LIMIT %d OFFSET %d", $query_args ), ARRAY_A );
    }

    public function display() {
        parent::display();
    }
}