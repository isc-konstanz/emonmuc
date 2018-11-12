var device = {
    states: null,

    'create':function(ctrlid, driverid, configs, callback) {
        return $.ajax({
            url: path+"muc/device/create.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'list':function(callback) {
        return $.ajax({
            url: path+"muc/device/list.json",
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'listStates':function(callback) {
        return $.ajax({
            url: path+"muc/device/states.json",
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'info':function(ctrlid, driverid, callback) {
        return $.ajax({
            url: path+"muc/device/info.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'get':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/device/get.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'scanStart':function(ctrlid, driverid, settings, callback) {
        return $.ajax({
            url: path+"muc/device/scan/start.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid+"&settings="+settings,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'scanProgress':function(ctrlid, driverid, callback) {
        return $.ajax({
            url: path+"muc/device/scan/progress.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'scanCancel':function(ctrlid, driverid, callback) {
        return $.ajax({
            url: path+"muc/device/scan/cancel.json",
            data: "ctrlid="+ctrlid+"&driverid="+driverid,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'update':function(ctrlid, id, configs, callback) {
        return $.ajax({
            url: path+"muc/device/update.json",
            data: "ctrlid="+ctrlid+"&id="+id+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'remove':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/device/delete.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    }

}
