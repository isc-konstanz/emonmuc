<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/muc_dialog.js"></script>

<div id="ctrl-config-modal" class="modal hide keyboard" tabindex="-1" role="dialog" aria-labelledby="ctrl-config-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="ctrl-config-label"><?php echo _('New Controller'); ?></h3>
    </div>
    <div id="ctrl-config-body" class="modal-body">
        <p style="margin-bottom: 18px;">
            <em><?php echo _('Multi Utility Communications (MUC) controller handle the communication protocols to a variety of devices and are the main entry point to configure metering units.'); ?>
            <br><br> 
            <?php echo _('A MUC controller registers several drivers (see the drivers tab) and is needed to configure their parameters.'); ?><br> 
            <?php echo _('Several MUC controllers may be added and configured, but it is recommended to use the local platform, if geographically possible.'); ?></em>
        </p>
        
        <label><?php echo _('Address: '); ?></label>
        <span>
            <select id="ctrl-config-type" class="input-small">
                <option value=HTTP><?php echo _('HTTP'); ?></option>
                <option value=HTTPS><?php echo _('HTTPS'); ?></option>
                <option value=MQTT><?php echo _('MQTT'); ?></option>
            </select>
            <input id="ctrl-config-address" type="text" value="localhost">
        </span>
        <label><?php echo _('Location description: '); ?></label>
        <input id="ctrl-config-description" type="text" value="Local">
        
        <div id="ctrl-config-loader" class="ajax-loader" style="display:none;"></div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="ctrl-config-save" class="btn btn-primary"><?php echo _('Save'); ?></button>
    </div>
</div>

<div id="ctrl-modal-delete" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="ctrl-delete-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="ctrl-modal-label"><?php echo _('Delete Controller'); ?></h3>
    </div>
    <div id="ctrl-delete-body" class="modal-body">
        <p><?php echo _('Deleting a Multi Utility Communication controller is permanent.'); ?>
            <br><br>
            <?php echo _('If this MUC controller is active and is registered, it will no longer be able to retrieve the configuration. '); ?>
            <?php echo _('All corresponding drivers and configurations will be removed, while feeds and all historic data is kept. '); ?>
            <?php echo _('To remove it, delete them manually afterwards.'); ?>
            <br><br>
            <?php echo _('Are you sure you want to proceed?'); ?>
        </p>
        <div id="ctrl-delete-loader" class="ajax-loader" style="display:none;"></div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="ctrl-delete-confirm" class="btn btn-primary"><?php echo _('Delete permanently'); ?></button>
    </div>
</div>
