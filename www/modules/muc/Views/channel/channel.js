var channel = {
    states: null,

    'create':function(ctrlid, driverid, deviceid, configs, callback) {
        return $.ajax({
            url: path+"muc/channel/create.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid+"&deviceid="+deviceid+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'list':function(callback) {
        return $.ajax({
            url: path+"muc/channel/list.json",
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'listStates':function(callback) {
        return $.ajax({
            url: path+"muc/channel/states.json",
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'get':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/channel/get.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'info':function(ctrlid, driverid, callback) {
        return $.ajax({
            url: path+"muc/channel/info.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'scan':function(ctrlid, driverid, deviceid, settings, callback) {
        return $.ajax({
            url: path+"muc/channel/scan.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid+"&deviceid="+deviceid+"&settings="+JSON.stringify(settings),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'write':function(ctrlid, id, value, valueType, callback) {
        return $.ajax({
            url: path+"muc/channel/write.json",
            data: "ctrlid="+ctrlid+"&id="+id+"&value="+value+"&valueType="+valueType,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'update':function(ctrlid, node, id, configs, callback) {
        return $.ajax({
            url: path+"muc/channel/update.json",
            data: "ctrlid="+ctrlid+"&nodeid="+node+"&id="+id+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'remove':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/channel/delete.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    }

}
