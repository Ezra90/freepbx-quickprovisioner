# Dynamic Handset Templates Documentation

## Overview

The HH Quick Provisioner now supports dynamic handset templates that allow you to define phone models with their specific configurations, visual layouts, and provisioning templates in a JSON format. This system enables easy addition of new phone models without modifying the core code.

## Template Structure

Each handset template is a JSON file located in the `/templates` directory, named `{MODEL}.json` (e.g., `T58G.json`).

### Required Sections

#### 1. Basic Information
```json
{
    "manufacturer": "Yealink",
    "model": "T58G",
    "display_name": "Yealink T58G",
    "max_line_keys": 27,
    "button_layout": "grid",
    "svg_fallback": true,
    "notes": "Wallpaper: 800x480 JPG/PNG recommended. Premium touchscreen model.",
    "options": {
        "provisioning_server": "http://yourserver/qp"
    }
}
```

#### 2. Configurable Options

Define user-configurable fields that will be rendered in the Edit/Generate Device form:

```json
{
    "configurable_options": [
        {
            "name": "static.security.admin_password",
            "type": "text",
            "default": "admin",
            "label": "Admin Password",
            "description": "Change default admin password for web/phone interface",
            "required": true
        },
        {
            "name": "phone_setting.keyboard_lock",
            "type": "bool",
            "default": 1,
            "label": "Enable Phone Lock",
            "description": "Enable the phone lock feature to prevent unauthorized use",
            "required": false
        },
        {
            "name": "phone_setting.time_zone",
            "type": "select",
            "default": "-5",
            "label": "Time Zone",
            "description": "Set phone time zone offset from UTC",
            "required": false,
            "options": {
                "-5": "UTC-05:00 (EST)",
                "-4": "UTC-04:00",
                "0": "UTC+00:00 (GMT)"
            }
        },
        {
            "name": "phone_setting.keyboard_lock.timeout",
            "type": "number",
            "default": 60,
            "label": "Auto-Lock Timeout (seconds)",
            "description": "Idle time before auto-locking",
            "required": false,
            "min": 0,
            "max": 3600
        }
    ]
}
```

**Field Types:**
- `text`: Simple text input
- `bool`: Boolean (On/Off) dropdown
- `select`: Dropdown with custom options
- `number`: Numeric input with optional min/max

**Field Properties:**
- `name`: Variable name used in template substitution
- `type`: Field input type
- `default`: Default value shown to user
- `label`: User-friendly label displayed in UI
- `description`: Help text shown below the field
- `required`: If true, field must be filled before saving (shows red asterisk)
- `options`: For select type, key-value pairs for dropdown
- `min`, `max`: For number type, validation bounds

#### 3. Visual Editor

Defines the phone's screen dimensions and button layout for the live preview:

```json
{
    "visual_editor": {
        "screen_width": 800,
        "screen_height": 480,
        "remote_image_url": "",
        "svg_fallback": true,
        "schematic": {
            "chassis_width": 900,
            "chassis_height": 650,
            "screen_x": 150,
            "screen_y": 80,
            "screen_width": 600,
            "screen_height": 360
        },
        "keys": [
            {
                "index": 1,
                "x": 50,
                "y": 120,
                "label_align": "center",
                "page": 1,
                "info": "Line 1"
            }
        ]
    }
}
```

**Properties:**
- `screen_width`, `screen_height`: Actual phone display resolution
- `remote_image_url`: Optional URL to phone image (if empty, uses SVG fallback)
- `svg_fallback`: Enable enhanced SVG rendering
- `schematic.chassis_*`: Overall preview container dimensions
- `schematic.screen_*`: Screen position and size within chassis
- `keys`: Array of programmable button definitions with coordinates

#### 4. Provisioning Configuration

Defines how to generate the configuration file:

```json
{
    "provisioning": {
        "content_type": "text/plain",
        "filename_pattern": "{mac}.cfg",
        "type_mapping": {
            "line": "15",
            "speed_dial": "13",
            "blf": "16",
            "voicemail": "18"
        },
        "digitmap_data": [
            {"pattern": "xxx", "timeout": "3"},
            {"pattern": "1xxxxxxxxxx", "timeout": "3"}
        ],
        "template": "#!version:1.0.0.1\n..."
    }
}
```

**Properties:**
- `content_type`: MIME type for the config file
- `filename_pattern`: Pattern for config filename (`{mac}` gets replaced)
- `type_mapping`: Maps abstract key types to vendor-specific codes
- `digitmap_data`: Optional array for dial plan patterns (demonstrates array repeaters)
- `template`: The actual INI-style configuration template with placeholders

## Template Syntax

### Variable Substitution

Use double curly braces for variable placeholders:

```ini
account.1.extension = {{extension}}
account.1.password = {{password}}
account.1.display_name = {{display_name}}
```

**Built-in Variables:**
- `{{mac}}`: Device MAC address (uppercase, no separators)
- `{{extension}}`: User extension number
- `{{password}}`: SIP secret/password
- `{{display_name}}`: User's display name
- `{{server_host}}`: PBX server IP/hostname
- `{{server_port}}`: SIP port (default 5060)
- `{{wallpaper}}`: Authenticated wallpaper URL
- `{{security_pin}}`: Phone lock PIN

**Custom Variables:**
Any configurable option defined in `configurable_options` can be used:
```ini
{{if static.security.admin_password}}
static.security.admin_password = {{static.security.admin_password}}
{{/if}}
```

### Conditional Blocks

Render content only if a variable is set and non-empty:

```ini
{{if wallpaper}}
phone_setting.backgrounds = {{wallpaper}}
{{/if}}

{{if account.1.hotline_number}}
account.1.hotline_number = {{account.1.hotline_number}}
{{/if}}
```

### Loop Constructs

#### Line Keys Loop

Automatically processes programmed line keys:

```ini
{{line_keys_loop}}
linekey.{{index}}.type = {{type}}
linekey.{{index}}.line = 1
linekey.{{index}}.value = {{value}}
linekey.{{index}}.label = {{label}}
{{/line_keys_loop}}
```

**Available Variables:**
- `{{index}}`: Key index number
- `{{type}}`: Mapped key type from `type_mapping`
- `{{value}}`: Key value (phone number, extension, etc.)
- `{{label}}`: Key display label

#### Contacts Loop

Processes directory contacts:

```ini
{{contacts_loop}}
remote_phonebook.data.{{index}}.name = {{name}}
remote_phonebook.data.{{index}}.phone_number = {{number}}
{{if photo_url}}
remote_phonebook.data.{{index}}.photo_url = {{photo_url}}
{{/if}}
{{/contacts_loop}}
```

**Available Variables:**
- `{{index}}`: 1-based contact number
- `{{name}}`: Contact name
- `{{number}}`: Contact phone number
- `{{custom_label}}`: Optional custom label
- `{{photo_url}}`: Authenticated photo URL (if set)

#### Custom Array Repeaters

Define custom repeating data structures like digitmap:

```json
"provisioning": {
    "digitmap_data": [
        {"pattern": "xxx", "timeout": "3"},
        {"pattern": "1xxxxxxxxxx", "timeout": "3"}
    ]
}
```

Use in template:
```ini
{{digitmap_loop}}
phone_setting.digitmap.{{index}} = {{pattern}}|{{timeout}}
{{/digitmap_loop}}
```

**Generic Loop Pattern:**
```ini
{{custom_name_loop}}
config.{{index}}.property = {{field_name}}
{{/custom_name_loop}}
```

The system looks for `custom_name_data` in the provisioning section or device custom options.

## Adding a New Handset Model

### Step 1: Create Template File

Create `/templates/{MODEL}.json` with all required sections:

```bash
cd /path/to/quickprovisioner/templates
cp T58G.json T56A.json
```

### Step 2: Customize Template

Edit the new template:
- Update `model` and `display_name`
- Adjust `max_line_keys` and screen dimensions
- Modify `configurable_options` for model-specific features
- Update button positions in `visual_editor.keys`
- Customize the provisioning `template` for the model's INI format

### Step 3: Test Template

1. Go to **Handset Model Templates** tab
2. Paste your JSON and click **Import Template**
3. Go to **Edit/Generate Device** tab
4. Select your new model from the dropdown
5. Verify that:
   - All configurable options appear with descriptions
   - Required fields show red asterisk (*)
   - SVG preview renders correctly
   - Button positions match expected layout

### Step 4: Test Provisioning

1. Create a test device with your new model
2. Configure line keys and options
3. Click **Preview Provisioning Config**
4. Verify the generated INI file is correct
5. Test actual provisioning with a physical phone

## Required/Optional Field Handling

### Marking Fields as Required

Set `"required": true` in the configurable option:

```json
{
    "name": "static.security.admin_password",
    "type": "text",
    "required": true,
    "label": "Admin Password",
    "description": "Required for security"
}
```

### Form Validation

The system automatically:
- Displays red asterisk (*) next to required field labels
- Validates all required fields on form submission
- Shows alert with list of missing required fields
- Prevents saving until all required fields are filled

### Best Practices

**Mark as Required:**
- Security settings (admin passwords)
- Critical configuration that breaks functionality if missing

**Mark as Optional:**
- Feature toggles (can use defaults)
- Enhancement settings
- Cosmetic preferences

## Examples

### Complete T58G Template

See `/templates/T58G.json` for a comprehensive example featuring:
- 24 configurable options with detailed descriptions
- Required admin password field
- 27 line key positions across 2 pages
- Enhanced SVG fallback rendering
- Digitmap array repeater example
- Complete INI template with all loops and conditionals

### Minimal Template Structure

```json
{
    "manufacturer": "Vendor",
    "model": "MODEL",
    "display_name": "Vendor MODEL",
    "max_line_keys": 10,
    "button_layout": "grid",
    "svg_fallback": true,
    "notes": "Basic model",
    "configurable_options": [],
    "visual_editor": {
        "screen_width": 320,
        "screen_height": 240,
        "svg_fallback": true,
        "schematic": {
            "chassis_width": 400,
            "chassis_height": 300,
            "screen_x": 40,
            "screen_y": 30,
            "screen_width": 320,
            "screen_height": 240
        },
        "keys": []
    },
    "provisioning": {
        "content_type": "text/plain",
        "filename_pattern": "{mac}.cfg",
        "type_mapping": {},
        "template": "# Minimal config\n"
    }
}
```

## Troubleshooting

### Template Won't Import

- Validate JSON syntax with `python3 -m json.tool template.json`
- Ensure `model` field exists and is unique
- Check all required sections are present

### Fields Not Showing

- Verify `configurable_options` array syntax
- Check `name`, `type`, and `label` are all set
- Look for duplicate field names

### Preview Not Rendering

- Verify `visual_editor.schematic` dimensions are set
- Check that `keys` array has valid positions
- Ensure `svg_fallback` is true if no `remote_image_url`

### Provisioning Output Wrong

- Test variable substitution with Preview Config
- Verify loop syntax: `{{loop_name_loop}}...{{/loop_name_loop}}`
- Check conditional syntax: `{{if variable}}...{{/if}}`
- Ensure `type_mapping` includes all key types used

### Array Repeater Not Working

- Define data in `provisioning` section as `{name}_data`
- Use consistent naming: `digitmap_data` → `{{digitmap_loop}}`
- Data must be JSON array of objects or scalars
- Use `{{index}}` for 1-based numbering

## Advanced Features

### Custom SVG Enhancements

The system supports enhanced SVG rendering with:
- Linear gradients for chassis and screen
- Model name label at top
- Screen frame highlights
- Customizable colors and effects

Modify `renderPreview()` JavaScript function to add custom SVG elements.

### Dynamic Screen Dimensions

Wallpaper and media endpoints automatically use template screen dimensions:
- `screen_width` and `screen_height` from visual_editor
- Applies correct aspect ratio for crop/fit modes
- Supports per-model wallpaper recommendations

### Security Features

All provisioning endpoints support:
- Local network auto-authorization
- Per-device HTTP Basic Auth for remote access
- Authenticated wallpaper and media URLs
- Admin password requirements in templates

## Migration Guide

### From Static to Dynamic Templates

If you have hardcoded phone configurations:

1. Extract configuration sections into JSON template
2. Replace hardcoded values with `{{variables}}`
3. Add configurable_options for user-adjustable settings
4. Test with Preview Config before deploying

### Updating Existing Templates

When updating templates already in use:

1. Make changes in a test environment first
2. Verify devices can still provision with changes
3. Test that existing devices aren't broken
4. Use Import to update production template
5. Test preview and actual provisioning

## Support

For issues or questions about dynamic handset templates:

1. Check this documentation first
2. Validate your JSON syntax
3. Test with the example T58G template
4. Review browser console for JavaScript errors
5. Check FreePBX logs for PHP errors

---

**Version:** 2.2.0  
**Last Updated:** 2026-01-03
