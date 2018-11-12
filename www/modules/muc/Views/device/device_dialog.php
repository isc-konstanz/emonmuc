<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/device/device_dialog.js"></script>

<style>
    #device-config-header th {
        text-align: left;
        font-weight: normal;
        color: #888;
    }
    #device-config-header td:nth-of-type(1),
    #device-config-header td:nth-of-type(2) { padding-right:8px; }

    #device-scan-progress {
        margin-top:-15px;
        margin-left:-15px;
        margin-right:-15px;
        -webkit-border-radius: 0px;
           -moz-border-radius: 0px;
                border-radius: 0px;
    }
</style>

<div id="device-config-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-config-modal" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-config-label"></h3>
    </div>
    <div id="device-config-body" class="modal-body">
        <table id="device-config-header">
            <tr>
                <th><?php echo _('Driver'); ?></th>
                <th><?php echo _('Name'); ?></th>
                <th><?php echo _('Description'); ?></th>
            </tr>
            <tr>
                <td>
                    <label id="device-config-driver" style="padding: 4px 6px; margin-bottom: 10px;"><span style="color:#888"><em><?php echo _('loading...'); ?></em></span></label>
                    <select id="device-config-driver-select" class="input-medium" style="display:none;"></select>
                </td>
                <td><input id="device-config-name" class="input-medium" type="text" required></td>
                <td><input id="device-config-description" class="input-large" type="text"></td>
            </tr>
        </table>
        <p id="device-config-info" style="display:none;"></p>
        
        <div id="device-config-container"></div>
    </div>
    <div class="modal-footer">
        <button id="device-config-back" class="btn" style="display:none; float:left"><?php echo _('Back'); ?></button>
        <button id="device-config-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-config-delete" class="btn btn-danger" style="display:none"><i class="icon-trash icon-white"></i> <?php echo _('Delete'); ?></button>
        <button id="device-config-scan" class="btn btn-info" style="display:none"><i class="icon-search icon-white"></i> <?php echo _('Scan'); ?></button>
        <button id="device-config-save" class="btn btn-primary"><?php echo _('Save'); ?></button>
    </div>
    <div id="device-config-loader" class="ajax-loader" style="display:none"></div>
</div>

<div id="device-scan-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="device-scan-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-scan-label"><?php echo _('Scan Devices'); ?></h3>
    </div>
    <div id="device-scan-body" class="modal-body">
        <div id="device-scan-progress" class="progress progress-default progress-striped active" style="display:none;">
            <div id="device-scan-progress-bar" class="bar" style="width:100%;"></div>
        </div>
        <div id="device-scan-driver">
            <label style="color: #888"><?php echo _('Driver to search devices for: '); ?></label>
            <select id="device-scan-driver-select" class="input-large"></select>
        </div>
        <p id="device-scan-info"></p>
        
        <div class="modal-container">
            <ul id="device-scan-results" class="scan-result" style="display:none"></ul>
            <div id="device-scan-results-none" class="alert" style="display:none"><?php echo _('No devices found'); ?></div>
            
            <div id="device-scan-container"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button id="device-scan-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-scan-start" class="btn btn-primary" style="border-radius: 4px;"><?php echo _('Scan'); ?></button>
    </div>
    <div id="device-scan-loader" class="ajax-loader" style="display:none"></div>
</div>

<div id="device-delete-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="device-delete-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="device-delete-label"></h3>
    </div>
    <div id="device-delete-body" class="modal-body">
        <p>
            <?php echo _('Deleting a device is permanent.'); ?>
        </p>
        <p style="color:#999">
            <?php echo _('All corresponding channels and configurations will be removed, while inputs, feeds and all historic data is kept. '); ?>
            <?php echo _('To remove those, delete them manually afterwards.'); ?>
        </p>
        <p>
            <?php echo _('Are you sure you want to proceed?'); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button id="device-delete-cancel" class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="device-delete-confirm" class="btn btn-primary"><?php echo _('Delete permanently'); ?></button>
    </div>
    <div id="device-delete-loader" class="ajax-loader" style="display:none;"></div>
</div>

<script>
    $(window).resize(function(){
        device_dialog.adjustConfig();
        device_dialog.adjustScan();
    });
</script>
