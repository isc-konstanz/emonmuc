<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/muc/Views/muc.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/table.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Lib/tablejs/custom-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/tablejs/muc-table-fields.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/configjs/config.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/driver/driver.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/muc.js"></script>

<style>
    #table input[type="text"] {
        width: 88%;
    }
    #table td:nth-of-type(1) { width:5%;}
    #table td:nth-of-type(2) { width:20%;}
    #table td:nth-of-type(3) { width:10%;}
    #table th:nth-of-type(4) { font-weight:normal; }
    #table td:nth-of-type(5), th:nth-of-type(5) { width:20%; text-align: right; }
    #table td:nth-of-type(6) { width:14px; text-align: center; }
    #table td:nth-of-type(7) { width:14px; text-align: center; }
    #table td:nth-of-type(8) { width:14px; text-align: center; }
</style>

<div>
    <div id="api-help-header" style="float:right;"><a href="api"><?php echo _('MUC API Help'); ?></a></div>
    <div id="ctrl-header"><h2><?php echo _('Controller'); ?></h2></div>
    
    <div id="table"><div align='center'></div></div>
    
    <div id="ctrl-none" class="alert alert-block hide">
        <h4 class="alert-heading"><?php echo _('No Controllers configured'); ?></h4>
            <p>
                <?php echo _('Multi Utility Communication (MUC) controller handle the communication protocols to a variety of devices and are the main entry point to configure metering units.'); ?>
                <br><br>
                <?php echo _('A MUC controller registers several drivers (see the drivers tab) and is needed to configure the communication protocol they implement.'); ?>
                <br>
                <?php echo _('Several MUC controllers may be added, but it is recommended to use the local platform, if geographically possible.'); ?>
                <br>
                <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('MUC API helper'); ?></a>
            </p>
    </div>
    
    <div id="toolbar-bottom"><hr>
        <button id="ctrl-new" class="btn btn-primary btn-small"><i class="icon-plus-sign icon-white" ></i>&nbsp;<?php echo _('New controller'); ?></button>
    </div>
    
    <div id="ctrl-loader" class="ajax-loader"></div>
</div>

<?php require "Modules/muc/Views/muc_dialog.php"; ?>
<?php require "Modules/muc/Views/driver/driver_dialog.php"; ?>

<script>
    var path = "<?php echo $path; ?>";

    var types = {
        HTTP: "HTTP",
        HTTPS: "HTTPS",
        MQTT: "MQTT"
    };
    
    // Extend table library field types
    for (z in muctablefields) table.fieldtypes[z] = muctablefields[z];
    for (z in customtablefields) table.fieldtypes[z] = customtablefields[z];
    table.element = "#table";
    table.deletedata = false;
    table.fields = {
        'type':{'title':'<?php echo _("Type"); ?>','type':"select",'options':types},
        'address':{'title':'<?php echo _("Address"); ?>','type':"text"},
        'description':{'title':'<?php echo _('Location'); ?>','type':"text"},
        'drivers':{'title':'<?php echo _("Drivers"); ?>','type':"driverlist"},
        'password':{'title':'<?php echo _('Password'); ?>','type':"text"},
        // Actions
        'add-action':{'title':'', 'type':"icon-enabled", 'icon':'icon-plus-sign'},
        'edit-action':{'title':'', 'type':"edit"},
        'delete-action':{'title':'', 'type':"delete"}
    }

    update();

    function update() {
        muc.list(function(data, textStatus, xhr) {
            table.data = data;
            
            table.draw();
            if (table.data.length == 0) {
                $("#api-help-header").hide();
                $("#ctrl-header").hide();
                $("#ctrl-none").show();
            } else {
                $("#api-help-header").show();
                $("#ctrl-header").show();
                $("#ctrl-none").hide();
            }
            $('#ctrl-loader').hide();
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

    $("#table").bind("onSave", function(e,id,fields_to_update) {
        
        $('#ctrl-loader').show();
        
        var result = muc.set(id,fields_to_update);
        update();
        
        $('#ctrl-loader').hide();

        if (!result.success) {
            alert('Unable to update muc:\n'+result.message);
            return false;
        }
    });

    $("#table").bind("onResume", function(e) {
        
        updaterStart(update, 5000);
    });

    $("#table").bind("onDelete", function(e,id,row) {
        
        muc_dialog.loadDelete(id, row);
    });

    $("#table").on('click', '.icon-plus-sign', function() {
        // Get MUC of clicked row
        var ctrl = table.data[$(this).attr('row')];
        
        driver_dialog.loadNew(ctrl);
    });

    $("#table").on('click', '.driver-label', function() {
        // Get the ids of the clicked lable
        var ctrlid = $(this).attr('ctrlid');
        var driverid = $(this).attr('driverid');

        $('#ctrl-loader').show();
        driver.get(ctrlid, driverid, function(result) {
            driver_dialog.loadConfig(result);
            
            $('#ctrl-loader').hide();
        });
    });

    $('#ctrl-new').on('click', function() {
        muc_dialog.loadConfig();
    });
</script>
