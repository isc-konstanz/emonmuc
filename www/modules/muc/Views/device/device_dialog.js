var device_dialog =
{
    'ctrlid': null,
    'driverid': null,
    'driver': null,
    'device': null,

    'drivers': null,

    'loadNew': function(driver) {
        if (driver != null) {
            this.ctrlid = driver.ctrlid;
            this.driverid = driver.id;
            this.driver = driver.name;
        }
        else {
            this.ctrlid = null;
            this.driverid = null;
            this.driver = null;
        }
        this.device = null;
        
        this.drawConfig();
    },

    'loadConfig': function(device) {
        this.ctrlid = null;
        this.driverid = null;
        this.driver = null;
        this.device = device;
        
        this.drawConfig();
    },

    'drawConfig':function() {
        $("#device-config-modal").modal('show');
        
        this.adjustConfig();
        
        var groups = {
            address: "Device address",
            settings: "Device settings",
            configs: "Configuration"
        };
        config.load($('#device-config-container'), groups);
        
        $('#device-config-driver').html('<span style="color:#888"><em>loading...</em></span>');
        $("#device-config-driver-select").empty().hide();
        $('#device-config-info').text('').hide();
        
        if (device_dialog.device != null) {
            $('#device-config-label').html('Configure Device: <b>'+device_dialog.device.id+'</b>');
            $('#device-config-driver').html('<b>'+device_dialog.device.driver+'</b>').show();
            $('#device-config-name').val(device_dialog.device.id);
            $('#device-config-description').val(device_dialog.device.description);
            
            if (typeof device_dialog.device.scanned !== 'undefined' && device_dialog.device.scanned) {
                $('#device-config-back').show();
                $('#device-config-scan').hide();
                $('#device-config-delete').hide();
            }
            else {
                $('#device-config-back').hide();
                $('#device-config-scan').hide();
                $('#device-config-delete').show();
            }
            device_dialog.drawPreferences('config');
        }
        else {
            $('#device-config-label').html('New Device');
            $('#device-config-name').val('');
            $('#device-config-description').val('');
            
            $('#device-config-back').hide();
            $('#device-config-delete').hide();
            
            if (device_dialog.driverid != null) {
                $('#device-config-driver').html('<b>'+device_dialog.driver+'</b>').show();

                $('#device-config-scan').hide();
                
                device_dialog.drawPreferences('config');
            }
            else {
                $('#device-config-scan').show();
                
                device_dialog.drawDrivers('config');
            }
        }
        device_dialog.registerConfigEvents();
    },

    'drawDrivers':function(modal) {
        if (device_dialog.drivers != null) {
            // Append drivers from database to select
            var driverSelect = $('#device-'+modal+'-driver-select');
            driverSelect.append("<option selected hidden='true' value=''>Select a driver</option>");
            
            var ctrl = null;
            for (var i in device_dialog.drivers) {
                var driver = device_dialog.drivers[i];
                
                if (ctrl !== driver.ctrlid) {
                    ctrl = driver.ctrlid;
                    driverSelect.append('<optgroup label="'+driver.ctrl+'">');
                }
                driverSelect.append('<option value="'+driver.id+'" ctrlid="'+driver.ctrlid+'">'+driver.name+'</option>');
            }
            if (device_dialog.driverid != null) {
                driverSelect.val(device_dialog.driverid);
            }
            driverSelect.show();
            if (modal == 'config') {
                $('#device-config-driver').hide();
            }
        }
        else {
            $.ajax({ url: path+"muc/driver/registered.json", dataType: 'json', async: true, success: function(result) {
                if (typeof result.success !== 'undefined' && !result.success) {
                    alert('Registered drivers could not be retrieved:\n'+result.message);

                    $('#device-'+modal+'-modal').modal('hide');
                    return;
                }
                device_dialog.drivers = result;
                device_dialog.drawDrivers(modal);
            }});
        }
    },

    'drawPreferences':function(modal) {
        $('#device-'+modal+'-loader').show();
        
        var ctrlid;
        var driverid;
        if (device_dialog.device != null) {
            ctrlid = device_dialog.device.ctrlid;
            driverid = device_dialog.device.driverid;
        }
        else {
            ctrlid = device_dialog.ctrlid;
            driverid = device_dialog.driverid;
        }
        
        device.info(ctrlid, driverid, function(result) {
            if (typeof result.success !== 'undefined' && !result.success) {
                $('#device-'+modal+'-info').text('').hide();
                
                alert('Device info could not be retrieved:\n'+result.message);
            }
            else {
                if (typeof result.description !== 'undefined') {
                    $('#device-'+modal+'-info').html('<span style="color:#888">'+result.description+'</span>').show();
                }
                else {
                    $('#device-'+modal+'-info').text('').hide();
                }
                
                config.draw(device_dialog.device, result);
            }
            $('#device-'+modal+'-loader').hide();
        });
    },

    'closeConfig':function(result) {
        $('#device-config-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Device could not be configured:\n'+result.message);
            return false;
        }
        update();
        $('#device-config-modal').modal('hide');
    },

    'adjustConfig':function() {
        if ($("#device-config-modal").length) {
            var h = $(window).height() - $("#device-config-modal").position().top - 180;
            $("#device-config-body").height(h);
        }
    },

    'registerConfigEvents':function() {

        $('#device-config-driver-select').off('change').on('change', function() {
            device_dialog.ctrlid = $('option:selected', this).attr('ctrlid');
            device_dialog.driverid = this.value;
            device_dialog.driver = $('option:selected', this).text();
            device_dialog.device = null;
            
            device_dialog.drawPreferences('config');
        });

        $("#device-config-save").off('click').on('click', function () {
            var id = $('#device-config-name').val();
            
            if (id == '' || (device_dialog.device == null && device_dialog.driverid == null)) {
                alert('Device needs to be configured first.');
                return false;
            }
            if (!config.valid()) {
                alert('Required parameters need to be configured first.');
                return false;
            }
            $('#device-config-loader').show();
            
            var configs = { 'id': id, 'description': $('#device-config-description').val() };
            
            configs['address'] = config.encode('address');
            configs['settings'] = config.encode('settings');
            
            // Make sure JSON.stringify gets passed the right object type
            configs['configs'] = $.extend({}, config.get('configs'));
            
            if (device_dialog.device != null 
                    && !(typeof device_dialog.device.scanned !== 'undefined' && device_dialog.device.scanned)) {
                
                if (device_dialog.device['disabled'] != null) {
                    configs['disabled'] = device_dialog.device['disabled'];
                }
                configs['channels'] = $.extend([], device_dialog.device.channels);
                
                var foo = JSON.stringify(configs);
                
                result = device.update(device_dialog.device.ctrlid, device_dialog.device.id, configs, 
                        device_dialog.closeConfig);
            }
            else {
                result = device.create(device_dialog.ctrlid, device_dialog.driverid, configs, 
                        device_dialog.closeConfig);
            }
        });

        $("#device-config-back").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            $('#device-scan-modal').modal('show');
        });

        $("#device-config-scan").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            
            var driver = null;
            if (device_dialog.driverid != null) {
                driver = {
                    'ctrlid':device_dialog.ctrlid,
                    'id':device_dialog.driverid,
                    'name':device_dialog.driver
                }
            }
            device_dialog.loadScan(driver);
        });

        $("#device-config-delete").off('click').on('click', function () {
            $('#device-config-modal').modal('hide');
            
            device_dialog.loadDelete(device_dialog.device);
        });
    },

    'loadScan':function(driver) {
        if (driver != null) {
            this.ctrlid = driver.ctrlid;
            this.driverid = driver.id;
            this.driver = driver.name;
        }
        else {
            this.ctrlid = null;
            this.driverid = null;
            this.driver = null;
        }
        this.device = null;
        
        this.scanUpdater = null;
        this.scanDevices = [];
        this.drawScan();
    },

    'drawScan':function() {
        $("#device-scan-modal").modal('show');
        
        device_dialog.adjustScan();
        
        var groups = {
            scanSettings: "Scan settings"
        };
        config.load($('#device-scan-container'), groups);
        
        $('#device-scan-progress-bar').css('width', '100%');
        $('#device-scan-progress').removeClass('progress-default progress-info progress-success progress-warning progress-error').hide();
        $('#device-scan-results').text('').hide();
        
        if (device_dialog.driverid != null) {
            $('#device-scan-label').html('Scan Devices: <b>'+device_dialog.driver+'</b>');
            $("#device-scan-driver-select").hide().empty();
            $('#device-scan-driver').hide();
            
            device_dialog.drawPreferences('scan');
        }
        else {
            $('#device-scan-label').html('Scan Devices');
            $("#device-scan-driver-select").show().empty();
            $('#device-scan-driver').show();
            
            device_dialog.drawDrivers('scan');
        }
        device_dialog.registerScanEvents();
    },

    'drawScanProgress':function(progress) {
        device_dialog.drawScanProgressBar(progress);
        
        if (!progress.success) {
            alert(progress.message);
            return;
        }
        
        device_dialog.scanDevices = progress.devices;
        if (device_dialog.scanDevices.length > 0) {
            
            $('#device-scan-results').show();
            $('#device-scan-results-none').hide();
            
            var list = '';
            for (var i = 0; i < device_dialog.scanDevices.length; i++) {
                var name = device_dialog.scanDevices[i].id;
                var description = device_dialog.scanDevices[i].description;
                list += '<li class="device-scan-row" title="Add" data-row='+i+'>' +
                        name+(description.length>0 ? ": <em style='color:#888'>"+description+"</em>" : "") +
                    '</li>';
            }
            $('#device-scan-results').html(list);
        }
        else {
            $('#device-scan-results').hide();
            $('#device-scan-results-none').show();
        }
    },

    'drawScanProgressBar':function(progress) {
        var bar = $('#device-scan-progress');

        var value = 100;
        var type = 'danger';
        if (progress.success) {
            value = progress.info.progress;
            
            if (progress.info.interrupted) {
                value = 100;
                type = 'warning';
            }
            else if (progress.info.finished) {
                value = 100;
                type = 'success';
            }
            else if (value > 0) {
                // If the progress value equals zero, set it to 5%, so the user can see the bar already
                if (value == 0) {
                    value = 5;
                }
                type = 'info';
            }
            else {
                value = 100;
                type = 'default';
            }
        }
        
        if (bar.css('width') == $('#device-scan-progress-bar').css('width')) {
            bar.html("<div id='device-scan-progress-bar' class='bar' style='width:"+value+"%;'></div>");
        }
        else {
            $('#device-scan-progress-bar').css('width', value+'%');
        }
        
        if (value < 100 || type == 'default') {
            bar.addClass('active');
        }
        else {
            bar.removeClass('active');
        }
        bar.removeClass('progress-default progress-info progress-success progress-warning progress-danger');
        bar.addClass('progress-'+type);
        bar.show();
    },

    'scanProgress':function(progress) {
        if (device_dialog.scanUpdater != null) {
            clearTimeout(device_dialog.scanUpdater);
            device_dialog.scanUpdater = null;
        }
        device_dialog.drawScanProgress(progress);
        
        // Continue to schedule scan progress requests every second until the scan info signals completion
        if (progress.success && !progress.info.finished && !progress.info.interrupted) {
            
            device_dialog.scanUpdater = setTimeout(function() {
                device.scanProgress(device_dialog.ctrlid, device_dialog.driverid, device_dialog.scanProgress);
            }, 1000);
        }
    },

    'adjustScan':function() {
        if ($("#device-scan-modal").length) {
            var h = $(window).height() - $("#device-scan-modal").position().top - 180;
            $("#device-scan-body").height(h);
        }
    },

    'registerScanEvents':function() {

        $('#device-scan-driver-select').off('change').on('change', function(){
            device_dialog.ctrlid = $('option:selected', this).attr('ctrlid');
            device_dialog.driverid = this.value;
            device_dialog.driver = $('option:selected', this).text();
            device_dialog.device = null;
            
            device_dialog.drawPreferences('scan');
            
            $('#device-scan-results').hide();
            $('#device-scan-results-none').hide();
        });

        $("#device-scan-start").off('click').on('click', function () {
            if (device_dialog.driverid == null) {
                alert('Driver needs to be configured first.');
                return false;
            }
            if (!config.valid()) {
                alert('Required parameters need to be configured first.');
                return false;
            }
            $('#device-scan-loader').show();
            
            var settings = config.encode('scanSettings');
            
            device.scanStart(device_dialog.ctrlid, device_dialog.driverid, settings, function(result) {
                $('#device-scan-loader').hide();
                
                device_dialog.scanProgress(result);
            });
        });

        $('#device-scan-results').on('click', '.device-scan-row', function() {
            var row = $(this).data('row');
            var device = device_dialog.scanDevices[row];
            device['driverid'] = device_dialog.driverid;
            device['driver'] = device_dialog.driver;
            device['scanned'] = true;
            
            $("#device-scan-modal").modal('hide');
            device_dialog.device = device;
            device_dialog.drawConfig();
        });
    },

    'loadDelete': function(device, tablerow) {
        this.ctrlid = null;
        this.driverid = null;
        this.driver = null;
        this.device = device;
        
        $('#device-delete-modal').modal('show');
        $('#device-delete-label').html('Delete Device: <b>'+device.id+'</b>');
        
        this.registerDeleteEvents(tablerow);
    },

    'closeDelete':function(result) {
        $('#device-delete-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Unable to delete device:\n'+result.message);
            return false;
        }
        
        update();
        $('#device-delete-modal').modal('hide');
    },

    'registerDeleteEvents':function(row) {
        
        $("#device-delete-confirm").off('click').on('click', function() {
            $('#device-delete-loader').show();
            device.remove(device_dialog.device.ctrlid, device_dialog.device.id,
                    device_dialog.closeDelete);
            
            if (typeof table !== 'undefined' && row != null) table.remove(row);
        });
    }
}
