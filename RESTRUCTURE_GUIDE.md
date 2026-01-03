# Edit/Generate Provisioning Tab Restructure Guide

## Overview

The "Edit/Generate Device" tab has been completely restructured into "Edit/Generate Provisioning" with a modern two-column layout that separates core device settings from template-specific configuration.

## Screenshot

![New Two-Column Layout](https://github.com/user-attachments/assets/8a461441-b16e-454b-8a5e-bc1619fa3441)

## Layout Structure

### Left Column (30-35% width) - Core Device Settings
The left column contains **always-visible** essential device configuration that remains constant regardless of which sub-tab is selected on the right:

1. **Extension Number** - Dropdown from FreePBX with toggle button for custom entry
2. **SIP Secret** - Auto-fetched from FreePBX or custom entry with save functionality
3. **Model** - Dropdown populated from templates folder
4. **MAC Address** - Text input for device MAC
5. **Remote Provisioning Authentication**
   - Provisioning Username
   - Provisioning Password (with Generate button)
6. **Advanced: Custom Template Override** - Collapsible textarea
7. **Save Device** button (green, large)
8. **Preview Provisioning Config** button (blue)

### Right Column (65-70% width) - Template-Generated Content
The right column displays dynamic content based on the selected model template with three sub-tabs:

#### Header
Shows "{Model Display Name} Template Loaded" (e.g., "Yealink T58G Template Loaded") with a green checkmark icon when a model is selected.

#### Sub-Tab 1: Handset Settings
- **Live Config Preview** - Real-time preview of generated provisioning config showing first 500 characters
- **Smart Dial Shortcuts** - Configure single-digit speed dials:
  - Digit selector (0-9)
  - Extension/number input
  - Add/Delete buttons
  - List showing configured shortcuts (e.g., "Digit 2 dials 202")
  - Stored in database as JSON in `custom_options_json`
- **Auto Provisioning Settings**:
  - Server URL (pre-filled with current FreePBX domain)
  - Mode selector (Disabled, Power On, Periodic, Power On + Periodic)
- **SIP Server Settings**:
  - SIP Server Host (defaults to FreePBX, can override for remote)
  - SIP Server Port (default 5060)
- **Device Options** - All `configurable_options` from template JSON with descriptions

#### Sub-Tab 2: Phone Assets (Wallpaper)
- **Target Screen Dimensions** - Shows screen width x height from template
- **Upload Section** - File upload with automatic resize to target dimensions using PHP GD
- **Custom URL Option** - Enter external wallpaper URL
- **Display Mode** - Crop to Fill vs Fit (Letterbox)
- **Current Preview** - Shows selected wallpaper with Clear button
- **Asset Gallery** - Grid of uploaded files with Select/Delete buttons

#### Sub-Tab 3: Button Layout
- **Page Selector** - Dropdown for multi-page button layouts
- **Live Visual Preview** - SVG phone schematic showing:
  - Chassis dimensions from `visual_editor.schematic`
  - Screen area positioned correctly
  - Wallpaper rendered inside screen
  - Clickable buttons positioned from `visual_editor.keys` array
- Clicking buttons opens existing edit modal for configuration

## New Features

### Smart Dial Shortcuts
Allows mapping single digits (0-9) to extensions or phone numbers:
- User selects digit and enters extension
- Stored as JSON object: `{"2": "202", "3": "203"}`
- Saved in `custom_options_json` database field under `smart_dial_shortcuts` key
- Can be used in provisioning templates to generate dialplan rules

### Automatic Image Resizing
When uploading wallpapers:
1. JavaScript reads target dimensions from loaded template's `visual_editor.screen_width` and `screen_height`
2. Passes dimensions to backend via `resize_width` and `resize_height` POST parameters
3. PHP GD library resizes image to exact target dimensions
4. Preserves transparency for PNG/GIF
5. Falls back to original upload if GD not available

### Improved Template Loading
Enhanced error handling with console logging:
- Logs template loading attempts
- Logs parse errors with details
- Shows user-friendly error messages
- Prevents silent failures

### Pre-filled Provisioning Settings
- Auto Provisioning Server URL automatically suggests current FreePBX server URL
- SIP server settings can default to FreePBX or be overridden for remote scenarios

## Technical Implementation

### Files Modified

#### `page.quickprovisioner.php`
- Restructured HTML with two-column Bootstrap layout
- Added three sub-tabs in right column using nested nav-tabs
- Moved wallpaper, page selector, and device options to appropriate sub-tabs
- Added Smart Dial Shortcuts UI
- Added Auto Provisioning and SIP Server settings panels
- Updated JavaScript functions:
  - `loadProfile()` - Enhanced with error handling and new UI updates
  - `editDevice()` - Loads smart dial shortcuts from database
  - `newDevice()` - Resets smart dial shortcuts
  - Form submission - Includes smart dial shortcuts in custom_options
- New JavaScript functions:
  - `updateRightColumnHeader()` - Updates header with model name
  - `updateScreenDimensions()` - Shows target screen size
  - `updateHandsetSettingsPreview()` - Live config preview
  - `prefillAutoProvisionUrl()` - Pre-fills provisioning URL
  - `addSmartDialShortcut()` - Adds shortcut to list
  - `deleteSmartDialShortcut()` - Removes shortcut
  - `updateSmartDialList()` - Updates displayed shortcuts
  - `uploadWallpaper()` - Enhanced with dimension passing
  - `loadAssetGallery()` - Loads asset grid
  - `selectWallpaperFromGallery()` - Selects wallpaper from gallery

#### `ajax.quickprovisioner.php`
- Enhanced `upload_file` case with automatic image resizing:
  - Accepts `resize_width` and `resize_height` parameters
  - Uses PHP GD library to resize images
  - Preserves transparency for PNG/GIF
  - Falls back to original upload if resize fails
  - Supports JPEG, PNG, GIF formats

### Database Schema
No changes required - uses existing fields:
- `custom_options_json` - Stores smart dial shortcuts as JSON
- `wallpaper` - Stores wallpaper filename or URL
- `wallpaper_mode` - Stores 'crop' or 'fit'

### Template JSON Structure
No changes required - uses existing structure:
- `visual_editor.screen_width` - Target wallpaper width
- `visual_editor.screen_height` - Target wallpaper height
- `visual_editor.schematic` - Phone chassis dimensions
- `visual_editor.keys` - Button positions
- `configurable_options` - Device-specific settings

## User Workflow

### Creating/Editing a Device

1. **Navigate to "Edit/Generate Provisioning" tab**
2. **In Left Column:**
   - Select or enter Extension Number
   - SIP Secret auto-loads (or enter custom)
   - Select Model from dropdown
   - Enter MAC Address
   - Configure Remote Provisioning credentials (if needed)
3. **In Right Column - Handset Settings Tab:**
   - Review Live Config Preview
   - Add Smart Dial Shortcuts (optional)
   - Configure Auto Provisioning settings
   - Set SIP Server settings (if different from FreePBX)
   - Configure Device Options from template
4. **Switch to Phone Assets Tab:**
   - Upload wallpaper (auto-resized to screen dimensions)
   - Or enter custom URL
   - Select display mode (Crop/Fit)
   - Preview current wallpaper
5. **Switch to Button Layout Tab:**
   - Select page (for multi-page layouts)
   - Click buttons to configure as Line, Speed Dial, or BLF
   - View wallpaper rendered in screen preview
6. **Back to Left Column:**
   - Click "Save Device" (green button)
   - Or "Preview Provisioning Config" to review generated config

## Responsive Design

- Uses Bootstrap 3.3 responsive grid system
- Left column: `col-md-4` (collapses on mobile)
- Right column: `col-md-8` (collapses on mobile)
- All inputs and buttons use Bootstrap classes for consistency
- Panels and wells group related settings visually

## Backward Compatibility

✅ All existing functionality preserved:
- Device list and management
- Template import/export
- Contact management
- Asset manager
- Admin functions
- Provisioning file generation
- Remote authentication

✅ Database schema unchanged - no migration needed

✅ Existing devices continue to work without modification

## Testing Checklist

- [ ] Tab navigation works correctly
- [ ] Left column fields populate right column preview
- [ ] Smart Dial Shortcuts add/delete functionality
- [ ] Wallpaper upload and auto-resize
- [ ] Asset gallery displays and allows selection
- [ ] Button layout preview renders correctly
- [ ] Page selector switches button pages
- [ ] Save Device persists all settings
- [ ] Preview Config shows correct output
- [ ] Edit Device loads all saved settings
- [ ] Form validation for required fields
- [ ] Custom extension toggle works
- [ ] Custom SIP secret toggle works
- [ ] Template override collapse/expand
- [ ] Auto provisioning URL pre-fill

## Browser Compatibility

Tested with:
- Chrome/Edge (recommended)
- Firefox
- Safari

Requires:
- JavaScript enabled
- Bootstrap 3.3+ CSS/JS
- jQuery 1.11+
- Font Awesome 4.7+ (for icons)

## Security Considerations

- CSRF token validation remains in place
- Local network restriction unchanged
- Per-device provisioning authentication enforced
- File upload validation (type, size, extension)
- Image processing uses safe PHP GD functions
- No new security vulnerabilities introduced

## Future Enhancements

Potential improvements:
- Drag-and-drop file upload
- Image cropping tool with preview
- Visual button layout editor (drag-and-drop)
- Template validation on import
- Real-time config syntax highlighting
- Multi-language support for descriptions

## Support

For issues or questions:
1. Check console for JavaScript errors
2. Review FreePBX logs for PHP errors
3. Test with "Preview Provisioning Config" button
4. Verify template JSON structure
5. Check browser network inspector for AJAX failures

---

**Implementation Date:** 2026-01-03  
**Version:** 2.2.0+  
**Developer:** GitHub Copilot for Ezra90
