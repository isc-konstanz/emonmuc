<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/muc/Views/muc.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Lib/tablejs/titatoggle-dist-min.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/table.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/custom-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/tablejs/muc-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/configjs/config.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/channel/channel.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/device/device.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/muc.js"></script>

<style>
    #table input[type="text"] {
        width: 88%;
    }
    #table td:nth-of-type(1) { width:14px; text-align: center; }
    #table td:nth-of-type(2) { width:10%;}
    #table td:nth-of-type(3) { width:10%;}
    #table td:nth-of-type(4) { width:10%;}
    #table th:nth-of-type(5) { font-weight:normal; }
    #table td:nth-of-type(6), th:nth-of-type(6) { text-align: right; }
    #table th[fieldg="channels"] { font-weight:normal; }
    #table th[fieldg="state"] { font-weight:normal; text-align: right; }
    #table td:nth-of-type(7) { width:14px; text-align: center; }
    #table td:nth-of-type(8) { width:14px; text-align: center; }
    #table td:nth-of-type(9) { width:14px; text-align: center; }
    #table td:nth-of-type(10) { width:14px; text-align: center; }
    #table th[fieldg="dummy-7"] { width:14px; text-align: center; }
    #table th[fieldg="dummy-8"] { width:14px; text-align: center; }
    #table th[fieldg="dummy-9"] { width:14px; text-align: center; }
    #table th[fieldg="dummy-10"] { width:14px; text-align: center; }
</style>

<div class="view-container">
	<div id="api-help-header" style="float:right;"><a href="api"><?php echo _('Device API Help'); ?></a></div>
    <div id="device-header"><h2><?php echo _('Device connections'); ?></h2></div>

    <div id="table"></div>

    <div id="device-none" class="alert alert-block hide">
        <h4 class="alert-heading"><?php echo _('No device connections'); ?></h4><br>
        <p>
            <?php echo _('Device connections are used to configure and prepare the communication with different metering units.'); ?>
            <br><br>
            <?php echo _('A device configures and prepares inputs, feeds possible device channels, representing e.g. different registers of defined metering units (see the channels tab).'); ?>
            <br>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Device API helper'); ?></a>
        </p>
    </div>

    <div id="toolbar_bottom"><hr>
        <button id="device-new" class="btn btn-primary btn-small" >&nbsp;<i class="icon-plus-sign icon-white" ></i>&nbsp;<?php echo _('New device'); ?></button>
        <button id="device-scan" class="btn btn-info btn-small" >&nbsp;<i class="icon-search icon-white" ></i>&nbsp;<?php echo _('Scan devices'); ?></button>
    </div>
    
    <div id="device-loader" class="ajax-loader"></div>
</div>

<?php require "Modules/muc/Views/device/device_dialog.php"; ?>
<?php require "Modules/muc/Views/channel/channel_dialog.php"; ?>

<script>
    var path = "<?php echo $path; ?>";
    
    // Extend table library field types
    for (z in muctablefields) table.fieldtypes[z] = muctablefields[z];
    for (z in customtablefields) table.fieldtypes[z] = customtablefields[z];
    table.element = "#table";
    table.groupprefix = "Driver ";
    table.groupby = 'driver';
    table.groupfields = {
        'dummy-4':{'title':'', 'type':"blank"},
        'channels':{'title':'<?php echo _("Channels"); ?>','type':"group-channellist"},
        'state':{'title':'<?php echo _('State'); ?>', 'type':"group-state"},
        'dummy-7':{'title':'', 'type':"blank"},
        'dummy-8':{'title':'', 'type':"blank"},
        'dummy-9':{'title':'', 'type':"blank"},
        'dummy-10':{'title':'', 'type':"blank"}
    }
    
    table.deletedata = false;
    table.fields = {
        'disabled':{'title':'', 'type':"disable"},
        'driver':{'title':'<?php echo _("Driver"); ?>','type':"fixed"},
        'id':{'title':'<?php echo _("Name"); ?>','type':"text"},
        'description':{'title':'<?php echo _('Description'); ?>','type':"text"},
        'channels':{'title':'<?php echo _("Channels"); ?>','type':"channellist"},
        'state':{'title':'<?php echo _("State"); ?>', 'type':"state"},
        // Actions
        'add-action':{'title':'', 'type':"icon-enabled", 'icon':'icon-plus-sign'},
        'scan-action':{'title':'', 'type':"icon-enabled", 'icon':'icon-search'},
//         'edit-action':{'title':'', 'type':"edit"},
        'delete-action':{'title':'', 'type':"delete"},
        'config-action':{'title':'', 'type':"iconconfig", 'icon':'icon-wrench'}
    }

    update();

    channel.states = null;
    function update() {
        device.list(function(data, textStatus, xhr) {
            table.data = data;

            table.draw();
            if (table.data.length != 0) {
                $("#device-none").hide();
                $("#device-header").show();
                $("#api-help-header").show();
            } else {
                $("#device-none").show();
                $("#device-header").hide();
                $("#api-help-header").hide();
            }
            $('#device-loader').hide();
        });

        channel.listStates(function(data, textStatus, xhr) {
            // Set the channel states for the labels to be colored correctly
            channel.states = data;
        });
    }

    var updater;
    function updaterStart(func, interval) {
        
        clearInterval(updater);
        updater = null;
        if (interval > 0) updater = setInterval(func, interval);
    }
    updaterStart(update, 5000);

    $("#table").bind("onEdit", function(e) {
        
        updaterStart(update, 0);
    });

    $("#table").bind("onDisable", function(e,id,row,disable) {
        // Get device of clicked row
        var configs = table.data[row];
        
        $('#device-loader').show();
        device.update(configs['ctrlid'], configs['id'], configs, function(result) {
            $('#device-loader').hide();
            
            if (!result.success) {
                alert('Unable to update device:\n'+result.message);
                return false;
            }
            update();
        });
    });

    $("#table").bind("onResume", function(e) {
        
        updaterStart(update, 5000);
    });

    $("#table").bind("onDelete", function(e,id,row) {
        // Get device of clicked row
        var device = table.data[row];
        
        device_dialog.loadDelete(device, row);
    });

    $("#table").on('click', '.icon-wrench', function() {
        // Get device of clicked row
        var device = table.data[$(this).attr('row')];
        
        device_dialog.loadConfig(device);
    });

    $("#table").on('click', '.icon-search', function() {
        // Get device of clicked row
        var device = table.data[$(this).attr('row')];

        channel_dialog.loadScan(device);
    });

    $("#table").on('click', '.icon-plus-sign', function() {
        // Do not open dialog if the icon-plus-sign is used on a group header
        if(!$(this).attr('group')) {
            // Get device of clicked row
            var device = table.data[$(this).attr('row')];

            channel_dialog.loadNew(device);
        }
    });

    $("#table").on('click', '.channel-label', function() {
        // Get the ids of the clicked lable
        var ctrlid = $(this).attr('ctrlid');
        var channelid = $(this).attr('channelid');

        $('#device-loader').show();
        channel.get(ctrlid, channelid, function(result) {
            $('#device-loader').hide();
            
            channel_dialog.loadConfig(result);
        });
    });

    $("#device-new").on('click', function () {
        
        device_dialog.loadNew();
    });

    $("#device-scan").on('click', function () {
        
        device_dialog.loadScan();
    });
</script>
