/**
 * Virtual Authors - Admin JavaScript
 *
 * Simplified approach with direct avatar updates.
 * Added enhanced debugging.
 *
 * @package Virtual_Authors
 * @author Tim Hosking (https://github.com/Munger)
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        console.log('VA Debug: Initializing Virtual Authors admin JS');
        initAvatarHandling();
        initAuthorManagement();
        initInlineEditing();
    });
    
    /**
     * Initialize avatar handling with a simplified approach
     */
    function initAvatarHandling() {
        console.log('VA Debug: Setting up avatar handling');
        
        // Log available nonce
        console.log('VA Debug: Nonce available:', virtualAuthors.nonce);
        
        // Make avatars clickable to trigger file upload
        $(document).on('click', '.va-avatar-interactive, .va-author-avatar img, .va-avatar-preview', function(e) {
            e.preventDefault();
            console.log('VA Debug: Avatar clicked');
            
            // Get the user ID from data attribute or closest container
            let userId = $(this).data('user-id');
            if (!userId) {
                userId = $(this).closest('[data-user-id]').data('user-id');
            }
            
            console.log('VA Debug: User ID from clicked avatar:', userId);
            
            if (!userId) {
                console.log('VA Debug: No user ID found, aborting upload');
                return;
            }
            
            // Create a hidden file input if not exists
            let fileInput = $('#va-temp-file-input');
            if (!fileInput.length) {
                console.log('VA Debug: Creating file input');
                fileInput = $('<input type="file" id="va-temp-file-input" style="display:none" accept="image/jpeg,image/png,image/gif">');
                $('body').append(fileInput);
            }
            
            // Store the user ID with the file input
            fileInput.data('user-id', userId);
            
            // Trigger the file input
            fileInput.trigger('click');
        });
        
        // Handle file selection for all avatar inputs
        $(document).on('change', '#va-temp-file-input, #va-avatar-file, .va-avatar-upload input[type="file"]', function() {
            const file = this.files[0];
            if (!file) {
                console.log('VA Debug: No file selected');
                return;
            }
            
            console.log('VA Debug: File selected', file.name, file.size, file.type);
            
            // Validate file type
            if (!file.type.match('image.*')) {
                console.log('VA Debug: Invalid file type', file.type);
                alert(virtualAuthors.strings.invalidImageType || 'Please select an image file (JPEG, PNG, or GIF).');
                return;
            }
            
            // Get user ID from data attribute or closest container
            let userId = $(this).data('user-id');
            if (!userId) {
                userId = $(this).closest('[data-user-id]').data('user-id');
            }
            
            console.log('VA Debug: User ID for file upload:', userId);
            
            if (!userId) {
                console.log('VA Debug: No user ID found, aborting upload');
                return;
            }
            
            // Upload the avatar immediately
            uploadAndUpdateAvatar(file, userId);
        });
        
        // Handle direct upload on profile page
        $('#va-avatar-file').on('change', function() {
            const file = this.files[0];
            if (!file) return;
            
            const userId = $('#va-profile-avatar-upload').data('user-id');
            if (!userId) {
                console.log('VA Debug: No user ID in profile upload, checking in URL');
                // Try to get user ID from URL
                const urlParams = new URLSearchParams(window.location.search);
                const userIdFromUrl = urlParams.get('user_id');
                if (userIdFromUrl) {
                    console.log('VA Debug: Found user ID in URL:', userIdFromUrl);
                    uploadAndUpdateAvatar(file, userIdFromUrl);
                } else {
                    console.log('VA Debug: No user ID found for profile');
                }
                return;
            }
            
            console.log('VA Debug: Profile page upload for user ID:', userId);
            uploadAndUpdateAvatar(file, userId);
        });
        
        // Paste functionality for avatars
        $(document).on('paste', function(e) {
            const activeElement = document.activeElement;
            // Only process paste if we're in a text area or input field
            if (activeElement && (activeElement.tagName === 'TEXTAREA' || activeElement.tagName === 'INPUT')) {
                const clipboardData = e.originalEvent.clipboardData || window.clipboardData;
                if (!clipboardData) return;
                
                // Check for images in clipboard data
                const items = clipboardData.items;
                if (items) {
                    for (let i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            console.log('VA Debug: Image found in clipboard');
                            // Create a File object from the pasted image
                            const blob = items[i].getAsFile();
                            const fileName = 'pasted-image.' + items[i].type.split('/')[1];
                            const file = new File([blob], fileName, {type: items[i].type});
                            
                            // Find the closest avatar element to determine the user
                            const containerElement = $(activeElement).closest('[data-user-id]');
                            if (containerElement.length) {
                                const userId = containerElement.data('user-id');
                                
                                if (userId) {
                                    console.log('VA Debug: Pasting image for user ID:', userId);
                                    // Upload and update the avatar
                                    uploadAndUpdateAvatar(file, userId);
                                    e.preventDefault();
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        });
        
        // Add drag and drop functionality to avatars
        $(document).on('dragover dragenter', '.va-avatar-interactive, .va-author-avatar, .va-avatar-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('va-avatar-drag-hover');
        });
        
        $(document).on('dragleave dragend drop', '.va-avatar-interactive, .va-author-avatar, .va-avatar-preview', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('va-avatar-drag-hover');
            
            if (e.type === 'drop') {
                console.log('VA Debug: File dropped on avatar');
                const dt = e.originalEvent.dataTransfer;
                if (dt && dt.files && dt.files.length) {
                    const file = dt.files[0];
                    console.log('VA Debug: Dropped file', file.name, file.size, file.type);
                    
                    // Validate it's an image
                    if (!file.type.match('image.*')) {
                        console.log('VA Debug: Invalid dropped file type', file.type);
                        alert(virtualAuthors.strings.invalidImageType || 'Please select an image file (JPEG, PNG, or GIF).');
                        return;
                    }
                    
                    // Get user ID from data attribute or closest container
                    let userId = $(this).data('user-id');
                    if (!userId) {
                        userId = $(this).closest('[data-user-id]').data('user-id');
                    }
                    
                    console.log('VA Debug: User ID for dropped file:', userId);
                    
                    if (userId) {
                        // Upload and update the avatar
                        uploadAndUpdateAvatar(file, userId);
                    }
                }
            }
        });
        
        // Global body handlers for drag and drop
        $('body').on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    }
    
    /**
     * Upload and immediately update avatar
     * 
     * @param {File} file File to upload
     * @param {number} userId User ID
     */
    function uploadAndUpdateAvatar(file, userId) {
        console.log('VA Debug: Starting avatar upload for user ID', userId);
        console.log('VA Debug: File details', {
            name: file.name,
            size: file.size,
            type: file.type,
            lastModified: file.lastModified
        });
        
        // Show loading indicator on all avatars for this user
        const avatars = $('.avatar[data-user-id="' + userId + '"], [data-user-id="' + userId + '"] .avatar, [data-user-id="' + userId + '"] img.avatar');
        
        console.log('VA Debug: Found', avatars.length, 'avatar elements to update');
        
        // Add loading indicators
        avatars.each(function() {
            const avatar = $(this);
            avatar.css('opacity', '0.5');
            
            // Create and append a spinner if not exists
            if (!avatar.siblings('.va-avatar-spinner').length) {
                const spinner = $('<div class="va-avatar-spinner"><div class="spinner"></div></div>');
                avatar.parent().append(spinner);
            }
        });
        
        // Create form data for the upload
        const formData = new FormData();
        formData.append('action', 'va_upload_avatar');
        formData.append('nonce', virtualAuthors.nonce);
        formData.append('user_id', userId);
        formData.append('avatar_file', file);
        
        // Debug log form data
        console.log('VA Debug: Form data:');
        console.log('- action:', 'va_upload_avatar');
        console.log('- nonce:', virtualAuthors.nonce);
        console.log('- user_id:', userId);
        console.log('- file:', file.name, file.size, file.type);
        
        // Verify formData has correct values
        if (formData.has('avatar_file')) {
            console.log('VA Debug: avatar_file in FormData', formData.get('avatar_file').name);
        } else {
            console.error('VA Debug: avatar_file missing from FormData!');
        }
        
        console.log('VA Debug: AJAX URL:', ajaxurl);
        
        // Upload the avatar
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('VA Debug: AJAX success. Response:', response);
                
                // Remove loading indicators
                avatars.css('opacity', '1');
                $('.va-avatar-spinner').remove();
                
                if (response.success) {
                    // Create a unique timestamp for cache busting
                    const timestamp = new Date().getTime();
                    const newUrl = response.data.url + '?t=' + timestamp;
                    
                    console.log('VA Debug: Avatar uploaded successfully. New URL:', newUrl);
                    
                    // Update all avatars for this user with the new URL
                    updateAllUserAvatars(userId, newUrl);
                    
                    // If we have WordPress panel avatar, update that too
                    $('#your-profile .user-profile-picture img').attr('src', newUrl);
                } else {
                    console.error('VA Debug: Upload failed:', response.data.message);
                    alert(response.data.message || 'Error uploading avatar');
                }
            },
            error: function(xhr, status, error) {
                console.error('VA Debug: AJAX Error:', status, error);
                console.error('VA Debug: Response text:', xhr.responseText);
                console.error('VA Debug: Status code:', xhr.status);
                
                // Remove loading indicators
                avatars.css('opacity', '1');
                $('.va-avatar-spinner').remove();
                
                alert('Server error (' + xhr.status + '). Please try again. ' + 
                      (xhr.responseText ? 'Details: ' + xhr.responseText : ''));
            }
        });
    }
    
    /**
     * Update all avatars for a user
     * 
     * @param {number} userId User ID
     * @param {string} newUrl New avatar URL
     */
    function updateAllUserAvatars(userId, newUrl) {
        console.log('VA Debug: Updating all avatars for user ID', userId, 'to', newUrl);
        
        // Get all avatar images for this user
        const avatars = $('.avatar[data-user-id="' + userId + '"], [data-user-id="' + userId + '"] .avatar, [data-user-id="' + userId + '"] img.avatar');
        
        console.log('VA Debug: Found', avatars.length, 'avatars to update');
        
        avatars.each(function() {
            const avatar = $(this);
            
            // Create a new image element with the same attributes to force a refresh
            const width = avatar.width() || avatar.attr('width') || '';
            const height = avatar.height() || avatar.attr('height') || '';
            const alt = avatar.attr('alt') || '';
            const classes = avatar.attr('class') || '';
            
            // Create the new image with the same attributes but new source
            const newImage = $('<img>').attr({
                'src': newUrl,
                'width': width,
                'height': height,
                'alt': alt,
                'class': classes,
                'data-user-id': userId
            });
            
            console.log('VA Debug: Replacing avatar with new image', newUrl);
            
            // Replace the old image with the new one
            avatar.replaceWith(newImage);
        });
        
        // Find and update any other avatar containers
        $('.va-avatar-preview img, .va-author-avatar img').each(function() {
            const container = $(this).closest('[data-user-id="' + userId + '"]');
            if (container.length || $(this).data('user-id') == userId) {
                console.log('VA Debug: Updating container avatar image', newUrl);
                $(this).attr('src', newUrl);
            }
        });
    }
    
    /**
     * Initialize inline editing functionality
     */
    function initInlineEditing() {
        console.log('VA Debug: Initializing inline editing');
        
        // Track changes in inline editor fields
        $('#va-inline-slug, #va-inline-bio').on('input', function() {
            $('#va-save-author-changes').prop('disabled', false);
        });
        
        // Save inline editor changes
        $('#va-save-author-changes').on('click', function() {
            const userId = $(this).data('user-id');
            if (!userId) {
                console.error('VA Debug: Invalid user ID for save changes');
                alert('Invalid user ID');
                return;
            }
            
            console.log('VA Debug: Saving author changes for user ID', userId);
            
            const slug = $('#va-inline-slug').val().trim();
            const bio = $('#va-inline-bio').val().trim();
            
            const button = $(this);
            const originalText = button.text();
            button.prop('disabled', true).text(virtualAuthors.strings.updating || 'Updating...');
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'va_update_author_details');
            formData.append('nonce', virtualAuthors.nonce);
            formData.append('user_id', userId);
            formData.append('bio', bio);
            formData.append('slug', slug);
            
            // Add avatar file if selected
            const avatarFile = $('#va-inline-avatar-upload')[0]?.files[0];
            if (avatarFile) {
                formData.append('avatar_file', avatarFile);
            }
            
            // Update author details
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('VA Debug: Author update response', response);
                    
                    if (response.success) {
                        // Update the UI with new author details
                        updateAuthorDetails(response.data);
                        
                        // Reset button
                        button.prop('disabled', true).text(originalText);
                        
                        // Show success message
                        alert('Author details updated successfully');
                    } else {
                        console.error('VA Debug: Author update failed', response.data.message);
                        alert(response.data.message || 'Error updating author details');
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VA Debug: Author update AJAX error', status, error);
                    console.error('VA Debug: Response text:', xhr.responseText);
                    alert('Server error. Please try again.');
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
    }
    
    /**
     * Update author details in UI
     * 
     * @param {Object} author Author data
     */
    function updateAuthorDetails(author) {
        console.log('VA Debug: Updating author details in UI', author);
        
        // Update name
        $('.va-author-name').text(author.name);
        
        // Update inline edit fields
        $('#va-inline-slug').val(author.slug);
        $('#va-inline-bio').val(author.bio);
        
        // Update save button data attribute
        $('#va-save-author-changes').data('user-id', author.id).prop('disabled', true);
        
        // Remove existing virtual badge
        $('.va-virtual-badge').remove();
        
        // Add virtual badge if needed
        if (author.isVirtual) {
            $('.va-author-name').after('<div class="va-virtual-badge"><span class="dashicons dashicons-businessman"></span> ' + 
                (virtualAuthors.strings.virtualAuthor || 'Virtual Author') + '</div>');
        }
        
        // Reset any inline avatar upload
        if ($('#va-inline-avatar-upload').length) {
            $('#va-inline-avatar-upload').val('');
        }
    }
    
    /**
     * Initialize author management in the post editor
     */
    function initAuthorManagement() {
        // Only run on post editor
        if (!$('#va-author-panel').length) {
            console.log('VA Debug: Not in post editor, skipping author management');
            return;
        }
        
        console.log('VA Debug: Initializing author management in post editor');
        
        // Show create author form
        $('#va-create-author-btn').on('click', function() {
            console.log('VA Debug: Showing create author form');
            $('#va-author-details').hide();
            $('#va-create-author-form').show();
        });
        
        // Hide create author form
        $('#va-cancel-create-author').on('click', function() {
            console.log('VA Debug: Hiding create author form');
            $('#va-create-author-form').hide();
            $('#va-author-details').show();
        });
        
        // Create new author
        $('#va-save-new-author').on('click', function() {
            const nameField = $('#va-new-author-name');
            const name = nameField.val().trim();
            
            if (!name) {
                console.log('VA Debug: Missing author name');
                alert(virtualAuthors.strings.nameRequired || 'Please enter an author name.');
                nameField.focus();
                return;
            }
            
            console.log('VA Debug: Creating new author:', name);
            
            const button = $(this);
            const originalText = button.text();
            button.prop('disabled', true).text(virtualAuthors.strings.creating || 'Creating...');
            
            // Get form data
            const formData = new FormData();
            formData.append('action', 'va_create_author');
            formData.append('nonce', virtualAuthors.nonce);
            formData.append('name', name);
            formData.append('slug', $('#va-new-author-slug').val().trim());
            formData.append('bio', $('#va-new-author-bio').val().trim());
            formData.append('post_id', $('#post_ID').val());
            
            // Add the avatar file if available
            const avatarFile = $('#va-new-author-avatar')[0]?.files[0];
            if (avatarFile) {
                console.log('VA Debug: Including avatar file in new author', avatarFile.name);
                formData.append('avatar_file', avatarFile);
            }
            
            // Create the author
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('VA Debug: Create author response', response);
                    
                    if (response.success) {
                        const newAuthor = response.data;
                        
                        // Update the WordPress core author dropdown 
                        $('select[name="post_author_override"]').val(newAuthor.user_id);
                        
                        // Trigger the change event on the WordPress core author dropdown
                        $('select[name="post_author_override"]').trigger('change');
                        
                        // Update the post author in WordPress core
                        updateWordPressAuthor(newAuthor.user_id);
                        
                        // Update the author details in the UI
                        updateAuthorDetails(newAuthor);
                        
                        // Update Gutenberg sidebar if active
                        updateGutenbergSidebar(newAuthor);
                        
                        // If avatar was uploaded, update all avatars
                        if (avatarFile && newAuthor.avatar) {
                            updateAllUserAvatars(newAuthor.user_id, newAuthor.avatar);
                        }
                        
                        // Hide form and show details
                        $('#va-create-author-form').hide();
                        $('#va-author-details').show();
                        
                        // Reset form for next use
                        $('#va-new-author-name').val('');
                        $('#va-new-author-slug').val('');
                        $('#va-new-author-bio').val('');
                        $('#va-new-author-avatar').val('');
                        $('#va-avatar-upload .va-avatar-preview').empty();
                    } else {
                        console.error('VA Debug: Create author failed', response.data.message);
                        alert(response.data.message || 'Error creating author');
                    }
                    button.prop('disabled', false).text(originalText);
                },
                error: function(xhr, status, error) {
                    console.error('VA Debug: Create author AJAX error', status, error);
                    console.error('VA Debug: Response text:', xhr.responseText);
                    alert('Server error. Please try again.');
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Generate slug from name
        $('#va-new-author-name').on('blur', function() {
            const slugField = $('#va-new-author-slug');
            if (slugField.val().trim() === '') {
                const name = $(this).val().trim();
                if (name) {
                    // Generate slug
                    const slug = name
                        .toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-')
                        .replace(/-+/g, '-');
                    
                    console.log('VA Debug: Generated slug from name', name, '->', slug);
                    slugField.val(slug);
                }
            }
        });
        
        // Listen for changes on the WordPress core author dropdown
        $('select[name="post_author_override"]').on('change', function() {
            const authorId = $(this).val();
            if (!authorId) return;
            
            console.log('VA Debug: Author dropdown changed to', authorId);
            
            // Get author data and update the interface
            fetchAuthorData(authorId);
        });
        
        // Initial author data load for the first render
        const initialAuthorId = $('select[name="post_author_override"]').val();
        if (initialAuthorId) {
            console.log('VA Debug: Initial author ID', initialAuthorId);
            // Get current author data and update UI
            fetchAuthorData(initialAuthorId);
        }
    }
    
    /**
     * Fetch author data and update UI
     * 
     * @param {number} authorId Author ID
     */
    function fetchAuthorData(authorId) {
        if (!authorId) return;
        
        console.log('VA Debug: Fetching author data for ID', authorId);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'va_get_author_data',
                nonce: virtualAuthors.nonce,
                user_id: authorId
            },
            success: function(response) {
                console.log('VA Debug: Fetch author data response', response);
                
                if (response.success) {
                    updateAuthorDetails(response.data);
                } else {
                    console.error('VA Debug: Fetch author data failed', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('VA Debug: Fetch author AJAX error', status, error);
                console.error('VA Debug: Response text:', xhr.responseText);
            }
        });
    }
    
    /**
     * Update WordPress author in the editor and save via AJAX
     */
    function updateWordPressAuthor(authorId) {
        console.log('VA Debug: Updating WordPress author to', authorId);
        
        // Update the core WP author dropdown
        $('select[name="post_author_override"]').val(authorId);
        
        // For Block Editor
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch && wp.data.select) {
            try {
                // Update post author in editor
                if (wp.data.dispatch('core/editor')) {
                    wp.data.dispatch('core/editor').editPost({ author: authorId });
                }
            } catch (e) {
                console.error('VA Debug: Error updating WordPress author:', e);
            }
        }
        
        // Save author to post via AJAX for immediate update
        const postId = $('#post_ID').val();
        if (postId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'va_update_post_author',
                    nonce: virtualAuthors.nonce,
                    post_id: postId,
                    author_id: authorId
                },
                success: function(response) {
                    console.log('VA Debug: Update post author response', response);
                    
                    // After successfully updating the post author, update the sidebar
                    if (response.success) {
                        updateGutenbergSidebar(response.data);
                    } else {
                        console.error('VA Debug: Update post author failed', response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VA Debug: Update post author AJAX error', status, error);
                    console.error('VA Debug: Response text:', xhr.responseText);
                }
            });
        }
    }
    
    /**
     * Update Gutenberg sidebar
     */
    function updateGutenbergSidebar(author) {
        if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
            return;
        }
        
        console.log('VA Debug: Updating Gutenberg sidebar with author', author);
        
        try {
            // Force refresh the sidebar
            if (wp.data.dispatch('core/edit-post')) {
                // First close and then open to force refresh
                wp.data.dispatch('core/edit-post').closeGeneralSidebar();
                setTimeout(() => {
                    wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
                }, 100);
            }
        } catch (e) {
            console.error('VA Debug: Error updating Gutenberg sidebar:', e);
        }
    }
    
    /**
     * Format text with paragraphs like WordPress wpautop
     * 
     * @param {string} text Text to format
     * @return {string} Formatted text
     */
    function wpautop(text) {
        if (!text) return '';
        
        // Normalize line endings
        text = text.replace(/\r\n|\r/g, '\n');
        
        // Remove more than two contiguous line breaks
        text = text.replace(/\n\n+/g, '\n\n');
        
        // Split up the contents into paragraphs
        const paragraphs = text.split(/\n\n/);
        
        // Format paragraphs
        for (let i = 0; i < paragraphs.length; i++) {
            const paragraph = paragraphs[i].trim();
            
            // Skip if empty
            if (!paragraph) continue;
            
            // Format paragraph
            if (paragraph.indexOf('<p>') !== 0) {
                paragraphs[i] = '<p>' + paragraph + '</p>';
            }
        }
        
        // Join it all back together
        return paragraphs.join('\n');
    }
    
})(jQuery);