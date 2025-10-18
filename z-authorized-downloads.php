<?php
/**
 * Plugin Name: Z Authorized Downloads
 * Contributors: zodannl, martenmoolenaar
 * Plugin URI: https://plugins.zodan.nl/wordpress-authorized-downloads
 * Tags: downloads, files, authorization, protected download
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Description: Adds an "Authorized only" meta field to attachments (visible in attachment edit screen and media modal) and manages a .htaccess rewrite section.
 * Version: 1.2.0
 * Stable Tag: 1.2.0
 * Author: Zodan (edited by ChatGPT)
 * Text Domain: z-authorized-downloads
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class z_authorized_downloads {

    protected $plugin_name = 'z-authorized-downloads';
    protected $version = '1.2.0';
    const HTACCESS_MARKER = 'Z Authorized Downloads';
    const OPTION_KEY = 'z_auth_att_options';
    const PROTECTED_PAGE_SLUG = 'protected-downloads';
    const CACHE_GROUP = 'z_authorized_downloads';

    public function __construct() {
        // Meta boxes + media modal
        add_action( 'add_meta_boxes', array( $this, 'setup_attachment_metaboxes' ) );
        add_action( 'save_post_attachment', array( $this, 'save_attachment_meta_box_data' ), 10, 2 );
        add_filter( 'attachment_fields_to_edit', array( $this, 'add_field_to_media_modal' ), 10, 2 );
        add_filter( 'attachment_fields_to_save', array( $this, 'save_field_from_media_modal' ), 10, 2 );

        // Admin settings page
        add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ __CLASS__, 'add_plugin_settings_link' ] );
        add_action( 'admin_enqueue_scripts', array($this, 'admins_css'), 11, 1 );

        // Template redirect for protected downloads
        add_action( 'template_redirect', array( $this, 'handle_protected_request' ) );
    }



    /** ---------------- ADMIN SETTINGS ---------------- */
    public function register_settings_page() {
        add_options_page(
            __( 'Authorized Downloads', 'z-authorized-downloads' ),
            __( 'Authorized Downloads', 'z-authorized-downloads' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, array( $this, 'sanitize_options' ) );
        add_settings_section( 'main_section', __( 'General Settings', 'z-authorized-downloads' ), '__return_false', self::OPTION_KEY );
        add_settings_field( 'filetypes', __( 'Protected File Types (comma-separated)', 'z-authorized-downloads' ), array( $this, 'render_filetypes_field' ), self::OPTION_KEY, 'main_section' );
        add_settings_field( 'default_roles', __( 'Default Allowed Roles', 'z-authorized-downloads' ), array( $this, 'render_default_roles_field' ), self::OPTION_KEY, 'main_section' );
    }

	public static function add_plugin_settings_link( $links ) {
		$settings_link = '<a href="options-general.php?page=z-authorized-downloads">' . __( 'Settings','z-authorized-downloads' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}



    public function sanitize_options( $options ) {
        $clean = array();
        $filetypes = isset( $options['filetypes'] ) ? sanitize_text_field( $options['filetypes'] ) : '';
        // normalize: remove spaces, lowercase
        $clean['filetypes'] = strtolower( preg_replace( '/\s+/', '', $filetypes ) );
        // Rewrite htaccess with new extensions (use WP_Filesystem checks inside)
        self::write_htaccess_section( self::generate_htaccess_rules( $clean['filetypes'] ) );
        $clean['default_roles'] = array();
        if ( isset( $options['default_roles'] ) && is_array( $options['default_roles'] ) ) {
            $all_roles = array_keys( wp_roles()->roles );
            $clean['default_roles'] = array_values( array_intersect( $options['default_roles'], $all_roles ) );
        }
        return $clean;
    }

    public function render_settings_page() {
        add_filter('admin_footer_text', array($this, 'z_admin_footer_print_thankyou'), 900);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Authorized Downloads Settings', 'z-authorized-downloads' ) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields( self::OPTION_KEY );
        do_settings_sections( self::OPTION_KEY );
        submit_button();
        echo '</form></div>';
    }

    public function admins_css($hook) {
        $plugins_url = plugin_dir_url( __FILE__ );
        wp_enqueue_style( 'zauthorizeddownloads-admin-css', $plugins_url . 'assets/admin-styles.css' , array(), $this->version );
    }

    public function render_filetypes_field() {
        $options = get_option( self::OPTION_KEY, array( 'filetypes' => '.pdf,.docx' ) );
        echo '<input type="text" name="' . esc_attr( self::OPTION_KEY ) . '[filetypes]" value="' . esc_attr( $options['filetypes'] ) . '" class="regular-text">';
        echo '<p class="description">' . esc_html__( 'Example: .pdf,.doc,.docx,.zip', 'z-authorized-downloads' ) . '</p>';
    }

    public function render_default_roles_field() {
        $options = get_option( self::OPTION_KEY, array() );
        $saved_roles = isset( $options['default_roles'] ) ? (array) $options['default_roles'] : array();
        global $wp_roles;
        if ( empty( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }
        echo '<fieldset class="z-auth-roles">';
        foreach ( $wp_roles->roles as $role_key => $role_data ) {
            $checked = in_array( $role_key, $saved_roles, true ) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr( self::OPTION_KEY ) . '[default_roles][]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ';
            echo esc_html( translate_user_role( $role_data['name'] ) );
            echo '</label>';
        }
        echo '<p class="description">' . esc_html__( 'If no specific roles are set per file, only these roles will be allowed to download protected files.', 'z-authorized-downloads' ) . '</p>';
        echo '</fieldset>';
    }



    /** ---------------- META BOX ---------------- */
    public function setup_attachment_metaboxes() {
        add_meta_box( 'authorized_attachment_meta_box', __( 'Authorize', 'z-authorized-downloads' ), array( $this, 'display_authorized_attachment_meta_box' ), 'attachment', 'side', 'high' );
    }

    public function display_authorized_attachment_meta_box( $post ) {
        wp_nonce_field( $this->plugin_name . '_attachment_meta_box', $this->plugin_name . '_attachment_meta_box_nonce' );
        // $meta_key = $this->plugin_name . '_document_download_needs_auth';
        // $value = get_post_meta( $post->ID, $meta_key, true );
        // echo '<label><input type="checkbox" name="' . esc_attr( $meta_key ) . '" value="1" ' . checked( $value, 1, false ) . '> ' . esc_html__( 'Authorized only', 'z-authorized-downloads' ) . '</label>';
        $meta_auth_key = $this->plugin_name . '_document_download_needs_auth';
        $meta_roles_key = $this->plugin_name . '_authorized_roles';

        $requires_auth = get_post_meta( $post->ID, $meta_auth_key, true );
        $roles_allowed = (array) get_post_meta( $post->ID, $meta_roles_key, true );

        echo '<p><label><input type="checkbox" name="' . esc_attr( $meta_auth_key ) . '" value="1" ' . checked( $requires_auth, 1, false ) . '> ';
        echo esc_html__( 'Authorized only', 'z-authorized-downloads' ) . '</label></p>';

        global $wp_roles;
        if ( empty( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }
        echo '<div class="z-auth-roles">';
        echo '<p><strong>' . esc_html__( 'Allow only these roles:', 'z-authorized-downloads' ) . '</strong></p>';
        foreach ( $wp_roles->roles as $role_key => $role_data ) {
            $checked = in_array( $role_key, $roles_allowed, true ) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr( $meta_roles_key ) . '[]" value="' . esc_attr( $role_key ) . '" ' . esc_attr( $checked ) . '> ' . esc_html( translate_user_role( $role_data['name'] ) );
            echo '</label>';
        }
        echo '</div>';
    }

    public function save_attachment_meta_box_data( $post_id, $post ) {
        if ( $post->post_type !== 'attachment' ) {
            return;
        }

        $nonce_key = $this->plugin_name . '_attachment_meta_box_nonce';
        if ( ! isset( $_POST[ $nonce_key ] ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) );
        if ( ! wp_verify_nonce( $nonce, $this->plugin_name . '_attachment_meta_box' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $meta_auth_key = $this->plugin_name . '_document_download_needs_auth';
        if ( isset( $_POST[ $meta_auth_key ] ) ) {
            update_post_meta( $post_id, $meta_auth_key, 1 );
        } else {
            delete_post_meta( $post_id, $meta_auth_key );
        }

        $meta_roles_key = $this->plugin_name . '_authorized_roles';
        if ( isset( $_POST[ $meta_roles_key ] ) && is_array( $_POST[ $meta_roles_key ] ) ) {
            $roles = array_map( 'sanitize_text_field', wp_unslash( $_POST[ $meta_roles_key ] ) );
            update_post_meta( $post_id, $meta_roles_key, $roles );
        } else {
            delete_post_meta( $post_id, $meta_roles_key );
        }

    }

    public function add_field_to_media_modal( $form_fields, $post ) {
        $meta_auth_key  = $this->plugin_name . '_document_download_needs_auth';
        $meta_roles_key = $this->plugin_name . '_authorized_roles';

        $requires_auth  = get_post_meta( $post->ID, $meta_auth_key, true );
        $roles_allowed  = (array) get_post_meta( $post->ID, $meta_roles_key, true );

        // Checkbox voor 'Authorized only'
        $html = '<label><input type="checkbox" name="attachments[' . (int) $post->ID . '][' . esc_attr( $meta_auth_key ) . ']" value="1" ' . checked( $requires_auth, 1, false ) . ' /> ';
        $html .= esc_html__( 'Download requires login', 'z-authorized-downloads' ) . '</label>';

        // Rollenlijst
        global $wp_roles;
        if ( empty( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        $html .= '<div class="z-auth-roles">';
        $html .= '<strong>' . esc_html__( 'Allow only these roles:', 'z-authorized-downloads' ) . '</strong>';
        foreach ( $wp_roles->roles as $role_key => $role_data ) {
            $checked = in_array( $role_key, $roles_allowed, true ) ? 'checked' : '';
            $html .= '<label>';
            $html .= '<input type="checkbox" name="attachments[' . (int) $post->ID . '][' . esc_attr( $meta_roles_key ) . '][]" value="' . esc_attr( $role_key ) . '" ' . $checked . '> ';
            $html .= esc_html( translate_user_role( $role_data['name'] ) );
            $html .= '</label>';
        }
        $html .= '</div>';

        // Voeg het veld toe aan de Media modal
        $form_fields[ $meta_auth_key ] = array(
            'label' => __( 'Authorized only', 'z-authorized-downloads' ),
            'input' => 'html',
            'html'  => $html,
        );

        return $form_fields;
    }

    public function save_field_from_media_modal( $post, $attachment ) {
        $meta_auth_key  = $this->plugin_name . '_document_download_needs_auth';
        $meta_roles_key = $this->plugin_name . '_authorized_roles';

        // Sla 'Authorized only' op
        if ( isset( $attachment[ $meta_auth_key ] ) ) {
            update_post_meta( $post['ID'], $meta_auth_key, 1 );
        } else {
            delete_post_meta( $post['ID'], $meta_auth_key );
        }

        // Sla rollen op
        if ( isset( $attachment[ $meta_roles_key ] ) && is_array( $attachment[ $meta_roles_key ] ) ) {
            $roles = array_map( 'sanitize_text_field', wp_unslash( $attachment[ $meta_roles_key ] ) );
            update_post_meta( $post['ID'], $meta_roles_key, $roles );
        } else {
            delete_post_meta( $post['ID'], $meta_roles_key );
        }

        return $post;
    }

    /** ---------------- PROTECTED DOWNLOAD HANDLER ---------------- */
    public function handle_protected_request() {
        if ( ! is_page( self::PROTECTED_PAGE_SLUG ) ) {
            return;
        }
        // Limit input to a basename (no path components) to avoid path-traversal attempts
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- no nonce possible, request is from .htaccess rewrite
        $file = isset( $_GET['file'] ) ? sanitize_file_name( wp_basename( sanitize_url( wp_unslash($_GET['file'] ) ) ) ): '';
        
        if ( ! $file ) {
            wp_die( esc_html__( 'No file specified.', 'z-authorized-downloads' ), esc_html__( 'Error', 'z-authorized-downloads' ), array( 'response' => 403 ) );
        }

        $attachment = $this->get_attachment_by_filename( $file );

        if ( ! $attachment ) {
            wp_die( esc_html__( 'File not found.', 'z-authorized-downloads' ), esc_html__( 'Error', 'z-authorized-downloads' ), array( 'response' => 404 ) );
        }

        $meta_key = $this->plugin_name . '_document_download_needs_auth';
        $requires_auth = get_post_meta( $attachment->ID, $meta_key, true );

if ( $requires_auth ) {
    if ( ! is_user_logged_in() ) {
        auth_redirect();
        exit;
    }

    $meta_roles_key = $this->plugin_name . '_authorized_roles';
    $allowed_roles = (array) get_post_meta( $attachment->ID, $meta_roles_key, true );

    if ( empty( $allowed_roles ) ) {
        $options = get_option( self::OPTION_KEY, array() );
        $allowed_roles = isset( $options['default_roles'] ) ? (array) $options['default_roles'] : array();
    }

    if ( ! empty( $allowed_roles ) ) {
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $intersection = array_intersect( $user_roles, $allowed_roles );

        if ( empty( $intersection ) ) {
            wp_die(
                esc_html__( 'You do not have permission to download this file.', 'z-authorized-downloads' ),
                esc_html__( 'Access Denied', 'z-authorized-downloads' ),
                array( 'response' => 403 )
            );
        }
    }
}

        $filepath = get_attached_file( $attachment->ID );

        // Use WP_Filesystem for file existence and reading
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        if ( empty( $wp_filesystem ) || ! method_exists( $wp_filesystem, 'exists' ) ) {
            wp_die( esc_html__( 'Filesystem API not available.', 'z-authorized-downloads' ), esc_html__( 'Error', 'z-authorized-downloads' ), array( 'response' => 500 ) );
        }

        if ( ! $wp_filesystem->exists( $filepath ) ) {
            wp_die( esc_html__( 'File missing.', 'z-authorized-downloads' ), esc_html__( 'Error', 'z-authorized-downloads' ), array( 'response' => 404 ) );
        }

        $file_contents = $wp_filesystem->get_contents( $filepath );
        if ( $file_contents === false ) {
            wp_die( esc_html__( 'Cannot read file.', 'z-authorized-downloads' ), esc_html__( 'Error', 'z-authorized-downloads' ), array( 'response' => 500 ) );
        }

        // Correct headers for file download / inline display
        if ( ! headers_sent() ) {
            header( 'Content-Type: ' . esc_attr( get_post_mime_type( $attachment->ID ) ) );
            // Use attachment filename from the attachment post to be safer
            $download_name = basename( get_attached_file( $attachment->ID ) );
            header( 'Content-Disposition: inline; filename="' . rawurlencode( $download_name ) . '"' );
            header( 'Content-Length: ' . strlen( $file_contents ) );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional: binary/file contents must not be escaped
        echo $file_contents;
        exit;
    }

    /** ---------------- HTACCESS MANAGEMENT ---------------- */

    public static function generate_htaccess_rules( $filetypes_csv ) {
        // $rules = array( '# BEGIN ' . self::HTACCESS_MARKER );

        // <FilesMatch "\.(doc|docx|xls|xlsx|pdf)$">
        // RedirectMatch 301 ^(.*) https://de-oude-maas.nl/protected-downloads/?file=$1
        // </FilesMatch>
        $exts = array_filter( array_map( 'trim', explode( ',', $filetypes_csv ) ) );
        $exts = array_values( array_filter( array_map( function ( $ext ) {
            return ltrim( $ext, '.' );
        }, $exts ) ) );

        if ( ! empty( $exts ) ) {
            $rules[] = '<FilesMatch "\.(' . implode('|', $exts) . ')$">';
            $rules[] = 'RedirectMatch 301 ^(.*) ' . esc_url_raw( home_url( '/' . self::PROTECTED_PAGE_SLUG . '/?file=$1' ) );
            $rules[] = '</FilesMatch>';           
        }
        // $rules[] = '# END ' . self::HTACCESS_MARKER;
        return $rules;
    }



    /**
     * Use WP_Filesystem to ensure file creation / writability and then call insert_with_markers.
     */
    public static function write_htaccess_section( $rules = array() ) {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $htaccess_file = ABSPATH . '.htaccess';

        if ( empty( $wp_filesystem ) || ! method_exists( $wp_filesystem, 'exists' ) ) {
            return false;
        }

        if ( ! $wp_filesystem->exists( $htaccess_file ) ) {
            // create empty htaccess
            $wp_filesystem->put_contents( $htaccess_file, "\n", FS_CHMOD_FILE );
        }

        // insert_with_markers will handle the marker section; ensure file is writable via WP_Filesystem if possible
        if ( method_exists( $wp_filesystem, 'is_writable' ) ) {
            if ( ! $wp_filesystem->is_writable( $htaccess_file ) ) {
                return false;
            }
        }

        // insert_with_markers expects the path to the file - it uses low-level file functions internally but this is the WP helper
        insert_with_markers( $htaccess_file, self::HTACCESS_MARKER, $rules );
        return true;
    }

    /** ---------------- ACTIVATION / DEACTIVATION ---------------- */
    public static function activate() {
        $page = get_page_by_path( self::PROTECTED_PAGE_SLUG );
        if ( ! $page ) {
            wp_insert_post( array(
                'post_title'   => 'Protected Downloads',
                'post_name'    => self::PROTECTED_PAGE_SLUG,
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => __( 'This page is used internally to serve protected downloads.', 'z-authorized-downloads' ),
            ) );
        }
        $options = get_option( self::OPTION_KEY, array( 'filetypes' => '.pdf,.docx' ) );
        self::write_htaccess_section( self::generate_htaccess_rules( $options['filetypes'] ) );
    }

    public static function deactivate() {
        if ( ! function_exists( 'WP_Filesystem' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        WP_Filesystem();
        global $wp_filesystem;

        $htaccess_file = ABSPATH . '.htaccess';
        if ( ! empty( $wp_filesystem ) && method_exists( $wp_filesystem, 'exists' ) && $wp_filesystem->exists( $htaccess_file ) ) {
            insert_with_markers( $htaccess_file, self::HTACCESS_MARKER, array() );
        }
    }

    /**
     * Get attachment by filename using direct DB query but with caching and proper escaping.
     */
    public static function get_attachment_by_filename( $filename ) {
        global $wpdb;

        $filename = wp_basename( sanitize_file_name( $filename ) );
        $cache_key = 'attachment_filename_' . md5( $filename );
        $cached = wp_cache_get( $cache_key, self::CACHE_GROUP );
        if ( false !== $cached ) {
            return $cached;
        }

        $like = '%' . $wpdb->esc_like( $filename ) . '%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Using direct query for performance; sanitized via $wpdb->prepare()
        $attachments = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s ORDER BY post_date DESC LIMIT 1",
            $like
        ) );

        $result = false;
        if ( ! empty( $attachments ) ) {
            $result = get_post( $attachments[0]->ID );
        }

        wp_cache_set( $cache_key, $result, self::CACHE_GROUP, MINUTE_IN_SECONDS * 5 );
        return $result;
    }


    // Print a thankyou notice
    public function z_admin_footer_print_thankyou( $data ) {
        $data = '<p class="zThanks"><a href="https://zodan.nl" target="_blank" rel="noreferrer">' .
                    esc_html__('Made with', 'z-authorized-downloads') . 
                    '<svg id="heart" data-name="heart" xmlns="http://www.w3.org/2000/svg" width="745.2" height="657.6" version="1.1" viewBox="0 0 745.2 657.6"><path class="heart" d="M372,655.6c-2.8,0-5.5-1.3-7.2-3.6-.7-.9-71.9-95.4-159.9-157.6-11.7-8.3-23.8-16.3-36.5-24.8-60.7-40.5-123.6-82.3-152-151.2C0,278.9-1.4,217.6,12.6,158.6,28,93.5,59,44.6,97.8,24.5,125.3,10.2,158.1,2.4,190.2,2.4s.3,0,.4,0c34.7,0,66.5,9,92.2,25.8,22.4,14.6,70.3,78,89.2,103.7,18.9-25.7,66.8-89,89.2-103.7,25.7-16.8,57.6-25.7,92.2-25.8,32.3-.1,65.2,7.8,92.8,22.1h0c38.7,20.1,69.8,69,85.2,134.1,14,59.1,12.5,120.3-3.8,159.8-28.5,69-91.3,110.8-152,151.2-12.8,8.5-24.8,16.5-36.5,24.8-88.1,62.1-159.2,156.6-159.9,157.6-1.7,2.3-4.4,3.6-7.2,3.6Z"></path></svg>' .
                    esc_html__('by Zodan', 'z-authorized-downloads') .
                '</a></p>';

        return $data;
    }

}

$GLOBALS['z_authorized_downloads'] = new z_authorized_downloads();
register_activation_hook( __FILE__, array( 'z_authorized_downloads', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'z_authorized_downloads', 'deactivate' ) );

?>