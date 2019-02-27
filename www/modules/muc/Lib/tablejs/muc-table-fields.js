var muctablefields = {

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

    'state-list': {
        'draw': function (t,row,child_row,field) {
            var ctrlid = t.data[row]['id'];
            var items = t.data[row][field];
            return list_format_states(ctrlid, items);
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
                        state !== 'SCANNING_FOR_CHANNELS' && state !== 'NO_VALUE_RECEIVED_YET' && 
                        state !== 'DRIVER_UNAVAILABLE' && state !== 'LOADING') {
                    
                    errorstate = state;
                    break;
                }
            }
            return list_format_state(errorstate);
        }
    },

    'group-state-list': {
        'draw': function(t,group,rows,field) {
            var out = "";
            for (i in rows) {
                var row=rows[i];
                var ctrlid = t.data[row]['ctrlid'];
                var items = t.data[row][field];
                out += list_format_states(ctrlid, items, true);
            }
            return out;
        }
    }
}

function list_format_state(state) {
	if (state === "") return "";
    
    var color = "rgb(255,0,0)";
    if (state === 'CONNECTED' || 
            state === 'SAMPLING' || 
            state === 'LISTENING' || 
            state === 'VALID') {
        
        color = "rgb(50,200,50)";
    }
    else if (state === 'READING' || 
            state === 'WRITING' || 
            state === 'STARTING_TO_LISTEN' || 
            state === 'SCANNING_FOR_CHANNELS' || 
            state === 'NO_VALUE_RECEIVED_YET') {
        
        color = "rgb(240,180,20)";
    }
    else if (state === 'CONNECTING' || 
            state === 'WAITING_FOR_CONNECTION_RETRY' || 
            state === 'DISCONNECTING') {
        
        color = "rgb(255,125,20)";
    }
    else if (state === 'LOADING' || 
            state === 'SAMPLING_AND_LISTENING_DISABLED' ||
            state === 'DRIVER_UNAVAILABLE') {
        
        color = "rgb(135,135,135)";
    }
    state = state.toLowerCase().split('_').join(' ');
    
    return "<span style='color:"+color+";'>"+state+"</span>";
}

function list_format_states(ctrlid, items, group) {
    if (group) return '';

    var out = '';
    if (items != null) {
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var label = "<small>"+item['id']+"</small>";
            var title = item['id'];
            if (typeof item['name'] !== 'undefined') {
            	title = item['name'];
            }
            else if (typeof item['description'] !== 'undefined' && item['description'].length > 0) {
            	title = item['description'];
            }
            
            var state = 'DRIVER_UNAVAILABLE';
            if (typeof item.state !== 'undefined') {
            	state = item.state;
            }
            else if (item['disabled']) {
                state = 'DISABLED';
            }
            else if (item['running']) {
                state = 'RUNNING';
            }
            out += list_format_label(item['id'], state, label, title, false);
        }
    }
    return out;
}

function list_format_label(id, state, label, title, group) {
    if (group) return '';
    
    var type = null;
    if (state != null && state != '') {
        if (state === 'CONNECTING' || 
                state === 'WAITING_FOR_CONNECTION_RETRY' || 
                state === 'DISCONNECTING') {
            
            type = 'warning';
        }
        else if (state === 'DELETED' || 
        		state === 'DISABLED' || 
                state === 'SAMPLING_AND_LISTENING_DISABLED') {
            
            type = 'important';
        }
        else if (state === 'DRIVER_UNAVAILABLE') {
            type = 'default';
        }
        else {
            type = 'info';
        }
        
        state = state.toLowerCase().split('_').join(' ');
        title += " (State: "+state+")";
    }
    else type = 'default';

    var label = "<span class='label label-"+type+"' title='"+title+"' style='cursor:pointer'>"+label+"</span> ";
    return "<a class='state-label' data-id='"+id+"'>"+label+"</a>";
}