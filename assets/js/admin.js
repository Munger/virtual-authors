/**
 * Virtual Authors - Admin JavaScript
 *
 * Simplified approach with direct avatar updates.
 *
 * @package Virtual_Authors
 * @author Tim Hosking (https://github.com/Munger)
 */

(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        initAvatarHandling();
        initAuthorManagement();
        initInlineEditing();
    });
    
    /**
     * Initialize avatar handling with a simplified approach
     */
    function initAvatarHandling() {
        // Make avatars clickable to trigger file upload
        $(document).on('click', '.va-avatar-interactive', function(e) {
            e.preventDefault();
            
            const userId = $(this).data('user-id');
            if (!userId) return;
            
            // Create a hidden file input if not exists
            let fileInput = $('#va-temp-file-input');
            if (!fileInput.length) {
                fileInput = $('<input type="file" id="va-temp-file-input" style="display:none" accept="image/jpeg,image/png,image/gif">');
                $('body').append(fileInput);
            }
            
            // Store the user ID with the file input
            fileInput.data('user-id', userId);
            
            // Trigger the file input
            fileInput.trigger('click');
        });
        
        // Handle file selection
        $(document).on('change', '#va-temp-file-input, #va-avatar-file', function() {
            const file = this.files[0];
            if (!file) return;
            
            // Validate file type
            if (!file.type.match('image.*')) {
                alert(virtualAuthors.strings.invalidImageType || 'Please select an image file (JPEG, PNG, or GIF).');
                return;
            }
            
            // Get user ID from data attribute
            const userId = $(this).data('user-id');
            if (!userId) return;
            
            // Upload the avatar immediately
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
                            // Create a File object from the pasted image
                            const blob = items[i].getAsFile();
                            const fileName = 'pasted-image.' + items[i].type.split('/')[1];
                            const file = new File([blob], fileName, {type: items[i].type});
                            
                            // Find the closest avatar element to determine the user
                            const containerElement = $(activeElement).closest('.va-author-edit-container, .va-create-author-form');
                            if (containerElement.length) {
                                // Get user ID from save button in container
                                const saveButton = containerElement.find('#va-save-author-changes');
                                const userId = saveButton.data('user-id');
                                
                                if (userId) {
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
        
        // Add drag and drop functionality to the entire body
        $('body').on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        $('body').on('drop', function(e) {
            // Prevent the browser from opening the image
            e.preventDefault();
            e.stopPropagation();
            
            const dt = e.originalEvent.dataTransfer;
            if (dt && dt.files && dt.files.length) {
                // Get the file from the drop event
                const file = dt.files[0];
                
                // Validate it's an image
                if (!file.type.match('image.*')) {
                    alert(virtualAuthors.strings.invalidImageType || 'Please select an image file (JPEG, PNG, or GIF).');
                    return;
                }
                
                // Find the nearest avatar element with a user ID
                const avatar = $(e.target).closest('.va-avatar-interactive');
                if (avatar.length) {
                    const userId = avatar.data('user-id');
                    if (userId) {
                        // Upload and update the avatar
                        uploadAndUpdateAvatar(file, userId);
                    }
                }
            }
        });
    }
    
    /**
     * Upload and immediately update avatar
     * 
     * @param {File} file File to upload
     * @param {number} userId User ID
     */
    function uploadAndUpdateAvatar(file, userId) {
        // Show loading indicator on all avatars for this user
        const avatars = $('img.avatar[data-user-id="' + userId + '"]');
        avatars.css('opacity', '0.5');
        
        // Create form data for the upload
        const formData = new FormData();
        formData.append('action', 'va_upload_avatar');
        formData.append('nonce', virtualAuthors.nonce);
        formData.append('user_id', userId);
        formData.append('avatar_file', file);
        
        // Upload the avatar
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Reset opacity
                avatars.css('opacity', '1');
                
                if (response.success) {
                    // Create a unique timestamp for cache busting
                    const timestamp = new Date().getTime();
                    
                    // Update all avatars for this user with the new URL
                    const newUrl = response.data.url;
                    
                    // Force immediate update by removing and recreating the images
                    avatars.each(function() {
                        const avatar = $(this);
                        const width = avatar.width();
                        const height = avatar.height();
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
                        
                        // Replace the old image with the new one
                        avatar.replaceWith(newImage);
                    });
                } else {
                    alert(response.data.message || 'Error uploading avatar');
                }
            },
            error: function() {
                // Reset opacity
                avatars.css('opacity', '1');
                alert('Server error. Please try again.');
            }
        });
    }
    
    /**
     * Initialize inline editing functionality
     */
    function initInlineEditing() {
        // Track changes in inline editor fields
        $('#va-inline-slug, #va-inline-bio').on('input', function() {
            $('#va-save-author-changes').prop('disabled', false);
        });
        
        // Save inline editor changes
        $('#va-save-author-changes').on('click', function() {
            const userId = $(this).data('user-id');
            if (!userId) {
                alert('Invalid user ID');
                return;
            }
            
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
                    if (response.success) {
                        // Update the UI with new author details
                        updateAuthorDetails(response.data);
                        
                        // Reset button
                        button.prop('disabled', true).text(originalText);
                        
                        // Show success message
                        alert('Author details updated successfully');
                    } else {
                        alert(response.data.message || 'Error updating author details');
                        button.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
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
        // Update name
        $('.va-author-name').text(author.name);
        
        // Don't update avatars here - they're handled by the uploadAndUpdateAvatar function
        
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
        if (!$('#va-author-panel').length) return;
        
        // Show create author form
        $('#va-create-author-btn').on('click', function() {
            $('#va-author-details').hide();
            $('#va-create-author-form').show();
        });
        
        // Hide create author form
        $('#va-cancel-create-author').on('click', function() {
            $('#va-create-author-form').hide();
            $('#va-author-details').show();
        });
        
        // Create new author
        $('#va-save-new-author').on('click', function() {
            const nameField = $('#va-new-author-name');
            const name = nameField.val().trim();
            
            if (!name) {
                alert(virtualAuthors.strings.nameRequired || 'Please enter an author name.');
                nameField.focus();
                return;
            }
            
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
                            // Force immediate update by removing and recreating the images
                            $('img.avatar[data-user-id="' + newAuthor.user_id + '"]').each(function() {
                                const avatar = $(this);
                                const width = avatar.width();
                                const height = avatar.height();
                                const alt = avatar.attr('alt') || '';
                                const classes = avatar.attr('class') || '';
                                
                                // Create the new image with the same attributes but new source
                                const newImage = $('<img>').attr({
                                    'src': newAuthor.avatar + '?t=' + new Date().getTime(),
                                    'width': width,
                                    'height': height,
                                    'alt': alt,
                                    'class': classes,
                                    'data-user-id': newAuthor.user_id
                                });
                                
                                // Replace the old image with the new one
                                avatar.replaceWith(newImage);
                            });
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
                        alert(response.data.message || 'Error creating author');
                    }
                    button.prop('disabled', false).text(originalText);
                },
                error: function() {
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
                        .replace(/[^a-z0-9\s-]/g, '')  // Fixed regex: removed extra backslashes
                        .replace(/\s+/g, '-')           // Fixed regex: removed extra backslashes
                        .replace(/-+/g, '-');
                    
                    slugField.val(slug);
                }
            }
        });
        
        // Listen for changes on the WordPress core author dropdown
        $('select[name="post_author_override"]').on('change', function() {
            const authorId = $(this).val();
            if (!authorId) return;
            
            // Get author data and update the interface
            fetchAuthorData(authorId);
        });
        
        // Initial author data load for the first render
        const initialAuthorId = $('select[name="post_author_override"]').val();
        if (initialAuthorId) {
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
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'va_get_author_data',
                nonce: virtualAuthors.nonce,
                user_id: authorId
            },
            success: function(response) {
                if (response.success) {
                    updateAuthorDetails(response.data);
                }
            }
        });
    }
    
    /**
     * Update WordPress author in the editor and save via AJAX
     */
    function updateWordPressAuthor(authorId) {
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
                console.error('Error updating WordPress author:', e);
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
                    // After successfully updating the post author, update the sidebar
                    if (response.success) {
                        updateGutenbergSidebar(response.data);
                    }
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
            console.error('Error updating Gutenberg sidebar:', e);
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