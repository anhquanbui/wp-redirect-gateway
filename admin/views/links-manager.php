<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $wpdb;
$table_links = $wpdb->prefix . 'rg_links';
$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

// --- 1. XỬ LÝ XÓA 1 LINK ---
if ( $action === 'delete' && isset( $_GET['link_id'] ) ) {
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( wp_verify_nonce( $nonce, 'wprg_delete_link' ) ) {
        $wpdb->delete( $table_links, array( 'id' => intval( $_GET['link_id'] ) ) );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link deleted successfully.', 'redirect-gateway-manager' ) . '</p></div>';
    }
}

// --- 2. XỬ LÝ NHÂN BẢN LINK (ĐÃ THÊM NONCE CHECK BẢO MẬT) ---
if ( $action === 'duplicate' && isset( $_GET['link_id'] ) ) {
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    $link_id = intval( $_GET['link_id'] );
    
    if ( wp_verify_nonce( $nonce, 'wprg_duplicate_link_' . $link_id ) ) {
        $original_link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_links} WHERE id = %d", $link_id ), ARRAY_A );
        if ( $original_link ) {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $new_slug = substr( str_shuffle( str_repeat( $characters, 5 ) ), 0, 30 );
            
            $wpdb->insert( $table_links, array(
                'name'         => $original_link['name'] . ' ' . __( '(Copy)', 'redirect-gateway-manager' ),
                'tag'          => isset($original_link['tag']) ? $original_link['tag'] : '',
                'original_url' => $original_link['original_url'],
                'slug'         => $new_slug,
                'ad_count'     => $original_link['ad_count'],
                'wait_time'    => isset($original_link['wait_time']) ? $original_link['wait_time'] : '', 
                'password'     => isset($original_link['password']) ? $original_link['password'] : '',
                'shortcode_id' => $original_link['shortcode_id']
            ));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link duplicated successfully.', 'redirect-gateway-manager' ) . '</p></div>';
        }
    }
}

// --- 3. XỬ LÝ XÓA HÀNG LOẠT BULK DELETE (ĐÃ SỬA CHUẨN SQL) ---
$post_action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
$post_action2 = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';

if ( $post_action === 'bulk-delete' || $post_action2 === 'bulk-delete' ) {
    // Gọi hàm kiểm tra Nonce mặc định của WP_List_Table
    check_admin_referer( 'bulk-links' ); 
    
    if ( isset( $_POST['bulk-delete'] ) && is_array( $_POST['bulk-delete'] ) ) {
        $raw_ids = wp_unslash( $_POST['bulk-delete'] );
        $ids = array_map( 'intval', $raw_ids );
        
        // Tạo dấu giữ chỗ (placeholders) thay vì nối mảng trực tiếp vào SQL
        $placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
        
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$table_links} WHERE id IN ( $placeholders )", $ids ) );
        
        /* translators: %d: Number of deleted links */
        echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Successfully deleted %d links.', 'redirect-gateway-manager' ), count($ids) ) . '</p></div>';
    }
}

// --- 4. XỬ LÝ THÊM MỚI LINK ---
if ( isset( $_POST['wprg_submit_link'] ) && check_admin_referer( 'wprg_add_link_nonce' ) ) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $slug = substr( str_shuffle( str_repeat( $characters, 5 ) ), 0, 30 );
    
    $wpdb->insert( $table_links, array(
        'name'         => isset( $_POST['link_name'] ) ? sanitize_text_field( wp_unslash( $_POST['link_name'] ) ) : '',
        'original_url' => isset( $_POST['original_url'] ) ? esc_url_raw( wp_unslash( $_POST['original_url'] ) ) : '',
        'slug'         => $slug,
        'ad_count'     => isset( $_POST['ad_count'] ) ? intval( $_POST['ad_count'] ) : 0,
        'wait_time'    => isset( $_POST['wait_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wait_time'] ) ) : '',
        'password'     => isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '',
        'shortcode_id' => isset( $_POST['shortcode_id'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode_id'] ) ) : ''
    ));
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link created successfully!', 'redirect-gateway-manager' ) . '</p></div>';
}

// --- 5. XỬ LÝ CẬP NHẬT (EDIT) LINK ---
if ( isset( $_POST['wprg_update_link'] ) && check_admin_referer( 'wprg_edit_link_nonce' ) ) {
    $edit_id = isset( $_POST['edit_link_id'] ) ? intval( $_POST['edit_link_id'] ) : 0;
    
    $wpdb->update( 
        $table_links, 
        array(
            'name'         => isset( $_POST['link_name'] ) ? sanitize_text_field( wp_unslash( $_POST['link_name'] ) ) : '',
            'tag'          => isset( $_POST['link_tag'] ) ? sanitize_text_field( wp_unslash( $_POST['link_tag'] ) ) : '',
            'original_url' => isset( $_POST['original_url'] ) ? esc_url_raw( wp_unslash( $_POST['original_url'] ) ) : '',
            'ad_count'     => isset( $_POST['ad_count'] ) ? intval( $_POST['ad_count'] ) : 0,
            'wait_time'    => isset( $_POST['wait_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wait_time'] ) ) : '', 
            'password'     => isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '', 
            'shortcode_id' => isset( $_POST['shortcode_id'] ) ? sanitize_text_field( wp_unslash( $_POST['shortcode_id'] ) ) : ''
        ),
        array( 'id' => $edit_id )
    );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link updated successfully!', 'redirect-gateway-manager' ) . '</p></div>';
    $action = ''; 
}

$shortcodes = get_option( 'wprg_shortcodes', array() );
require_once WPRG_PLUGIN_DIR . 'admin/class-links-table.php';
$links_table = new WPRG_Links_Table();
$links_table->prepare_items();

$raw_aff_links = get_option( 'wprg_affiliate_links', '' );
$aff_links_array = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw_aff_links ) ) );
$max_ads = count( $aff_links_array );
if ( $max_ads < 1 ) $max_ads = 1;
?>

<div class="wrap">
    <h1 class="wp-heading-inline" style="display: inline-block; vertical-align: middle;"><?php esc_html_e( 'Redirect Links Manager', 'redirect-gateway-manager' ); ?></h1>
    
    <?php if ( $action !== 'edit' ) : ?>
        <a href="#form-tao-link" class="page-title-action" style="vertical-align: middle;"><?php esc_html_e( 'Create New Link', 'redirect-gateway-manager' ); ?></a>
        
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-flex; align-items: center; margin-left: 4px; vertical-align: middle;">
            <input type="hidden" name="action" value="wprg_export_links_csv">
            <?php wp_nonce_field( 'wprg_export_links_action', 'wprg_export_links_nonce' ); ?>
            <button type="submit" class="page-title-action" style="background: #00a32a; color: #fff; border-color: #008a20; margin: 0; padding: 4px 10px; line-height: 1.5; display: inline-flex; align-items: center; gap: 4px;">
                <span class="dashicons dashicons-media-spreadsheet" style="font-size: 16px; width: 16px; height: 16px; margin-top: 1px;"></span> <?php esc_html_e( 'Export Excel (CSV)', 'redirect-gateway-manager' ); ?>
            </button>
        </form>

    <?php else: ?>
        <a href="?page=wprg-links" class="page-title-action" style="vertical-align: middle;"><?php esc_html_e( 'Back to list', 'redirect-gateway-manager' ); ?></a>
    <?php endif; ?>
    <hr class="wp-header-end">

    <?php if ( $action === 'edit' && isset( $_GET['link_id'] ) ) : 
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $edit_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . $table_links . " WHERE id = %d", intval( $_GET['link_id'] ) ), ARRAY_A );
        if ( $edit_data ) :
    ?>
        <div class="wprg-form-container">
            <h3>📝 <?php esc_html_e( 'Edit Link', 'redirect-gateway-manager' ); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'wprg_edit_link_nonce' ); ?>
                <input type="hidden" name="edit_link_id" value="<?php echo esc_attr( $edit_data['id'] ); ?>">

                <div class="wprg-form-group" style="display: flex; gap: 20px;">
                    <div style="flex: 2;">
                        <label for="link_name">
                            <?php esc_html_e( 'Display Name', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Internal name to easily identify and search in the list.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="link_name" type="text" id="link_name" placeholder="<?php esc_attr_e( 'Ex: Game download link...', 'redirect-gateway-manager' ); ?>" value="<?php echo esc_attr( $edit_data['name'] ); ?>" required>
                    </div>
                    <div style="flex: 1;">
                        <label for="link_tag">
                            <?php esc_html_e( 'Classification Tag', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Used to classify Customers or Campaigns. You can search for Links using this Tag.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="link_tag" type="text" id="link_tag" placeholder="<?php esc_attr_e( 'Ex: Customer_A', 'redirect-gateway-manager' ); ?>" value="<?php echo esc_attr( isset($edit_data['tag']) ? $edit_data['tag'] : '' ); ?>">
                    </div>
                </div>

                <div class="wprg-form-group">
                    <label for="original_url">
                        <?php esc_html_e( 'Original Link (Destination)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'The actual URL you want users to visit after viewing the ad.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="original_url" type="url" id="original_url" value="<?php echo esc_url( $edit_data['original_url'] ); ?>" placeholder="<?php esc_attr_e( 'https://...', 'redirect-gateway-manager' ); ?>" required>
                </div>

                <div class="wprg-form-group" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label for="shortcode_id">
                            <?php esc_html_e( 'Select Gateway', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Select the countdown interface you created in the Shortcodes section.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <select name="shortcode_id" id="shortcode_id" required>
                            <option value=""><?php esc_html_e( '-- Select Gateway --', 'redirect-gateway-manager' ); ?></option>
                            <?php foreach ( $shortcodes as $sc ) : ?>
                                <option value="<?php echo esc_attr( $sc['id'] ); ?>" <?php selected( $edit_data['shortcode_id'], $sc['id'] ); ?>><?php echo esc_html( $sc['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width: 150px;">
                        <label for="ad_count">
                            <?php esc_html_e( 'Ad Views Count', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Enter 0 to get the link immediately. The maximum is equal to the number of Affiliate Links you entered in the Settings.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="ad_count" type="number" id="ad_count" value="<?php echo esc_attr( $edit_data['ad_count'] ); ?>" min="0" max="<?php echo esc_attr( $max_ads ); ?>">
                    </div>
                </div>

                <div class="wprg-form-group">
                    <label for="wait_time">
                        <?php esc_html_e( 'Custom Wait Time (Optional)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Enter in the format 15,10,5 to override the Gateway time. Leave blank to use the default time.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="wait_time" type="text" id="wait_time" placeholder="<?php esc_attr_e( 'Ex: 15,10,5', 'redirect-gateway-manager' ); ?>" value="<?php echo esc_attr( isset($edit_data['wait_time']) ? $edit_data['wait_time'] : '' ); ?>">
                </div>

                <div class="wprg-form-group">
                    <label for="password">
                        <?php esc_html_e( 'Password Protection (Optional)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Users must enter this password correctly to start the countdown to get the link.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="password" type="text" id="password" placeholder="<?php esc_attr_e( 'Leave blank for no password', 'redirect-gateway-manager' ); ?>" value="<?php echo esc_attr( isset($edit_data['password']) ? $edit_data['password'] : '' ); ?>">
                </div>

                <div style="margin-top: 30px;">
                    <input type="submit" name="wprg_update_link" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Update', 'redirect-gateway-manager' ); ?>">
                    <a href="?page=wprg-links" class="button button-large" style="margin-left: 10px;"><?php esc_html_e( 'Cancel', 'redirect-gateway-manager' ); ?></a>
                </div>
            </form>
        </div>
    <?php endif; 
    
    // PHẦN HIỂN THỊ BẢNG VÀ TẠO LINK MỚI
    else : ?>
        <form method="post" id="links-filter">
            <?php 
                $links_table->search_box( __( 'Search Links', 'redirect-gateway-manager' ), 'search_id' );
                $links_table->display(); 
            ?>
        </form>

        <br><br>
        
        <div id="form-tao-link" class="wprg-form-container">
            <h3>✨ <?php esc_html_e( 'Create New Redirect Link', 'redirect-gateway-manager' ); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'wprg_add_link_nonce' ); ?>
                
                <div class="wprg-form-group" style="display: flex; gap: 20px;">
                    <div style="flex: 2;">
                        <label for="link_name">
                            <?php esc_html_e( 'Display Name', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Internal name to easily identify and search in the list.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="link_name" type="text" id="link_name" placeholder="<?php esc_attr_e( 'Ex: Game download link...', 'redirect-gateway-manager' ); ?>" required>
                    </div>
                    <div style="flex: 1;">
                        <label for="link_tag">
                            <?php esc_html_e( 'Classification Tag', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Used to classify Customers or Campaigns. You can search for Links using this Tag.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="link_tag" type="text" id="link_tag" placeholder="<?php esc_attr_e( 'Ex: Customer_A', 'redirect-gateway-manager' ); ?>">
                    </div>
                </div>

                <div class="wprg-form-group">
                    <label for="original_url">
                        <?php esc_html_e( 'Original Link (Destination)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'The actual URL you want users to visit after viewing the ad.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="original_url" type="url" id="original_url" placeholder="<?php esc_attr_e( 'https://...', 'redirect-gateway-manager' ); ?>" required>
                </div>

                <div class="wprg-form-group" style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <label for="shortcode_id">
                            <?php esc_html_e( 'Select Gateway', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Select the countdown interface you created in the Shortcodes section.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <select name="shortcode_id" id="shortcode_id" required>
                            <option value=""><?php esc_html_e( '-- Select Gateway --', 'redirect-gateway-manager' ); ?></option>
                            <?php foreach ( $shortcodes as $sc ) : ?>
                                <option value="<?php echo esc_attr( $sc['id'] ); ?>"><?php echo esc_html( $sc['name'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="width: 150px;">
                        <label for="ad_count">
                            <?php esc_html_e( 'Ad Views Count', 'redirect-gateway-manager' ); ?>
                            <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Enter 0 to get the link immediately. The maximum is equal to the number of Affiliate Links you entered in the Settings.', 'redirect-gateway-manager' ); ?></span></span>
                        </label>
                        <input name="ad_count" type="number" id="ad_count" value="1" min="0" max="<?php echo esc_attr( $max_ads ); ?>">
                    </div>
                </div>

                <div class="wprg-form-group">
                    <label for="wait_time">
                        <?php esc_html_e( 'Custom Wait Time (Optional)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Enter in the format 15,10,5 to override the Gateway time. Leave blank to use the default time.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="wait_time" type="text" id="wait_time" placeholder="<?php esc_attr_e( 'Ex: 15,10,5', 'redirect-gateway-manager' ); ?>">
                </div>

                <div class="wprg-form-group">
                    <label for="password">
                        <?php esc_html_e( 'Password Protection (Optional)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Users must enter this password correctly to start the countdown to get the link.', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="password" type="text" id="password" placeholder="<?php esc_attr_e( 'Leave blank for no password', 'redirect-gateway-manager' ); ?>">
                </div>

                <div style="margin-top: 30px;">
                    <input type="submit" name="wprg_submit_link" class="button button-primary button-large" value="<?php esc_attr_e( 'Save New Link', 'redirect-gateway-manager' ); ?>">
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>