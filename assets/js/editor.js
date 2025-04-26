/**
 * Virtual Authors - Editor JavaScript
 *
 * Handles integration with the WordPress block editor (Gutenberg).
 *
 * @package Virtual_Authors
 * @author Tim Hosking (https://github.com/Munger)
 */

(function($) {
    'use strict';
    
    // WordPress components
    const { registerPlugin, PluginDocumentSettingPanel } = wp.plugins || {};
    const { __ } = wp.i18n || { __: function(text) { return text; } };
    const { useSelect, useDispatch, dispatch, select } = wp.data || {};
    const { createElement, Fragment, useState, useEffect } = wp.element || {};
    const { Button, Spinner, TextControl, TextareaControl } = wp.components || {};
    
    // Track editor state
    let authorUpdateInProgress = false;
    let lastAuthorId = null;
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize if we're in the block editor
        if (typeof wp !== 'undefined' && wp.plugins && wp.plugins.registerPlugin) {
            initializeBlockEditorIntegration();
        }
        
        // Handle author selection in classic editor
        initClassicEditorIntegration();

        // Add additional monitoring for author changes in the standard WordPress interface
        monitorStandardAuthorChanges();
    });

    /**
     * Monitor standard WordPress author changes
     * This ensures we catch all author changes, including those from the standard WP interface
     */
    function monitorStandardAuthorChanges() {
        // Direct event listener on post_author_override (standard WP dropdown)
        $(document).on('change', 'select[name="post_author_override"]', function() {
            const authorId = $(this).val();
            if (authorId && authorId !== lastAuthorId) {
                lastAuthorId = authorId;
                fetchAndUpdateAuthorPanel(authorId);
            }
        });

        // Monitor changes to the standard WP author button/popup in the sidebar
        // Check for changes every second to ensure we don't miss updates
        setInterval(function() {
            if (authorUpdateInProgress) return;

            // Get the current author from the standard WP dropdown
            const currentAuthor = $('select[name="post_author_override"]').val();
            
            // If changed and not null, update our panel
            if (currentAuthor && currentAuthor !== lastAuthorId) {
                lastAuthorId = currentAuthor;
                fetchAndUpdateAuthorPanel(currentAuthor);
            }
        }, 1000);

        // For Gutenberg, also add mutation observer to detect changes to the author field
        if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
            // Find elements that might contain author info
            const observeAuthorChanges = function() {
                // Target Gutenberg author components
                const authorElements = document.querySelectorAll('.editor-post-author__select');
                
                if (authorElements.length > 0) {
                    // Set up mutation observer for each author element
                    authorElements.forEach(function(element) {
                        const observer = new MutationObserver(function(mutations) {
                            if (authorUpdateInProgress) return;
                            
                            // When element changes, check if author changed
                            try {
                                const currentAuthor = wp.data.select('core/editor').getEditedPostAttribute('author');
                                if (currentAuthor && currentAuthor !== lastAuthorId) {
                                    lastAuthorId = currentAuthor;
                                    fetchAndUpdateAuthorPanel(currentAuthor);
                                }
                            } catch (e) {
                                console.error('Error detecting author change:', e);
                            }
                        });
                        
                        // Observe changes to the element and its children
                        observer.observe(element, { 
                            attributes: true, 
                            childList: true, 
                            subtree: true 
                        });
                    });
                } else {
                    // If elements not found yet, try again later
                    setTimeout(observeAuthorChanges, 1000);
                }
            };
            
            // Start the observation process
            observeAuthorChanges();
        }
    }
    
    /**
     * Initialize classic editor integration
     * Enhanced to better watch for author changes in the standard WordPress author dropdown
     */
    function initClassicEditorIntegration() {
        // Add mutation observer to watch for changes in the author meta box
        const targetNode = document.getElementById('authordiv');
        if (targetNode) {
            const config = { attributes: true, childList: true, subtree: true };
            const callback = function(mutationsList, observer) {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList' || 
                        (mutation.type === 'attributes' && mutation.attributeName === 'value')) {
                        
                        // Don't process if we initiated the change
                        if (authorUpdateInProgress) {
                            continue;
                        }
                        
                        // Find the selected author from WordPress's native dropdown
                        const authorSelect = $('#post_author_override');
                        if (authorSelect.length) {
                            const authorId = authorSelect.val();
                            if (authorId && authorId !== lastAuthorId) {
                                lastAuthorId = authorId;
                                // Update VA panel directly with the new author
                                fetchAndUpdateAuthorPanel(authorId);
                            }
                        }
                    }
                }
            };
            
            // Create and start the observer
            const observer = new MutationObserver(callback);
            observer.observe(targetNode, config);
        }
        
        // Direct event listener on the classic editor author dropdown
        $('#post_author_override').on('change', function() {
            if (authorUpdateInProgress) return;
            
            const authorId = $(this).val();
            if (authorId && authorId !== lastAuthorId) {
                authorUpdateInProgress = true;
                lastAuthorId = authorId;
                
                // Update our VA author panel with the author data
                fetchAndUpdateAuthorPanel(authorId);
                
                // Update the post via AJAX
                updatePostAuthorViaAjax(authorId, $('#post_ID').val());
                
                // Update Gutenberg if it's active
                updateGutenbergAuthor(authorId);
                
                setTimeout(() => {
                    authorUpdateInProgress = false;
                }, 500);
            }
        });
    }
    
    /**
     * Fetch author data and update the panel
     * New helper function to streamline author updates
     */
    function fetchAndUpdateAuthorPanel(authorId) {
        if (!authorId) return;
        
        // Set update in progress flag
        authorUpdateInProgress = true;
        
        // Get author data and update UI
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
                    // Update author details in our panel
                    updateAuthorPanelUI(response.data);
                }
                
                // Reset update flag after a delay
                setTimeout(() => {
                    authorUpdateInProgress = false;
                }, 500);
            },
            error: function() {
                // Reset update flag on error
                setTimeout(() => {
                    authorUpdateInProgress = false;
                }, 500);
            }
        });
    }
    
    /**
     * Update post author via AJAX
     */
    function updatePostAuthorViaAjax(authorId, postId) {
        if (!postId) return;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'va_update_post_author',
                nonce: virtualAuthors.nonce,
                author_id: authorId,
                post_id: postId
            },
            success: function(response) {
                if (!response.success) {
                    console.error('Error updating post author:', response.data.message);
                }
            }
        });
    }
    
    /**
     * Update Gutenberg editor author if active
     */
    function updateGutenbergAuthor(authorId) {
        if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
            return;
        }
        
        try {
            if (wp.data.dispatch('core/editor')) {
                // Prevent the subscription from triggering again
                authorUpdateInProgress = true;
                
                wp.data.dispatch('core/editor').editPost({ author: authorId });
                
                // Don't force refresh the sidebar on every change to avoid flickering
                setTimeout(() => {
                    authorUpdateInProgress = false;
                }, 1000);
            }
        } catch (e) {
            console.error('Error updating Gutenberg author:', e);
            authorUpdateInProgress = false;
        }
    }
    
    /**
     * Update author panel UI elements
     * Enhanced to fully refresh all author-related elements
     */
    function updateAuthorPanelUI(author) {
        if (!author) return;
        
        // Update author name
        $('.va-author-name').text(author.name);
        
        // Update avatar
        $('.va-author-avatar img').attr('src', author.avatar + '?t=' + new Date().getTime());
        
        // Update inline edit fields
        $('#va-inline-slug').val(author.slug);
        $('#va-inline-bio').val(author.bio);
        
        // Update save button data attribute
        $('#va-save-author-changes').data('user-id', author.id).prop('disabled', true);
        
        // Update virtual badge - remove existing first
        $('.va-virtual-badge').remove();
        
        // Add virtual badge if needed
        if (author.isVirtual) {
            $('.va-author-name').after('<div class="va-virtual-badge"><span class="dashicons dashicons-businessman"></span> ' + 
                (virtualAuthors.strings.virtualAuthor || 'Virtual Author') + '</div>');
        }
        
        // Reset the inline avatar upload
        $('#va-inline-avatar-upload').val('');
    }
    
    /**
     * Format bio content with paragraphs
     */
    function formatBioContent(content) {
        if (!content) return '';
        
        // Simple wp_autop-like functionality
        content = content.replace(/\r\n|\r/g, '\n');
        content = content.replace(/\n\n+/g, '\n\n');
        
        const paragraphs = content.split('\n\n');
        
        for (let i = 0; i < paragraphs.length; i++) {
            const para = paragraphs[i].trim();
            if (para && para.indexOf('<p>') !== 0) {
                paragraphs[i] = '<p>' + para + '</p>';
            }
        }
        
        return paragraphs.join('\n');
    }
    
    /**
     * Initialize block editor integration
     * Enhanced to better sync with author changes
     */
    function initializeBlockEditorIntegration() {
        // Don't register if we're not in the post editor
        if (!$('.editor-styles-wrapper').length) {
            return;
        }
        
        // Register plugin for sidebar panel
        if (registerPlugin) {
            registerPlugin('virtual-authors-sidebar', {
                render: AuthorSidebarPanel,
                icon: 'admin-users'
            });
        }
        
        // Enhance Post Author component
        enhancePostAuthorComponent();
        
        // Listen for author changes in the editor
        listenForAuthorChanges();
    }
    
    /**
     * Author Sidebar Panel component
     */
    function AuthorSidebarPanel() {
        // Use WordPress hooks
        const author = useSelect(select => {
            const postAuthor = select('core/editor').getEditedPostAttribute('author');
            const authorsData = select('core').getAuthors();
            
            if (!postAuthor || !authorsData || !authorsData.length) {
                return null;
            }
            
            return authorsData.find(a => a.id === postAuthor);
        }, []);
        
        // Loading state
        const [isLoading, setIsLoading] = useState(false);
        const [authorData, setAuthorData] = useState(null);
        
        // Load extended author data
        useEffect(() => {
            if (author && author.id) {
                setIsLoading(true);
                
                // Get our extended author data via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'va_get_author_data',
                        nonce: virtualAuthors.nonce,
                        user_id: author.id
                    },
                    success: function(response) {
                        if (response.success) {
                            setAuthorData(response.data);
                            
                            // Also update the main author panel when author changes in sidebar
                            updateAuthorPanelUI(response.data);
                        }
                        setIsLoading(false);
                    },
                    error: function() {
                        setIsLoading(false);
                    }
                });
            }
        }, [author?.id]);
        
        // If no author or loading, show placeholder
        if (!author || isLoading) {
            return createElement(
                PluginDocumentSettingPanel,
                {
                    name: 'virtual-authors-panel',
                    title: __('Author', 'virtual-authors')
                },
                createElement(Spinner)
            );
        }
        
        // Get author details
        const name = author.name;
        const avatar = authorData?.avatar || author.avatar_urls?.[96];
        const bio = authorData?.bio || '';
        const isVirtual = authorData?.isVirtual || false;
        
        // Create elements for sidebar
        return createElement(
            PluginDocumentSettingPanel,
            {
                name: 'virtual-authors-panel',
                title: __('Author', 'virtual-authors')
            },
            createElement('div', { className: 'va-sidebar-author-info' },
                createElement('div', { className: 'va-sidebar-author-avatar' },
                    createElement('img', { src: avatar + '?t=' + new Date().getTime(), alt: name })
                ),
                createElement('div', { className: 'va-sidebar-author-name' }, name),
                isVirtual && createElement('div', { className: 'va-virtual-badge' }, 
                    createElement('span', { className: 'dashicons dashicons-businessman' }),
                    __('Virtual Author', 'virtual-authors')
                ),
                bio && createElement('div', { 
                    className: 'va-sidebar-author-bio',
                    dangerouslySetInnerHTML: { __html: formatBioContent(bio) }
                }),
                createElement(Button, {
                    isPrimary: true,
                    className: 'va-sidebar-edit-btn',
                    onClick: () => {
                        // Scroll to and highlight our author panel
                        const panel = $('#virtual-authors-meta-box');
                        if (panel.length) {
                            $('html, body').animate({
                                scrollTop: panel.offset().top - 50
                            }, 500);
                            
                            panel.css('background-color', '#f0f7fb');
                            setTimeout(() => {
                                panel.css('background-color', '');
                            }, 1500);
                        }
                    }
                }, __('Edit Author Details', 'virtual-authors'))
            )
        );
    }
    
    /**
     * Enhance the core Post Author component
     */
    function enhancePostAuthorComponent() {
        if (!wp.hooks || !wp.hooks.addFilter) {
            return;
        }
        
        try {
            // Add "Create Author" button to the post author component
            wp.hooks.addFilter(
                'editor.PostAuthor',
                'virtual-authors/enhance-author-component',
                function(OriginalComponent) {
                    return function(props) {
                        // Render the original component
                        const original = createElement(OriginalComponent, props);
                        
                        // Only modify if user can create users
                        if (virtualAuthors.canCreateUsers !== 'true') {
                            return original;
                        }
                        
                        // Return modified component with our button
                        return createElement(
                            Fragment,
                            {},
                            original,
                            createElement(
                                Button,
                                {
                                    isPrimary: true,
                                    className: 'va-sidebar-create-author',
                                    onClick: () => {
                                        const panel = $('#virtual-authors-meta-box');
                                        const createBtn = $('#va-create-author-btn');
                                        
                                        if (panel.length && createBtn.length) {
                                            // Scroll to panel
                                            $('html, body').animate({
                                                scrollTop: panel.offset().top - 50
                                            }, 500);
                                            
                                            // Trigger create author click
                                            createBtn.trigger('click');
                                        }
                                    }
                                },
                                __('Create New Author', 'virtual-authors')
                            )
                        );
                    };
                }
            );
        } catch (e) {
            console.error('Error enhancing author component:', e);
        }
    }
    
    /**
     * Listen for author changes in the editor
     * Enhanced to immediately update the author panel
     */
    function listenForAuthorChanges() {
        if (!wp.data || !wp.data.subscribe) {
            return;
        }
        
        let previousAuthor = null;
        
        // Subscribe to store changes
        wp.data.subscribe(() => {
            // Check if we're in the middle of an update
            if (authorUpdateInProgress) {
                return;
            }
            
            try {
                const currentAuthor = wp.data.select('core/editor').getEditedPostAttribute('author');
                
                // If author changed and it's a real change (not null to null)
                if (currentAuthor && previousAuthor !== null && currentAuthor !== previousAuthor) {
                    authorUpdateInProgress = true;
                    lastAuthorId = currentAuthor;
                    
                    // Sync with our author panel by fetching full author data
                    fetchAndUpdateAuthorPanel(currentAuthor);
                    
                    // Update core dropdown
                    $('select[name="post_author_override"]').val(currentAuthor);
                    
                    setTimeout(() => {
                        authorUpdateInProgress = false;
                    }, 1000);
                }
                
                previousAuthor = currentAuthor;
            } catch (e) {
                console.error('Error in author change listener:', e);
                authorUpdateInProgress = false;
            }
        });
    }
})(jQuery);