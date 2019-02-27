<?php
    global $path;
?>

<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/muc_dialog.js"></script>

<style>
    #ctrl-config-modal table th {
        text-align: left;
        font-weight: normal;
        color: #888;
    }
    #ctrl-config-modal table td:nth-of-type(1),
    #ctrl-config-modal table td:nth-of-type(2) {
        padding-right:8px;
    }

    /* For Firefox */
    #ctrl-config-modal input[type=number] {
        -moz-appearance: textfield;
    }

    /* Webkit browsers like Safari and Chrome */
    #ctrl-config-modal input[type=number]::-webkit-inner-spin-button,
    #ctrl-config-modal input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0px;
    }
</style>

<div id="ctrl-config-modal" class="modal hide keyboard" tabindex="-1" role="dialog" aria-labelledby="ctrl-config-label" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="ctrl-config-label"><?php echo _('New Controller'); ?></h3>
    </div>
    <div id="ctrl-config-body" class="modal-body">
        <p style="margin-bottom: 18px; color: #888">
            <em><?php echo _('Multi Utility Communications (MUC) controller handle the communication protocols to a variety of devices and are the main entry point to configure metering units.'); ?>
            <br><br> 
            <?php echo _('controllers register several protocol drivers and are needed to configure their parameters. '); ?>
            <?php echo _('Several controllers may be added and configured, but it is recommended to use the local platform, if geographically possible.'); ?></em>
        </p>
        
        <table>
            <tr>
                <th></th>
                <th><?php echo _('Name'); ?></th>
                <th><?php echo _('Description'); ?></th>
            </tr>
            <tr>
                <td>
                    <select id="ctrl-config-type" class="input-small">
                        <option value=http><?php echo _('HTTP'); ?></option>
                        <option value=https><?php echo _('HTTPS'); ?></option>
                        <option value=redis><?php echo _('Redis'); ?></option>
                    </select>
                </td>
                <td><input id="ctrl-config-name" class="input-medium" type="text" pattern="[a-zA-Z0-9-_.:/ ]+" required></td>
                <td><input id="ctrl-config-description" class="input-large" type="text"></td>
            </tr>
        </table>
        <h4 style="padding-top:20px;"><?php echo _('Options'); ?></h4>
        <div id="ctrl-config-options"></div>
        
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
        <p><?php echo _('Deleting a Multi Utility Communication (MUC) controller is permanent.'); ?>
            <br><br>
            <?php echo _('If this controller is active and is registered, it will no longer be able to retrieve configurations. '); ?>
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
