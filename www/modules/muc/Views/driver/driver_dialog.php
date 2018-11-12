<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/driver/driver_dialog.js"></script>

<div id="driver-config-modal" class="modal hide keyboard modal-adjust" tabindex="-1" role="dialog" aria-labelledby="driver-config-modal" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="driver-config-label"></h3>
    </div>
    <div id="driver-config-body" class="modal-body">
        <div id="driver-config-ctrl" style="display:none">
            <label><?php echo _('Controller to register driver for: '); ?></label>
            <select id="driver-config-ctrl-select" class="input-large"></select>
        </div>
        
        <h4 id="driver-config-header"><?php echo _('Driver'); ?></h4>
        
        <select id="driver-config-select" class="input-large" style="display:none" disabled></select>
        <p id="driver-config-description"></p>
        
        <div id="driver-config-container"></div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="driver-config-delete" class="btn btn-danger" style="display:none; cursor:pointer;"><i class="icon-trash icon-white"></i> <?php echo _('Delete'); ?></button>
        <button id="driver-config-save" class="btn btn-primary"><?php echo _('Save'); ?></button>
    </div>
    <div id="driver-config-loader" class="ajax-loader" style="display:none"></div>
</div>

<div id="driver-delete-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="driver-delete-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="driver-delete-label"></h3>
    </div>
    <div id="driver-delete-body" class="modal-body">
        <p>
            <?php echo _('Deleting a driver is permanent.'); ?>
        </p>
        <p style="color:#999">
            <?php echo _('All corresponding devices, channels and configurations will be removed, while inputs, feeds and all historic data is kept. '); ?>
            <?php echo _('To remove those, delete them manually afterwards.'); ?>
        </p>
        <p>
            <?php echo _('Are you sure you want to proceed?'); ?>
        </p>
        <div id="driver-delete-loader" class="ajax-loader" style="display:none;"></div>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="driver-delete-confirm" class="btn btn-primary"><?php echo _('Delete permanently'); ?></button>
    </div>
</div>

<script>
    $(window).resize(function(){
        driver_dialog.adjustConfig();
    });
</script>
