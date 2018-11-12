var muctablefields = 
{
    'disable': {
        'draw': function(t,row,child_row,field) {
            var icon = "";
            if (t.data[row][field] == true) icon = 'icon-remove';
            else if (t.data[row][field] == false) icon = 'icon-ok';

            if (typeof t.data[row]['ctrlid'] === 'undefined' || (t.data[row]['ctrlid'] > 0 && t.data[row]['state'] != 'UNAVAILABLE')) {
                return "<i class='"+icon+"' type='disable' row='"+row+"' style='cursor:pointer'></i>";
            }
            else {
                return "<i class='"+icon+"' type='disable' row='"+row+"' style='cursor:pointer; opacity:0.33'></i>";
            }
        },

        'event': function() {
            // Event code for clickable switch state icon's
            $(table.element).on('click', 'i[type=disable]', function() {
                var row = $(this).parent().attr('row');
                var field = $(this).parent().attr('field');

                if (typeof table.data[row]['ctrlid'] === 'undefined' || (table.data[row]['ctrlid'] > 0 && table.data[row]['state'] != 'UNAVAILABLE')) {
                    $(table.element).trigger("onEdit");
                    
                    table.data[row][field] = !table.data[row][field];
                    $(table.element).trigger("onDisable",[table.data[row]['id'],row,table.data[row][field]]);
                    if (table.data[row][field]) $(this).attr('class', 'icon-remove'); else $(this).attr('class', 'icon-ok');
                    table.draw();
                    $(table.element).trigger("onResume");
                }
            });
        }
    },

    'icon-enabled': {
        'draw': function(t,row,child_row,field) {

            if (typeof t.data[row]['ctrlid'] === 'undefined' || (t.data[row]['ctrlid'] > 0 && t.data[row]['state'] != 'UNAVAILABLE')) {
                return "<i class='"+t.fields[field].icon+"' type='icon' row='"+row+"' style='cursor:pointer'></i>";
            }
            else {
                return "<i class='"+t.fields[field].icon+"' type='icon' row='"+row+"' style='cursor:pointer; opacity:0.33' disabled></i>";
            }
        }
    },

    'state': {
        'draw': function (t,row,child_row,field) {
            var state = t.data[row][field];
            return list_format_state(state);
        }
    },

    'driverlist': {
        'draw': function (t,row,child_row,field) {
            var ctrlid = t.data[row]['id'];
            var driverlist = t.data[row][field];
            return list_format_driverlist(ctrlid, driverlist);
        }
    },

    'devicelist': {
        'draw': function (t,row,child_row,field) {
            var ctrlid = t.data[row]['ctrlid'];
            var devicelist = t.data[row][field];
            return list_format_devicelist(ctrlid, devicelist);
        }
    },

    'channellist': {
        'draw': function (t,row,child_row,field) {
            var ctrlid = t.data[row]['ctrlid'];
            var channellist = t.data[row][field];
            return list_format_channellist(ctrlid, channellist);
        }
    },

    'group-state': {
        'draw': function(t,group,rows,field) {
            var errorstate = '';
            for (i in rows) {
                var row=rows[i];
                var state = t.data[row][field];
                if (state !== 'CONNECTED' && state !== 'SAMPLING' && state !== 'LISTENING' && state !== 'VALID' && 
                        state !== 'READING' && state !== 'WRITING' && state !== 'STARTING_TO_LISTEN' && 
                        state !== 'SCANNING_FOR_CHANNELS' && state !== 'NO_VALUE_RECEIVED_YET') {
                    
                    errorstate = state;
                    break;
                }
            }
            return list_format_state(errorstate);
        }
    },

    'group-devicelist': {
        'draw': function(t,group,rows,field) {
            var out = "";
            for (i in rows) {
                var row=rows[i];
                var ctrlid = t.data[row]['ctrlid'];
                var devicelist = t.data[row][field];
                out += list_format_devicelist(ctrlid, devicelist, true);
            }
            return out;
        }
    },

    'group-channellist': {
        'draw': function(t,group,rows,field) {
            var out = "";
            for (i in rows) {
                var row=rows[i];
                var ctrlid = t.data[row]['ctrlid'];
                var channellist = t.data[row][field];
                out += list_format_devicelist(ctrlid, channellist, true);
            }
            return out;
        }
    }
}

function list_format_state(state){

    var color = "rgb(255,0,0)";
    if (state === 'CONNECTED' || state === 'SAMPLING' || state === 'LISTENING' || 
            state === 'VALID') {
        color = "rgb(50,200,50)";
    }
    else if (state === 'READING' || state === 'WRITING' || state === 'STARTING_TO_LISTEN' || 
            state === 'SCANNING_FOR_CHANNELS' || state === 'NO_VALUE_RECEIVED_YET') {
        color = "rgb(240,180,20)";
    }
    else if (state === 'CONNECTING' || state === 'WAITING_FOR_CONNECTION_RETRY' || 
            state === 'DISCONNECTING') {
        color = "rgb(255,125,20)";
    }
    else if (state === 'LOADING' || state === 'SAMPLING_AND_LISTENING_DISABLED') {
        color = "rgb(135,135,135)";
    }
    state = state.toLowerCase().split('_').join(' ');

    return "<span style='color:"+color+";'>"+state+"</span>";
}

function list_format_driverlist(ctrlid, driverlist){

    var out = '';
    if (driverlist != null && typeof driver !== 'undefined') {
        for (var i = 0; i < driverlist.length; i++) {
            
            var id = driverlist[i];
            var label = "<small>"+id+"</small>";
            var title = "Driver " + id;
            
            var label = "<span class='label label-info' title='Driver "+id+"' style='cursor:pointer'>"+label+"</span> ";
            out += "<a class='driver-label' ctrlid='"+ctrlid+"'' driverid='"+id+"'>"+label+"</a>";
        }
    }
    return out;
}

function list_format_devicelist(ctrlid, devicelist, group){

    var out = '';
    if (devicelist != null) {
        for (var i = 0; i < devicelist.length; i++) {
            
            var id = devicelist[i];
            var label = "<small>"+id+"</small>";
            var title = "Device " + id;
            
            if (typeof device !== 'undefined' && device.states != null) {
                for (var s = 0; s < device.states.length; s++) {
                    if (device.states[s]['ctrlid'] == ctrlid && device.states[s]['id'] === id) {
                        out += list_format_label(device.states[s]['id'], device.states[s]['ctrlid'], device.states[s]['state'], "device", label, title, group);
                        break;
                    }
                }
            }
            else {
                out += list_format_label(id, ctrlid, null, "device", label, title, group);
            }
        }
    }
    return out;
}

function list_format_channellist(ctrlid, channellist, group){

    var out = '';
    if (channellist != null) {
        for (var i = 0; i < channellist.length; i++) {
            
            var id = channellist[i];
            var label = "<small>"+id+"</small>";
            var title = "Channel " + id;

            if (typeof channel !== 'undefined' && channel.states != null) {
                for (var s = 0; s < channel.states.length; s++) {
                    if (channel.states[s]['ctrlid'] == ctrlid && channel.states[s]['id'] === id) {
                        out += list_format_label(channel.states[s]['id'], channel.states[s]['ctrlid'], channel.states[s]['state'], "channel", label, title, group);
                        break;
                    }
                }
            }
            else {
                out += list_format_label(id, ctrlid, null, "channel", label, title, group);
            }
        }
    }
    return out;
}

function list_format_label(id, ctrlid, state, type, label, title, group){
    
    if (group) return '';
    
    var labeltype = null;
    if (state != null) {
        if (state === 'CONNECTING' || state === 'WAITING_FOR_CONNECTION_RETRY' || state === 'DISCONNECTING') {
            labeltype = 'warning';
        }
        else if (state === 'DELETED' || state === 'DISABLED' || state === 'DRIVER_UNAVAILABLE') {
            labeltype = 'important';
        }
        else labeltype = 'info';
        
        state = state.toLowerCase().split('_').join(' ');
        title += " (State: "+state+")";
    }
    else labeltype = 'default';

    var label = "<span class='label label-"+labeltype+"' title='"+title+"' style='cursor:pointer'>"+label+"</span> ";
    return "<a class='"+type+"-label' ctrlid='"+ctrlid+"' "+type+"id='"+id+"'>"+label+"</a>";
}