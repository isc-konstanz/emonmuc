var channel = {

    create:function(ctrlid, driverid, deviceid, configs, callback) {
        return channel.request(callback, "muc/channel/create.json", "ctrlid="+ctrlid+"&driverid="+driverid+"&deviceid="+deviceid+
        		"&configs="+JSON.stringify(configs));
    },

    list:function(callback) {
        return channel.request(callback, "muc/channel/list.json");
    },

    states:function(callback) {
        return channel.request(callback, "muc/channel/states.json");
    },

    records:function(callback) {
        return channel.request(callback, "muc/channel/records.json");
    },

    info:function(ctrlid, driverid, callback) {
        return channel.request(callback, "muc/channel/info.json", "ctrlid="+ctrlid+"&driverid="+driverid);
    },

    scan:function(ctrlid, driverid, deviceid, settings, callback) {
        return channel.request(callback, "muc/channel/scan.json", "ctrlid="+ctrlid+"&driverid="+driverid+"&deviceid="+deviceid+
        		"&settings="+JSON.stringify(settings));
    },

    get:function(ctrlid, id, callback) {
        return channel.request(callback, "muc/channel/get.json", "ctrlid="+ctrlid+"&id="+id);
    },

    write:function(ctrlid, id, value, valueType, callback) {
        return channel.request(callback, "muc/channel/write.json", "ctrlid="+ctrlid+"&id="+id+"&value="+value+"&valueType="+valueType);
    },

    update:function(ctrlid, node, id, configs, callback) {
        return channel.request(callback, "muc/channel/update.json", "ctrlid="+ctrlid+"&nodeid="+node+"&id="+id+
        		"&configs="+JSON.stringify(configs));
    },

    remove:function(ctrlid, id, callback) {
        return channel.request(callback, "muc/channel/delete.json", "ctrlid="+ctrlid+"&id="+id);
    },

    request:function(callback, action, data) {
    	var request = {
	        'url': path+action,
	        'dataType': 'json',
	        'async': true,
	        'success': callback,
	        'error': function(error) {
	            var message = "Failed to request server";
	            if (typeof error !== 'undefined') {
	                message += ": ";
	                
	                if (typeof error.responseText !== 'undefined') {
	                    message += error.responseText;
	                }
	                else if (typeof error !== 'string') {
	                    message += JSON.stringify(error);
	                }
	                else {
	                    message += error;
	                }
	            }
	            console.warn(message);
	            if (typeof callback === 'function') {
		            callback({
		            	'success': false,
		            	'message': message
		            });
	            }
//	        	return channel.request(callback, action, data);
	        }
	    }
		if (typeof data !== 'undefined') {
			request['data'] = data;
		}
	    return $.ajax(request);
    }
}
