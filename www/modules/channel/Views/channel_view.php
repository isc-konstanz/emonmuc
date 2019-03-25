<?php
    global $path;
?>

<link href="<?php echo $path; ?>Lib/nodejs/nodes.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Views/muc.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Lib/tablejs/titatoggle-dist-min.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/channel/Views/channel.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Lib/nodejs/nodes.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/configjs/config.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/channel/channel.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Views/device/device.js"></script>

<div class="view-container">
    <div id="channel-header" class="hide">
        <span id="api-help" style="float:right"><a href="api"><?php echo _('Channel API Help'); ?></a></span>
        <h2><?php echo _('Channels'); ?></h2>
    </div>
    <div id="channel-none" class="alert alert-block hide" style="margin-top:12px">
        <h4 class="alert-heading"><?php echo _('No Channels configured'); ?></h4>
        <p>
            <?php echo _('Channels represents single data points, representing e.g. the metered active power of a smart meter, the temperature of a temperature sensor, '); ?>
            <?php echo _('any value of digital or analog I/O modules or the manufacture data of the device.'); ?>
            <?php echo _('If configured to log sampled data, values will be written into inputs for the same key, to allow further processing.'); ?>
            <?php echo _('You may want the next link as a guide for generating your request: '); ?><a href="api"><?php echo _('Channels API helper'); ?></a>
        </p>
    </div>
    <div id="channels"></div>
    
    <div id="channel-footer" class="hide">
        <button id="device-new" class="btn btn-small"><span class="icon-plus-sign"></span>&nbsp;<?php echo _('New device connection'); ?></button>
        <a id="ctrl-config" class="btn btn-small" href="<?php echo $path; ?>muc/view"><span class="icon-cog"></span>&nbsp;<?php echo _('Controllers'); ?></a>
    </div>
    <div id="channel-loader" class="ajax-loader"></div>
</div>

<div id="channels-delete-modal" class="modal hide" tabindex="-1" role="dialog" aria-labelledby="channels-delete-modal" aria-hidden="true" data-backdrop="static">
    <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
        <h3 id="channels-delete-modal"><?php echo _('Delete Channels'); ?></h3>
    </div>
    <div class="modal-body">
        <p><?php echo _('The following channels will be deleted permanently:'); ?>
        </p>
        <div id="channels-delete-list"></div>
        <p style="color:#999">
            <?php echo _('Corresponding configurations will be removed, while inputs, feeds and all historic data will be kept. '); ?>
            <?php echo _('To remove those, delete them manually afterwards.'); ?>
        </p>
        <p>
            <?php echo _('Are you sure you want to proceed?'); ?>
        </p>
    </div>
    <div class="modal-footer">
        <button class="btn" data-dismiss="modal" aria-hidden="true"><?php echo _('Cancel'); ?></button>
        <button id="channels-delete-confirm" class="btn btn-primary"><?php echo _('Delete'); ?></button>
    </div>
</div>

<?php require "Modules/muc/Views/device/device_dialog.php"; ?>
<?php require "Modules/muc/Views/channel/channel_dialog.php"; ?>

<script>

const INTERVAL_RECORDS = 5000;
const INTERVAL_REDRAW = 15000;
var redrawTime = new Date().getTime();
var redraw = false;
var updater;
var timeout;
var path = "<?php echo $path; ?>";

nodes.actions = {
    'select': {'type': 'select'},
    'filler': {'type': 'filler'},
    'delete': {
        'title': '<?php echo _("Delete Selected"); ?>',
        'type': 'icon',
        'icon': 'icon-trash',
        'event': onDeleteSelected,
        'hide': true
    },
    'expand': {'type': 'expand'}
};
nodes.header = {
	'select': {'type': 'select'},
    'id': {
        'title': '<?php echo _("Name"); ?>',
        'type': 'text',
        'class': 'device-name',
        'draw': drawName
    },
    'description': {
        'title': '<?php echo _("Description"); ?>',
        'type': 'text',
        'class': 'device-description'
    },
    'filler': {'type': 'filler'},
    'time': {
        'type': 'time',
        'class': 'device-time'
    },
    'scan': {
        'title': '<?php echo _("Scan"); ?>',
        'type': 'icon',
        'icon': 'icon-search',
        'class': 'device-scan',
        'event': onDeviceScan
    },
    'add': {
        'title': '<?php echo _("Add"); ?>',
        'type': 'icon',
        'icon': 'icon-plus-sign',
        'event': onDeviceAdd
    },
    'config': {
        'title': '<?php echo _("Configure"); ?>',
        'type': 'icon',
        'icon': 'icon-wrench',
        'event': onDeviceConfig
    }
};
nodes.body = {
    'select': {'type': 'select'},
    'id': {
        'title': '<?php echo _("Name"); ?>',
        'type': 'text',
        'class': 'channel-name'
    },
    'description': {
        'title': '<?php echo _("Description"); ?>',
        'type': 'text',
        'class': 'channel-description'
    },
    'filler': {'type': 'filler'},
    'time': {
        'type': 'time',
        'class': 'channel-time'
    },
    'flag': {
        'title': '<?php echo _("Flag"); ?>',
        'type': 'text',
        'class': 'channel-flag',
        'draw': drawRecordFlag
    },
    'sample': {
        'title': '<?php echo _("Sample"); ?>',
        'type': 'text',
        'class': 'channel-sample',
        'draw': drawRecordValue
    },
    'unit': {
        'title': '<?php echo _("Unit"); ?>',
        'type': 'text',
        'class': 'channel-unit',
        'draw': drawUnit
    },
    'write': {
        'title': '<?php echo _("Write"); ?>',
        'type': 'icon',
        'icon': 'icon-pencil',
        'class': 'channel-write',
        'event': onChannelWrite
    },
    'config': {
        'title': '<?php echo _("Configure"); ?>',
        'type': 'icon',
        'icon': 'icon-wrench',
        'class': 'channel-config',
        'event': onChannelConfig
    }
};
nodes.id = 'deviceid';
nodes.empty = "No channels configured yet. <a class='device-add'>Add</a> or <a class='device-scan'>scan</a> for channels with the buttons on this connection block.";
nodes.init($('#channels'));

var records = {};

setTimeout(function() {
    device.list(function(result) {
        draw(result);
        channel.records(drawRecords);
        updaterStart();
    });
}, 100);

function update() {
    device.list(draw);
}

function updateView() {
    var time = new Date().getTime();
    if (time - redrawTime >= INTERVAL_REDRAW) {
        redrawTime = time;
        redraw = true;
        
        device.list(function(result) {
            draw(result);
            redraw = false;
            
            channel.records(drawRecords);
        });
    }
    else if (!redraw) {
        if ((time - redrawTime) % INTERVAL_RECORDS < 1000) {
            
            channel.records(drawRecords);
        }
        else if (Object.keys(records).length > 0) {
            for (var id in records) {
                // TODO:
//                 $('#'+id+'-time').html(drawRecordTime(id, time));
            }
        }
    }
}

function updaterStart() {
    if (updater != null) {
        clearInterval(updater);
    }
    updater = setInterval(updateView, 1000);
}

function updaterStop() {
    clearInterval(updater);
    updater = null;
}

//---------------------------------------------------------------------------------------------
// Draw devices and channels
//---------------------------------------------------------------------------------------------
function draw(result) {
    $('#channel-loader').hide();
    
    if (typeof result.success !== 'undefined' && !result.success) {
        //alert("Error:\n" + result.message);
        return;
    }
    else if (result.length == 0) {
        $("#channel-header").hide();
        $("#channel-footer").show();
        $("#channel-none").show();
        $("#channels").hide();
        
        return;
    }
    $("#channel-header").show();
    $("#channel-footer").show();
    $("#channel-none").hide();
    $("#channels").show();
    
    let devices = {};
    let channels = {};
    for (let i in result) {
        let device = result[i];
        if (!channels.hasOwnProperty(device.id)) channels[device.id] = {};
        if (typeof device.channels !== 'undefined' && device.channels.length > 0) {
            for (let j in device.channels) {
                let channel = device.channels[j];
                channels[device.id][channel.id] = channel;
            }
        }
        delete device['channels'];
        devices[device.id] = device;
    }
    nodes.draw({nodes: devices, items: channels}, registerEvents);
}

function drawName(device) {
	return device.id+(device.description.length>0 ? ":" : "");
}

function drawRecords(result) {
    if (typeof result.success !== 'undefined' && !result.success) {
        return;
    }
    for (var i in result) {
        var id = 'channel-muc'+result[i].ctrlid+'-'+result[i].id.toLowerCase().replace(/[_.:/]/g, '-');
        
        if (typeof records[id] !== 'undefined' && typeof channels[id] !== 'undefined' && !redraw) {
            var record = records[id];
            
            records[id] = result[i];
            if (record.flag != result[i].flag || record.value != result[i].value) {
                $('#'+id+'-flag').html(drawRecordFlag(record));
                $('#'+id+'-sample').html(drawRecordValue(record));
            }
        }
    }
}

function drawRecordFlag(record) {
    var flag;
    if (typeof record.flag !== 'undefined') {
        flag = record.flag;
    }
    else {
        flag = 'LOADING';
    }
    
    var color;
    if (flag === 'VALID' || flag === 'CONNECTED' || flag === 'SAMPLING' || flag === 'LISTENING' ||
    	flag === 'READING' || flag === 'WRITING' || flag === 'STARTING_TO_LISTEN' || 
        flag === 'SCANNING_FOR_CHANNELS') {
        color = "rgb(40, 167, 69)";
    }
    else if (flag === 'CONNECTING' || flag === 'WAITING_FOR_CONNECTION_RETRY' || flag === 'DISCONNECTING') {
        color = "rgb(204, 119, 0)";
    }
    else if (flag === 'LOADING' || flag === 'SAMPLING_AND_LISTENING_DISABLED' || flag === 'NO_VALUE_RECEIVED_YET') {
        color = "rgb(135,135,135)";
    }
    else {
        color = "rgb(220, 53, 69)";
    }
    return "<span style='color:"+color+"'>"+flag.toLowerCase().replace(/[_]/g, ' ')+"</span>";
}

function drawRecordValue(record) {
	var id = nodes.formatId('item', record.id);
    var html = "";
    var value = "<span style='color: #999'>null</span>";
    if (typeof record.value !== 'undefined' && record.value !== null) {
        value = record.value;
    }
    
    var type = record.configs.valueType;
    if (type === 'BOOLEAN') {
        var checked = "";
        if (typeof value === 'string' || value instanceof String) {
            value = (value == 'true');
        }
        if (value) {
            checked = "checked";
        }
        html = "<div class='channel-checkbox'>" +
                    "<div id='"+id+"-value' class='channel-value checkbox checkbox-slider--b-flat'>" +
                        "<label>" +
                            "<input type='checkbox' onclick='return false;' "+checked+"><span></span></input>" +
                        "</label>" +
                    "</div>" +
                    "<div id='"+id+"-input' class='channel-input checkbox checkbox-slider--b-flat checkbox-slider-info hide'>" +
                        "<label>" +
                            "<input id='"+id+"-slider' class='channel-slider' type='checkbox' "+checked+"><span></span></input>" +
                        "</label>" +
                    "</div>" +
                "</div>";
    }
    else if (type === 'DOUBLE' || type === 'FLOAT') {
        if (!isNaN(value)) {
            value = parseFloat(value);
            if (Math.abs(value) >= 1000) {
                value = value.toFixed(0);
            }
            else if (Math.abs(value) >= 100) {
                value = value.toFixed(1);
            }
            else {
                value = value.toFixed(2);
            }
        }
        html = "<span id='"+id+"-value' class='channel-value'>"+value+"</span>" +
                "<input id='"+id+"-input' class='channel-input input-small' type='number' step='any' style='display:none'></input>";
    }
    else if (type === 'LONG' || type === 'INTEGER' || type === 'SHORT') {
        if (!isNaN(value)) {
            value = parseInt(value).toFixed(0);
        }
        html = "<span id='"+id+"-value' class='channel-value'>"+value+"</span>" +
                "<input id='"+id+"-input' class='channel-input input-small' type='number' step='1' style='display:none'></input>";
    }
    else {
        html = "<span id='"+id+"-value' class='channel-value'>"+value+"</span>" +
                "<input id='"+id+"-input' class='channel-input input-small' type='text' style='display:none'></input>";
    }
    return html;
}

function drawUnit(channel) {
    return typeof channel.configs.unit !== 'undefined' ? channel.configs.unit : "";
}

function registerEvents() {

    $(".channel-sample").off('click');
    $(".channel-sample").on('click', '.channel-slider', function(e) {
        e.stopPropagation();

        var id = $(this).closest('.node-item').data('id');
        
        var value = null;
        if (typeof records[id] !== 'undefined') {
            value = records[id].value;
        }
        if (typeof value === 'string' || value instanceof String) {
            value = (value == 'true');
        }
        if (value !== $(this).is(':checked')) {
            $('#'+id+'-write').data('action', 'write');
            $('#'+id+'-write span').removeClass('icon-remove').addClass('icon-share-alt');
        }
        else {
            $('#'+id+'-write').data('action', 'cancel');
            $('#'+id+'-write span').removeClass('icon-share-alt').addClass('icon-remove');
        }
    });

    $(".channel-sample").on('click', '.channel-input', function(e) {
        e.stopPropagation();
    }),

    $(".channel-sample").off('keyup').on('keyup', '.channel-input', function(e) {
        e.stopPropagation();
        
        var self = this;
        if (timeout != null) {
            clearTimeout(timeout);
        }
        timeout = setTimeout(function() {
            timeout = null;
            
            var id = $(self).closest('.node-item').data('id');
            
            var value = null;
            if (typeof records[id] !== 'undefined' && typeof records[id].value !== 'undefined') {
                value = records[id].value;
                if (value != null && !isNaN(value)) {
                    value = value.toFixed(3);
                }
            }
            var newVal = $(self).val();
            if (newVal != "" && newVal !== value) {
                $('#'+id+'-write').data('action', 'write');
                $('#'+id+'-write span').removeClass('icon-remove').addClass('icon-share-alt');
            }
            else {
                $('#'+id+'-write').data('action', 'cancel');
                $('#'+id+'-write span').removeClass('icon-share-alt').addClass('icon-remove');
            }
        }, 250);
    });
}

function getChannelInputValue(id, type) {
    if (type == 'BOOLEAN') {
        return $('#'+id+'-slider').is(':checked');
    }
    else {
        return $('#'+id+'-input').val();
    }
}

function setChannelInputValue(id, type, value) {
    if (type == 'BOOLEAN') {
        if (typeof value === 'string' || value instanceof String) {
            value = (value == 'true');
        }
        $('#'+id+'-slider').prop('checked', value);
    }
    else {
        if (!isNaN(value) && (type == 'DOUBLE' || type == 'FLOAT')) {
            value = parseFloat(value);
        }
        else if (!isNaN(value) && (type == 'LONG' || type == 'INTEGER' || type == 'SHORT')) {
            value = parseInt(value);
        }
        $('#'+id+'-input').val(value);
    }
}

$.ajax({ url: path+"muc/driver/registered.json", dataType: 'json', async: true, success: function(result) {
    if (typeof result.success === 'undefined' || result.success) {
        device_dialog.drivers = result;
    }
}});

$("#device-new").on('click', function () {
    
    device_dialog.loadNew();
});

function onDeleteSelected() {
    var list = "";
    for (var id in channels) {
        if (selected[id]) {
            list += "<li>"+channels[id].id+"</li>";
        }
    }
    $('#channels-delete-list').html("<ul>"+list+"</ul>");
    $('#channels-delete-modal').modal('show');
}

$("#channels-delete-confirm").on('click', function () {
    var count = 0;
    for (var id in channels) {
        if (selected[id]) {
            delete selected[id];
            
            $('#'+id+'-item').remove();
            channel.remove(channels[id].ctrlid, channels[id].id);

            count++;
        }
    }
    update();
    
    drawSelected(count);
    $('#channels-delete-modal').modal('hide');
});

function onDeviceScan() {
    // Get device of clicked row
    var id = $(this).closest('.node-item').data('id');
    var device = devices[id];
    
    channel_dialog.loadScan(device);
}

function onDeviceAdd() {
    // Get device of clicked row
    var id = $(this).closest('.node-item').data('id');
    var device = devices[id];
    
    channel_dialog.loadNew(device);
}

function onDeviceConfig() {
    // Get device of clicked row
    var id = $(this).closest('.node-item').data('id');
    var device = devices[id];
    
    device_dialog.loadConfig(device);
}

function onChannelWrite() {
    var id = $(this).closest('.node-item').data('id');
    var ch = channels[id];
    
    var action = $(this).data('action');
    if (action == 'edit') {
        updaterStop();
        
        $(this).data('action', 'cancel');
        $(this).find('span').removeClass('icon-pencil').addClass('icon-remove');
        
        var type = ch.configs.valueType;
        var value = "";
        if (typeof records[id].value !== 'undefined') {
            value = records[id].value;
        }
        setChannelInputValue(id, type, value);
        
        $('#'+id+'-value').hide();
        $('#'+id+'-input').fadeIn();
    }
    else if (action == 'write') {
        $(this).data('action', 'edit');
        $(this).find('span').removeClass('icon-share-alt').addClass('icon-pencil');
        
        var type = ch.configs.valueType;
        var value = getChannelInputValue(id, type);
        
        channel.write(ch.ctrlid, ch.id, value, type, function(result) {
            if (typeof result.success !== 'undefined' && !result.success) {
                alert("Error:\n" + result.message);
                return;
            }
            channel.records(drawRecords);
        });
        
        $(".channel-input").hide();
        $('.channel-value').fadeIn();

        updaterStart();
    }
    else {
        $(this).data('action', 'edit');
        $(this).find('span').removeClass('icon-remove').addClass('icon-pencil');
        
        $(".channel-input").hide();
        $('.channel-value').fadeIn();

        updaterStart();
    }
}

function onChannelConfig() {
    // Get channel of clicked row
    var id = $(this).closest('.node-item').data('id');
    var channel = channels[id];
    
    channel_dialog.loadConfig(channel);
}

</script>
