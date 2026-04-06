<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WPRG_WPML_Integration {

    // Biến lưu trữ ngôn ngữ ngầm
    private $detected_lang = '';

    public function __construct() {
        if ( defined( 'ICL_LANGUAGE_CODE' ) || class_exists('SitePress') ) {
            add_filter( 'wprg_build_gateway_link', array( $this, 'translate_gateway_link' ), 10, 2 );
            add_filter( 'wprg_gateway_page_id', array( $this, 'get_translated_page_id' ), 10, 1 );
            
            // Hook mới để ép URL hiển thị đúng ngôn ngữ
            add_filter( 'wprg_final_gateway_url', array( $this, 'force_gateway_url_language' ), 10, 1 );
            
            add_filter( 'the_content', array( $this, 'auto_translate_content_links' ), 99 );
        }
    }

    /**
     * Dịch link Frontend
     */
    public function auto_translate_content_links( $content ) {
        if ( empty( $content ) ) return $content;
        $raw_home_url = untrailingslashit( get_option('home') ); 
        $escaped_home = preg_quote( $raw_home_url, '/' );
        $pattern = '/' . $escaped_home . '\/go\/([a-zA-Z0-9_\-]+)/i';

        return preg_replace_callback( $pattern, function( $matches ) {
            $slug = $matches[1];
            $base_link = home_url( '/go/' . $slug );
            return apply_filters( 'wprg_build_gateway_link', $base_link, $slug );
        }, $content );
    }

    /**
     * Nối đuôi ngôn ngữ vào link /go/
     */
    public function translate_gateway_link( $base_link, $slug ) {
        if ( function_exists( 'apply_filters' ) ) {
            $lang_home_url = apply_filters( 'wpml_home_url', home_url() );
            $lang_home_url = untrailingslashit( $lang_home_url );
            return $lang_home_url . '/go/' . $slug;
        }
        return $base_link;
    }

    /**
     * Dò tìm xem khách click từ ngôn ngữ nào (SIÊU CHUẨN XÁC)
     */
    public function get_translated_page_id( $page_id ) {
        if ( ! function_exists( 'apply_filters' ) ) return $page_id;

        $lang_code = '';

        // 1. Nếu khách đang click từ trang có thư mục ngôn ngữ (VD: /zh-hans/)
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
            $home_url = untrailingslashit( home_url() );
            $path = str_replace( $home_url, '', $referer );
            // Dò tìm thư mục ngôn ngữ ngay trong URL của trang vừa click
            if ( preg_match( '#^/([a-z]{2}(?:-[a-z0-9]+)?)/#i', $path, $matches ) ) {
                $lang_code = $matches[1];
            }
        }
        
        // 2. Nếu không có, dự phòng bằng Cookie của WPML
        if ( empty($lang_code) && isset( $_COOKIE['_icl_current_language'] ) ) {
            $lang_code = sanitize_text_field( wp_unslash( $_COOKIE['_icl_current_language'] ) );
        }

        // Lưu lại để hàm ép URL dùng
        if ( $lang_code ) {
            $this->detected_lang = $lang_code;
            
            // Xoay trục WPML sang ngôn ngữ mới
            do_action( 'wpml_switch_language', $lang_code );
            
            $translated_id = apply_filters( 'wpml_object_id', $page_id, 'page', true, $lang_code );
            if ( $translated_id ) return $translated_id;
        }
        
        return $page_id;
    }

    /**
     * Ép đường link Gateway phải có tiền tố ngôn ngữ (Dù trang đó chỉ có tiếng Anh)
     */
    public function force_gateway_url_language( $url ) {
        if ( ! empty( $this->detected_lang ) && function_exists('apply_filters') ) {
            // Hàm này bắt buộc WPML phải gắn /zh-hans/ vào link
            return apply_filters( 'wpml_permalink', $url, $this->detected_lang );
        }
        return $url;
    }
}
new WPRG_WPML_Integration();