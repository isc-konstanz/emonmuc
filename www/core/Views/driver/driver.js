var driver = {

    create:function(ctrlid, id, configs, callback) {
    return driver.request(callback, "muc/driver/create.json", "ctrlid="+ctrlid+"&id="+id+
    		"&configs="+JSON.stringify(configs));
    },

    list:function(callback) {
        return driver.request(callback, "muc/driver/list.json");
    },

    unconfigured:function(ctrlid, callback) {
        return driver.request(callback, "muc/driver/unconfigured.json");
    },

    info:function(ctrlid, id, callback) {
        return driver.request(callback, "muc/driver/info.json", "ctrlid="+ctrlid+"&id="+id);
    },

    get:function(ctrlid, id, callback) {
        return driver.request(callback, "muc/driver/get.json", "ctrlid="+ctrlid+"&id="+id);
    },

    update:function(ctrlid, id, configs, callback) {
        return driver.request(callback, "muc/driver/update.json", "ctrlid="+ctrlid+"&id="+id+
    		"&configs="+JSON.stringify(configs));
    },

    remove:function(ctrlid, id, callback) {
        return driver.request(callback, "muc/driver/delete.json", "ctrlid="+ctrlid+"&id="+id);
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
//	        	return driver.request(callback, action, data);
	        }
	    }
		if (typeof data !== 'undefined') {
			request['data'] = data;
		}
	    return $.ajax(request);
    }
}
