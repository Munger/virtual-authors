# Virtual Authors Plugin Structure

```
virtual-authors/
├── assets/
│   ├── css/
│   │   └── admin.css        # Styles for admin & editor interface
│   ├── js/
│   │   ├── admin.js         # Core admin functionality (avatar uploads, etc)
│   │   └── editor.js        # Post editor integration
│   └── images/
│       └── default-avatar.png  # Default avatar image
├── includes/
│   ├── class-avatar-handler.php   # Avatar upload & display functionality
│   ├── class-author-manager.php   # Virtual author creation & management
│   └── class-editor-integration.php  # Post editor integration
├── languages/                     # Translation files (empty directory)
├── virtual-authors.php            # Main plugin file
└── README.md                      # Plugin documentation
```

## Key Changes from Original Version

This simplified structure offers several benefits:

1. **Reduced File Count**: Consolidates functionality into fewer files
   - Merged profile-fields.php and users-list.php into author-manager.php
   - Combined all admin JS into a single file
   - Eliminated complex React components in favor of standard HTML/CSS

2. **Simplified Editor Integration**: Uses WordPress meta boxes instead of complex Gutenberg integration
   - Removed rich text editor in favor of standard textarea
   - Uses standard WordPress styling instead of custom components

3. **Maintained Core Features**:
   - Custom avatar uploads with drag-and-drop, paste functionality
   - Virtual author creation and management
   - Clean avatar URLs with rewrite rules
   - Author profile management

4. **Eliminated Complexity**:
   - Removed React/JSX build process for simpler JS development
   - Simplified CSS structure
   - Reduced WordPress hook dependencies

The simplified plugin maintains all core functionality while being more maintainable and easier to extend.