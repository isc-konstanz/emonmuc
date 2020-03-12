var muc = {

    create:function(type, name, description, options, callback) {
        return muc.request(callback, "muc/create.json", "type="+type+"&name="+name+"&description="+description+
            "&options="+JSON.stringify(options));
    },

    list:function(callback, child) {
    	if (child) {
            return muc.request(callback, "muc/list/"+child+".json");
    	}
        return muc.request(callback, "muc/list.json");
    },

    load:function(id, callback) {
        return muc.request(callback, "muc/load.json", "id="+id);
    },

    get:function(id, callback) {
        return muc.request(callback, "muc/get.json", "id="+id);
    },

    update:function(id, fields, callback) {
        return muc.request(callback, "muc/update.json", "id="+id+
            "&fields="+JSON.stringify(fields));
    },

    remove:function(id, callback) {
        return muc.request(callback, "muc/delete.json", "id="+id);
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
//                return muc.request(callback, action, data);
            }
        }
        if (typeof data !== 'undefined') {
            request['data'] = data;
        }
        return $.ajax(request);
    }
}
