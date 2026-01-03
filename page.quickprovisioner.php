<?php
function qp_is_local_network() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($ip === '::1') return true;
    if (preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip)) {
        return true;
    }
    return false;
}

if (!qp_is_local_network()) {
    die('Remote access denied. Admin UI is local network only.');
}

if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

// Verify FreePBX Core is available
if (!class_exists('FreePBX') || !\FreePBX::Core()) {
    die('FreePBX Core not available. Please ensure FreePBX is properly installed.');
}

$devices = \FreePBX::Database()->query("SELECT * FROM quickprovisioner_devices ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$extensions = [];
try {
    $users = \FreePBX::Core()->getAllUsers();
    if ($users && is_array($users)) {
        foreach ($users as $user) {
            if (isset($user['extension'])) {
                $extensions[] = $user['extension'];
            }
        }
    }
} catch (Exception $e) {
    // Log error but don't stop execution
    error_log("Quick Provisioner: Failed to fetch extensions - " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['qp_csrf'])) {
    $_SESSION['qp_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['qp_csrf'];
?>
<div class="container-fluid">
    <h1>HH Quick Provisioner v2.2.0</h1>

    <ul class="nav nav-tabs" role="tablist">
        <li class="active"><a data-toggle="tab" href="#tab-list" onclick="loadDevices()">Device List</a></li>
        <li><a data-toggle="tab" href="#tab-edit">Edit/Generate Provisioning</a></li>
        <li><a data-toggle="tab" href="#tab-contacts">Contacts</a></li>
        <li><a data-toggle="tab" href="#tab-assets" onclick="loadAssets()">Asset Manager</a></li>
        <li><a data-toggle="tab" href="#tab-templates" onclick="loadTemplates()">Handset Model Templates</a></li>
        <li><a data-toggle="tab" href="#tab-admin">Admin</a></li>
    </ul>

    <div class="tab-content" style="padding-top:20px;">

        <div id="tab-list" class="tab-pane fade in active">
            <button class="btn btn-success" onclick="newDevice()">Add New</button>
            <button class="btn btn-default" onclick="loadDevices()">Refresh</button>
            <table class="table table-striped" style="margin-top:15px;">
                <thead><tr><th>MAC</th><th>Extension</th><th>Secret</th><th>Model</th><th>Actions</th></tr></thead>
                <tbody id="deviceListBody"></tbody>
            </table>
        </div>

        <div id="tab-edit" class="tab-pane fade">
            <form id="deviceForm">
                <input type="hidden" id="deviceId">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" id="extension" name="extension">
                <div class="row">
                    <!-- LEFT COLUMN: Core Settings (Always Visible ~30-35%) -->
                    <div class="col-md-4">
                        <h4>Core Device Settings</h4>
                        <div class="form-group">
                            <label>Extension Number</label>
                            <div id="extension_select_wrapper">
                                <div class="input-group">
                                    <select id="extension_select" class="form-control" required onchange="extensionSelectChanged()">
                                        <option value="">-- Select Extension --</option>
                                        <?php foreach ($extensions as $ext): ?>
                                            <option value="<?= htmlspecialchars($ext) ?>"><?= htmlspecialchars($ext) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" onclick="toggleCustomExtension()" title="Toggle custom extension input">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div id="extension_custom_wrapper" style="display:none;">
                                <div class="input-group">
                                    <input type="text" id="extension_custom" class="form-control" placeholder="Enter custom extension" onchange="customExtensionChanged()">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" onclick="toggleCustomExtension()" title="Back to dropdown">
                                            <i class="fa fa-list"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <small class="text-muted">Select from FreePBX extensions or enter a custom value</small>
                        </div>
                        
                        <div class="form-group">
                            <label>SIP Secret</label>
                            <input type="hidden" id="custom_sip_secret" name="custom_sip_secret">
                            <div id="secret_preview_wrapper">
                                <div class="input-group">
                                    <input type="text" id="sip_secret_preview" class="form-control" readonly placeholder="Select extension to auto-load">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" onclick="copyToClipboard('sip_secret_preview')" title="Copy to clipboard">
                                            <i class="fa fa-copy"></i>
                                        </button>
                                        <button type="button" class="btn btn-default" onclick="toggleCustomSecret()" title="Enter custom secret to save">
                                            <i class="fa fa-edit"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <div id="secret_custom_wrapper" style="display:none;">
                                <div class="input-group">
                                    <input type="text" id="sip_secret_custom_input" class="form-control" placeholder="Enter custom SIP secret">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-success" onclick="saveCustomSecret()" title="Save custom secret">
                                            <i class="fa fa-save"></i>
                                        </button>
                                        <button type="button" class="btn btn-default" onclick="toggleCustomSecret()" title="Back to auto-fetch">
                                            <i class="fa fa-refresh"></i>
                                        </button>
                                    </span>
                                </div>
                            </div>
                            <small class="text-muted">Auto-fetched from FreePBX or enter custom secret (will be saved and used in provisioning)</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Model</label>
                            <select id="model" class="form-control" onchange="loadProfile(); updateTemplateHeader(); renderHandsetSettings(); renderPhoneAssets(); renderButtonLayout();">
                                <!-- Populated by loadTemplates() -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>MAC Address</label>
                            <input type="text" id="mac" class="form-control" required>
                        </div>
                        
                        <hr>
                        
                        <h5>Remote Provisioning Authentication</h5>
                        <div class="form-group">
                            <label>Provisioning Username</label>
                            <input type="text" id="prov_username" class="form-control" placeholder="Required for remote provisioning">
                            <small class="text-muted">HTTP Basic Auth for remote provisioning</small>
                        </div>
                        <div class="form-group">
                            <label>Provisioning Password</label>
                            <div class="input-group">
                                <input type="text" id="prov_password" class="form-control" placeholder="Required for remote provisioning">
                                <span class="input-group-btn">
                                    <button type="button" class="btn btn-default" onclick="generateProvPassword()">Generate</button>
                                </span>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="panel-group" id="advancedAccordion">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" data-parent="#advancedAccordion" href="#collapseAdvanced">
                                            Advanced: Custom Template Override <i class="fa fa-chevron-down"></i>
                                        </a>
                                    </h4>
                                </div>
                                <div id="collapseAdvanced" class="panel-collapse collapse">
                                    <div class="panel-body">
                                        <textarea id="custom_template_override" class="form-control" rows="8" placeholder="Paste custom template here to override default..."></textarea>
                                        <p class="text-warning" style="margin-top:10px;">⚠️ Warning: This overrides the model template entirely. Use with caution.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <button type="submit" class="btn btn-success btn-block" style="margin-bottom:10px;">
                            <i class="fa fa-save"></i> Save Device
                        </button>
                        <button type="button" class="btn btn-info btn-block" onclick="previewConfig()" style="margin-bottom:10px;">
                            <i class="fa fa-eye"></i> Preview Provisioning Config
                        </button>
                        <button type="button" class="btn btn-primary btn-block" onclick="generateProvisioningFile()" style="font-weight:bold;">
                            <i class="fa fa-file-text-o"></i> Generate Provisioning File
                        </button>
                    </div>
                    
                    <!-- RIGHT COLUMN: Template-Driven Content (~65-70%) -->
                    <div class="col-md-8">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4 id="templateHeader" style="margin:0;">Select a model to load template settings</h4>
                            </div>
                            <div class="panel-body">
                                <!-- Sub-tabs -->
                                <ul class="nav nav-tabs" role="tablist" id="rightColumnTabs">
                                    <li class="active"><a data-toggle="tab" href="#subtab-handset">Handset Settings</a></li>
                                    <li><a data-toggle="tab" href="#subtab-assets">Phone Assets</a></li>
                                    <li><a data-toggle="tab" href="#subtab-layout">Button Layout</a></li>
                                </ul>
                                
                                <div class="tab-content" style="padding-top:15px;">
                                    <!-- Sub-tab 1: Handset Settings -->
                                    <div id="subtab-handset" class="tab-pane fade in active">
                                        <div id="handsetSettingsContent">
                                            <p class="text-muted">Select a model to view handset settings.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Sub-tab 2: Phone Assets -->
                                    <div id="subtab-assets" class="tab-pane fade">
                                        <div id="phoneAssetsContent">
                                            <p class="text-muted">Select a model to manage phone assets.</p>
                                        </div>
                                    </div>
                                    
                                    <!-- Sub-tab 3: Button Layout -->
                                    <div id="subtab-layout" class="tab-pane fade">
                                        <div id="buttonLayoutContent">
                                            <div class="form-group">
                                                <label>Page</label>
                                                <select id="pageSelect" class="form-control" onchange="renderPreview()"></select>
                                            </div>
                                            <div id="previewContainer" style="position:relative; margin:0 auto; border:1px solid #ccc;">
                                                <div id="keysLayer"></div>
                                            </div>
                                            <p class="text-muted" style="margin-top:10px;">Click any button to configure (Type, Value, Label)</p>
                                        </div>
                                    </div>
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

        <div id="tab-admin" class="tab-pane fade">
            <!-- PBX Controls Panel -->
            <div class="panel panel-primary">
                <div class="panel-heading">
                    <h3 class="panel-title">🎛️ PBX Controls</h3>
                </div>
                <div class="panel-body">
                    <p class="text-info">
                        <i class="fa fa-info-circle"></i> Use these controls to apply configuration changes or restart the PBX.
                    </p>

                    <div class="form-group">
                        <button class="btn btn-success" onclick="reloadPBX()">
                            <i class="fa fa-refresh"></i> Reload Config
                        </button>
                        <span class="text-muted">Apply configuration changes without interrupting calls</span>
                    </div>

                    <div class="form-group">
                        <button class="btn btn-warning" onclick="restartPBX()">
                            <i class="fa fa-power-off"></i> Restart PBX
                        </button>
                        <span class="text-danger">
                            <i class="fa fa-exclamation-triangle"></i> <strong>Warning:</strong> This will briefly interrupt active calls!
                        </span>
                    </div>

                    <hr>

                    <div id="pbxStatus" style="margin-top: 15px;"></div>
                </div>
            </div>

            <!-- Module Updates Panel -->
            <div class="panel panel-info" style="margin-top: 30px;">
                <div class="panel-heading">
                    <h3 class="panel-title">🔄 Module Updates</h3>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <strong>Current Version:</strong> <span id="currentVersion">2.2.0</span>
                    </div>
                    <div class="form-group">
                        <strong>Git Commit:</strong> <span id="currentCommit">Loading...</span>
                    </div>

                    <button class="btn btn-primary" onclick="checkForUpdates()" id="checkUpdatesBtn">
                        Check for Updates
                    </button>

                    <div id="updateStatus" style="margin-top: 15px; display: none;">
                        <div id="updateStatusMessage"></div>

                        <div id="changelogSection" style="margin-top: 15px; display: none;">
                            <h4>Changelog:</h4>
                            <div class="list-group" id="changelogList" style="max-height: 300px; overflow-y: auto;">
                                <!-- Changelog items will be inserted here -->
                            </div>

                            <div style="margin-top: 15px;">
                                <p><strong>Do you want to update?</strong></p>
                                <button class="btn btn-success" onclick="performUpdate()" id="confirmUpdateBtn">
                                    Yes, Update Now
                                </button>
                                <button class="btn btn-default" onclick="cancelUpdate()" style="margin-left: 10px;">
                                    No, Cancel
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="updateResult" style="margin-top: 15px; display: none;"></div>
                </div>
            </div>
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

<div class="modal fade" id="generateModal">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4>Generated Provisioning File</h4>
      </div>
      <div class="modal-body">
        <p><strong>Filename:</strong> <span id="generatedFilename"></span></p>
        <textarea id="generatedConfig" class="form-control" rows="15" readonly style="font-family: monospace;"></textarea>
        <p class="text-muted" style="margin-top:10px;">
          <strong>Deploy path:</strong> <span id="deployPath">/var/www/html/provisioning/<span id="deployFilename"></span></span>
        </p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-success" onclick="downloadConfig()">
          <i class="fa fa-download"></i> Download .cfg
        </button>
        <button class="btn btn-primary" onclick="deployConfig()">
          <i class="fa fa-upload"></i> Deploy to Server
        </button>
        <button class="btn btn-default" onclick="copyGeneratedToClipboard()">
          <i class="fa fa-copy"></i> Copy to Clipboard
        </button>
        <button class="btn btn-default" data-dismiss="modal">Close</button>
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
    $.post('ajax.quickprovisioner.php', {action:'list_devices_with_secrets', csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            $('#deviceListBody').html('');
            r.devices.forEach(function(d) {
                var secretDisplay = '';
                if (d.secret) {
                    // Display secret directly in plain text with source badge
                    var escapedSecret = $('<div>').text(d.secret).html();
                    var sourceBadge = '';
                    if (d.secret_source === 'Custom') {
                        sourceBadge = ' <span class="label label-info" title="Custom secret stored in database">Custom</span>';
                    } else if (d.secret_source === 'FreePBX') {
                        sourceBadge = ' <span class="label label-success" title="Fetched from FreePBX">FreePBX</span>';
                    }
                    secretDisplay = '<span>' + escapedSecret + sourceBadge + '</span>';
                } else {
                    secretDisplay = '<span class="text-muted">N/A</span>';
                }
                var mac = $('<div>').text(d.mac).html();
                var ext = $('<div>').text(d.extension).html();
                var model = $('<div>').text(d.model).html();
                var row = '<tr><td>' + mac + '</td><td>' + ext + '</td><td>' + secretDisplay + '</td><td>' + model + '</td><td><button class="btn btn-sm btn-default" onclick="editDevice(' + d.id + ')">Edit</button> <button class="btn btn-sm btn-danger" onclick="deleteDevice(' + d.id + ')">Delete</button></td></tr>';
                $('#deviceListBody').append(row);
            });
        } else {
            var errorMsg = $('<div>').text(r.message || 'Unknown error').html();
            $('#deviceListBody').html('<tr><td colspan="5" class="text-danger">Error loading devices: ' + errorMsg + '</td></tr>');
        }
    }, 'json').fail(function() {
        $('#deviceListBody').html('<tr><td colspan="5" class="text-danger">Failed to load devices</td></tr>');
    });
}

function editDevice(id) {
    currentDeviceId = id;
    $.post('ajax.quickprovisioner.php', {action:'get_device', id:id, csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            var d = r.data;
            $('#deviceId').val(d.id);
            $('#mac').val(d.mac);

            // Handle extension - check if it's a FreePBX extension or custom
            var extensionFound = false;
            $('#extension_select option').each(function() {
                if ($(this).val() === d.extension) {
                    extensionFound = true;
                    return false;
                }
            });

            if (extensionFound) {
                $('#extension_select').val(d.extension);
                $('#extension').val(d.extension);
                $('#extension_select_wrapper').show();
                $('#extension_custom_wrapper').hide();
            } else {
                // Custom extension
                $('#extension_custom').val(d.extension);
                $('#extension').val(d.extension);
                $('#extension_select_wrapper').hide();
                $('#extension_custom_wrapper').show();
            }

            // Load custom secret if available
            $('#custom_sip_secret').val(d.custom_sip_secret || '');
            loadSipSecret(); // Load secret after extension set
            $('#model').val(d.model).trigger('change');
            $('#wallpaper').val(d.wallpaper);
            updateWallpaperPreview(d.wallpaper);
            $('#wallpaper_mode').val(d.wallpaper_mode);
            $('#prov_username').val(d.prov_username || '');
            $('#prov_password').val(d.prov_password || '');
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
        $.post('ajax.quickprovisioner.php', {action:'delete_device', id:id, csrf_token: '<?= $csrf_token ?>'}, function(r) {
            if (r.status) {
                alert('Device deleted');
                loadDevices();
            } else {
                alert('Error: ' + r.message);
            }
        }, 'json');
    }
}

function newDevice() {
    $('#deviceForm')[0].reset();
    $('#deviceId').val('');
    $('#extension_select').val('');
    $('#extension').val('');
    $('#extension_custom').val('');
    $('#extension_select_wrapper').show();
    $('#extension_custom_wrapper').hide();
    $('#sip_secret_preview').val('');
    $('#sip_secret_custom_input').val('');
    $('#custom_sip_secret').val('');
    $('#secret_preview_wrapper').show();
    $('#secret_custom_wrapper').hide();
    $('#prov_username').val('');
    $('#prov_password').val('');
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
            // Label with required indicator
            html += '<label title="' + (opt.description || '') + '">';
            html += opt.label;
            if (opt.required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';
            
            // Field based on type
            if (opt.type === 'bool') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"' + (opt.required ? ' required' : '') + '>';
                html += '<option value="">Default (' + opt.default + ')</option>';
                html += '<option value="1">On</option>';
                html += '<option value="0">Off</option>';
                html += '</select>';
            } else if (opt.type === 'select') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"' + (opt.required ? ' required' : '') + '>';
                html += '<option value="">Default (' + opt.default + ')</option>';
                for (var val in opt.options) {
                    html += '<option value="' + val + '">' + opt.options[val] + '</option>';
                }
                html += '</select>';
            } else if (opt.type === 'number') {
                html += '<input type="number" name="custom_options[' + opt.name + ']" class="form-control" ';
                html += 'min="' + (opt.min || '') + '" max="' + (opt.max || '') + '" ';
                html += 'placeholder="Default: ' + opt.default + '"' + (opt.required ? ' required' : '') + '>';
            } else {
                html += '<input type="text" name="custom_options[' + opt.name + ']" class="form-control" ';
                html += 'placeholder="Default: ' + opt.default + '"' + (opt.required ? ' required' : '') + '>';
            }
            
            // Description help text
            if (opt.description) {
                html += '<small class="help-block text-muted">' + opt.description + '</small>';
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
        // Enhanced SVG scaffold with better visual representation
        var svg = `<svg width="${sch.chassis_width}" height="${sch.chassis_height}" xmlns="http://www.w3.org/2000/svg">
            <!-- Phone chassis with gradient -->
            <defs>
                <linearGradient id="chassis-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#4a4a4a;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#2a2a2a;stop-opacity:1" />
                </linearGradient>
                <linearGradient id="screen-gradient" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#1a1a1a;stop-opacity:1" />
                    <stop offset="100%" style="stop-color:#0a0a0a;stop-opacity:1" />
                </linearGradient>
            </defs>
            <!-- Chassis background -->
            <rect width="100%" height="100%" fill="url(#chassis-gradient)" rx="30" ry="30"/>
            <!-- Screen area with border -->
            <rect x="${sch.screen_x}" y="${sch.screen_y}" width="${sch.screen_width}" height="${sch.screen_height}" 
                  fill="url(#screen-gradient)" stroke="#555" stroke-width="2" rx="5" ry="5"/>
            <!-- Screen frame highlight -->
            <rect x="${sch.screen_x + 2}" y="${sch.screen_y + 2}" width="${sch.screen_width - 4}" height="${sch.screen_height - 4}" 
                  fill="none" stroke="#666" stroke-width="1" rx="4" ry="4"/>
            <!-- Model label -->
            <text x="50%" y="30" fill="#999" font-size="18" font-weight="bold" text-anchor="middle" font-family="Arial, sans-serif">
                ${profile.display_name || model}
            </text>
            <!-- SVG Fallback indicator -->
            <text x="50%" y="${sch.screen_y + sch.screen_height/2}" fill="#444" font-size="16" text-anchor="middle" 
                  dominant-baseline="middle" font-family="Arial, sans-serif">
                SVG Preview Mode
            </text>
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

function extensionSelectChanged() {
    var ext = $('#extension_select').val();
    $('#extension').val(ext);
    loadSipSecret();
    updateAccountSetupPreview();
}

function loadSipSecret() {
    var ext = $('#extension').val();
    if (!ext) {
        $('#sip_secret_preview').val('');
        updateAccountSetupPreview();
        return;
    }

    // First check if we have a custom secret saved for this device
    var customSecret = $('#custom_sip_secret').val();
    if (customSecret) {
        $('#sip_secret_preview').val(customSecret + ' (Custom)');
        updateAccountSetupPreview();
        return;
    }

    // Otherwise fetch from FreePBX
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
        updateAccountSetupPreview();
    }, 'json');
}

function toggleCustomExtension() {
    var selectWrapper = $('#extension_select_wrapper');
    var customWrapper = $('#extension_custom_wrapper');
    if (customWrapper.is(':visible')) {
        // Switch back to dropdown
        customWrapper.hide();
        selectWrapper.show();
        var ext = $('#extension_select').val();
        $('#extension').val(ext);
        loadSipSecret();
    } else {
        // Switch to custom input
        selectWrapper.hide();
        customWrapper.show();
        $('#extension_custom').focus();
        $('#extension').val($('#extension_custom').val());
        $('#sip_secret_preview').val('');
    }
}

function customExtensionChanged() {
    var ext = $('#extension_custom').val();
    $('#extension').val(ext);
    // Clear secret when custom extension is changed
    $('#sip_secret_preview').val('');
    updateAccountSetupPreview();
}

function toggleCustomSecret() {
    var previewWrapper = $('#secret_preview_wrapper');
    var customWrapper = $('#secret_custom_wrapper');
    if (customWrapper.is(':visible')) {
        // Switch back to auto-fetch
        customWrapper.hide();
        previewWrapper.show();
        loadSipSecret();
    } else {
        // Switch to custom input
        previewWrapper.hide();
        customWrapper.show();
        // Pre-fill with current custom secret if exists
        var customSecret = $('#custom_sip_secret').val();
        if (customSecret) {
            $('#sip_secret_custom_input').val(customSecret);
        } else {
            // Try to get from preview (strip " (Custom)" if present)
            var previewVal = $('#sip_secret_preview').val();
            if (previewVal && !previewVal.includes('Error:')) {
                $('#sip_secret_custom_input').val(previewVal.replace(' (Custom)', ''));
            }
        }
        $('#sip_secret_custom_input').focus();
    }
}

function saveCustomSecret() {
    var secret = $('#sip_secret_custom_input').val().trim();
    if (!secret) {
        alert('Please enter a secret');
        return;
    }

    // Save to hidden field
    $('#custom_sip_secret').val(secret);

    // Show in preview
    $('#sip_secret_preview').val(secret + ' (Custom)');

    // Switch back to preview mode
    $('#secret_custom_wrapper').hide();
    $('#secret_preview_wrapper').show();

    updateAccountSetupPreview();
    alert('Custom secret saved. Click "Save Device" to persist to database.');
}

function copyToClipboard(id) {
    var text = document.getElementById(id).value;
    if (!text) {
        alert('No secret to copy.');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Copied to clipboard!');
        }).catch(function(err) {
            // Fallback: create temporary textarea
            fallbackCopyToClipboard(text);
        });
    } else {
        // Fallback for non-HTTPS or unsupported browsers
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Copied to clipboard!');
    } catch (err) {
        alert('Copy failed. Please copy manually: ' + text);
    }
    document.body.removeChild(textarea);
}

function generateProvPassword() {
    var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    var password = '';
    for (var i = 0; i < 16; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    $('#prov_password').val(password);
}

function loadAssets() {
    $.post('ajax.quickprovisioner.php', {action: 'list_assets', csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            var html = '';
            r.files.forEach(function(file) {
                html += '<div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:15px;">';
                html += '<div class="thumbnail">';
                html += '<img src="assets/uploads/' + file.filename + '" style="width:100%; height:150px; object-fit:cover;">';
                html += '<div class="caption">';
                html += '<p style="font-size:11px; word-break:break-all;">' + file.filename + '</p>';
                html += '<p style="font-size:10px; color:#666;">' + formatFileSize(file.size) + '</p>';
                html += '<button class="btn btn-xs btn-primary" onclick="selectAsset(\'' + file.filename + '\')">Select</button> ';
                html += '<button class="btn btn-xs btn-danger" onclick="deleteAsset(\'' + file.filename + '\')">Delete</button>';
                html += '</div></div></div>';
            });
            $('#assetGrid').html(html);
        }
    }, 'json');
}

function selectAsset(filename) {
    $('#wallpaper').val(filename);
    updateWallpaperPreview(filename);
    renderPreview();
    $('a[href="#tab-edit"]').tab('show');
}

function clearWallpaper() {
    $('#wallpaper').val('');
    $('#wallpaperPreview').hide();
    renderPreview();
}

function updateWallpaperPreview(filename) {
    if (filename) {
        $('#wallpaperPreviewImg').attr('src', 'assets/uploads/' + filename);
        $('#wallpaperPreview').show();
    } else {
        $('#wallpaperPreview').hide();
    }
}

function deleteAsset(filename) {
    if (!confirm('Delete ' + filename + '?')) return;
    $.post('ajax.quickprovisioner.php', {action: 'delete_asset', filename: filename, csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            loadAssets();
        } else {
            alert('Error: ' + r.message);
        }
    }, 'json');
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

function loadContacts() {
    if (!currentDeviceId) {
        $('#contactsList').html('<p>Please save device first.</p>');
        return;
    }
    var html = '<table class="table table-striped"><thead><tr><th>Name</th><th>Number</th><th>Photo</th><th>Actions</th></tr></thead><tbody>';
    currentContacts.forEach(function(c, idx) {
        html += '<tr>';
        html += '<td>' + (c.name || '') + '</td>';
        html += '<td>' + (c.number || '') + '</td>';
        html += '<td>' + (c.photo ? '<img src="assets/uploads/' + c.photo + '" style="width:50px; height:50px; object-fit:cover;">' : 'None') + '</td>';
        html += '<td><button onclick="editContact(' + idx + ')">Edit</button> <button onclick="removeContact(' + idx + ')">Delete</button></td>';
        html += '</tr>';
    });
    html += '</tbody></table>';
    $('#contactsList').html(html);
}

function addContact() {
    $('#contactIndex').text(currentContacts.length);
    $('#contactName').val('');
    $('#contactNumber').val('');
    $('#contactLabel').val('');
    $('#contactPhoto').val('');
    $('#contactModal').modal('show');
}

function editContact(idx) {
    $('#contactIndex').text(idx);
    var c = currentContacts[idx] || {};
    $('#contactName').val(c.name || '');
    $('#contactNumber').val(c.number || '');
    $('#contactLabel').val(c.custom_label || '');
    $('#contactPhoto').val(c.photo || '');
    $('#contactModal').modal('show');
}

function saveContact() {
    var idx = parseInt($('#contactIndex').text());
    var contact = {
        name: $('#contactName').val(),
        number: $('#contactNumber').val(),
        custom_label: $('#contactLabel').val(),
        photo: $('#contactPhoto').val()
    };
    if (idx < currentContacts.length) {
        currentContacts[idx] = contact;
    } else {
        currentContacts.push(contact);
    }
    loadContacts();
    $('#contactModal').modal('hide');
}

function removeContact(idx) {
    if (confirm('Remove this contact?')) {
        currentContacts.splice(idx, 1);
        loadContacts();
    }
}

function clearContact() {
    $('#contactName').val('');
    $('#contactNumber').val('');
    $('#contactLabel').val('');
    $('#contactPhoto').val('');
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

    // Validate extension field
    var extension = $('#extension').val();
    if (!extension) {
        alert('Please select or enter an extension');
        return;
    }

    // Validate required configurable options
    var model = $('#model').val();
    var profile = profiles[model];
    if (profile && profile.configurable_options) {
        var missingRequired = [];
        profile.configurable_options.forEach(function(opt) {
            if (opt.required) {
                var fieldValue = $('[name="custom_options[' + opt.name + ']"]').val();
                if (!fieldValue || fieldValue.trim() === '') {
                    missingRequired.push(opt.label);
                }
            }
        });
        
        if (missingRequired.length > 0) {
            alert('Please fill in the following required fields:\n\n• ' + missingRequired.join('\n• '));
            return;
        }
    }

    // Prepare form data
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

// Update Management Functions
function checkForUpdates() {
    $('#checkUpdatesBtn').prop('disabled', true).text('Checking...');
    $('#updateStatus').hide();
    $('#updateResult').hide();

    $.post('ajax.quickprovisioner.php', {
        action: 'check_updates',
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        $('#checkUpdatesBtn').prop('disabled', false).text('Check for Updates');

        if (r.status) {
            $('#currentCommit').text(r.current_commit.substring(0, 7));
            $('#updateStatus').show();

            if (r.has_updates) {
                $('#updateStatusMessage').html('<div class="alert alert-info"><strong>⬆️ Updates Available!</strong><br>New version available: ' + r.remote_commit.substring(0, 7) + '</div>');
                loadChangelog(r.current_commit, r.remote_commit);
            } else {
                $('#updateStatusMessage').html('<div class="alert alert-success"><strong>✅ Up to Date</strong><br>You are running the latest version.</div>');
                $('#changelogSection').hide();
            }
        } else {
            $('#updateStatus').show();
            $('#updateStatusMessage').html('<div class="alert alert-danger"><strong>Error:</strong> ' + (r.message || 'Failed to check for updates') + '</div>');
        }
    }, 'json').fail(function() {
        $('#checkUpdatesBtn').prop('disabled', false).text('Check for Updates');
        $('#updateStatus').show();
        $('#updateStatusMessage').html('<div class="alert alert-danger"><strong>Error:</strong> Failed to check for updates. Please try again.</div>');
    });
}

function loadChangelog(currentCommit, remoteCommit) {
    $.post('ajax.quickprovisioner.php', {
        action: 'get_changelog',
        current_commit: currentCommit,
        remote_commit: remoteCommit,
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status && r.commits && r.commits.length > 0) {
            var html = '';
            r.commits.forEach(function(commit) {
                var date = new Date(commit.date);
                var timeAgo = formatTimeAgo(date);
                html += '<div class="list-group-item">';
                html += '<strong>' + commit.hash.substring(0, 7) + '</strong> - ' + escapeHtml(commit.message);
                html += '<br><small class="text-muted">' + escapeHtml(commit.author) + ', ' + timeAgo + '</small>';
                html += '</div>';
            });
            $('#changelogList').html(html);
            $('#changelogSection').show();
        } else {
            $('#changelogList').html('<div class="list-group-item">No changelog available</div>');
            $('#changelogSection').show();
        }
    }, 'json').fail(function() {
        $('#changelogList').html('<div class="list-group-item text-danger">Failed to load changelog</div>');
        $('#changelogSection').show();
    });
}

function performUpdate() {
    if (!confirm('Are you sure you want to update? This will pull the latest changes from GitHub.')) {
        return;
    }

    $('#confirmUpdateBtn').prop('disabled', true).text('Updating...');
    $('#changelogSection').hide();
    $('#updateStatusMessage').html('<div class="alert alert-info">Updating... Please wait...</div>');

    $.post('ajax.quickprovisioner.php', {
        action: 'perform_update',
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        $('#confirmUpdateBtn').prop('disabled', false).text('Yes, Update Now');

        if (r.status) {
            var msg = '<div class="alert alert-success">';
            msg += '<strong>✅ Updated successfully!</strong><br>';
            msg += r.old_commit.substring(0, 7) + ' → ' + r.new_commit.substring(0, 7);
            if (r.new_version) {
                msg += '<br>New version: ' + r.new_version;
            }
            msg += '<br><br>' + escapeHtml(r.message || 'Please refresh the page to see changes.');
            msg += '</div>';
            $('#updateResult').html(msg).show();
            $('#updateStatus').hide();

            // Update current commit display
            $('#currentCommit').text(r.new_commit.substring(0, 7));
            if (r.new_version) {
                $('#currentVersion').text(r.new_version);
            }
        } else {
            $('#updateResult').html('<div class="alert alert-danger"><strong>Error:</strong> ' + escapeHtml(r.message || 'Update failed') + '</div>').show();
            $('#changelogSection').show();
        }
    }, 'json').fail(function() {
        $('#confirmUpdateBtn').prop('disabled', false).text('Yes, Update Now');
        $('#updateResult').html('<div class="alert alert-danger"><strong>Error:</strong> Update request failed. Please try again.</div>').show();
        $('#changelogSection').show();
    });
}

function cancelUpdate() {
    $('#changelogSection').hide();
    $('#updateStatus').hide();
}

function formatTimeAgo(date) {
    var seconds = Math.floor((new Date() - date) / 1000);
    var intervals = {
        year: 31536000,
        month: 2592000,
        week: 604800,
        day: 86400,
        hour: 3600,
        minute: 60
    };

    for (var key in intervals) {
        var interval = Math.floor(seconds / intervals[key]);
        if (interval >= 1) {
            return interval + ' ' + key + (interval > 1 ? 's' : '') + ' ago';
        }
    }
    return 'just now';
}

function escapeHtml(text) {
    var map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// PBX Control Functions
function reloadPBX() {
    if (!confirm('Apply configuration changes? This will not interrupt calls.')) return;

    $('#pbxStatus').html('<i class="fa fa-spinner fa-spin"></i> Reloading...');

    $.post('ajax.quickprovisioner.php', {
        action: 'restart_pbx',
        type: 'reload',
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status) {
            $('#pbxStatus').html('<span class="text-success"><i class="fa fa-check"></i> ' + r.message + '</span>');
        } else {
            $('#pbxStatus').html('<span class="text-danger"><i class="fa fa-times"></i> ' + r.message + '</span>');
        }
    }, 'json');
}

function restartPBX() {
    if (!confirm('Are you sure you want to restart the PBX?\n\nThis will briefly interrupt any active calls!')) return;

    $('#pbxStatus').html('<i class="fa fa-spinner fa-spin"></i> Restarting PBX...');

    $.post('ajax.quickprovisioner.php', {
        action: 'restart_pbx',
        type: 'restart',
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status) {
            $('#pbxStatus').html('<span class="text-success"><i class="fa fa-check"></i> ' + r.message + '</span>');
        } else {
            $('#pbxStatus').html('<span class="text-danger"><i class="fa fa-times"></i> ' + r.message + '</span>');
        }
    }, 'json');
}

// Load current commit on page load
$(document).ready(function() {
    $.post('ajax.quickprovisioner.php', {
        action: 'check_updates',
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        if (r.status && r.current_commit) {
            $('#currentCommit').text(r.current_commit.substring(0, 7));
        }
    }, 'json');
});

// === NEW FUNCTIONS FOR UI RESTRUCTURE ===

// Update template header when model is loaded
function updateTemplateHeader() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!model) {
        $('#templateHeader').text('Select a model to load template settings');
        return;
    }
    if (profile && profile.display_name) {
        $('#templateHeader').html('<i class="fa fa-check-circle text-success"></i> ' + profile.display_name + ' Template Loaded');
    } else {
        $('#templateHeader').text('Loading template...');
    }
}

// Render Handset Settings tab
function renderHandsetSettings() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!profile) {
        $('#handsetSettingsContent').html('<p class="text-muted">Select a model to view handset settings.</p>');
        return;
    }
    
    var html = '';
    
    // Section 1: Auto Provisioning
    html += '<h4>1. Auto Provisioning</h4>';
    html += '<div class="well">';
    html += '<div class="form-group">';
    html += '<label>Auto Provisioning Server URL</label>';
    html += '<input type="text" class="form-control" id="auto_prov_url" placeholder="http://voiphillhotel.duckdns.org">';
    html += '<small class="text-muted">Pre-filled from FreePBX domain, editable</small>';
    html += '</div>';
    html += '<div class="form-group">';
    html += '<label>auto_provision.mode</label>';
    html += '<select class="form-control" id="auto_prov_mode">';
    html += '<option value="0">0 - Disabled</option>';
    html += '<option value="1">1 - Power on only</option>';
    html += '<option value="2">2 - Repeatedly</option>';
    html += '<option value="3">3 - Power on + Repeatedly</option>';
    html += '</select>';
    html += '<small class="text-muted">Select provisioning mode for the device</small>';
    html += '</div>';
    html += '</div>';
    
    // Section 2: Account Setup (Live Preview)
    html += '<h4>2. Account Setup (Live Preview)</h4>';
    html += '<div class="well" style="background:#f9f9f9;">';
    html += '<pre id="accountSetupPreview" style="background:white; padding:10px; border:1px solid #ddd;">Loading...</pre>';
    html += '<small class="text-muted">Updates live as you change left column values</small>';
    html += '</div>';
    
    // Section 3: SIP Server
    html += '<h4>3. SIP Server Configuration</h4>';
    html += '<div class="well">';
    html += '<div class="form-group">';
    html += '<label>account.1.sip_server.1.address</label>';
    html += '<input type="text" class="form-control" id="sip_server_address" placeholder="voiphillhotel.duckdns.org">';
    html += '<small class="text-muted">Suggest FreePBX domain</small>';
    html += '</div>';
    html += '<div class="form-group">';
    html += '<label>account.1.sip_server.1.port</label>';
    html += '<input type="number" class="form-control" id="sip_server_port" value="5060">';
    html += '</div>';
    html += '<div class="form-group">';
    html += '<label>account.1.sip_server.1.transport_type</label>';
    html += '<select class="form-control" id="sip_transport_type">';
    html += '<option value="0">0 - UDP</option>';
    html += '<option value="1">1 - TCP</option>';
    html += '<option value="2">2 - TLS</option>';
    html += '</select>';
    html += '</div>';
    html += '<small class="text-muted">Can customize for remote scenarios using different port/server</small>';
    html += '</div>';
    
    // Section 4: Template Configurable Options
    if (profile.configurable_options && profile.configurable_options.length > 0) {
        html += '<h4>4. Template Configurable Options</h4>';
        html += '<div class="well">';
        html += '<div id="deviceOptions">';
        profile.configurable_options.forEach(function(opt) {
            html += '<div class="form-group">';
            html += '<label title="' + (opt.description || '') + '">';
            html += opt.label;
            if (opt.required) {
                html += ' <span class="text-danger">*</span>';
            }
            html += '</label>';
            
            if (opt.type === 'bool') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"' + (opt.required ? ' required' : '') + '>';
                html += '<option value="">Default (' + opt.default + ')</option>';
                html += '<option value="1">On</option>';
                html += '<option value="0">Off</option>';
                html += '</select>';
            } else if (opt.type === 'select') {
                html += '<select name="custom_options[' + opt.name + ']" class="form-control"' + (opt.required ? ' required' : '') + '>';
                html += '<option value="">Default (' + opt.default + ')</option>';
                for (var val in opt.options) {
                    html += '<option value="' + val + '">' + opt.options[val] + '</option>';
                }
                html += '</select>';
            } else if (opt.type === 'number') {
                html += '<input type="number" name="custom_options[' + opt.name + ']" class="form-control" ';
                html += 'min="' + (opt.min || '') + '" max="' + (opt.max || '') + '" ';
                html += 'placeholder="Default: ' + opt.default + '"' + (opt.required ? ' required' : '') + '>';
            } else {
                html += '<input type="text" name="custom_options[' + opt.name + ']" class="form-control" ';
                html += 'placeholder="Default: ' + opt.default + '"' + (opt.required ? ' required' : '') + '>';
            }
            
            if (opt.description) {
                html += '<small class="help-block text-muted">' + opt.description + '</small>';
            }
            html += '</div>';
        });
        html += '</div>';
        html += '</div>';
    }
    
    $('#handsetSettingsContent').html(html);
    updateAccountSetupPreview();
}

// Update Account Setup Live Preview
function updateAccountSetupPreview() {
    var ext = $('#extension').val();
    var secret = $('#sip_secret_preview').val();
    var displayName = ext; // Can be enhanced to fetch actual display name
    
    var preview = 'account.1.enable = 1\n';
    preview += 'account.1.label = ' + displayName + '\n';
    preview += 'account.1.display_name = ' + displayName + '\n';
    preview += 'account.1.auth_name = ' + ext + '\n';
    preview += 'account.1.user_name = ' + ext + '\n';
    preview += 'account.1.password = ' + (secret ? secret.replace(' (Custom)', '') : '[Not set]');
    
    $('#accountSetupPreview').text(preview);
}

// Render Phone Assets tab
function renderPhoneAssets() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!profile) {
        $('#phoneAssetsContent').html('<p class="text-muted">Select a model to manage phone assets.</p>');
        return;
    }
    
    var screenWidth = profile.visual_editor?.screen_width || 800;
    var screenHeight = profile.visual_editor?.screen_height || 480;
    
    var html = '';
    
    // Upload Section
    html += '<h4>Upload Wallpaper</h4>';
    html += '<div class="well">';
    html += '<div style="border:2px dashed #ddd; padding:30px; text-align:center; background:#f9f9f9; margin-bottom:15px;">';
    html += '<i class="fa fa-upload" style="font-size:48px; color:#999;"></i>';
    html += '<p style="margin-top:10px;">Drag & Drop or Click to Upload</p>';
    html += '<p class="text-muted">Target dimensions: ' + screenWidth + ' x ' + screenHeight + '</p>';
    html += '<p class="text-muted">Supported formats: JPG, PNG | Max file size: 5MB</p>';
    html += '<label class="btn btn-default">';
    html += '<input type="file" id="wallpaperUploadInput" accept="image/jpeg,image/png" style="display:none;">';
    html += '<i class="fa fa-folder-open"></i> Choose File';
    html += '</label>';
    html += '</div>';
    html += '</div>';
    
    // Current Wallpaper Display
    html += '<h4>Current Wallpaper</h4>';
    html += '<div class="well">';
    html += '<div class="form-group">';
    html += '<label>Wallpaper</label>';
    html += '<div class="input-group">';
    html += '<input type="text" id="wallpaper" name="wallpaper" class="form-control" readonly placeholder="No wallpaper selected">';
    html += '<span class="input-group-btn">';
    html += '<button type="button" class="btn btn-default" onclick="clearWallpaper()" title="Clear wallpaper">';
    html += '<i class="fa fa-times"></i>';
    html += '</button>';
    html += '</span>';
    html += '</div>';
    html += '<div id="wallpaperPreview" style="margin-top:10px; display:none;">';
    html += '<img id="wallpaperPreviewImg" style="max-width:100%; max-height:200px; border:1px solid #ccc; border-radius:4px;">';
    html += '</div>';
    html += '</div>';
    html += '<div class="form-group">';
    html += '<label>Wallpaper Mode</label>';
    html += '<select id="wallpaper_mode" name="wallpaper_mode" class="form-control" onchange="renderPreview()">';
    html += '<option value="crop">Crop to Fill (recommended)</option>';
    html += '<option value="fit">Fit (Letterbox - may show black bars)</option>';
    html += '</select>';
    html += '<small class="text-muted">Crop mode fills the entire screen by cropping edges</small>';
    html += '</div>';
    html += '</div>';
    
    // Custom URL Option
    html += '<h4>Or Use Custom URL</h4>';
    html += '<div class="well">';
    html += '<div class="input-group">';
    html += '<input type="text" class="form-control" id="wallpaper_custom_url" placeholder="http://example.com/wallpaper.jpg">';
    html += '<span class="input-group-btn">';
    html += '<button class="btn btn-primary" onclick="useCustomWallpaperUrl()">Use URL</button>';
    html += '</span>';
    html += '</div>';
    html += '</div>';
    
    // Asset Gallery
    html += '<h4>Available Assets</h4>';
    html += '<div id="assetGallery" class="row"></div>';
    
    $('#phoneAssetsContent').html(html);
    
    // Load assets into gallery
    loadAssetsIntoGallery();
    
    // Setup file upload handler
    $('#wallpaperUploadInput').on('change', function() {
        uploadWallpaperAsset();
    });
}

// Load assets into gallery
function loadAssetsIntoGallery() {
    $.post('ajax.quickprovisioner.php', {action: 'list_assets', csrf_token: '<?= $csrf_token ?>'}, function(r) {
        if (r.status) {
            var html = '';
            r.files.forEach(function(file) {
                html += '<div class="col-xs-6 col-sm-4 col-md-3" style="margin-bottom:15px;">';
                html += '<div class="thumbnail">';
                html += '<img src="assets/uploads/' + file.filename + '" style="width:100%; height:150px; object-fit:cover;">';
                html += '<div class="caption">';
                html += '<p style="font-size:11px; word-break:break-all; margin-bottom:5px;">' + file.filename + '</p>';
                html += '<p style="font-size:10px; color:#666; margin-bottom:10px;">' + formatFileSize(file.size) + '</p>';
                html += '<button class="btn btn-xs btn-primary btn-block" onclick="selectAssetForWallpaper(\'' + file.filename + '\')">Select</button>';
                html += '</div></div></div>';
            });
            $('#assetGallery').html(html || '<p class="text-muted col-xs-12">No assets uploaded yet.</p>');
        }
    }, 'json');
}

// Select asset for wallpaper
function selectAssetForWallpaper(filename) {
    $('#wallpaper').val(filename);
    updateWallpaperPreview(filename);
    renderPreview();
    // Switch to Button Layout tab to see the preview
    $('a[href="#subtab-layout"]').tab('show');
}

// Use custom wallpaper URL
function useCustomWallpaperUrl() {
    var url = $('#wallpaper_custom_url').val().trim();
    if (!url) {
        alert('Please enter a URL');
        return;
    }
    $('#wallpaper').val(url);
    updateWallpaperPreview(url);
    renderPreview();
    $('a[href="#subtab-layout"]').tab('show');
}

// Upload wallpaper asset
function uploadWallpaperAsset() {
    var file = $('#wallpaperUploadInput')[0].files[0];
    if (!file) return;
    
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
                alert('Asset uploaded successfully!');
                selectAssetForWallpaper(r.url);
                loadAssetsIntoGallery();
            } else {
                alert('Error: ' + r.message);
            }
        }
    });
}

// Render Button Layout tab
function renderButtonLayout() {
    var model = $('#model').val();
    var profile = profiles[model];
    if (!profile) {
        $('#buttonLayoutContent').html('<p class="text-muted">Select a model to view button layout.</p>');
        return;
    }
    
    updatePageSelect();
    renderPreview();
}

// Generate Provisioning File
function generateProvisioningFile() {
    if (!currentDeviceId) {
        alert('Please save the device first before generating provisioning file.');
        return;
    }
    
    // Show loading state
    var btn = $('button[onclick="generateProvisioningFile()"]');
    var originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');
    
    $.post('ajax.quickprovisioner.php', {
        action: 'generate_config',
        id: currentDeviceId,
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        btn.prop('disabled', false).html(originalText);
        
        if (r.status) {
            $('#generatedConfig').val(r.config);
            $('#generatedFilename').text(r.filename);
            $('#deployFilename').text(r.filename);
            $('#generateModal').modal('show');
        } else {
            alert('Error generating config: ' + (r.message || 'Unknown error'));
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html(originalText);
        alert('Failed to generate config. Please try again.');
    });
}

// Download Config
function downloadConfig() {
    var config = $('#generatedConfig').val();
    var filename = $('#generatedFilename').text();
    var blob = new Blob([config], {type: 'text/plain'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

// Deploy Config
function deployConfig() {
    if (!confirm('Deploy this configuration to the server?')) return;
    
    var btn = $('button[onclick="deployConfig()"]');
    var originalText = btn.html();
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deploying...');
    
    $.post('ajax.quickprovisioner.php', {
        action: 'deploy_config',
        id: currentDeviceId,
        csrf_token: '<?= $csrf_token ?>'
    }, function(r) {
        btn.prop('disabled', false).html(originalText);
        
        if (r.status) {
            alert('Config deployed successfully to:\n' + r.path);
            $('#deployPath').text(r.path);
        } else {
            alert('Error deploying config: ' + (r.message || 'Unknown error'));
        }
    }, 'json').fail(function() {
        btn.prop('disabled', false).html(originalText);
        alert('Failed to deploy config. Please try again.');
    });
}

// Copy Generated Config to Clipboard
function copyGeneratedToClipboard() {
    var text = $('#generatedConfig').val();
    if (!text) {
        alert('No config to copy.');
        return;
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function() {
            alert('Config copied to clipboard!');
        }).catch(function(err) {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

</script>
<?php
?>
