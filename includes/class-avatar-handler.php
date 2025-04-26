<?php
/**
 * Avatar Handler Class
 *
 * Handles avatar uploads, storage, and display throughout WordPress.
 * Completely replaces Gravatar with locally hosted avatars.
 *
 * @package Virtual_Authors
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Avatar Handler Class
 */
class VA_Avatar_Handler {
    /**
     * Instance of this class.
     *
     * @var VA_Avatar_Handler
     */
    private static $instance = null;
    
    /**
     * Full path to avatar directory.
     *
     * @var string
     */
    private $avatar_dir;
    
    /**
     * URL to avatar directory.
     *
     * @var string
     */
    private $avatar_url;
    
    /**
     * Constructor.
     */
    private function __construct() {
        // Set up avatar paths
        $upload_dir = wp_upload_dir();
        $this->avatar_dir = trailingslashit($upload_dir['basedir']) . VA_AVATAR_DIR_NAME;
        $this->avatar_url = trailingslashit($upload_dir['baseurl']) . VA_AVATAR_DIR_NAME;
        
        // Set up hooks
        $this->setup_hooks();
    }
    
    /**
     * Get instance of this class.
     *
     * @return VA_Avatar_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Setup hooks for avatar handling.
     */
    private function setup_hooks() {
        // Filter WordPress avatars
        add_filter('get_avatar', array($this, 'filter_avatar'), 10, 5);
        add_filter('get_avatar_url', array($this, 'filter_avatar_url'), 10, 2);
        add_filter('avatar_defaults', array($this, 'remove_default_avatars'));
        
        // Prevent any use of gravatar.com
        add_filter('pre_option_show_avatars', array($this, 'filter_show_avatars'));
        add_filter('option_show_avatars', array($this, 'filter_show_avatars'));
        
        // Ajax handlers for avatar uploads
        add_action('wp_ajax_va_upload_avatar', array($this, 'ajax_upload_avatar'));
        
        // Delete avatar when user is deleted
        add_action('delete_user', array($this, 'delete_avatar'));
        
        // Add avatar field to user profile
        add_action('show_user_profile', array($this, 'add_avatar_field'));
        add_action('edit_user_profile', array($this, 'add_avatar_field'));
        
        // Save avatar on profile update
        add_action('personal_options_update', array($this, 'save_avatar_field'));
        add_action('edit_user_profile_update', array($this, 'save_avatar_field'));
        
        // Add column to users list
        add_filter('manage_users_columns', array($this, 'add_avatar_column'));
        add_filter('manage_users_custom_column', array($this, 'manage_avatar_column'), 10, 3);
        
        // Remove Gravatar.com references
        add_filter('user_profile_picture_description', array($this, 'remove_gravatar_references'));
    }
    
    /**
     * Remove Gravatar references from user profile.
     *
     * @param string $description Profile picture description.
     * @return string Filtered description.
     */
    public function remove_gravatar_references($description) {
        // Simple replacement to remove Gravatar references
        return __('Upload your own avatar below.', 'virtual-authors');
    }
    
    /**
     * Force show avatars to be true.
     *
     * @param mixed $value Option value.
     * @return bool Always true.
     */
    public function filter_show_avatars($value) {
        return true;
    }
    
    /**
     * Remove default avatars.
     *
     * @param array $defaults Default avatar options.
     * @return array Filtered avatar options.
     */
    public function remove_default_avatars($defaults) {
        // Replace all defaults with our own default avatar
        return array(
            'va_default' => __('Default Avatar', 'virtual-authors')
        );
    }
    
    /**
     * Filter avatar HTML output.
     *
     * @param string $avatar      Avatar image HTML.
     * @param mixed  $id_or_email User ID, email, or comment object.
     * @param int    $size        Avatar size in pixels.
     * @param string $default     URL to default avatar image.
     * @param string $alt         Alternative text.
     * @return string Filtered avatar HTML.
     */
    public function filter_avatar($avatar, $id_or_email, $size, $default, $alt) {
        // Get user ID from various input types
        $user_id = $this->get_user_id($id_or_email);
        
        if (!$user_id) {
            // If not a user or user not found, use default avatar
            $avatar_url = $this->get_default_avatar_url();
        } else {
            // Get the avatar URL
            $avatar_url = $this->get_avatar_url_for_user($user_id);
            
            if (!$avatar_url) {
                // No custom avatar, use default
                $avatar_url = $this->get_default_avatar_url();
            }
        }
        
        // Create HTML for the avatar with data-user attribute for JavaScript targeting
        $html = sprintf(
            '<img alt="%s" src="%s" class="avatar avatar-%d photo va-avatar" height="%d" width="%d" loading="lazy" data-user="%d" />',
            esc_attr($alt),
            esc_url($avatar_url),
            esc_attr($size),
            esc_attr($size),
            esc_attr($size),
            intval($user_id)
        );
        
        return $html;
    }
    
    /**
     * Filter avatar URL.
     *
     * @param string $url         URL to avatar.
     * @param mixed  $id_or_email User ID, email, or comment object.
     * @return string Filtered avatar URL.
     */
    public function filter_avatar_url($url, $id_or_email) {
        // Get user ID from various input types
        $user_id = $this->get_user_id($id_or_email);
        
        // If it's a user, return our custom avatar URL
        if ($user_id) {
            $avatar_url = $this->get_avatar_url_for_user($user_id);
            if ($avatar_url) {
                return $avatar_url;
            }
        }
        
        // No user or no avatar, return default
        return $this->get_default_avatar_url();
    }
    
    /**
     * Get default avatar URL.
     *
     * @return string Default avatar URL.
     */
    public function get_default_avatar_url() {
        return VA_PLUGIN_URL . 'assets/images/default-avatar.png';
    }
    
    /**
     * Add avatar field to user profile.
     *
     * @param WP_User $user User object.
     */
    public function add_avatar_field($user) {
        $avatar_path = get_user_meta($user->ID, 'va_avatar_path', true);
        ?>
        <h2><?php _e('Author Avatar', 'virtual-authors'); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label><?php _e('Avatar', 'virtual-authors'); ?></label>
                </th>
                <td>
                    <div class="va-avatar-preview" style="margin-bottom: 15px;">
                        <?php echo get_avatar($user->ID, 96); ?>
                    </div>
                    
                    <div class="va-avatar-upload" id="va-profile-avatar-upload">
                        <div class="va-avatar-preview"></div>
                        <input type="file" name="va_avatar_file" id="va-avatar-file" accept="image/jpeg,image/png,image/gif" />
                        <p class="description">
                            <?php _e('Upload a custom avatar. Supported formats: JPEG, PNG, GIF.', 'virtual-authors'); ?>
                        </p>
                        <p class="description">
                            <?php _e('You can also drag and drop or paste an image.', 'virtual-authors'); ?>
                        </p>
                    </div>
                    
                    <?php if ($avatar_path): ?>
                    <div style="margin-top: 10px;">
                        <label>
                            <input type="checkbox" name="va_remove_avatar" value="1" />
                            <?php _e('Remove custom avatar', 'virtual-authors'); ?>
                        </label>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save avatar field on profile update.
     *
     * @param int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    public function save_avatar_field($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Check if we want to remove the avatar
        if (isset($_POST['va_remove_avatar']) && $_POST['va_remove_avatar'] == '1') {
            $this->remove_avatar($user_id);
            return true;
        }
        
        // Process avatar upload if present
        if (isset($_FILES['va_avatar_file']) && !empty($_FILES['va_avatar_file']['tmp_name'])) {
            // Get the user
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }
            
            // Process the upload
            $result = $this->process_avatar_upload($_FILES['va_avatar_file'], $user);
            
            if (is_wp_error($result)) {
                // Add an error message visible on the profile page
                add_action('user_profile_update_errors', function($errors) use ($result) {
                    $errors->add('avatar_error', $result->get_error_message());
                });
                return false;
            }
            
            // Clear all caches for this user
            clean_user_cache($user_id);
            
            // Add a timestamp to force browser refresh
            update_user_meta($user_id, 'va_avatar_timestamp', time());
        }
        
        return true;
    }
    
    /**
     * Get user ID from various input types.
     *
     * @param mixed $id_or_email User ID, email, or comment object.
     * @return int|false User ID or false if not found.
     */
    public function get_user_id($id_or_email) {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        } elseif (is_string($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            if ($user) {
                return $user->ID;
            }
        } elseif (is_object($id_or_email)) {
            if (!empty($id_or_email->user_id)) {
                return (int) $id_or_email->user_id;
            } elseif (!empty($id_or_email->comment_author_email)) {
                $user = get_user_by('email', $id_or_email->comment_author_email);
                if ($user) {
                    return $user->ID;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add avatar column to users list.
     *
     * @param array $columns Users list columns.
     * @return array Modified columns.
     */
    public function add_avatar_column($columns) {
        $new_columns = array();
        
        // Add avatar column after checkbox
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'cb') {
                $new_columns['avatar'] = __('Avatar', 'virtual-authors');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Manage avatar column content.
     *
     * @param string $output      Custom column output.
     * @param string $column_name Column name.
     * @param int    $user_id     User ID.
     * @return string Column content.
     */
    public function manage_avatar_column($output, $column_name, $user_id) {
        if ($column_name !== 'avatar') {
            return $output;
        }
        
        // Create a wrapper with edit trigger
        $output = '<div class="va-avatar-edit" data-user-id="' . esc_attr($user_id) . '">';
        $output .= get_avatar($user_id, 32);
        $output .= '</div>';
        return $output;
    }
    
    /**
     * Get avatar URL for a user.
     *
     * @param int $user_id User ID.
     * @return string|false Avatar URL or false if not set.
     */
    public function get_avatar_url_for_user($user_id) {
        $avatar_path = get_user_meta($user_id, 'va_avatar_path', true);
        
        if (!$avatar_path) {
            return false;
        }
        
        // Get user slug for clean URL
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Use custom slug if available, otherwise use nicename
        $slug = get_user_meta($user_id, 'va_author_slug', true);
        $slug = !empty($slug) ? $slug : $user->user_nicename;
        
        // Get timestamp for cache busting
        $timestamp = get_user_meta($user_id, 'va_avatar_timestamp', true);
        $timestamp = !empty($timestamp) ? $timestamp : time();
        
        // Return clean URL with timestamp
        return home_url("/author-avatar/{$slug}") . '?t=' . $timestamp;
    }
    
    /**
     * Ajax handler for avatar upload.
     */
    public function ajax_upload_avatar() {
        // Check nonce
        check_ajax_referer('va_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(array('message' => __('Permission denied', 'virtual-authors')));
            return;
        }
        
        // Check for user ID
        if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
            wp_send_json_error(array('message' => __('Invalid user ID', 'virtual-authors')));
            return;
        }
        
        $user_id = intval($_POST['user_id']);
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'virtual-authors')));
            return;
        }
        
        // Check for file
        if (!isset($_FILES['avatar_file']) || empty($_FILES['avatar_file']['tmp_name'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'virtual-authors')));
            return;
        }
        
        // Process the upload
        $result = $this->process_avatar_upload($_FILES['avatar_file'], $user);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        // Clear any caches
        clean_user_cache($user_id);
        
        // Add a timestamp for cache busting
        update_user_meta($user_id, 'va_avatar_timestamp', time());
        
        wp_send_json_success(array(
            'url' => $this->get_avatar_url_for_user($user_id),
            'avatar_html' => get_avatar($user_id, 96)
        ));
    }
    
    /**
     * Process avatar upload.
     *
     * @param array    $file Upload file array.
     * @param WP_User  $user User object.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function process_avatar_upload($file, $user) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Make sure upload directory exists
        if (!file_exists($this->avatar_dir)) {
            wp_mkdir_p($this->avatar_dir);
        }
        
        // Check for file errors
        if ($file['error'] != UPLOAD_ERR_OK) {
            $error_message = $this->get_upload_error_message($file['error']);
            return new WP_Error('upload_error', $error_message);
        }
        
        // Verify the file is an image
        $file_type = wp_check_filetype($file['name']);
        if (!$file_type['type'] || !preg_match('/(jpeg|jpg|png|gif)$/i', $file_type['ext'])) {
            return new WP_Error('invalid_file', __('Invalid file type. Please upload a JPG, PNG, or GIF image.', 'virtual-authors'));
        }
        
        // Prepare filename using user slug or login
        $slug = get_user_meta($user->ID, 'va_author_slug', true);
        $slug = !empty($slug) ? $slug : $user->user_login;
        $slug = sanitize_title($slug);
        
        // Add random suffix to prevent caching issues
        $random_suffix = substr(md5(time()), 0, 6);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $slug . '-' . $random_suffix . '.' . $extension;
        
        // Prepare upload overrides
        $override = array(
            'test_form' => false,
            'unique_filename_callback' => function() use ($filename) {
                return $filename;
            }
        );
        
        // Define custom upload dir
        $custom_upload_dir = array(
            'path' => $this->avatar_dir,
            'url' => $this->avatar_url,
            'subdir' => '',
            'basedir' => $this->avatar_dir,
            'baseurl' => $this->avatar_url,
            'error' => false
        );
        
        // Use a custom upload location
        add_filter('upload_dir', function() use ($custom_upload_dir) {
            return $custom_upload_dir;
        });
        
        // Upload the file
        $uploadedfile = wp_handle_upload($file, $override);
        
        // Remove our custom upload dir filter
        remove_all_filters('upload_dir');
        
        if (!$uploadedfile || isset($uploadedfile['error'])) {
            $error_message = isset($uploadedfile['error']) ? $uploadedfile['error'] : __('Unknown upload error', 'virtual-authors');
            return new WP_Error('upload_error', $error_message);
        }
        
        // Get the relative path (to save in metadata)
        $relative_path = VA_AVATAR_DIR_NAME . '/' . $filename;
        
        // Delete previous avatar file
        $this->remove_avatar_file($user->ID);
        
        // Update user meta
        update_user_meta($user->ID, 'va_avatar_path', $relative_path);
        update_user_meta($user->ID, 'va_avatar_timestamp', time());
        
        // Clear any caches
        clean_user_cache($user->ID);
        
        return true;
    }
    
    /**
     * Get upload error message.
     *
     * @param int $error_code PHP upload error code.
     * @return string Error message.
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'virtual-authors');
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'virtual-authors');
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded.', 'virtual-authors');
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded.', 'virtual-authors');
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder.', 'virtual-authors');
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk.', 'virtual-authors');
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload.', 'virtual-authors');
            default:
                return __('Unknown upload error.', 'virtual-authors');
        }
    }
    
    /**
     * Remove avatar file (not user meta).
     *
     * @param int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    private function remove_avatar_file($user_id) {
        $avatar_path = get_user_meta($user_id, 'va_avatar_path', true);
        
        if ($avatar_path) {
            // Get the full path
            $upload_dir = wp_upload_dir();
            $full_path = trailingslashit($upload_dir['basedir']) . $avatar_path;
            
            // Delete the file if it exists
            if (file_exists($full_path)) {
                @unlink($full_path);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove avatar for a user.
     *
     * @param int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    public function remove_avatar($user_id) {
        // Remove the file
        $this->remove_avatar_file($user_id);
        
        // Remove the user meta
        delete_user_meta($user_id, 'va_avatar_path');
        delete_user_meta($user_id, 'va_avatar_timestamp');
        
        // Clear cache
        clean_user_cache($user_id);
        
        return true;
    }
    
    /**
     * Delete avatar when user is deleted.
     *
     * @param int $user_id User ID.
     */
    public function delete_avatar($user_id) {
        $this->remove_avatar($user_id);
    }
}

// Initialize the Avatar Handler
VA_Avatar_Handler::get_instance();