<?php
/**
 * Author Manager Class
 *
 * Handles creating and managing virtual authors.
 *
 * @package Virtual_Authors
 * @author Tim Hosking (https://github.com/Munger)
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Author Manager Class
 */
class VA_Author_Manager {
    /**
     * Instance of this class.
     *
     * @var VA_Author_Manager
     */
    private static $instance = null;
    
    /**
     * Constructor.
     */
    private function __construct() {
        // Setup hooks
        $this->setup_hooks();
    }
    
    /**
     * Get instance of this class.
     *
     * @return VA_Author_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Setup hooks.
     */
    private function setup_hooks() {
        // AJAX handlers for author creation and management
        add_action('wp_ajax_va_create_author', array($this, 'ajax_create_author'));
        add_action('wp_ajax_va_update_author_details', array($this, 'ajax_update_author_details'));
        add_action('wp_ajax_va_get_author_data', array($this, 'ajax_get_author_data'));
        add_action('wp_ajax_va_update_post_author', array($this, 'ajax_update_post_author'));
        
        // Prevent virtual authors from logging in
        add_filter('authenticate', array($this, 'prevent_virtual_author_login'), 30, 3);
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add custom fields to user profile
        add_action('show_user_profile', array($this, 'add_custom_fields'));
        add_action('edit_user_profile', array($this, 'add_custom_fields'));
        
        // Save custom profile fields
        add_action('personal_options_update', array($this, 'save_custom_fields'));
        add_action('edit_user_profile_update', array($this, 'save_custom_fields'));
        
        // Add virtual author column to users list
        add_filter('manage_users_columns', array($this, 'add_virtual_author_column'));
        add_filter('manage_users_custom_column', array($this, 'manage_virtual_author_column'), 10, 3);
        
        // Add filter for virtual authors in users list
        add_action('restrict_manage_users', array($this, 'add_virtual_authors_filter'));
        add_filter('pre_get_users', array($this, 'filter_users_by_virtual'));
    }
    
    /**
     * Register REST API routes.
     */
    public function register_rest_routes() {
        register_rest_route('virtual-authors/v1', '/authors', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_authors'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
        
        register_rest_route('virtual-authors/v1', '/author/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_get_author'),
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }
    
    /**
     * REST API callback to get authors.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response REST response object.
     */
    public function rest_get_authors($request) {
        $authors = get_users(array(
            'role__in' => array('author', 'editor', 'administrator'),
            'fields' => array('ID', 'display_name')
        ));
        
        $formatted_authors = array();
        foreach ($authors as $author) {
            $formatted_authors[] = array(
                'id' => $author->ID,
                'name' => $author->display_name,
                'slug' => $this->get_author_slug($author->ID),
                'bio' => get_user_meta($author->ID, 'description', true),
                'avatar' => get_avatar_url($author->ID),
                'isVirtual' => (bool) get_user_meta($author->ID, 'va_is_virtual', true)
            );
        }
        
        return rest_ensure_response($formatted_authors);
    }
    
    /**
     * REST API callback to get author.
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response|WP_Error REST response object or error.
     */
    public function rest_get_author($request) {
        $user_id = $request->get_param('id');
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('author_not_found', __('Author not found', 'virtual-authors'), array('status' => 404));
        }
        
        $author_data = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'slug' => $this->get_author_slug($user->ID),
            'bio' => get_user_meta($user->ID, 'description', true),
            'avatar' => get_avatar_url($user->ID),
            'isVirtual' => (bool) get_user_meta($user->ID, 'va_is_virtual', true)
        );
        
        return rest_ensure_response($author_data);
    }
    
    /**
     * Get author slug.
     *
     * @param int $user_id User ID.
     * @return string Author slug.
     */
    public function get_author_slug($user_id) {
        $slug = get_user_meta($user_id, 'va_author_slug', true);
        
        if (empty($slug)) {
            $user = get_userdata($user_id);
            $slug = $user ? $user->user_nicename : '';
        }
        
        return $slug;
    }
    
    /**
     * Add custom fields to user profile.
     *
     * @param WP_User $user User object.
     */
    public function add_custom_fields($user) {
        // Get user data
        $is_virtual = get_user_meta($user->ID, 'va_is_virtual', true);
        $author_slug = get_user_meta($user->ID, 'va_author_slug', true);
        
        // Section title
        $section_title = $is_virtual ? __('Virtual Author Settings', 'virtual-authors') : __('Author Settings', 'virtual-authors');
        ?>
        <h2><?php echo esc_html($section_title); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="va_author_slug"><?php _e('Author Slug', 'virtual-authors'); ?></label>
                </th>
                <td>
                    <input type="text" name="va_author_slug" id="va_author_slug" value="<?php echo esc_attr($author_slug); ?>" class="regular-text" />
                    <p class="description"><?php _e('Custom slug for author pages and avatar URLs. Leave blank to use the default user nicename.', 'virtual-authors'); ?></p>
                </td>
            </tr>
            
            <?php if ($is_virtual): ?>
            <tr>
                <th>
                    <label><?php _e('Virtual Author', 'virtual-authors'); ?></label>
                </th>
                <td>
                    <p>
                        <span class="dashicons dashicons-yes" style="color: green;"></span>
                        <?php _e('This is a virtual author created by the Virtual Authors plugin.', 'virtual-authors'); ?>
                    </p>
                    <p class="description">
                        <?php _e('Virtual authors cannot log in to the site.', 'virtual-authors'); ?>
                    </p>
                </td>
            </tr>
            <?php endif; ?>
        </table>
        <?php
    }
    
    /**
     * Save custom profile fields.
     *
     * @param int $user_id User ID.
     * @return bool True on success, false on failure.
     */
    public function save_custom_fields($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        // Save author slug
        if (isset($_POST['va_author_slug'])) {
            $slug = sanitize_title($_POST['va_author_slug']);
            update_user_meta($user_id, 'va_author_slug', $slug);
            
            // Also update user_nicename if slug is provided
            if (!empty($slug)) {
                wp_update_user(array(
                    'ID' => $user_id,
                    'user_nicename' => $slug
                ));
            }
        }
        
        return true;
    }
    
    /**
     * Add virtual author column to users list.
     *
     * @param array $columns Users list columns.
     * @return array Modified columns.
     */
    public function add_virtual_author_column($columns) {
        $columns['va_virtual'] = __('Virtual Author', 'virtual-authors');
        return $columns;
    }
    
    /**
     * Manage virtual author column content.
     *
     * @param string $output      Custom column output.
     * @param string $column_name Column name.
     * @param int    $user_id     User ID.
     * @return string Column content.
     */
    public function manage_virtual_author_column($output, $column_name, $user_id) {
        if ($column_name !== 'va_virtual') {
            return $output;
        }
        
        if (get_user_meta($user_id, 'va_is_virtual', true)) {
            return '<span class="dashicons dashicons-yes" style="color:green;"></span>';
        }
        
        return '—';
    }
    
    /**
     * Add filter for virtual authors.
     */
    public function add_virtual_authors_filter() {
        $selected = isset($_GET['va_virtual']) ? $_GET['va_virtual'] : '';
        ?>
        <label for="filter-by-virtual" class="screen-reader-text">
            <?php _e('Filter by virtual author status', 'virtual-authors'); ?>
        </label>
        <select name="va_virtual" id="filter-by-virtual">
            <option value=""><?php _e('All authors', 'virtual-authors'); ?></option>
            <option value="1" <?php selected($selected, '1'); ?>>
                <?php _e('Virtual authors only', 'virtual-authors'); ?>
            </option>
            <option value="0" <?php selected($selected, '0'); ?>>
                <?php _e('Regular authors only', 'virtual-authors'); ?>
            </option>
        </select>
        <?php
    }
    
    /**
     * Filter users by virtual status.
     *
     * @param WP_User_Query $query User query.
     */
    public function filter_users_by_virtual($query) {
        if (!is_admin()) {
            return;
        }
        
        if (!isset($_GET['va_virtual']) || $_GET['va_virtual'] === '') {
            return;
        }
        
        $is_virtual = $_GET['va_virtual'] === '1';
        
        // Add meta query to filter by virtual status
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }
        
        $meta_query[] = array(
            'key' => 'va_is_virtual',
            'value' => '1',
            'compare' => $is_virtual ? '=' : 'NOT EXISTS'
        );
        
        $query->set('meta_query', $meta_query);
    }
    
    /**
     * Ajax handler for creating a new author.
     */
    public function ajax_create_author() {
        // Verify nonce
        check_ajax_referer('va_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('create_users')) {
            wp_send_json_error(array('message' => __('Permission denied', 'virtual-authors')));
            return;
        }
        
        // Get and validate fields
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $bio = isset($_POST['bio']) ? wp_kses_post($_POST['bio']) : '';
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Please provide a name', 'virtual-authors')));
            return;
        }
        
        // Create the author
        $user_id = $this->create_author(array(
            'name' => $name,
            'bio' => $bio,
            'slug' => $slug
        ));
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => $user_id->get_error_message()));
            return;
        }
        
        // Process avatar if uploaded
        if (isset($_FILES['avatar_file']) && !empty($_FILES['avatar_file']['tmp_name'])) {
            // Get the Avatar Handler instance
            $avatar_handler = VA_Avatar_Handler::get_instance();
            
            // Process the upload
            $avatar_handler->process_avatar_upload($_FILES['avatar_file'], get_userdata($user_id));
        }
        
        // Assign author to post if post_id is provided
        if ($post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_author' => $user_id
            ));
        }
        
        // Return success
        wp_send_json_success(array(
            'user_id' => $user_id,
            'id' => $user_id,
            'name' => $name,
            'slug' => $this->get_author_slug($user_id),
            'bio' => $bio,
            'avatar' => get_avatar_url($user_id),
            'isVirtual' => true
        ));
    }
    
    /**
     * Ajax handler for updating author details.
     */
    public function ajax_update_author_details() {
        // Verify nonce
        check_ajax_referer('va_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('edit_users')) {
            wp_send_json_error(array('message' => __('Permission denied', 'virtual-authors')));
            return;
        }
        
        // Get and validate fields
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $bio = isset($_POST['bio']) ? wp_kses_post($_POST['bio']) : '';
        $slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID', 'virtual-authors')));
            return;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'virtual-authors')));
            return;
        }
        
        // Update the author details
        $update_data = array(
            'bio' => $bio,
            'slug' => $slug
        );
        
        $result = $this->update_author($user_id, $update_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        // Process avatar if uploaded
        $avatar_file = null;
        if (isset($_FILES['avatar_file']) && !empty($_FILES['avatar_file']['tmp_name'])) {
            $avatar_file = $_FILES['avatar_file'];
        } elseif (isset($_FILES['va_inline_avatar_upload']) && !empty($_FILES['va_inline_avatar_upload']['tmp_name'])) {
            $avatar_file = $_FILES['va_inline_avatar_upload'];
        }
        
        if ($avatar_file) {
            // Get the Avatar Handler instance
            $avatar_handler = VA_Avatar_Handler::get_instance();
            
            // Process the upload
            $result = $avatar_handler->process_avatar_upload($avatar_file, $user);
            
            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
                return;
            }
        }
        
        // Clear any avatar caches
        clean_user_cache($user_id);
        
        // Get updated author data
        $author_data = array(
            'id' => $user_id,
            'name' => $user->display_name,
            'slug' => $this->get_author_slug($user_id),
            'bio' => get_user_meta($user_id, 'description', true),
            'avatar' => get_avatar_url($user_id) . '?t=' . time(), // Add timestamp to bust cache
            'isVirtual' => (bool) get_user_meta($user_id, 'va_is_virtual', true)
        );
        
        // Return success
        wp_send_json_success($author_data);
    }
    
    /**
     * Ajax handler for getting author data.
     */
    public function ajax_get_author_data() {
        // Verify nonce
        check_ajax_referer('va_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'virtual-authors')));
            return;
        }
        
        // Get and validate fields
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Invalid user ID', 'virtual-authors')));
            return;
        }
        
        $user = get_userdata($user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => __('User not found', 'virtual-authors')));
            return;
        }
        
        // Get author data
        $author_data = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'slug' => $this->get_author_slug($user->ID),
            'bio' => get_user_meta($user->ID, 'description', true),
            'avatar' => get_avatar_url($user->ID) . '?t=' . time(), // Add timestamp to bust cache
            'isVirtual' => (bool) get_user_meta($user->ID, 'va_is_virtual', true)
        );
        
        // Update post author if post_id is provided
        if ($post_id) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_author' => $user_id
            ));
        }
        
        wp_send_json_success($author_data);
    }
    
    /**
     * Ajax handler for updating post author.
     */
    public function ajax_update_post_author() {
        // Verify nonce
        check_ajax_referer('va_nonce', 'nonce');
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'virtual-authors')));
            return;
        }
        
        // Get and validate fields
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $author_id = isset($_POST['author_id']) ? intval($_POST['author_id']) : 0;
        
        if (!$post_id || !$author_id) {
            wp_send_json_error(array('message' => __('Invalid post or author ID', 'virtual-authors')));
            return;
        }
        
        // Check if user can edit this post
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => __('You do not have permission to edit this post', 'virtual-authors')));
            return;
        }
        
        // Update the post author
        $result = wp_update_post(array(
            'ID' => $post_id,
            'post_author' => $author_id
        ));
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        // Get user data for response
        $user = get_userdata($author_id);
        if (!$user) {
            wp_send_json_error(array('message' => __('Author not found', 'virtual-authors')));
            return;
        }
        
        // Return success with updated author data
        wp_send_json_success(array(
            'id' => $author_id,
            'name' => $user->display_name,
            'slug' => $this->get_author_slug($author_id),
            'bio' => get_user_meta($author_id, 'description', true),
            'avatar' => get_avatar_url($author_id) . '?t=' . time(), // Add timestamp to bust cache
            'isVirtual' => (bool) get_user_meta($author_id, 'va_is_virtual', true)
        ));
    }
    
    /**
     * Prevent virtual authors from logging in.
     *
     * @param WP_User|WP_Error $user User object or error.
     * @param string           $username Username.
     * @param string           $password Password.
     * @return WP_User|WP_Error User object or error.
     */
    public function prevent_virtual_author_login($user, $username, $password) {
        // If authentication has already failed, don't interfere
        if (is_wp_error($user)) {
            return $user;
        }
        
        // Check if this is a virtual author
        $is_virtual = get_user_meta($user->ID, 'va_is_virtual', true);
        
        if ($is_virtual) {
            return new WP_Error(
                'virtual_author',
                __('This is a virtual author account and cannot be used to log in.', 'virtual-authors')
            );
        }
        
        return $user;
    }
    
    /**
     * Create a new author.
     *
     * Public method that can be called programmatically.
     *
     * @param array $author_data Author data.
     * @return int|WP_Error New author ID or error.
     */
    public function create_author($author_data) {
        // Validate data
        if (empty($author_data['name'])) {
            return new WP_Error('missing_name', __('Author name is required', 'virtual-authors'));
        }
        
        $name = sanitize_text_field($author_data['name']);
        $bio = isset($author_data['bio']) ? wp_kses_post($author_data['bio']) : '';
        $slug = isset($author_data['slug']) ? sanitize_title($author_data['slug']) : sanitize_title($name);
        
        // Create unique email and login
        $base_login = sanitize_user(strtolower(str_replace(' ', '.', $name)));
        $random_suffix = wp_rand(1000, 9999);
        $email = $base_login . '.' . $random_suffix . '@virtual.local';
        $login = $base_login . $random_suffix;
        
        // Make sure login and email are unique
        while (username_exists($login) || email_exists($email)) {
            $random_suffix = wp_rand(1000, 9999);
            $email = $base_login . '.' . $random_suffix . '@virtual.local';
            $login = $base_login . $random_suffix;
        }
        
        // Create the user
        $user_id = wp_insert_user(array(
            'user_login' => $login,
            'user_nicename' => $slug,
            'display_name' => $name,
            'first_name' => $name,
            'nickname' => $name,
            'user_email' => $email,
            'description' => $bio,
            'role' => 'author',
            'user_pass' => wp_generate_password(24)
        ));
        
        if (is_wp_error($user_id)) {
            return $user_id;
        }
        
        // Add metadata to mark as virtual author
        update_user_meta($user_id, 'va_is_virtual', 1);
        update_user_meta($user_id, 'va_author_slug', $slug);
        
        // Disable login by setting user_status to 2 (direct DB update since WP doesn't expose this)
        global $wpdb;
        $wpdb->update(
            $wpdb->users,
            array('user_status' => 2),
            array('ID' => $user_id)
        );
        
        return $user_id;
    }
    
    /**
     * Update author profile.
     *
     * @param int   $user_id User ID.
     * @param array $data    Author data to update.
     * @return bool|WP_Error True on success, error object on failure.
     */
    public function update_author($user_id, $data) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return new WP_Error('user_not_found', __('User not found', 'virtual-authors'));
        }
        
        $user_data = array('ID' => $user_id);
        
        // Update name if provided
        if (isset($data['name']) && !empty($data['name'])) {
            $user_data['display_name'] = sanitize_text_field($data['name']);
        }
        
        // Update bio if provided
        if (isset($data['bio'])) {
            $bio = wp_kses_post($data['bio']);
            update_user_meta($user_id, 'description', $bio);
        }
        
        // Update slug if provided
        if (isset($data['slug']) && !empty($data['slug'])) {
            $slug = sanitize_title($data['slug']);
            update_user_meta($user_id, 'va_author_slug', $slug);
            $user_data['user_nicename'] = $slug;
        }
        
        // Update user data
        if (count($user_data) > 1) { // More than just the ID
            $result = wp_update_user($user_data);
            
            if (is_wp_error($result)) {
                return $result;
            }
        }
        
        return true;
    }
}

// Initialize the Author Manager
VA_Author_Manager::get_instance();