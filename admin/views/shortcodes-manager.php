<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// --- XỬ LÝ LƯU SHORTCODE MỚI ---
if ( isset( $_POST['wprg_submit_shortcode'] ) && check_admin_referer( 'wprg_add_shortcode_nonce' ) ) {
    $name      = isset( $_POST['sc_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sc_name'] ) ) : '';
    $wait_time = isset( $_POST['sc_wait_time'] ) ? sanitize_text_field( wp_unslash( $_POST['sc_wait_time'] ) ) : '';
    $page_id   = isset( $_POST['sc_page_id'] ) ? intval( wp_unslash( $_POST['sc_page_id'] ) ) : 0; 
    
    $sc_id = substr( md5( time() . wp_rand() ), 0, 8 );
    $shortcodes = get_option( 'wprg_shortcodes', array() );
    
    $shortcodes[ $sc_id ] = array(
        'id'        => $sc_id,
        'name'      => $name,
        'wait_time' => $wait_time,
        'page_id'   => $page_id
    );

    update_option( 'wprg_shortcodes', $shortcodes );
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shortcode created successfully!', 'redirect-gateway-manager' ) . '</p></div>';
}

// --- XỬ LÝ XÓA SHORTCODE (Đã bổ sung Nonce bảo mật CSRF) ---
if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_sc' && isset( $_GET['sc_id'] ) ) {
    $delete_id = sanitize_text_field( wp_unslash( $_GET['sc_id'] ) );
    
    // Kiểm tra Nonce trước khi xóa
    if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_sc_' . $delete_id ) ) {
        $shortcodes = get_option( 'wprg_shortcodes', array() );
        if ( isset( $shortcodes[ $delete_id ] ) ) {
            unset( $shortcodes[ $delete_id ] );
            update_option( 'wprg_shortcodes', $shortcodes );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Shortcode deleted.', 'redirect-gateway-manager' ) . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Security check failed. Please try again.', 'redirect-gateway-manager' ) . '</p></div>';
    }
}

$shortcodes = get_option( 'wprg_shortcodes', array() );
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Manage Shortcodes (Gateway)', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <?php if ( empty( $shortcodes ) ) : ?>
        <div class="notice notice-info" style="border-left-color: #00a32a; padding: 15px; background: #e1fbd6; margin-bottom: 20px;">
            <h3 style="margin-top: 0; color: #00a32a;"><?php esc_html_e( '💡 Important tip for first-time setup!', 'redirect-gateway-manager' ); ?></h3>
            <p style="font-size: 14px;"><?php echo wp_kses_post( __( 'Because the Gateway\'s countdown and security system must run in <strong>real-time</strong>, the page selected as the Gateway <strong>MUST NEVER BE CACHED</strong>.', 'redirect-gateway-manager' ) ); ?></p>
            <p><?php echo wp_kses_post( __( 'After creating a Shortcode and assigning it to a Page, remember to copy that Page\'s URL and add it to the <strong>Never Cache URL</strong> list in the settings of caching plugins like WP Rocket, LiteSpeed, W3 Total Cache, etc!', 'redirect-gateway-manager' ) ); ?></p>
        </div>
    <?php endif; ?>

    <div style="display: flex; gap: 25px; align-items: flex-start; flex-wrap: wrap;">
        <div class="wprg-form-container" style="flex: 1; min-width: 350px;">
            <h3>✨ <?php esc_html_e( 'Create New Shortcode', 'redirect-gateway-manager' ); ?></h3>
            <form method="post" action="">
                <?php wp_nonce_field( 'wprg_add_shortcode_nonce' ); ?>
                <div class="wprg-form-group">
                    <label for="sc_name">
                        <?php esc_html_e( 'Identifier Name', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Internal name for easy identification when selecting a Gateway while creating a Link (e.g., Action Games Gateway).', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="sc_name" type="text" id="sc_name" required placeholder="<?php esc_attr_e( 'Example: Action Games Gateway', 'redirect-gateway-manager' ); ?>">
                </div>

                <div class="wprg-form-group">
                    <label for="sc_wait_time">
                        <?php esc_html_e( 'Wait time (seconds)', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'Enter 1 number for all steps (e.g., 10) or multiple comma-separated numbers for each ad step (e.g., 15,5,3).', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <input name="sc_wait_time" type="text" id="sc_wait_time" value="10" placeholder="<?php esc_attr_e( 'Ex: 10,5,3', 'redirect-gateway-manager' ); ?>" required>
                </div>

                <div class="wprg-form-group">
                    <label for="sc_page_id">
                        <?php esc_html_e( 'Select Page to place Shortcode', 'redirect-gateway-manager' ); ?>
                        <span class="wprg-tooltip-icon">?<span class="wprg-tooltip-text"><?php esc_html_e( 'The page containing this shortcode will act as the countdown "Security Gate".', 'redirect-gateway-manager' ); ?></span></span>
                    </label>
                    <select name="sc_page_id" id="sc_page_id" required>
                        <option value=""><?php esc_html_e( '-- Select a page --', 'redirect-gateway-manager' ); ?></option>
                        <?php
                        $pages = get_pages();
                        foreach ( $pages as $page ) { echo '<option value="' . esc_attr( $page->ID ) . '">' . esc_html( $page->post_title ) . '</option>'; }
                        ?>
                    </select>
                    <p style="color: #d63638; font-size: 12px; margin-top: 8px; font-weight: 500; line-height: 1.4;">
                        <?php esc_html_e( '⚠️ Note: The selected page must be excluded from the website\'s cache!', 'redirect-gateway-manager' ); ?>
                    </p>
                </div>

                <div style="margin-top: 30px;">
                    <input type="submit" name="wprg_submit_shortcode" class="button button-primary button-large" value="<?php esc_attr_e( 'Create Shortcode', 'redirect-gateway-manager' ); ?>" style="width: 100%; text-align: center;">
                </div>
            </form>
        </div>

        <div class="wprg-form-container" style="flex: 2; min-width: 500px; padding: 0; overflow: hidden;">
            <h3 style="margin: 20px 25px 0 25px;">📋 <?php esc_html_e( 'List of created Shortcodes', 'redirect-gateway-manager' ); ?></h3>
            <table class="wp-list-table widefat fixed striped" style="border: none; border-top: 1px solid #ccd0d4; margin-top: 15px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Gateway Name', 'redirect-gateway-manager' ); ?></th>
                        <th style="width: 25%;"><?php esc_html_e( 'Shortcode', 'redirect-gateway-manager' ); ?></th>
                        <th style="width: 15%;"><?php esc_html_e( 'Time', 'redirect-gateway-manager' ); ?></th>
                        <th><?php esc_html_e( 'Destination Page', 'redirect-gateway-manager' ); ?></th>
                        <th style="width: 10%;"><?php esc_html_e( 'Action', 'redirect-gateway-manager' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $shortcodes ) ) : ?>
                        <tr><td colspan="5" style="padding: 20px; text-align: center; color: #666;"><?php esc_html_e( 'No shortcodes yet. Let\'s create your first one next to this!', 'redirect-gateway-manager' ); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ( $shortcodes as $sc ) : 
                            // Tạo link xóa kèm Nonce bảo vệ
                            $delete_url = wp_nonce_url( "?page=wprg-shortcodes&action=delete_sc&sc_id=" . urlencode( $sc['id'] ), 'delete_sc_' . $sc['id'] );
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html( $sc['name'] ); ?></strong></td>
                                <td><input type="text" readonly value='[wprg_gateway id="<?php echo esc_attr( $sc['id'] ); ?>"]' class="large-text" onfocus="this.select();" style="..." /></td>
                                <td><strong><?php echo esc_html( $sc['wait_time'] ); ?></strong> s</td>
                                <td><?php $page_url = get_permalink( $sc['page_id'] ); echo '<a href="' . esc_url( $page_url ) . '" target="_blank" style="...">🔗 ' . esc_html__( 'View page', 'redirect-gateway-manager' ) . '</a>'; ?></td>
                                <td><a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this shortcode?', 'redirect-gateway-manager' ) ); ?>');" style="...">❌ <?php esc_html_e( 'Delete', 'redirect-gateway-manager' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>