<?php
    global $path;
?>

<link href="<?php echo $path; ?>Modules/channel/Views/channel.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Views/muc.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/muc/Lib/tablejs/titatoggle-dist-min.css" rel="stylesheet">
<link href="<?php echo $path; ?>Modules/channel/Lib/groupjs/groups.css" rel="stylesheet">
<script type="text/javascript" src="<?php echo $path; ?>Modules/channel/Lib/groupjs/groups.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/muc/Lib/configjs/config.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/channel/Views/device.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>Modules/channel/Views/channel.js"></script>

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
    <div id="channel-actions" class="hide"></div>
    <div id="channel-groups"></div>
    
    <div id="channel-footer" class="hide">
        <button id="device-new" class="btn btn-small" >&nbsp;<i class="icon-plus-sign" ></i>&nbsp;<?php echo _('New device connection'); ?></button>
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

groups.actions = {
    'delete': {
        'title':'<?php echo _("Delete Selected"); ?>',
        'class':'action',
        'icon':'icon-trash',
        'event':'deleteSelected',
        'hide':true
    }
};
groups.header = {
    'name': {'class':'name'},
    'description': {'class':'description'},
};
groups.init($('#channel-actions'));

var devices = {};
var channels = {};
var records = {};

var collapsed = {};
var selected = {};

setTimeout(function() {
    device.list(function(result) {
        draw(result);
        device.load();
        channel.load();
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
        if ((time - redrawTime) % INTERVAL_RECORDS == 0) {
            
            channel.records(drawRecords);
        }
        else if (Object.keys(records).length > 0) {
            for (var id in records) {
                $('#'+id+'-time').html(drawRecordTime(id, time));
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
    $("#channel-groups").empty();
    
    if (typeof result.success !== 'undefined' && !result.success) {
        alert("Error:\n" + result.message);
        return;
    }
    else if (result.length == 0) {
        $("#channel-header").hide();
        $("#channel-actions").hide();
        $("#channel-footer").show();
        $("#channel-none").show();

        return;
    }
    devices = {};
    channels = {};
    
    $("#channel-header").show();
    $("#channel-actions").show();
    $("#channel-footer").show();
    $("#channel-none").hide();
    
    var count = 0;
    for (var i in result) {
        count += drawDevice(result[i]);
    }
    drawSelected(count);
    
    registerEvents();
}

function drawDevice(device) {
    var deviceid = 'device-muc'+device.ctrlid+'-'+device.id.toLowerCase().replace(/[._]/g, '-');
    
    if (typeof collapsed[deviceid] === 'undefined') {
        collapsed[deviceid] = true;
    }
    
    var description;
    if (typeof device.description !== 'undefined') {
        description = device.description;
    }
    else description = "";
    
    var groups = "";
    var count = 0;
    var checked = '';
    if (typeof device.channels !== 'undefined' && device.channels.length > 0) {
        for (var i in device.channels) {
            var channel = device.channels[i];
            var channelid = 'channel-muc'+channel.ctrlid+'-'+channel.id.toLowerCase().replace(/[._]/g, '-');
            
            channels[channelid] = channel;
            
            if (typeof selected[channelid] === 'undefined') {
                selected[channelid] = false;
            }
            if (selected[channelid]) {
                count++;
            }
            groups += drawChannel(channelid, channel);
        }
    }
    else {
        groups += "<div id='"+deviceid+"-none' class='alert'>" +
                "No channels configured yet. <a class='device-add'>Add</a> or <a class='device-scan'>scan</a> for channels with the buttons on this connection block." +
            "</div>";
    }
    if (count > 0 && count == device.channels.length) {
        checked = 'checked';
    }
    
    $("#channel-groups").append(
        "<div class='device group'>" +
            "<div id='"+deviceid+"-header' class='group-header' data-toggle='collapse' data-target='#"+deviceid+"-body'>" +
                "<div class='group-item' data-id='"+deviceid+"'>" +
                    "<div class='group-collapse'>" +
                        "<span id='"+deviceid+"-icon' class='icon-chevron-"+(collapsed[deviceid] ? 'right' : 'down')+" icon-collapse'></span>" +
                        "<input id='"+deviceid+"-select' class='device-select select hide' type='checkbox' "+checked+"></input>" +
                    "</div>" +
                    "<div class='name'><span>"+device.id+(description.length>0 ? ":" : "")+"</span></div>" +
                    "<div class='description'><span>"+description+"</span></div>" +
                    "<div class='group-grow'></div>" +
                    "<div class='action device-scan'><span class='icon-search' title='Scan'></span></div>" +
                    "<div class='action device-add'><span class='icon-plus-sign' title='Add'></span></div>" +
                    "<div class='action device-config'><span class='icon-wrench' title='Configure'></span></div>" +
                    "</div>" +
                "</div>" +
            "<div id='"+deviceid+"-body' class='group-body collapse "+(collapsed[deviceid] ? '' : 'in')+"'>" +
                groups +
            "</div>" +
        "</div>"
    );
    if (count > 0 && count < device.channels.length) {
        $('#'+deviceid+'-select').prop('indeterminate', true);
    }
    delete device['channels'];
    devices[deviceid] = device;
    
    return count;
}

function drawChannel(id, channel) {
    var time = (new Date()).getTime();
    
    var checked = "";
    if (selected[id]) {
        checked = "checked";
    }
    
    if (typeof channel.configs === 'undefined') {
        channel.configs = {};
    }
    
    var description = "";
    if (typeof channel.description !== 'undefined') {
        description = channel.description;
    }
    
    var unit = "";
    if (typeof channel.configs.unit !== 'undefined') {
        unit = channel.configs.unit;
    }
    
    var type = "DOUBLE";
    if (typeof channel.configs.valueType !== 'undefined') {
        type = channel.configs.valueType;
    }
    
    return "<div id='"+id+"-item' class='group-item' data-id='"+id+"'>" +
            "<div class='group-select'><input id='"+id+"-select' class='channel-select select' type='checkbox' "+checked+"></input></div>" +
            "<div class='channel-name'><span>"+channel.id+"</span></div>" +
            "<div class='channel-description'><span>"+description+"</span></div>" +
            "<div class='group-grow'></div>" +
            "<div id='"+id+"-time' class='channel-time'>"+drawRecordTime(id, time)+"</div>" +
            "<div id='"+id+"-flag' class='channel-flag'>"+drawRecordFlag(id)+"</div>" +
            "<div id='"+id+"-sample' class='channel-sample'>"+drawRecordValue(id)+"</div>" +
            "<div id='"+id+"-unit' class='channel-unit'><span>"+unit+"</span></div>" +
            "<div id='"+id+"-write' class='action channel-action channel-write' data-action='edit'><span class='icon-pencil' title='Add'></span></div>" +
            "<div id='"+id+"-config' class='action channel-action channel-config'><span class='icon-wrench' title='Configure'></span></div>" +
        "</div>";
}

function drawRecords(result) {
    records = {};
    for (var i in result) {
        var record = result[i];
        var id = 'channel-muc'+record.ctrlid+'-'+record.id.toLowerCase().replace(/[._]/g, '-');
        
        records[id] = record;
        if (typeof channels[id] !== 'undefined' && !redraw) {
            $('#'+id+'-flag').html(drawRecordFlag(id));
            $('#'+id+'-sample').html(drawRecordValue(id));
        }
    }
}

function drawRecordTime(id, time) {
    var updated = "n/a"
    var color = "rgb(255,0,0)";
    
    if (typeof records[id] !== 'undefined' && records[id].time > 0) {
        var update = (new Date(records[id].time)).getTime();
        var delta = (time - update);
        var secs = Math.abs(delta)/1000;
        var mins = secs/60;
        var hour = secs/3600;
        var day = hour/24;
        
        if ($.isNumeric(secs)) {
            updated = secs.toFixed(0) + "s";
            if (secs.toFixed(0) == 0) updated = "now";
            else if (day>7 && delta>0) updated = "inactive";
            else if (day>2) updated = day.toFixed(1)+" days";
            else if (hour>2) updated = hour.toFixed(0)+" hrs";
            else if (secs>180) updated = mins.toFixed(0)+" mins";
            
            secs = Math.abs(secs);
            if (delta<0) color = "rgb(60,135,170)"
            else if (secs<25) color = "rgb(50,200,50)"
            else if (secs<60) color = "rgb(240,180,20)"; 
            else if (secs<(3600*2)) color = "rgb(255,125,20)"
        }
    }
    return "<span style='color:"+color+";'>"+updated+"</span>";
}

function drawRecordFlag(id) {
    var flag;
    if (typeof records[id] !== 'undefined') {
        flag = records[id].flag;
    }
    else {
        flag = 'LOADING';
    }
    
    var color;
    if (flag === 'VALID' || flag === 'CONNECTED' || flag === 'SAMPLING' || flag === 'LISTENING') {
        color = "rgb(50,200,50)";
    }
    else if (flag === 'READING' || flag === 'WRITING' || flag === 'STARTING_TO_LISTEN' || 
            flag === 'SCANNING_FOR_CHANNELS' || flag === 'NO_VALUE_RECEIVED_YET') {
        color = "rgb(240,180,20)";
    }
    else if (flag === 'CONNECTING' || flag === 'WAITING_FOR_CONNECTION_RETRY' || 
            flag === 'DISCONNECTING') {
        color = "rgb(255,125,20)";
    }
    else if (flag === 'LOADING' || flag === 'SAMPLING_AND_LISTENING_DISABLED') {
        color = "rgb(135,135,135)";
    }
    else {
        color = "rgb(255,0,0)";
    }
    return "<span style='color:"+color+"'>"+flag.toLowerCase().replace(/[_]/g, ' ')+"</span>";
}

function drawRecordValue(id) {
    var html = "";
    
    var value = "<span style='color: #999'>null</span>";
    if (typeof records[id] !== 'undefined' && typeof records[id].value !== 'undefined' && records[id].value !== null) {
        value = records[id].value;
    }
    
    var type = channels[id].configs.valueType;
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

function drawSelected(count) {
    if (count == 0) {
        $('#group-action-select-all input').prop('checked', false).prop('indeterminate', false);
        $('#group-action-delete').hide();
    }
    else if (count < Object.keys(channels).length) {
        $('#group-action-select-all input').prop('checked', false).prop('indeterminate', true);
        $('#group-action-delete').show();
    }
    else {
        $('#group-action-select-all input').prop('checked', true).prop('indeterminate', false);
        $('#group-action-delete').show();
    }
}

function selectAll(state) {
    if (state) {
        $('#group-action-delete').show();
    }
    else {
        $('#group-action-delete').hide();
    }
    for (var id in devices) {
        if (state && !$('#'+id+'-body').hasClass('in')) {
            $('#'+id+'-body').collapse('show');
        }
        $('#'+id+'-select').prop('checked', state).prop('indeterminate', false);
    }
    for (var id in channels) {
        selected[id] = state;
        
        $('#'+id+'-select').prop('checked', state);
    }
}

function selectDevice(id, state) {
    var device = devices[id];
    
    var count = 0;
    for (var i in channels) {
        if (channels[i].deviceid == device.id) {
            selected[i] = state;

            $('#'+i+'-select').prop('checked', state);
        }
        if (selected[i]) count++;
    }
    if (state) {
        $('#'+id+'-select').prop('indeterminate', false);
        if (!$('#'+id+'-collapse').hasClass('in')) {
            $('#'+id+'-collapse').collapse('show');
        }
    }
    drawSelected(count);
}

function selectChannel(id, state) {
    var checked = true;
    var indeterminate = false;
    var channel = channels[id];
    var deviceid = 'device-muc'+channel.ctrlid+'-'+channel.deviceid.toLowerCase().replace(/[._]/g, '-');
    
    selected[id] = state;
    
    var count = 0;
    for (var i in channels) {
        if (channels[i].deviceid == channel.deviceid) {
            if (selected[i]) {
                indeterminate = true;
            }
            else {
                checked = false;
            }
        }
        if (selected[i]) count++;
    }
    drawSelected(count);
    
    if (checked) indeterminate = false;
    $('#'+deviceid+'-select').prop('checked', checked).prop('indeterminate', indeterminate);
}

function registerEvents() {

    $("#group-action-select-all").off('click').on('click', function(e) {
        var state = $(this).find('input').prop('checked')
        groups.drawExpandAction(state);
        
        if (state) {
            selectAll(state);
        }
    });

    $(".group-header .group-item").off('mouseover').on("mouseover", function(e) {
        var id = $(this).data('id');
        $("#"+id+"-icon").hide();
        $("#"+id+"-select").show();
    }),

    $(".group-header .group-item").off('mouseout').on("mouseout", function(e) {
        var id = $(this).data('id');
        $("#"+id+"-icon").show();
        $("#"+id+"-select").hide();
    }),

    $(".collapse").off('show hide').on('show hide', function(e) {
        // Remember if the device block is collapsed, to redraw it correctly
        var id = $(this).attr('id').replace('-body', '');
        var collapse = $(this).hasClass('in');

        collapsed[id] = collapse;
        if (collapse) {
            $("#"+id+"-icon").removeClass('icon-chevron-down').addClass('icon-chevron-right').show();
            $("#"+id+"-select").hide();
        }
        else {
            if (hover) {
                $("#"+id+"-icon").removeClass('icon-chevron-right').addClass('icon-chevron-down');
            }
            else {
                $("#"+id+"-icon").hide();
                $("#"+id+"-select").show();
            }
        }
    }),

    $(".group-body .group-item").off('click').on('click', function(e) {
        e.stopPropagation();
        
        var id = $(this).data('id');
        var select = $('#'+id+'-select');
        var state = !select.prop('checked');
        
        select.prop('checked', state);
        selectChannel(id, state);
    });

    $(".device-select").off('click').on('click', function(e) {
        e.stopPropagation();
        
        var id = $(this).closest('.group-item').data('id');
        var state = $(this).prop('checked');
        
        selectDevice(id, state);
    });

    $(".device-config").off('click').on('click', function(e) {
        e.stopPropagation();
        
        // Get device of clicked row
        var id = $(this).closest('.group-item').data('id');
        var device = devices[id];
        
        device_dialog.loadConfig(device);
    });

    $(".device-add").off('click').on('click', function(e) {
        e.stopPropagation();
        
        // Get device of clicked row
        var id = $(this).closest('.group-item').data('id');
        var device = devices[id];
        
        channel_dialog.loadNew(device);
    });

    $(".device-scan").off('click').on('click', function(e) {
        e.stopPropagation();
        
        // Get device of clicked row
        var id = $(this).closest('.group-item').data('id');
        var device = devices[id];
        
        channel_dialog.loadScan(device);
    });

    $(".channel-select").off('click').on('click', function(e) {
        e.stopPropagation();
        
        var id = $(this).closest('.group-item').data('id');
        var state = $(this).prop('checked');
        selectChannel(id, state);
    });

    $(".channel-config").off('click').on('click', function(e) {
        e.stopPropagation();

        // Get channel of clicked row
        var id = $(this).closest('.group-item').data('id');
        var channel = channels[id];
        
        channel_dialog.loadConfig(channel);
    });

    $(".channel-write").off('click').on('click', function(e) {
        e.stopPropagation();
        
        var id = $(this).closest('.group-item').data('id');
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
    });

    $(".channel-sample").off('click');
    $(".channel-sample").on('click', '.channel-slider', function(e) {
        e.stopPropagation();

        var id = $(this).closest('.group-item').data('id');
        
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
            
            var id = $(self).closest('.group-item').data('id');
            
            var value = null;
            if (typeof records[id] !== 'undefined') {
                value = records[id].value;
                if (!isNaN(value)) {
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
        }, 200);
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

$("#group-action-delete").on('click', function () {
    var list = "";
    for (var id in channels) {
        if (selected[id]) {
            list += "<li>"+channels[id].id+"</li>";
        }
    }
    $('#channels-delete-list').html("<ul>"+list+"</ul>");
    $('#channels-delete-modal').modal('show');
});

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

</script>
