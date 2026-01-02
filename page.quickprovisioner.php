<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

$devices = \FreePBX::Database()->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$extensions = [];
$users = \FreePBX::Core()->getAllUsers();
foreach ($users as $user) {
    $extensions[] = $user['extension'];
}

session_start();
if (!isset($_SESSION['qp_csrf'])) {
    $_SESSION['qp_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['qp_csrf'];
?>
<div class="container-fluid">
    <h1>HH Quick Provisioner v2.1.0</h1>

    <ul class="nav nav-tabs" role="tablist">
        <li class="active"><a data-toggle="tab" href="#tab-list" onclick="loadDevices()">Device List</a></li>
        <li><a data-toggle="tab" href="#tab-edit">Add/Edit Device</a></li>
        <li><a data-toggle="tab" href="#tab-contacts">Contacts</a></li>
        <li><a data-toggle="tab" href="#tab-assets" onclick="loadAssets()">Asset Manager</a></li>
        <li><a data-toggle="tab" href="#tab-templates" onclick="loadTemplates()">Handset Model Templates</a></li>
    </ul>

    <div class="tab-content" style="padding-top:20px;">

        <div id="tab-list" class="tab-pane fade in active">
            <button class="btn btn-success" onclick="newDevice()">Add New</button>
            <button class="btn btn-default" onclick="loadDevices()">Refresh</button>
            <table class="table table-striped" style="margin-top:15px;">
                <thead><tr><th>MAC</th><th>Extension</th><th>Model</th><th>Actions</th></tr></thead>
                <tbody id="deviceListBody"></tbody>
            </table>
        </div>

        <div id="tab-edit" class="tab-pane fade">
            <form id="deviceForm">
                <input type="hidden" id="deviceId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group"><label>MAC Address</label><input type="text" id="mac" class="form-control" required></div>
                        <div class="form-group"><label>Extension</label>
                            <select id="extension" class="form-control" required onchange="loadSipSecret()">
                                <?php foreach ($extensions as $ext): ?>
                                    <option value="<?= htmlspecialchars($ext) ?>"><?= htmlspecialchars($ext) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>SIP Secret (Preview)</label>
                            <input type="text" id="sip_secret_preview" class="form-control" readonly placeholder="Select extension to load">
                            <button type="button" class="btn btn-sm btn-default" onclick="copyToClipboard('sip_secret_preview')">Copy</button>
                            <small class="text-muted">Unmasked for admin verification. Only visible to logged-in users.</small>
                        </div>
                        <div class="form-group"><label>Model</label>
                            <select id="model" class="form-control" onchange="loadProfile(); updatePageSelect(); renderPreview(); showModelNotes(); loadDeviceOptions();">
                                <!-- Populated by loadTemplates() -->
                            </select>
                        </div>
                        <div id="modelNotes" style="margin-bottom:15px; color:#666;"></div>
                        <hr>
                        <div class="form-group"><label>Wallpaper</label>
                            <div class="input-group">
                                <input type="text" id="wallpaper" class="form-control" readonly placeholder="Click Pick to select" onchange="renderPreview()">
                                <span class="input-group-btn"><button type="button" class="btn btn-default" onclick="$('a[href=\"#tab-assets\"]').tab('show'); loadAssets()">Pick</button></span>
                            </div>
                        </div>
                        <div class="form-group"><label>Wallpaper Mode</label>
                            <select id="wallpaper_mode" class="form-control" onchange="renderPreview()">
                                <option value="crop">Crop to Fill</option>
                                <option value="fit">Fit (Letterbox)</option>
                            </select>
                        </div>
                        <div class="form-group"><label>Page</label>
                            <select id="pageSelect" class="form-control" onchange="renderPreview()"></select>
                        </div>
                        <hr>
                        <div id="deviceOptions">
                            <h4>Device Options</h4>
                        </div>
                        <hr>
                        <div id="advancedOptions">
                            <h4>Advanced: Custom Template Override</h4>
                            <textarea id="custom_template_override" class="form-control" rows="10" placeholder="Paste custom template here to override default..."></textarea>
                            <p class="text-warning">Warning: This overrides the model template entirely. Use with caution.</p>
                        </div>
                        <button type="submit" class="btn btn-success btn-block">Save Device</button>
                        <button type="button" class="btn btn-info btn-block" onclick="previewConfig()">Preview Provisioning Config</button>
                    </div>
                    <div class="col-md-8">
                        <div class="panel panel-default">
                            <div class="panel-heading">Live Visual Preview</div>
                            <div class="panel-body">
                                <div id="previewContainer" style="position:relative; margin:0 auto; border:1px solid #ccc;">
                                    <div id="keysLayer"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div id="tab-contacts" class="tab-pane fade">
            <h3>Manage Contacts for Device</h3>
            <p>Select a device from list to edit contacts.</p>
            <div id="contactsList"></div>
            <button class="btn btn-success" onclick="addContact()">Add Contact</button>
        </div>

        <div id="tab-assets" class="tab-pane fade">
            <h3>Upload Wallpaper / Contact Photo</h3>
            <input type="file" id="assetUpload" class="form-control">
            <br><button class="btn btn-primary" onclick="uploadAsset()">Upload</button>
            <hr>
            <div id="assetGrid" class="row"></div>
        </div>

        <div id="tab-templates" class="tab-pane fade">
            <h3>Import Template JSON</h3>
            <p>Paste a complete template JSON below. Must include "model", "display_name", "provisioning.template", etc.</p>
            <textarea id="driverInput" class="form-control" rows="15" placeholder='Example structure:
{
  "manufacturer": "Yealink",
  "model": "T48G",
  "display_name": "Yealink T48G",
  "configurable_options": [ ... ],
  "visual_editor": { ... },
  "provisioning": {
    "template": "#!version:1.0.0.1\n..."
  }
}'></textarea>
            <br>
            <button class="btn btn-primary" onclick="importDriver()">Import Template</button>
            <button class="btn btn-info" onclick="showExampleJSON()">Show Example JSON</button>
            <hr>
            <div id="importFeedback"></div>
            <table class="table">
                <thead><tr><th>Model</th><th>Display Name</th><th>Actions</th></tr></thead>
                <tbody id="templatesList"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="keyModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h4>Edit Key <span id="keyIndex"></span></h4></div>
      <div class="modal-body">
        <input type="hidden" id="keyId">
        <div class="form-group"><label>Type</label><select id="keyType" class="form-control"><option value="line">Line</option><option value="speed_dial">Speed Dial</option><option value="blf">BLF</option></select></div>
        <div class="form-group"><label>Value</label><input type="text" id="keyValue" class="form-control"></div>
        <div class="form-group"><label>Label</label><input type="text" id="keyLabel" class="form-control"></div>
        <button class="btn btn-default" onclick="saveKey()">Save</button>
        <button class="btn btn-warning" onclick="clearKey()">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="contactModal">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h4>Edit Contact <span id="contactIndex"></span></h4></div>
      <div class="modal-body">
        <input type="hidden" id="contactId">
        <div class="form-group"><label>Name</label><input type="text" id="contactName" class="form-control"></div>
        <div class="form-group"><label>Number</label><input type="text" id="contactNumber" class="form-control"></div>
        <div class="form-group"><label>Custom Label</label><input type="text" id="contactLabel" class="form-control"></div>
        <div class="form-group"><label>Photo</label><input type="text" id="contactPhoto" class="form-control" readonly placeholder="Pick from Assets"></div>
        <button class="btn btn-default" onclick="saveContact()">Save</button>
        <button class="btn btn-warning" onclick="clearContact()">Clear</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="configPreviewModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h4>Provisioning Config Preview</h4></div>
      <div class="modal-body">
        <textarea id="configPreview" class="form-control" rows="15" readonly></textarea>
      </div>
      <div class="modal-footer">
        <button class="btn btn-default" onclick="$('#configPreviewModal').modal('hide')">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
var currentKeys = [];
var currentContacts = [];
var currentDeviceId = null;
var profiles = {};

function loadDevices() {
    $('#deviceListBody').html('');
    <?php foreach ($devices as $d): ?>
        var row = '<tr><td><?= $d["mac"] ?></td><td><?= $d["extension"] ?></td><td><?= $d["model"] ?></td><td><button onclick="editDevice(<?= $d["id"] ?>)">Edit</button> <button onclick="deleteDevice(<?= $d["id"] ?>)">Delete</button></td></tr>';
        $('#deviceListBody').append(row);
    <?php endforeach; ?>
}

function editDevice(id) {
    currentDeviceId = id;
    $.post('ajax.quickprovisioner.php', {action:'get_device', id:id, csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            var d = r.data;
            $('#deviceId').val(d.id);
            $('#mac').val(d.mac);
            $('#extension').val(d.extension);
            loadSipSecret();  // Load secret after extension set
            $('#model').val(d.model).trigger('change');
            $('#wallpaper').val(d.wallpaper);
            $('#wallpaper_mode').val(d.wallpaper_mode);
            currentKeys = JSON.parse(d.keys_json) || [];
            currentContacts = JSON.parse(d.contacts_json) || [];
            var custom_options = JSON.parse(d.custom_options_json) || {};
            for (var key in custom_options) {
                $('[name="custom_options[' + key + ']"]').val(custom_options[key]);
            }
            $('#custom_template_override').val(d.custom_template_override);
            renderPreview();
        }
    }, 'json');
    $('a[href="#tab-edit"]').tab('show');
}

function deleteDevice(id) {
    if (confirm('Delete device?')) {
        // Implement AJAX delete
        loadDevices();
    }
}

function newDevice() {
    $('#deviceForm')[0].reset();
    $('#deviceId').val('');
    $('#sip_secret_preview').val('');
    currentKeys = [];
    currentContacts = [];
    currentDeviceId = null;
    renderPreview();
}

function loadTemplates() {
    $.post('ajax.quickprovisioner.php', {action:'list_drivers', csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            $('#templatesList').html('');
            $('#model').html('');
            r.list.forEach(function(t) {
                var row = '<tr><td>' + t.model + '</td><td>' + t.display_name + '</td><td><button onclick="downloadTemplate(\'' + t.model + '\')">Download</button> <button onclick="deleteTemplate(\'' + t.model + '\')">Delete</button></td></tr>';
                $('#templatesList').append(row);
                $('#model').append('<option value="' + t.model + '">' + t.display_name + '</option>');
            });
        }
    }, 'json');
}

function loadProfile() {
    var model = $('#model').val();
    if (!model) return;
    $.post('ajax.quickprovisioner.php', {action:'get_driver', model:model, csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            profiles[model] = JSON.parse(r.json);
            showModelNotes();
            loadDeviceOptions();
            updatePageSelect();
            renderPreview();
        } else {
            alert('Error loading model: ' + r.message);
        }
    }, 'json');
}

function showModelNotes() {
    var model = $('#model').val();
    var profile = profiles[model];
    $('#modelNotes').html(profile ? profile.notes || '' : '');
}

function loadDeviceOptions() {
    var model = $('#model').val();
    var profile = profiles[model];
    var html = '';
    if (profile && profile.configurable_options) {
        profile.configurable_options.forEach(function(opt) {
            html += '<div class="form-group">';
            html += '<label title="' + (opt.description || '') + '">' + opt.label + '</label>';
            if (opt.type === 'bool') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"><option value="">Default (' + opt.default + ')</option><option value="1">On</option><option value="0">Off</option></select>';
            } else if (opt.type === 'select') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"><option value="">Default (' + opt.default + ')</option>';
                for (var val in opt.options) {
                    html += '<option value="' + val + '">' + opt.options[val] + '</option>';
                }
                html += '</select>';
            } else if (opt.type === 'number') {
                html += '<input type="number" name="custom_options[' + opt.name + ']" class="form-control" min="' + (opt.min || '') + '" max="' + (opt.max || '') + '" placeholder="Default: ' + opt.default + '">';
            } else {
                html += '<input type="text" name="custom_options[' + opt.name + ']" class="form-control" placeholder="Default: ' + opt.default + '">';
            }
            html += '</div>';
        });
    }
    $('#deviceOptions').html(html);
}

function updatePageSelect() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!profile) return;
    var perPage = 10; // Default; can pull from template if added
    var maxPages = Math.ceil(profile.max_line_keys / perPage);
    $('#pageSelect').html('');
    for (var i = 1; i <= maxPages; i++) {
        $('#pageSelect').append('<option value="' + i + '">Page ' + i + '</option>');
    }
}

function renderPreview() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!profile) return;
    var ve = profile.visual_editor;
    var page = parseInt($('#pageSelect').val()) || 1;
    var wallpaper = $('#wallpaper').val();
    var mode = $('#wallpaper_mode').val();

    var container = $('#previewContainer');
    container.empty().css({width: ve.schematic.chassis_width + 'px', height: ve.schematic.chassis_height + 'px'});

    if (ve.background_image_url) {
        container.css('backgroundImage', 'url(' + ve.background_image_url + ')');
    } else if (ve.svg_fallback) {
        var sch = ve.schematic;
        var svg = `<svg width="${sch.chassis_width}" height="${sch.chassis_height}" xmlns="http://www.w3.org/2000/svg">
            <rect width="100%" height="100%" fill="#333" rx="30"/>
            <rect x="${sch.screen_x}" y="${sch.screen_y}" width="${sch.screen_width}" height="${sch.screen_height}" fill="#000"/>
            <text x="50%" y="50%" fill="#666" font-size="24" text-anchor="middle" dominant-baseline="middle">SVG Fallback</text>
        </svg>`;
        container.css('backgroundImage', 'url(data:image/svg+xml;base64,' + btoa(svg) + ')');
    }

    if (wallpaper) {
        var screenDiv = $('<div>').css({
            position: 'absolute',
            left: ve.schematic.screen_x + 'px',
            top: ve.schematic.screen_y + 'px',
            width: ve.schematic.screen_width + 'px',
            height: ve.schematic.screen_height + 'px',
            backgroundImage: 'url(assets/uploads/' + wallpaper + ')',
            backgroundSize: mode === 'crop' ? 'cover' : 'contain',
            backgroundRepeat: 'no-repeat',
            backgroundPosition: 'center'
        });
        container.append(screenDiv);
    }

    var keysLayer = $('<div id="keysLayer">').css({position: 'absolute', top: 0, left: 0});
    container.append(keysLayer);
    ve.keys.forEach(function(key) {
        if (key.page === page) {
            var btn = $('<button>').css({
                position: 'absolute',
                left: key.x + 'px',
                top: key.y + 'px',
                width: '100px',
                textAlign: key.label_align
            }).text(currentKeys.find(k => k.index === key.index)?.label || key.info).click(function() {
                editKey(key.index);
            });
            keysLayer.append(btn);
        }
    });
}

function editKey(index) {
    $('#keyIndex').text(index);
    var key = currentKeys.find(k => k.index === index) || {};
    $('#keyType').val(key.type || 'line');
    $('#keyValue').val(key.value || '');
    $('#keyLabel').val(key.label || '');
    $('#keyModal').modal('show');
}

function saveKey() {
    var index = parseInt($('#keyIndex').text());
    var type = $('#keyType').val();
    var value = $('#keyValue').val();
    var label = $('#keyLabel').val();
    var existing = currentKeys.find(k => k.index === index);
    if (existing) {
        existing.type = type;
        existing.value = value;
        existing.label = label;
    } else {
        currentKeys.push({index: index, type: type, value: value, label: label});
    }
    renderPreview();
    $('#keyModal').modal('hide');
}

function clearKey() {
    var index = parseInt($('#keyIndex').text());
    currentKeys = currentKeys.filter(k => k.index !== index);
    renderPreview();
    $('#keyModal').modal('hide');
}

function previewConfig() {
    if (!currentDeviceId) return alert('Save device first');
    $.post('ajax.quickprovisioner.php', {action:'preview_config', id:currentDeviceId, csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            $('#configPreview').val(r.config);
            $('#configPreviewModal').modal('show');
        } else {
            alert('Error: ' + r.message);
        }
    }, 'json');
}

function importDriver() {
    var json = $('#driverInput').val().trim();
    if (!json) {
        $('#importFeedback').html('<div class="alert alert-warning">Please paste JSON first.</div>');
        return;
    }
    $('#importFeedback').html('<div class="alert alert-info">Importing...</div>');
    $.post('ajax.quickprovisioner.php', {
        action: 'import_driver',
        json: json,
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status) {
            $('#importFeedback').html('<div class="alert alert-success">Template imported successfully!</div>');
            loadTemplates();
            $('#driverInput').val('');
        } else {
            $('#importFeedback').html('<div class="alert alert-danger">Error: ' + (r.message || 'Unknown error') + '</div>');
        }
    }, 'json').fail(function() {
        $('#importFeedback').html('<div class="alert alert-danger">AJAX request failed. Check console or CSRF token.</div>');
    });
}

function showExampleJSON() {
    var example = JSON.stringify({
        "manufacturer": "Yealink",
        "model": "T48G",
        "display_name": "Yealink T48G",
        "max_line_keys": 29,
        "button_layout": "grid",
        "svg_fallback": true,
        "notes": "Wallpaper: 800x480 recommended.",
        "configurable_options": [
            {
                "name": "phone_setting.keyboard_lock",
                "type": "bool",
                "default": 1,
                "label": "Enable Phone Lock"
            },
            {
                "name": "phone_setting.keyboard_lock.password",
                "type": "text",
                "default": "1234",
                "label": "Unlock PIN"
            }
        ],
        "visual_editor": {
            "screen_width": 800,
            "screen_height": 480,
            "remote_image_url": "https://example.com/t48g.png",
            "schematic": { /* ... */ },
            "keys": [ /* ... generated grid ... */ ]
        },
        "provisioning": {
            "content_type": "text/plain",
            "filename_pattern": "{mac}.cfg",
            "type_mapping": {"line": "15", "speed_dial": "13", "blf": "16"},
            "template": "#!version:1.0.0.1\naccount.1.enable = 1\n{{line_keys_loop}}\nlinekey.{{index}}.type = {{type}}\n{{/line_keys_loop}}"
        }
    }, null, 2);
    $('#driverInput').val(example);
}

function loadSipSecret() {
    var ext = $('#extension').val();
    if (!ext) {
        $('#sip_secret_preview').val('');
        return;
    }
    $.post('ajax.quickprovisioner.php', {
        action: 'get_sip_secret',
        extension: ext,
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status) {
            $('#sip_secret_preview').val(r.secret);
        } else {
            $('#sip_secret_preview').val('Error: ' + r.message);
        }
    }, 'json');
}

function copyToClipboard(id) {
    var text = document.getElementById(id).value;
    navigator.clipboard.writeText(text).then(function() {
        alert('Copied to clipboard!');
    }).catch(function() {
        alert('Copy failed.');
    });
}

function uploadAsset() {
    var file = $('#assetUpload')[0].files[0];
    if (!file) return alert('Select file');
    var fd = new FormData();
    fd.append('file', file);
    fd.append('action', 'upload_file');
    fd.append('csrf_token', '<?= $csrf_token ?>');
    $.ajax({
        url: 'ajax.quickprovisioner.php',
        type: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        success: function(r) {
            if (r.status) {
                loadAssets();
            } else {
                alert('Error: ' + r.message);
            }
        }
    });
}

$('#deviceForm').submit(function(e) {
    e.preventDefault();
    var data = {
        action: 'save_device',
        data: $(this).serialize(),
        keys_json: JSON.stringify(currentKeys),
        contacts_json: JSON.stringify(currentContacts)
    };
    $.post('ajax.quickprovisioner.php', data, function(r) {
        if (r.status) {
            alert('Saved!');
            loadDevices();
            newDevice();
        } else {
            alert('Error: ' + r.message);
        }
    }, 'json');
});

loadDevices();
loadTemplates();
</script>
<?php
?>
