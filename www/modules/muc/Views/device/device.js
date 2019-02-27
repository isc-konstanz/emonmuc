var device = {

    create:function(ctrlid, driverid, configs, callback) {
        return device.request(callback, "muc/device/create.json", "ctrlid="+ctrlid+"&driverid="+driverid+
    		"&configs="+JSON.stringify(configs));
    },

    list:function(callback) {
        return device.request(callback, "muc/device/list.json");
    },

    states:function(callback) {
        return device.request(callback, "muc/device/states.json");
    },

    info:function(ctrlid, driverid, callback) {
        return device.request(callback, "muc/device/info.json", "ctrlid="+ctrlid+"&driverid="+driverid);
    },

    get:function(ctrlid, id, callback) {
        return device.request(callback, "muc/device/get.json", "ctrlid="+ctrlid+"&id="+id);
    },

    scanStart:function(ctrlid, driverid, settings, callback) {
        return device.request(callback, "muc/device/scan/start.json", "ctrlid="+ctrlid+"&driverid="+driverid+"&settings="+settings);
    },

    scanProgress:function(ctrlid, driverid, callback) {
        return device.request(callback, "muc/device/scan/progress.json", "ctrlid="+ctrlid+"&driverid="+driverid);
    },

    scanCancel:function(ctrlid, driverid, callback) {
        return device.request(callback, "muc/device/scan/cancel.json", "ctrlid="+ctrlid+"&driverid="+driverid);
    },

    update:function(ctrlid, id, configs, callback) {
        return device.request(callback, "muc/device/update.json", "ctrlid="+ctrlid+"&id="+id+
    		"&configs="+JSON.stringify(configs));
    },

    remove:function(ctrlid, id, callback) {
        return device.request(callback, "muc/device/delete.json", "ctrlid="+ctrlid+"&id="+id);
    },

    request:function(callback, action, data) {
    	var request = {
	        url: path+action,
	        dataType: 'json',
	        async: true,
	        success: callback,
	        error: function(error) {
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
//	        	return device.request(callback, action, data);
	        }
	    }
		if (typeof data !== 'undefined') {
			request['data'] = data;
		}
	    return $.ajax(request);
    }
}
