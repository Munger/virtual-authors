<?php
/**
 * Plugin Name: Virtual Authors
 * Description: Enhance post authorship with custom avatars and efficient author management directly from the post editor.
 * Version: 1.0.1
 * Author: Tim Hosking
 * Author URI: https://github.com/Munger
 * Text Domain: virtual-authors
 * Domain Path: /languages
 * 
 * @author Tim Hosking (https://github.com/Munger)
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VA_VERSION', '1.0.1');
define('VA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VA_AVATAR_DIR_NAME', 'avatars'); // Directory name inside uploads

/**
 * Main plugin class.
 * 
 * Initializes components and sets up the plugin.
 */
class Virtual_Authors {
    /**
     * Instance of this class.
     *
     * @var object
     */
    private static $instance = null;
    
    /**
     * Get an instance of this class.
     *
     * @return Virtual_Authors
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the plugin.
     */
    private function __construct() {
        // Load plugin components
        $this->load_components();
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Load plugin components.
     */
    private function load_components() {
        // Include required files
        require_once VA_PLUGIN_DIR . 'includes/class-avatar-handler.php';
        require_once VA_PLUGIN_DIR . 'includes/class-author-manager.php';
        require_once VA_PLUGIN_DIR . 'includes/class-editor-integration.php';
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Initialize rewrite rules
        add_action('init', array($this, 'init_rewrite_rules'));
        
        // Create avatar directory if it doesn't exist
        $this->ensure_avatar_directory();
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Remove all Gravatar references
        $this->disable_gravatar();
    }
    
    /**
     * Disable Gravatar across WordPress.
     */
    private function disable_gravatar() {
        // Disable Gravatar completely
        add_filter('option_avatar_default', function() {
            return 'va_default';
        });
        
        // Block any attempt to use Gravatar URLs
        add_filter('get_avatar', function($avatar) {
            if (strpos($avatar, 'gravatar.com') !== false) {
                // Use our avatar handler directly
                $avatar_handler = VA_Avatar_Handler::get_instance();
                return $avatar_handler->get_custom_avatar('', '', 96);
            }
            return $avatar;
        }, 1, 1);
    }
    
    /**
     * Initialize rewrite rules.
     */
    public function init_rewrite_rules() {
        // Add rewrite rule for avatar URLs
        add_rewrite_rule(
            'author-avatar/([^/]+)/?$',
            'index.php?va_avatar=$matches[1]',
            'top'
        );
        
        // Add query vars
        add_filter('query_vars', function($query_vars) {
            $query_vars[] = 'va_avatar';
            return $query_vars;
        });
        
        // Handle avatar requests
        add_action('template_redirect', array($this, 'handle_avatar_request'));
    }
    
    /**
     * Handle avatar request.
     * Improved to better handle cache busting and improve performance.
     */
    public function handle_avatar_request() {
        $avatar_slug = get_query_var('va_avatar');
        
        if (empty($avatar_slug)) {
            return;
        }
        
        // Try to find user by slug first
        $users = get_users(array(
            'meta_key' => 'va_author_slug',
            'meta_value' => $avatar_slug,
            'number' => 1
        ));
        
        // If not found, try by login
        if (empty($users)) {
            $user = get_user_by('login', $avatar_slug);
            $users = $user ? array($user) : array();
        }
        
        // If not found, try by nicename
        if (empty($users)) {
            $user = get_user_by('slug', $avatar_slug);
            $users = $user ? array($user) : array();
        }
        
        if (empty($users)) {
            // User not found, serve default avatar
            $this->serve_default_avatar();
            exit;
        }
        
        $user = $users[0];
        $avatar_path = get_user_meta($user->ID, 'va_avatar_path', true);
        
        if (empty($avatar_path)) {
            // No avatar set, serve default avatar
            $this->serve_default_avatar();
            exit;
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        $full_path = trailingslashit($upload_dir['basedir']) . $avatar_path;
        
        // Check if file exists
        if (!file_exists($full_path)) {
            // Avatar file not found, serve default avatar
            $this->serve_default_avatar();
            exit;
        }
        
        // Get file modification time for strong ETag
        $last_modified = filemtime($full_path);
        $etag = md5($last_modified . $avatar_path);
        
        // Check if browser has valid cached version
        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;
        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        
        // If browser has a valid cached version, return 304 Not Modified
        if (($if_none_match && $if_none_match === $etag) || 
            ($if_modified_since && strtotime($if_modified_since) >= $last_modified)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }
        
        // Set cache headers for better performance
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // 1 week
        header('Cache-Control: public, max-age=604800');
        header('Pragma: public');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
        
        // Serve the avatar
        $file_info = getimagesize($full_path);
        if ($file_info && isset($file_info['mime'])) {
            header('Content-Type: ' . $file_info['mime']);
            header('Content-Length: ' . filesize($full_path));
            readfile($full_path);
            exit;
        }
        
        // Unable to serve avatar, serve default
        $this->serve_default_avatar();
        exit;
    }
    
    /**
     * Serve default avatar.
     * Improved with better caching directives.
     */
    private function serve_default_avatar() {
        $default_avatar = VA_PLUGIN_DIR . 'assets/images/default-avatar.png';
        
        if (file_exists($default_avatar)) {
            $last_modified = filemtime($default_avatar);
            $etag = md5($last_modified . 'default-avatar');
            
            // Check if browser has valid cached version
            $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;
            $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
            
            // If browser has a valid cached version, return 304 Not Modified
            if (($if_none_match && $if_none_match === $etag) || 
                ($if_modified_since && strtotime($if_modified_since) >= $last_modified)) {
                header('HTTP/1.1 304 Not Modified');
                exit;
            }
            
            // Set cache headers for better performance
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // 1 week
            header('Cache-Control: public, max-age=604800');
            header('Pragma: public');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $last_modified) . ' GMT');
            
            header('Content-Type: image/png');
            header('Content-Length: ' . filesize($default_avatar));
            readfile($default_avatar);
            exit;
        }
        
        // If default avatar doesn't exist, return 404
        status_header(404);
        include(get_query_template('404'));
        exit;
    }
    
    /**
     * Ensure avatar directory exists.
     */
    private function ensure_avatar_directory() {
        $upload_dir = wp_upload_dir();
        $avatar_dir = trailingslashit($upload_dir['basedir']) . VA_AVATAR_DIR_NAME;
        
        if (!file_exists($avatar_dir)) {
            wp_mkdir_p($avatar_dir);
            // Add index.php file to prevent directory listing
            file_put_contents($avatar_dir . '/index.php', '<?php // Silence is golden');
        }
    }
    
    /**
     * Enqueue scripts and styles for the admin.
     *
     * @param string $hook Current admin page.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on post editor, profile, or users pages
        if (!in_array($hook, array('post.php', 'post-new.php', 'profile.php', 'user-edit.php', 'users.php'))) {
            return;
        }
        
        // Enqueue main CSS
        wp_enqueue_style(
            'virtual-authors-admin',
            VA_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            VA_VERSION
        );
        
        // Enqueue admin JavaScript
        wp_enqueue_script(
            'virtual-authors-admin',
            VA_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            VA_VERSION,
            true
        );
        
        // Add localized data
        wp_localize_script(
            'virtual-authors-admin',
            'virtualAuthors',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('va_nonce'),
                'canCreateUsers' => current_user_can('create_users') ? 'true' : 'false',
                'canEditUsers' => current_user_can('edit_users') ? 'true' : 'false',
                'strings' => array(
                    'authorPanel' => __('Author Details', 'virtual-authors'),
                    'createNew' => __('Create New Author', 'virtual-authors'),
                    'name' => __('Name', 'virtual-authors'),
                    'bio' => __('Bio', 'virtual-authors'),
                    'slug' => __('Slug', 'virtual-authors'),
                    'avatar' => __('Avatar', 'virtual-authors'),
                    'save' => __('Save', 'virtual-authors'),
                    'cancel' => __('Cancel', 'virtual-authors'),
                    'uploading' => __('Uploading...', 'virtual-authors'),
                    'dropImage' => __('Drop image here or click to upload', 'virtual-authors'),
                    'pasteImage' => __('You can also paste an image', 'virtual-authors'),
                    'creating' => __('Creating...', 'virtual-authors'),
                    'updating' => __('Updating...', 'virtual-authors'),
                    'nameRequired' => __('Please enter an author name.', 'virtual-authors'),
                    'invalidImageType' => __('Please select an image file (JPEG, PNG, or GIF).', 'virtual-authors'),
                    'virtualAuthor' => __('Virtual Author', 'virtual-authors'),
                )
            )
        );
        
        // On post editor, enqueue editor-specific assets
        if (in_array($hook, array('post.php', 'post-new.php'))) {
            wp_enqueue_script(
                'virtual-authors-editor',
                VA_PLUGIN_URL . 'assets/js/editor.js',
                array('jquery', 'wp-data', 'wp-element', 'wp-components'),
                VA_VERSION,
                true
            );
        }
    }
    
    /**
     * Load text domain for internationalization.
     */
    public function load_textdomain() {
        load_plugin_textdomain('virtual-authors', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Plugin activation.
     */
    public function activate() {
        // Create avatar directory
        $this->ensure_avatar_directory();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
Virtual_Authors::get_instance();