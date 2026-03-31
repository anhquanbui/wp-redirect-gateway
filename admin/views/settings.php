<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// 1. XỬ LÝ LƯU CÀI ĐẶT CHÍNH
if ( isset( $_POST['wprg_save_settings'] ) && check_admin_referer( 'wprg_settings_nonce' ) ) {
    update_option( 'wprg_affiliate_links', isset( $_POST['wprg_affiliate_links'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wprg_affiliate_links'] ) ) : '' );
    update_option( 'wprg_require_active_tab', isset( $_POST['wprg_require_active_tab'] ) ? '1' : '0' );
    update_option( 'wprg_single_link_mode', isset( $_POST['wprg_single_link_mode'] ) ? '1' : '0' );
    update_option( 'wprg_delete_data', isset( $_POST['wprg_delete_data'] ) ? 'yes' : 'no' );
    update_option( 'wprg_open_link_new_tab', isset( $_POST['wprg_open_link_new_tab'] ) ? '1' : '0' );
    update_option( 'wprg_new_tab_delay', isset( $_POST['wprg_new_tab_delay'] ) ? intval( $_POST['wprg_new_tab_delay'] ) : 0 );
    update_option( 'wprg_auto_retry_error', isset( $_POST['wprg_auto_retry_error'] ) ? '1' : '0' );
    update_option( 'wprg_cookie_pass_val', isset( $_POST['wprg_cookie_pass_val'] ) ? intval( $_POST['wprg_cookie_pass_val'] ) : 24 );
    update_option( 'wprg_cookie_pass_unit', isset( $_POST['wprg_cookie_pass_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_cookie_pass_unit'] ) ) : 'hours' );
    
    // Cài đặt bảo mật (Security Settings)
    update_option( 'wprg_captcha_type', isset( $_POST['wprg_captcha_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_captcha_type'] ) ) : 'recaptcha' );
    update_option( 'wprg_recaptcha_site', isset( $_POST['wprg_recaptcha_site'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_recaptcha_site'] ) ) : '' );
    update_option( 'wprg_recaptcha_secret', isset( $_POST['wprg_recaptcha_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_recaptcha_secret'] ) ) : '' );
    update_option( 'wprg_turnstile_site', isset( $_POST['wprg_turnstile_site'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_turnstile_site'] ) ) : '' );
    update_option( 'wprg_turnstile_secret', isset( $_POST['wprg_turnstile_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_turnstile_secret'] ) ) : '' );
    update_option( 'wprg_rel_noopener', isset( $_POST['wprg_rel_noopener'] ) ? '1' : '0' );
    update_option( 'wprg_rel_noreferrer', isset( $_POST['wprg_rel_noreferrer'] ) ? '1' : '0' );

    $active_tab = isset( $_POST['wprg_active_tab'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_active_tab'] ) ) : 'tab-ads';
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'redirect-gateway-manager' ) . '</p></div>';
} 
// 2. XỬ LÝ LƯU CÀI ĐẶT TỰ ĐỘNG BACKUP
elseif ( isset( $_POST['wprg_save_backup_settings'] ) && check_admin_referer( 'wprg_backup_settings_nonce' ) ) {
    $old_enable = get_option( 'wprg_enable_auto_backup', '0' );
    $old_time   = get_option( 'wprg_backup_time', '00:00' );
    
    $new_enable = isset( $_POST['wprg_enable_auto_backup'] ) ? '1' : '0';
    $new_time   = isset( $_POST['wprg_backup_time'] ) ? sanitize_text_field( wp_unslash( $_POST['wprg_backup_time'] ) ) : '00:00';
    $new_limit  = isset( $_POST['wprg_backup_limit'] ) ? intval( $_POST['wprg_backup_limit'] ) : 7;
    
    update_option( 'wprg_enable_auto_backup', $new_enable );
    update_option( 'wprg_backup_time', $new_time );
    update_option( 'wprg_backup_limit', $new_limit );

    if ( $old_enable !== $new_enable || $old_time !== $new_time ) {
        if ( function_exists('wprg_reschedule_backup_cron') ) { wprg_reschedule_backup_cron(); }
    }
    
    $active_tab = 'tab-import-export'; 
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Auto Backup settings updated successfully.', 'redirect-gateway-manager' ) . '</p></div>';
} else {
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'tab-ads'; 
}

// 3. XỬ LÝ CÁC THÔNG BÁO TỪ BACKUP/RESTORE
if ( isset( $_GET['wprg_import_success'] ) && $_GET['wprg_import_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'JSON configuration file imported successfully.', 'redirect-gateway-manager' ) . '</p></div>';
    $active_tab = 'tab-import-export';
}

if ( isset( $_GET['wprg_restore_success'] ) && $_GET['wprg_restore_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible" style="border-left-color: #d63638;"><p><strong>' . esc_html__( 'WARNING: ALL data successfully RESTORED from JSON Backup file!', 'redirect-gateway-manager' ) . '</strong></p></div>';
    $active_tab = 'tab-import-export';
}

if ( isset( $_GET['wprg_delete_success'] ) && $_GET['wprg_delete_success'] == '1' ) {
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Backup file deleted successfully.', 'redirect-gateway-manager' ) . '</p></div>';
    $active_tab = 'tab-import-export';
}

// 4. LẤY DỮ LIỆU ĐỂ HIỂN THỊ RA FORM
$current_links = get_option( 'wprg_affiliate_links', '' );
$require_active_tab = get_option( 'wprg_require_active_tab', '1' ); 
$single_link_mode = get_option( 'wprg_single_link_mode', '0' );   
$delete_data = get_option( 'wprg_delete_data', 'no' );
$open_new_tab = get_option( 'wprg_open_link_new_tab', '0' );
$new_tab_delay = get_option( 'wprg_new_tab_delay', 0 );
$auto_retry_error = get_option( 'wprg_auto_retry_error', '0' );
$cookie_pass_val = get_option( 'wprg_cookie_pass_val', 24 );
$cookie_pass_unit = get_option( 'wprg_cookie_pass_unit', 'hours' );

// Dữ liệu bảo mật (Security Data)
$captcha_type = get_option( 'wprg_captcha_type', 'recaptcha' );
$recap_site = get_option( 'wprg_recaptcha_site', '' );
$recap_secret = get_option( 'wprg_recaptcha_secret', '' );
$ts_site = get_option( 'wprg_turnstile_site', '' );
$ts_secret = get_option( 'wprg_turnstile_secret', '' );
$rel_noopener = get_option( 'wprg_rel_noopener', '1' ); 
$rel_noreferrer = get_option( 'wprg_rel_noreferrer', '0' ); 

// Dữ liệu Backup
$enable_auto_backup = get_option( 'wprg_enable_auto_backup', '0' );
$backup_time = get_option( 'wprg_backup_time', '00:00' );
$backup_limit = get_option( 'wprg_backup_limit', 7 );

// QUÉT THƯ MỤC SERVER ĐỂ TÌM CÁC FILE BACKUP TỰ ĐỘNG
$upload_dir = wp_upload_dir();
$backup_dir = $upload_dir['basedir'] . '/wprg-backups';
$auto_backup_files = array();
if ( file_exists( $backup_dir ) ) {
    $files = glob( $backup_dir . '/wprg-autobackup-*.json' );
    if ( $files ) {
        rsort( $files ); 
        $auto_backup_files = $files;
    }
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Gateway System Settings', 'redirect-gateway-manager' ); ?></h1>
    <hr class="wp-header-end">

    <h2 class="nav-tab-wrapper" style="margin-top: 15px; border-bottom: 1px solid #c3c4c7;">
        <a href="#tab-ads" class="nav-tab <?php echo ($active_tab === 'tab-ads') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ads"><?php esc_html_e( '🎯 Ads & Links', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-ux" class="nav-tab <?php echo ($active_tab === 'tab-ux') ? 'nav-tab-active' : ''; ?>" data-tab="tab-ux"><?php esc_html_e( '🎨 User Experience (UX)', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-security" class="nav-tab <?php echo ($active_tab === 'tab-security') ? 'nav-tab-active' : ''; ?>" data-tab="tab-security"><?php esc_html_e( '🛡️ Security & Anti-Bot', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-system" class="nav-tab <?php echo ($active_tab === 'tab-system') ? 'nav-tab-active' : ''; ?>" data-tab="tab-system"><?php esc_html_e( '⚙️ System', 'redirect-gateway-manager' ); ?></a>
        <a href="#tab-import-export" class="nav-tab <?php echo ($active_tab === 'tab-import-export') ? 'nav-tab-active' : ''; ?>" data-tab="tab-import-export"><?php esc_html_e( '🔄 Import/Export & Backup', 'redirect-gateway-manager' ); ?></a>
    </h2>

    <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-top: none; max-width: 800px;">
        
        <form method="post" action="" id="wprg-main-settings-form">
            <?php wp_nonce_field( 'wprg_settings_nonce' ); ?>
            <input type="hidden" name="wprg_active_tab" id="wprg_active_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div id="tab-ads" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ads') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Affiliate Link Configuration (For Watch Ads)', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wprg_affiliate_links"><?php echo wp_kses_post( __( 'Round-Robin Link List<br><small>(One link per line)</small>', 'redirect-gateway-manager' ) ); ?></label></th>
                        <td>
                            <textarea name="wprg_affiliate_links" id="wprg_affiliate_links" rows="6" class="large-text code"><?php echo esc_textarea( $current_links ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'These links will be opened in a round-robin sequence from the 2nd click onwards.', 'redirect-gateway-manager' ); ?></p>
                            <p style="color: #d63638; font-weight: 500; font-size: 13px; margin-top: 5px;">
                                <?php esc_html_e( '* SEO Tip: The Gateway system automatically blocks search bots from following these links (safe nofollow).', 'redirect-gateway-manager' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-ux" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-ux') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'User Experience Settings', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php echo wp_kses_post( __( 'Require Active Tab', 'redirect-gateway-manager' ) ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_require_active_tab" value="1" <?php checked( $require_active_tab, '1' ); ?>>
                                <?php esc_html_e( 'Enable (Countdown pauses if the user switches to another tab)', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Link Opening Limit', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_single_link_mode" value="1" <?php checked( $single_link_mode, '1' ); ?>>
                                <?php esc_html_e( 'Only allow opening one link at a time', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Open Destination in New Tab', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_open_link_new_tab" id="wprg_open_link_new_tab" value="1" <?php checked( $open_new_tab, '1' ); ?>>
                                <?php esc_html_e( 'Enable (Destination page opens in a new tab when the final link button is clicked)', 'redirect-gateway-manager' ); ?>
                            </label>
                            
                            <div id="wprg-new-tab-delay-wrap" style="margin-top: 10px; margin-left: 25px; <?php echo ($open_new_tab === '1') ? 'display:block;' : 'display:none;'; ?>">
                                <label for="wprg_new_tab_delay" style="font-weight: 500;"><?php esc_html_e( 'Delay opening link (Seconds):', 'redirect-gateway-manager' ); ?></label>
                                <input type="number" name="wprg_new_tab_delay" id="wprg_new_tab_delay" value="<?php echo esc_attr( $new_tab_delay ); ?>" min="0" max="60" style="width: 70px; margin-left: 5px;">
                                <p class="description"><?php esc_html_e( 'Enter 0 to load immediately. Example: 3 will hold the user on a blank tab for 3 seconds before loading the destination.', 'redirect-gateway-manager' ); ?></p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Auto-Retry on Error', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_auto_retry_error" value="1" <?php checked( $auto_retry_error, '1' ); ?>>
                                <?php esc_html_e( 'Enable (Automatically retry up to 2 times on network loss or WAF blocks)', 'redirect-gateway-manager' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-security" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-security') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Gateway Protection', 'redirect-gateway-manager' ); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Password Expiry Time', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <input type="number" name="wprg_cookie_pass_val" value="<?php echo esc_attr( $cookie_pass_val ); ?>" min="1" style="width: 80px;">
                            <select name="wprg_cookie_pass_unit">
                                <option value="seconds" <?php selected( $cookie_pass_unit, 'seconds' ); ?>><?php esc_html_e( 'Seconds', 'redirect-gateway-manager' ); ?></option>
                                <option value="minutes" <?php selected( $cookie_pass_unit, 'minutes' ); ?>><?php esc_html_e( 'Minutes', 'redirect-gateway-manager' ); ?></option>
                                <option value="hours" <?php selected( $cookie_pass_unit, 'hours' ); ?>><?php esc_html_e( 'Hours', 'redirect-gateway-manager' ); ?></option>
                                <option value="days" <?php selected( $cookie_pass_unit, 'days' ); ?>><?php esc_html_e( 'Days', 'redirect-gateway-manager' ); ?></option>
                                <option value="weeks" <?php selected( $cookie_pass_unit, 'weeks' ); ?>><?php esc_html_e( 'Weeks', 'redirect-gateway-manager' ); ?></option>
                                <option value="months" <?php selected( $cookie_pass_unit, 'months' ); ?>><?php esc_html_e( 'Months', 'redirect-gateway-manager' ); ?></option>
                                <option value="years" <?php selected( $cookie_pass_unit, 'years' ); ?>><?php esc_html_e( 'Years', 'redirect-gateway-manager' ); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e( 'How long the browser will "remember" the link unlock password (Default: 24 Hours).', 'redirect-gateway-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Anti-Bot System', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label style="margin-right: 20px;">
                                <input type="radio" name="wprg_captcha_type" value="recaptcha" <?php checked($captcha_type, 'recaptcha'); ?> onclick="document.getElementById('wrap-recaptcha').style.display='block'; document.getElementById('wrap-turnstile').style.display='none';"> Google reCAPTCHA v3
                            </label>
                            <label>
                                <input type="radio" name="wprg_captcha_type" value="turnstile" <?php checked($captcha_type, 'turnstile'); ?> onclick="document.getElementById('wrap-recaptcha').style.display='none'; document.getElementById('wrap-turnstile').style.display='block';"> Cloudflare Turnstile
                            </label>
                        </td>
                    </tr>
                </table>

                <div id="wrap-recaptcha" style="<?php echo ($captcha_type === 'recaptcha') ? 'display:block;' : 'display:none;'; ?> background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 10px;">
                    <h4 style="margin-top:0;">Google reCAPTCHA v3</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_recaptcha_site"><?php esc_html_e( 'Site Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_recaptcha_site" id="wprg_recaptcha_site" type="text" class="regular-text" value="<?php echo esc_attr( $recap_site ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_recaptcha_secret"><?php esc_html_e( 'Secret Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_recaptcha_secret" id="wprg_recaptcha_secret" type="text" class="regular-text" value="<?php echo esc_attr( $recap_secret ); ?>"></td>
                        </tr>
                    </table>
                </div>

                <div id="wrap-turnstile" style="<?php echo ($captcha_type === 'turnstile') ? 'display:block;' : 'display:none;'; ?> background: #f9f9f9; padding: 15px; border: 1px solid #ddd; margin-top: 10px;">
                    <h4 style="margin-top:0; color: #f38020;">Cloudflare Turnstile</h4>
                    <table class="form-table">
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_turnstile_site"><?php esc_html_e( 'Site Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_turnstile_site" id="wprg_turnstile_site" type="text" class="regular-text" value="<?php echo esc_attr( $ts_site ); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row" style="width: 150px;"><label for="wprg_turnstile_secret"><?php esc_html_e( 'Secret Key', 'redirect-gateway-manager' ); ?></label></th>
                            <td><input name="wprg_turnstile_secret" id="wprg_turnstile_secret" type="text" class="regular-text" value="<?php echo esc_attr( $ts_secret ); ?>"></td>
                        </tr>
                    </table>
                    <p class="description" style="margin-top: 10px;"><?php esc_html_e( 'Tip: Cloudflare Turnstile is lighter, privacy-focused, and does not require image puzzles.', 'redirect-gateway-manager' ); ?></p>
                </div>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Link Open Attributes', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label style="margin-right: 20px;">
                                <input type="checkbox" name="wprg_rel_noopener" value="1" <?php checked( $rel_noopener, '1' ); ?>>
                                <code>rel="noopener"</code> <?php esc_html_e( '(Recommended)', 'redirect-gateway-manager' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="wprg_rel_noreferrer" value="1" <?php checked( $rel_noreferrer, '1' ); ?>>
                                <code>rel="noreferrer"</code> <?php esc_html_e( '(Disable for Affiliate Links)', 'redirect-gateway-manager' ); ?>
                            </label>
                            <p class="description" style="margin-top: 5px;"><?php esc_html_e( 'The noopener option provides excellent Tabnabbing protection. The noreferrer option hides your website source but may strip affiliate commissions.', 'redirect-gateway-manager' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-system" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-system') ? 'display:block;' : 'display:none;'; ?>">
                <h3 style="margin-top: 0; color: #d63638;"><?php esc_html_e( 'Data & Uninstall', 'redirect-gateway-manager' ); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Delete Data', 'redirect-gateway-manager' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="wprg_delete_data" value="yes" <?php checked( $delete_data, 'yes' ); ?>>
                                <span style="color: #d63638; font-weight: 500;"><?php esc_html_e( 'Delete all data when uninstalling this plugin', 'redirect-gateway-manager' ); ?></span>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div id="wprg-submit-wrapper" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:none;' : 'display:block;'; ?>">
                <p class="submit" style="border-top: 1px solid #f0f0f1; padding-top: 15px; margin-top: 20px;">
                    <input type="submit" name="wprg_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'redirect-gateway-manager' ); ?>">
                </p>
            </div>
        </form>

        <div id="tab-import-export" class="wprg-tab-content" style="<?php echo ($active_tab === 'tab-import-export') ? 'display:block;' : 'display:none;'; ?>">
            
            <h3 style="margin-top: 0; color: #2271b1;"><?php esc_html_e( 'Import / Export Configuration (Settings Only)', 'redirect-gateway-manager' ); ?></h3>
            <table class="form-table" style="margin-bottom: 30px;">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Export Settings', 'redirect-gateway-manager' ); ?></th>
                    <td>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_export_settings">
                            <?php wp_nonce_field( 'wprg_export_nonce_action', 'wprg_export_nonce' ); ?>
                            <?php submit_button( esc_html__( 'Download JSON File', 'redirect-gateway-manager' ), 'secondary', 'submit', false ); ?>
                        </form>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Import Settings', 'redirect-gateway-manager' ); ?></th>
                    <td>
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_import_settings">
                            <?php wp_nonce_field( 'wprg_import_nonce_action', 'wprg_import_nonce' ); ?>
                            <input type="file" name="wprg_import_file" accept=".json" required />
                            <br><br>
                            <?php submit_button( esc_html__( 'Import Configuration', 'redirect-gateway-manager' ), 'primary', 'submit', false ); ?>
                            <p class="description" style="color:#d63638;"><?php esc_html_e( 'Warning: This action will overwrite all current settings!', 'redirect-gateway-manager' ); ?></p>
                        </form>
                    </td>
                </tr>
            </table>

            <hr style="border: 0; border-top: 1px dashed #ccc; margin: 30px 0;">

            <h3 style="margin-top: 0; color: #0073aa;">📦 <?php esc_html_e( 'Comprehensive Data Backup Management', 'redirect-gateway-manager' ); ?></h3>
            <table class="form-table">
                
                <tr>
                    <th scope="row"><?php esc_html_e( 'Manual Backup', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-bottom: 25px;">
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_backup">
                            <?php wp_nonce_field( 'wprg_backup_action', 'wprg_backup_nonce' ); ?>
                            <button type="submit" class="button button-secondary">
                                <span class="dashicons dashicons-download" style="margin-top:3px;"></span> <?php esc_html_e( 'Download Full Backup (JSON)', 'redirect-gateway-manager' ); ?>
                            </button>
                            <p class="description"><?php esc_html_e( 'Package all Settings, Links, and Logs into a single JSON file.', 'redirect-gateway-manager' ); ?></p>
                        </form>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Auto-Backup Configuration', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <form method="post" action="">
                            <?php wp_nonce_field( 'wprg_backup_settings_nonce' ); ?>
                            <label style="display:block; margin-bottom:10px;">
                                <input type="checkbox" name="wprg_enable_auto_backup" id="wprg_enable_auto_backup" value="1" <?php checked( $enable_auto_backup, '1' ); ?>>
                                <strong><?php esc_html_e( 'Automatically backup settings and database daily', 'redirect-gateway-manager' ); ?></strong>
                            </label>
                            
                            <div id="wprg-backup-time-wrap" style="margin-left: 25px; margin-bottom: 15px; <?php echo ($enable_auto_backup === '1') ? 'display:block;' : 'display:none;'; ?>">
                                <div style="display: flex; gap: 20px; margin-bottom: 5px;">
                                    <div>
                                        <label for="wprg_backup_time" style="font-weight: 500; margin-right: 5px;"><?php esc_html_e( 'Run time:', 'redirect-gateway-manager' ); ?></label>
                                        <input type="time" name="wprg_backup_time" id="wprg_backup_time" value="<?php echo esc_attr( $backup_time ); ?>" style="padding: 3px 8px;">
                                    </div>
                                    <div>
                                        <label for="wprg_backup_limit" style="font-weight: 500; margin-right: 5px;"><?php esc_html_e( 'Max stored backups:', 'redirect-gateway-manager' ); ?></label>
                                        <input type="number" name="wprg_backup_limit" id="wprg_backup_limit" value="<?php echo esc_attr( $backup_limit ); ?>" min="1" max="50" style="padding: 3px 8px; width: 60px;">
                                    </div>
                                </div>
                                <p class="description"><?php echo wp_kses_post( __( 'System saves files to <code>wp-content/uploads/wprg-backups/</code>. Exceeding the max limit will auto-delete the oldest.', 'redirect-gateway-manager' ) ); ?></p>
                            </div>
                            
                            <div style="margin-top: 10px;">
                                <input type="submit" name="wprg_save_backup_settings" class="button button-small" value="<?php esc_attr_e( 'Save Auto-Backup Settings', 'redirect-gateway-manager' ); ?>">
                            </div>
                        </form>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Server Backups', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <?php if ( empty( $auto_backup_files ) ) : ?>
                            <p style="color: #666; font-style: italic;"><?php esc_html_e( 'No automatic backups found.', 'redirect-gateway-manager' ); ?></p>
                        <?php else : ?>
                            <div style="max-height: 250px; overflow-y: auto; border: 1px solid #ccd0d4; border-radius: 4px; max-width: 600px; margin-top: 5px; background: #fff;">
                                <table class="wp-list-table widefat striped" style="margin: 0; border: none; width: 100%;">
                                    <thead style="position: sticky; top: 0; background: #f6f7f7; box-shadow: 0 1px 1px rgba(0,0,0,.04); z-index: 1;">
                                        <tr>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Backup File Name', 'redirect-gateway-manager' ); ?></th>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Size', 'redirect-gateway-manager' ); ?></th>
                                            <th style="background: #f6f7f7; border-bottom: 1px solid #ccd0d4;"><?php esc_html_e( 'Action', 'redirect-gateway-manager' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $auto_backup_files as $file ) : 
                                            $filename = basename( $file );
                                            $filesize = size_format( filesize( $file ) );
                                            $filetime = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), filemtime( $file ) );
                                        ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; justify-content: space-between;">
                                                        <div>
                                                            <strong><?php echo esc_html( $filename ); ?></strong><br>
                                                            <span style="color: #666; font-size: 12px;"><?php echo esc_html( $filetime ); ?></span>
                                                        </div>
                                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;" title="<?php esc_attr_e( 'Delete this file', 'redirect-gateway-manager' ); ?>">
                                                            <input type="hidden" name="action" value="wprg_delete_backup">
                                                            <input type="hidden" name="backup_file" value="<?php echo esc_attr( $filename ); ?>">
                                                            <?php wp_nonce_field( 'wprg_delete_backup_action', 'wprg_delete_backup_nonce' ); ?>
                                                            <button type="submit" style="background: none; border: none; padding: 0; color: #d63638; cursor: pointer; display: flex; align-items: center;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to PERMANENTLY DELETE this backup file?', 'redirect-gateway-manager' ) ); ?>');">
                                                                <span class="dashicons dashicons-no-alt" style="font-size: 20px; width: 20px; height: 20px;"></span>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                                <td style="vertical-align: middle;"><?php echo esc_html( $filesize ); ?></td>
                                                <td style="vertical-align: middle;">
                                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin: 0;">
                                                        <input type="hidden" name="action" value="wprg_auto_restore">
                                                        <input type="hidden" name="backup_file" value="<?php echo esc_attr( $filename ); ?>">
                                                        <?php wp_nonce_field( 'wprg_auto_restore_action', 'wprg_auto_restore_nonce' ); ?>
                                                        <button type="submit" class="button button-primary button-small" onclick="return confirm('<?php echo esc_js( __( 'DANGER WARNING: This action will WIPE OUT current data and restore from this backup. Are you sure?', 'redirect-gateway-manager' ) ); ?>');">
                                                            <?php esc_html_e( 'Restore Now', 'redirect-gateway-manager' ); ?>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <p class="description" style="margin-top: 10px;">
                                <?php 
                                /* translators: %d: Maximum number of backups */
                                printf( esc_html__( 'Displaying auto-backup files on the Server (Max %d copies).', 'redirect-gateway-manager' ), intval($backup_limit) ); 
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr style="border-top: 1px solid #eee;">
                    <th scope="row" style="padding-top: 25px;"><?php esc_html_e( 'Restore from Local Machine', 'redirect-gateway-manager' ); ?></th>
                    <td style="padding-top: 25px;">
                        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <input type="hidden" name="action" value="wprg_full_restore">
                            <?php wp_nonce_field( 'wprg_restore_action', 'wprg_restore_nonce' ); ?>
                            <input type="file" name="wprg_restore_file" accept=".json" required />
                            <br><br>
                            <button type="submit" class="button" onclick="return confirm('<?php echo esc_js( __( 'DANGER WARNING: Are you sure you want to overwrite all Settings, Links, and Logs with the uploaded file?', 'redirect-gateway-manager' ) ); ?>');">
                                <span class="dashicons dashicons-update" style="margin-top:3px;"></span> <?php esc_html_e( 'Upload & Restore', 'redirect-gateway-manager' ); ?>
                            </button>
                        </form>
                    </td>
                </tr>
            </table>
        </div>

    </div>
</div>