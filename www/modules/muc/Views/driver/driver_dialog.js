var driver_dialog = {
    'ctrlid': null,
    'driverid': null,
    'driver': null,

    'loadNew': function(ctrl) {
        if (ctrl != null) {
            this.ctrlid = ctrl.id;
        }
        else {
            this.ctrlid = null;
        }
        this.driverid = null;
        this.driver = null;
        
        this.drawConfig();
        this.registerConfigEvents();
    },

    'loadConfig': function(driver) {
        this.ctrlid = null
        this.driverid = driver.id;
        this.driver = driver;
        
        this.drawConfig();
        this.registerConfigEvents();
    },

    'drawConfig':function() {
        $("#driver-config-modal").modal('show');
        
        driver_dialog.adjustConfig();
        
        var groups = {
            configs: "Configuration"
        };
        config.load($('#driver-config-container'), groups);

        $('#driver-config-description').text('');
        
        if (driver_dialog.driver != null) {
            $('#driver-config-label').html('Configure Driver: <b>'+driver_dialog.driver.name+'</b>');
            $('#driver-config-select').hide().text('');
            $("#driver-config-ctrl-select").text('');
            $('#driver-config-ctrl').hide();
            
            $('#driver-config-delete').show();
            
            driver_dialog.drawPreferences()
        }
        else {
            $('#driver-config-label').html('New Driver');
            $('#driver-config-select').show().prop('disabled', true).empty();
            $("#driver-config-ctrl-select").text('');
            $('#driver-config-ctrl').show();
            
            $('#driver-config-delete').hide();
            
            if (this.ctrlid != null) {
                $('#driver-config-ctrl').hide();
                
                this.drawDrivers();
            }
            else {
                // Append MUCs from database to select
                muc.list(function(data, textStatus, xhr) {
                    ctrlSelect = $("#driver-config-ctrl-select");
                    ctrlSelect.append("<option selected hidden='true' value=''>Select a controller</option>").val('');
                    
                    $.each(data, function() {
                        ctrlSelect.append($("<option />").val(this.id).text(this.description));
                    });
                });
            }
        }
    },

    'drawDrivers':function() {
        if (driver_dialog.ctrlid > 0) {
            $('#driver-config-loader').show();
            
            driver.unconfigured(driver_dialog.ctrlid, function(result) {
                if (typeof result.success !== 'undefined' && !result.success) {
                    alert('Configured drivers could not be retrieved:\n'+result.message);
                    return false;
                }
                if (result.length > 0) {
                    var driverSelect = $("#driver-config-select");
                    driverSelect.prop('disabled', false);
                    driverSelect.append("<option selected hidden='true' value=''>Select a driver</option>").val('');
                    
                    for (var i = 0; i < result.length; i++) {
                        var driverDesc = result[i];
                        var driverName;
                        if (typeof driverDesc.name !== 'undefined') {
                            driverName = driverDesc.name;
                        }
                        else {
                            driverName = driverDesc.id;
                        }
                        driverSelect.append($("<option />").val(driverDesc.id).text(driverName));
                    }
                }
                $('#driver-config-loader').hide();
            });
        }
    },

    'drawPreferences':function() {
        $('#driver-config-loader').show();
        
        var ctrlid;
        var driverid;
        if (driver_dialog.driver != null) {
            ctrlid = driver_dialog.driver.ctrlid;
            driverid = driver_dialog.driver.id;
        }
        else {
            ctrlid = driver_dialog.ctrlid;
            driverid = driver_dialog.driverid;
        }
        
        driver.info(ctrlid, driverid, function(result) {
            if (typeof result.success !== 'undefined' && !result.success) {
                alert('Driver info could not be retrieved:\n'+result.message);
            }
            else {
                if (typeof result.description !== 'undefined') {
                    $('#driver-config-description').html('<span style="color:#888">'+result.description+'</span>');
                }
                
                config.draw(driver_dialog.driver, result);
            }
            $('#driver-config-loader').hide();
        });
    },

    'closeConfig':function(result) {
        $('#driver-config-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Driver could not be configured:\n'+result.message);
            return false;
        }
        update();
        $('#driver-config-modal').modal('hide');
    },

    'adjustConfig':function() {
        if ($("#driver-config-modal").length) {
            var h = $(window).height() - $("#driver-config-modal").position().top - 180;
            $("#driver-config-body").height(h);
        }
    },

    'registerConfigEvents':function() {

        $('#driver-config-ctrl-select').off('change').on('change', function(){
            var ctrlid = this.value;
            if (ctrlid.length > 0) {
                driver_dialog.ctrlid = ctrlid;
                driver_dialog.driverid = null;
                driver_dialog.driver = null;
                
                driver_dialog.drawDrivers();
            }
        });

        $('#driver-config-select').off('change').on('change', function(){
            var driverid = this.value;
            if (driverid.length > 0) {
                driver_dialog.driverid = driverid;
                driver_dialog.driver = null;
                
                driver_dialog.drawPreferences();
            }
        });

        $("#driver-config-save").off('click').on('click', function () {
            if (driver_dialog.driver == null && (driver_dialog.ctrlid == null || driver_dialog.driverid == null)) {
                alert('Driver needs to be configured first.');
                return false;
            }
            if (!config.valid()) {
                alert('Required parameters need to be configured first.');
                return false;
            }
            $('#driver-config-loader').show();
            
            var configs = { 'id': driver_dialog.driverid };
            
            // Make sure JSON.stringify gets passed the right object type
            configs['configs'] = $.extend({}, config.get('configs'));
            
            var result;
            if (driver_dialog.driver != null) {
                configs['devices'] = $.extend([], driver_dialog.driver.devices);
                
                result = driver.update(driver_dialog.driver.ctrlid, driver_dialog.driver.id, configs, 
                        driver_dialog.closeConfig);
            }
            else {
                result = driver.create(driver_dialog.ctrlid, driver_dialog.driverid, configs, 
                        driver_dialog.closeConfig); 
            }
        });

        $("#driver-config-delete").off('click').on('click', function () {
            
            $('#driver-config-modal').modal('hide');
            
            driver_dialog.loadDelete(driver_dialog.driver);
        });
    },

    'loadDelete': function(driver, tablerow) {
        this.ctrlid = null;
        this.driverid = null;
        this.driver = driver;
        
        $('#driver-delete-modal').modal('show');
        $('#driver-delete-label').html('Delete Driver: <b>'+driver.name+'</b>');
        
        this.registerDeleteEvents(tablerow, null);
    },

    'closeDelete':function(result) {
        $('#driver-delete-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Unable to delete driver:\n'+result.message);
            return false;
        }
        
        update();
        $('#driver-delete-modal').modal('hide');
    },

    'registerDeleteEvents':function(row) {
        
        $("#driver-delete-confirm").off('click').on('click', function() {
            $('#driver-delete-loader').show();
            driver.remove(driver_dialog.driver.ctrlid, driver_dialog.driver.id, 
                    driver_dialog.closeDelete);
            
            if (typeof table !== 'undefined' && row != null) table.remove(row);
        });
    }
}
