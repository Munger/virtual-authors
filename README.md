# Virtual Authors

A WordPress plugin that enhances post authorship with custom avatars and efficient author management directly from the post editor.

## Description

Virtual Authors provides a seamless way to manage authors in WordPress, allowing you to create and assign authors directly from the post editor. It integrates with WordPress's standard user system while adding powerful features like custom avatars (with drag-and-drop, paste, and direct upload functionality).

### Key Features

- **Enhanced Author Management** - Create and edit authors directly from the post editor
- **Custom Avatars** - Replace Gravatar with locally hosted images that can be uploaded, pasted, or drag-and-dropped
- **Author Bios** - Add formatted author bios
- **Custom Author Slugs** - Set custom slugs for cleaner author archive URLs
- **One-Click Author Creation** - Create new authors directly from the post editor
- **WordPress Integration** - Fully compatible with WordPress's core author functionality
- **User Security** - "Virtual" authors cannot log in to the WordPress admin area

## Installation

1. Upload the `virtual-authors` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Start using the author panel in the post editor

## Usage

### Creating a New Author

1. Open the post editor
2. Look for the "Author Details" meta box
3. Click the "Create New Author" button
4. Fill in the author details (name, bio, and optionally upload an avatar)
5. Click "Create Author" to create and assign the author to the post

### Editing Author Bio

1. In the "Author Details" panel, click "Edit Bio"
2. Update the bio
3. Click "Save Bio" to save your changes

### Managing Avatars

Avatars can be managed in several ways:

- **In the Post Editor** - When creating a new author, you can upload, paste, or drag and drop an avatar
- **In the Users List** - Click on an author's avatar to open the avatar editor
- **In User Profile** - Use the avatar uploader in the user profile

### Custom Slugs

Custom author slugs can be set in the user profile. These slugs are used in URLs and can help create more user-friendly permalinks.

## Technical Details

- Avatars are stored in the `/wp-content/uploads/avatars/` directory
- Avatar URLs are rewritten to clean URLs like `/author-avatar/author-slug`
- Virtual authors have the `va_is_virtual` user meta set to `1`
- Custom slugs are stored in the `va_author_slug` user meta field
- Avatar paths are stored in the `va_avatar_path` user meta field

## License

GPL v2 or later.

## Changelog

### 1.0.0
- Initial release