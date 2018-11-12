<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/muc/Views/muc.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Lib/tablejs/titatoggle-dist-min.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/table.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/custom-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/tablejs/muc-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/configjs/config.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/device/device.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/driver/driver.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/muc.js"></script>

<style>
    #table input[type="text"] {
        width: 88%;
    }
    #table td:nth-of-type(1) { width:14px; text-align: center; }
    #table td:nth-of-type(2) { width:10%;}
    #table td:nth-of-type(3) { width:10%;}
    #table th:nth-of-type(4), td:nth-of-type(4) { font-weight:normal; }
    #table th[fieldg="devices"] { font-weight:normal; }
    #table td:nth-of-type(5) { width:14px; text-align: center; }
    #table td:nth-of-type(6) { width:14px; text-align: center; }
    #table td:nth-of-type(7) { width:14px; text-align: center; }
    #table td:nth-of-type(8) { width:14px; text-align: center; }
</style>

<div class="view-container">
	<div id="api-help-header" style="float:right;"><a href="api"><?php echo _('Driver API Help'); ?></a></div>
    <div id="driver-header"><h2><?php echo _('Drivers'); ?></h2></div>

    <div id="table"></div>

    <div id="driver-none" class="alert alert-block hide">
        <h4 class="alert-heading"><?php echo _('No drivers created'); ?></h4>
        <p>
            <?php echo _('Drivers are used to configure the basic communication with different devices.'); ?>
            <br><br>
            <?php echo _('A driver implements for example the necessary communication protocol, to read several configured energy metering devices (see the devices tab).'); ?>
            <br>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Driver API helper'); ?></a>
        </p>
    </div>

    <div id="toolbar-bottom"><hr>
        <button id="driver-new" class="btn btn-primary btn-small"><i class="icon-plus-sign icon-white" ></i>&nbsp;<?php echo _('New driver'); ?></button>
    </div>

    <div id="driver-loader" class="ajax-loader"></div>
</div>

<?php require "Modules/muc/Views/driver/driver_dialog.php"; ?>
<?php require "Modules/muc/Views/device/device_dialog.php"; ?>

<script>
    var path = "<?php echo $path; ?>";
    
    // Extend table library field types
    for (z in muctablefields) table.fieldtypes[z] = muctablefields[z];
    for (z in customtablefields) table.fieldtypes[z] = customtablefields[z];
    table.element = "#table";
    table.groupprefix = "Controller ";
    table.groupby = 'controller';

    table.deletedata = false;
    table.fields = {
        'disabled':{'title':'', 'type':"disable"},
        'name':{'title':'<?php echo _("Name"); ?>','type':"fixed"},
        'controller':{'title':'<?php echo _("Controller"); ?>','type':"fixed"},
        'devices':{'title':'<?php echo _("Devices"); ?>','type':"devicelist"},
        // Actions
        'add-action':{'title':'', 'type':"icon-enabled", 'icon':'icon-plus-sign'},
        'scan-action':{'title':'', 'type':"icon-enabled", 'icon':'icon-search'},
        'delete-action':{'title':'', 'type':"delete"},
        'config-action':{'title':'', 'type':"iconconfig", 'icon':'icon-wrench'}
    }

    device.states = null;

    muc.list(function(data, textStatus, xhr) {
        if (data.length == 0) {
            $("#driver-new").prop('disabled', true);
        } else {
            $("#driver-new").prop('disabled', false);
        }
    });

    update();

    function update() {
        driver.list(function(data, textStatus, xhr) {
            table.data = data;
            
            table.draw();
            if (table.data.length != 0) {
                $("#driver-none").hide();
                $("#driver-header").show();
                $("#api-help-header").show();
            } else {
                $("#driver-none").show();
                $("#driver-header").hide();
                $("#api-help-header").hide();
            }
            $('#driver-loader').hide();
        });
        
        device.listStates(function(data, textStatus, xhr) {
            if (device.states == null) {
                device.states = data;
                table.draw();
            }
            else device.states = data;
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
        // Get driver of clicked row
        var configs = table.data[row];
        
        $('#driver-loader').show();
        driver.update(configs['ctrlid'], configs['id'], configs, function(result) {
            $('#driver-loader').hide();
            
            if (!result.success) {
                alert('Unable to update driver:\n'+result.message);
                return false;
            }
            update();
        });
    });

    $("#table").bind("onResume", function(e) {
        
        updaterStart(update, 5000);
    });

    $("#table").bind("onDelete", function(e,id,row) {
        // Get driver of clicked row
        var driver = table.data[row];
        
        driver_dialog.loadDelete(driver, row);
    });

    $("#table").on('click', '.icon-wrench', function() {
        // Get driver of clicked row
        var driver = table.data[$(this).attr('row')];
        
        driver_dialog.loadConfig(driver);
    });

    $("#table").on('click', '.icon-search', function() {
        // Get driver of clicked row
        var driver = table.data[$(this).attr('row')];
        
        device_dialog.loadScan(driver);
    });

    $("#table").on('click', '.icon-plus-sign', function() {
        // Do not open dialog if the icon-plus-sign is used on a group header
        if(!$(this).attr('group')) {
            // Get driver of clicked row
            var driver = table.data[$(this).attr('row')];
            
            device_dialog.loadNew(driver);
        }
    });

    $("#table").on('click', '.device-label', function() {
        // Get the ids of the clicked lable
        var ctrlid = $(this).attr('ctrlid');
        var deviceid = $(this).attr('deviceid');

        $('#driver-loader').show();
        device.get(ctrlid, deviceid, function(result) {
            device_dialog.loadConfig(result);
            
            $('#driver-loader').hide();
        });
    });

    $("#driver-new").on('click', function () {
        
        driver_dialog.loadNew();
    });
</script>
