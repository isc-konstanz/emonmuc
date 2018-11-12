var driver = {

    'create':function(ctrlid, id, configs, callback) {
        return $.ajax({
            url: path+"muc/driver/create.json",
            data: "ctrlid="+ctrlid+"&id="+id+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'list':function(callback) {
        return $.ajax({
            url: path+"muc/driver/list.json",
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'unconfigured':function(ctrlid, callback) {
        return $.ajax({
            url: path+"muc/driver/unconfigured.json",
            data: "ctrlid="+ctrlid,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'info':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/driver/info.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'get':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/driver/get.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'update':function(ctrlid, id, configs, callback) {
        return $.ajax({
            url: path+"muc/driver/update.json",
            data: "ctrlid="+ctrlid+"&id="+id+"&configs="+JSON.stringify(configs),
            dataType: 'json',
            async: true,
            success: callback
        });
    },

    'remove':function(ctrlid, id, callback) {
        return $.ajax({
            url: path+"muc/driver/delete.json",
            data: "ctrlid="+ctrlid+"&id="+id,
            dataType: 'json',
            async: true,
            success: callback
        });
    }

}
