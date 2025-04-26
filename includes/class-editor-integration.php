<?php
/**
 * Editor Integration Class
 *
 * Handles integration with the WordPress editor, adding the author panel.
 * Updated with simplified avatar interaction.
 *
 * @package Virtual_Authors
 * @author Tim Hosking (https://github.com/Munger)
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editor Integration Class
 */
class VA_Editor_Integration {
    /**
     * Instance of this class.
     *
     * @var VA_Editor_Integration
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
     * @return VA_Editor_Integration
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
        // Add meta box to post editor
        add_action('add_meta_boxes', array($this, 'add_author_meta_box'));
        
        // Save post author info
        add_action('save_post', array($this, 'save_post_author'), 10, 2);
        
        // Add editor data to the page
        add_action('admin_head-post.php', array($this, 'add_editor_data'));
        add_action('admin_head-post-new.php', array($this, 'add_editor_data'));
    }
    
    /**
     * Add author meta box to post editor.
     */
    public function add_author_meta_box() {
        add_meta_box(
            'virtual-authors-meta-box',
            __('Author Details', 'virtual-authors'),
            array($this, 'render_author_meta_box'),
            'post',
            'normal',
            'high'
        );
    }
    
    /**
     * Render author meta box.
     * Updated with simplified avatar interaction.
     *
     * @param WP_Post $post Post object.
     */
    public function render_author_meta_box($post) {
        // Get current author
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        
        // Nonce for security
        wp_nonce_field('va_save_author_data', 'va_author_nonce');
        
        // Check if we have an author
        if (!$author) {
            echo '<p>' . __('No author selected.', 'virtual-authors') . '</p>';
            return;
        }
        
        // Get avatar URL with timestamp for cache busting
        $avatar_url = get_avatar_url($author->ID, array('size' => 96));
        $timestamp = get_user_meta($author->ID, 'va_avatar_timestamp', true);
        if ($timestamp) {
            $avatar_url = add_query_arg('t', $timestamp, $avatar_url);
        }
        
        // Get author data
        $author_data = array(
            'id' => $author->ID,
            'name' => $author->display_name,
            'slug' => $this->get_author_slug($author->ID),
            'bio' => get_user_meta($author->ID, 'description', true),
            'avatar' => $avatar_url,
            'isVirtual' => (bool) get_user_meta($author->ID, 'va_is_virtual', true)
        );
        
        // Start output
        ?>
        <div class="va-author-panel" id="va-author-panel">
            <!-- Create button positioned at top-right via CSS -->
            <?php if (current_user_can('create_users')) : ?>
                <div class="va-create-button">
                    <button type="button" class="button" id="va-create-author-btn">
                        <?php _e('Create New Author', 'virtual-authors'); ?>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Author details section -->
            <div class="va-author-info">
                <div class="va-author-details" id="va-author-details">
                    <!-- Interactive avatar -->
                    <div class="va-avatar-interactive" data-user-id="<?php echo esc_attr($author_id); ?>">
                        <img src="<?php echo esc_url($avatar_url); ?>" 
                             alt="<?php echo esc_attr($author->display_name); ?>" 
                             width="96" height="96" 
                             data-user-id="<?php echo esc_attr($author_id); ?>" 
                             class="avatar avatar-96 photo va-avatar">
                        <div class="va-avatar-overlay"><?php _e('Click to change', 'virtual-authors'); ?></div>
                    </div>
                    
                    <div class="va-author-meta">
                        <h3 class="va-author-name"><?php echo esc_html($author->display_name); ?></h3>
                        
                        <?php if ($author_data['isVirtual']) : ?>
                            <div class="va-virtual-badge">
                                <span class="dashicons dashicons-businessman"></span>
                                <?php _e('Virtual Author', 'virtual-authors'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="va-author-edit-container">
                            <div class="va-form-field va-slug-field">
                                <label for="va-inline-slug"><?php _e('Slug:', 'virtual-authors'); ?></label>
                                <input type="text" id="va-inline-slug" name="va_inline_slug" value="<?php echo esc_attr($author_data['slug']); ?>" class="regular-text">
                            </div>
                            
                            <div class="va-form-field va-bio-field">
                                <label for="va-inline-bio"><?php _e('Bio:', 'virtual-authors'); ?></label>
                                <textarea id="va-inline-bio" name="va_inline_bio" rows="6" class="large-text"><?php echo esc_textarea($author_data['bio']); ?></textarea>
                            </div>
                            
                            <div class="va-form-buttons inline-edit-buttons">
                                <button type="button" class="button button-primary" id="va-save-author-changes" data-user-id="<?php echo esc_attr($author_id); ?>" disabled>
                                    <?php _e('Save Author', 'virtual-authors'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Author creation form (hidden by default) -->
            <div class="va-create-author-form" id="va-create-author-form" style="display:none;">
                <h3><?php _e('Create New Author', 'virtual-authors'); ?></h3>
                
                <div class="va-form-field">
                    <label for="va-new-author-name"><?php _e('Name', 'virtual-authors'); ?></label>
                    <input type="text" id="va-new-author-name" name="va_new_author_name" class="regular-text">
                </div>
                
                <div class="va-form-field">
                    <label for="va-new-author-slug"><?php _e('Slug', 'virtual-authors'); ?></label>
                    <input type="text" id="va-new-author-slug" name="va_new_author_slug" class="regular-text">
                    <p class="description"><?php _e('Used in URLs. Leave blank to generate from name.', 'virtual-authors'); ?></p>
                </div>
                
                <div class="va-form-field">
                    <label for="va-new-author-bio"><?php _e('Bio', 'virtual-authors'); ?></label>
                    <textarea id="va-new-author-bio" name="va_new_author_bio" rows="4" class="regular-text"></textarea>
                </div>
                
                <div class="va-form-field">
                    <label for="va-new-author-avatar"><?php _e('Avatar', 'virtual-authors'); ?></label>
                    <div class="va-avatar-interactive" data-user-id="new">
                        <img src="<?php echo esc_url(VA_PLUGIN_URL . 'assets/images/default-avatar.png'); ?>" 
                             alt="<?php _e('Default avatar', 'virtual-authors'); ?>" 
                             width="96" height="96" 
                             class="avatar avatar-96 photo va-avatar">
                        <div class="va-avatar-overlay"><?php _e('Click to upload', 'virtual-authors'); ?></div>
                        <input type="file" id="va-new-author-avatar" name="va_new_author_avatar" accept="image/jpeg,image/png,image/gif" style="display:none;">
                    </div>
                    <p class="description"><?php _e('Click to select an image, or drag and drop.', 'virtual-authors'); ?></p>
                </div>
                
                <div class="va-form-buttons">
                    <button type="button" class="button" id="va-cancel-create-author">
                        <?php _e('Cancel', 'virtual-authors'); ?>
                    </button>
                    <button type="button" class="button button-primary" id="va-save-new-author">
                        <?php _e('Create Author', 'virtual-authors'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get author slug.
     *
     * @param int $user_id User ID.
     * @return string Author slug.
     */
    private function get_author_slug($user_id) {
        $slug = get_user_meta($user_id, 'va_author_slug', true);
        
        if (empty($slug)) {
            $user = get_userdata($user_id);
            $slug = $user ? $user->user_nicename : '';
        }
        
        return $slug;
    }
    
    /**
     * Save post author information.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function save_post_author($post_id, $post) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the nonce
        if (!isset($_POST['va_author_nonce']) || !wp_verify_nonce($_POST['va_author_nonce'], 'va_save_author_data')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Since we're no longer managing author selection in our panel,
        // we just rely on WordPress's built-in author handling
    }
    
    /**
     * Add editor data to the page.
     */
    public function add_editor_data() {
        // Get post author
        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        
        if (!$author) {
            return;
        }
        
        // Get avatar URL with timestamp for cache busting
        $avatar_url = get_avatar_url($author->ID);
        $timestamp = get_user_meta($author->ID, 'va_avatar_timestamp', true);
        if ($timestamp) {
            $avatar_url = add_query_arg('t', $timestamp, $avatar_url);
        }
        
        // Get author data
        $author_data = array(
            'id' => $author->ID,
            'name' => $author->display_name,
            'slug' => $this->get_author_slug($author->ID),
            'bio' => get_user_meta($author->ID, 'description', true),
            'avatar' => $avatar_url,
            'isVirtual' => (bool) get_user_meta($author->ID, 'va_is_virtual', true)
        );
        
        // Output the data
        ?>
        <script type="text/javascript">
            window.virtualAuthorsData = <?php echo wp_json_encode($author_data); ?>;
        </script>
        <?php
    }
}

// Initialize the Editor Integration
VA_Editor_Integration::get_instance();