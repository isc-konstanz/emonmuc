var channel_dialog = {
    'ctrlid': null,
    'driverid': null,
    'deviceid': null,
    'channel': null,

    'loadNew':function(device) {
        if (device != null) {
            this.ctrlid = device.ctrlid;
            this.driverid = device.driverid;
            this.deviceid = device.id;
        }
        else {
            this.ctrlid = null;
            this.driverid = null;
            this.deviceid = null;
        }
        this.channel = null;
        
        this.drawConfig();
    },

    'loadConfig': function(channel) {
        this.ctrlid = null;
        this.driverid = null;
        this.deviceid = null;
        this.channel = channel;
        
        this.drawConfig();
    },

    'drawConfig':function() {
        $("#channel-config-modal").modal('show');
        
        channel_dialog.adjustConfig();
        
        var groups = {
            address: "Channel address",
            settings: "Channel settings",
            logging: "Logging settings",
            configs: "Configuration"
        };
        config.load($('#channel-config-container'), groups);
        
        $('#channel-config-device').html('<span style="color:#888"><em>loading...</em></span>');
        $("#channel-config-device-select").empty().hide();
        $('#channel-config-info').text('').hide();
        
        if (channel_dialog.channel != null) {
            $('#channel-config-label').html('Configure Channel: <b>'+channel_dialog.channel.id+'</b>');
            $('#channel-config-device').html('<b>'+channel_dialog.channel.deviceid+'</b>').show();
            $('#channel-config-name').val(channel_dialog.channel.id);
            $('#channel-config-description').val(channel_dialog.channel.description);
            
            
            if (typeof channel_dialog.channel.scanned !== 'undefined' && channel_dialog.channel.scanned) {
                $('#channel-config-back').show();
                $('#channel-config-scan').hide();
                $('#channel-config-delete').hide();
            }
            else {
                $('#channel-config-back').hide();
                $('#channel-config-scan').hide();
                $('#channel-config-delete').show();
            }
            channel_dialog.drawPreferences('config');
        }
        else {
            $('#channel-config-label').html('New Channel');
            $('#channel-config-name').val('');
            $('#channel-config-description').val('');

            $('#channel-config-back').hide();
            $('#channel-config-delete').hide();
            
            if (channel_dialog.driverid != null) {
                $('#channel-config-device').html('<b>'+channel_dialog.deviceid+'</b>').show();
                
                $('#channel-config-scan').hide();
                
                channel_dialog.drawPreferences('config');
            }
            else {
                $('#channel-config-scan').show();

                channel_dialog.drawDevices('config');
            }
        }
        channel_dialog.registerConfigEvents();
    },

    'drawDevices':function(modal) {
        device.list(function(data, textStatus, xhr) {
            // Append devices from database to select
            var deviceSelect = $('#channel-'+modal+'-device-select');
            if (data.length > 0) {
                deviceSelect.append("<option selected hidden='true' value=''>Select a device</option>");
                
                $.each(data, function() {
                    if (this.ctrlid > 0) {
                        deviceSelect.append('<option value="'+this.id+'" ctrlid="'+this.ctrlid+'" driverid="'+this.driverid+'">'+this.id+'</option>');
                    }
                });
            }
            else {
                deviceSelect.prop('disabled', true);
                alert('Unable to find configured devices');
            }
            
            deviceSelect.show();
            if (modal == 'config') {
                $('#channel-config-device').hide();
            }
        });
    },

    'drawPreferences':function(modal) {
        $('#channel-'+modal+'-loader').show();
        
        var ctrlid;
        var driverid;
        if (channel_dialog.channel != null) {
            ctrlid = channel_dialog.channel.ctrlid;
            driverid = channel_dialog.channel.driverid;
        }
        else {
            ctrlid = channel_dialog.ctrlid;
            driverid = channel_dialog.driverid;
        }
        
        channel.info(ctrlid, driverid, function(result) {
            if (typeof result.success !== 'undefined' && !result.success) {
                $('#channel-'+modal+'-info').text('').hide();
                
                alert('Channel info could not be retrieved:\n'+result.message);
            }
            else {
                if (typeof result.description !== 'undefined') {
                    $('#channel-'+modal+'-info').html('<span style="color:#888">'+result.description+'</span>').show();
                }
                else {
                    $('#channel-'+modal+'-info').text('').hide();
                }
                
                config.draw(channel_dialog.channel, result);
            }
            $('#channel-'+modal+'-loader').hide();
        });
    },

    'closeConfig':function(result) {
        $('#channel-config-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Channel could not be configured:\n'+result.message);
            return false;
        }
        update();
        $('#channel-config-modal').modal('hide');
    },

    'adjustConfig':function() {
        if ($("#channel-config-modal").length) {
            var h = $(window).height() - $("#channel-config-modal").position().top - 180;
            $("#channel-config-body").height(h);
        }
    },

    'registerConfigEvents':function() {

        $('#channel-config-device-select').off('change').on('change', function() {
            channel_dialog.ctrlid = $('option:selected', this).attr('ctrlid');
            channel_dialog.driverid = $('option:selected', this).attr('driverid');
            channel_dialog.deviceid = this.value;
            channel_dialog.channel = null;
            
            channel_dialog.drawPreferences('config');
        });

        $("#channel-config-save").off('click').on('click', function () {
            var id = $('#channel-config-name').val();
            
            if (id == '' || (channel_dialog.channel == null && channel_dialog.deviceid == null)) {
                alert('Channel needs to be configured first.');
                return false;
            }
            if (!config.valid()) {
                alert('Required parameters need to be configured first.');
                return false;
            }
            $('#channel-config-loader').show();
            
            var driverid = channel_dialog.driverid != null ? channel_dialog.driverid : channel_dialog.channel.driverid;
            var deviceid = channel_dialog.deviceid != null ? channel_dialog.deviceid : channel_dialog.channel.deviceid;
            var description = $('#channel-config-description').val();
            
            var configs = {
            		'id': id,
            		'driverid': driverid,
            		'deviceid': deviceid,
            		'description': description,
            };
            
            configs['address'] = config.encode('address');
            configs['settings'] = config.encode('settings');
            
            // Make sure JSON.stringify gets passed the right object type
            configs['logging'] = $.extend({}, config.get('logging'));
            configs['configs'] = $.extend({}, config.get('configs'));
            
            if (channel_dialog.channel != null 
                    && !(typeof channel_dialog.channel.scanned !== 'undefined' && channel_dialog.channel.scanned)) {
                
                if (channel_dialog.channel['disabled'] != null) {
                    configs['disabled'] = channel_dialog.channel['disabled'];
                }
                
                result = channel.update(channel_dialog.channel.ctrlid, channel_dialog.channel.nodeid, 
                        channel_dialog.channel.id, configs, 
                        channel_dialog.closeConfig);
            }
            else {
                channel.create(channel_dialog.ctrlid, channel_dialog.driverid, channel_dialog.deviceid, configs,
                        channel_dialog.closeConfig);
            }
        });

        $("#channel-config-back").off('click').on('click', function () {
            $('#channel-config-modal').modal('hide');
            $('#channel-scan-modal').modal('show');
        });

        $("#channel-config-scan").off('click').on('click', function () {
            $('#channel-config-modal').modal('hide');
            
            channel_dialog.loadScan();
        });

        $("#channel-config-delete").off('click').on('click', function () {
            $('#channel-config-modal').modal('hide');
            
            channel_dialog.loadDelete(channel_dialog.channel);
        });
    },

    'loadScan':function(device) {
        if (device != null) {
            this.ctrlid = device.ctrlid;
            this.driverid = device.driverid;
            this.deviceid = device.id;
        }
        else {
            this.ctrlid = null;
            this.driverid = null;
            this.deviceid = null;
        }
        this.device = null;

        this.scanChannels = [];
        this.drawScan();
    },

    'drawScan':function() {
        $("#channel-scan-modal").modal('show');
        
        channel_dialog.adjustScan();
        
        var groups = {
            scanSettings: "Scan settings"
        };
        config.load($('#channel-scan-container'), groups);
        
        $('#channel-scan-results').text('').hide();
        
        if (channel_dialog.deviceid != null) {
            $('#channel-scan-label').html('Scan Channels: <b>'+channel_dialog.deviceid+'</b>');
            $("#channel-scan-device-select").hide().empty();
            $('#channel-scan-device').hide();
            
            channel_dialog.drawPreferences('scan');
        }
        else {
            $('#channel-scan-label').html('Scan Devices');
            $("#channel-scan-device-select").show().empty();
            $('#channel-scan-device').show();
            
            channel_dialog.drawDevices('scan');
        }
        channel_dialog.registerScanEvents();
    },

    'drawScanProgress':function() {
        if (channel_dialog.scanChannels.length > 0) {
            $('#channel-scan-results').show();
            $('#channel-scan-results-none').hide();
            
            var list = '';
            for (var i = 0; i < channel_dialog.scanChannels.length; i++) {
            	list += '<li class="channel-scan-row" title="Add" data-row='+i+'>'+channel_dialog.scanChannels[i]['description']+'</li>';
            }
            $('#channel-scan-results').html(list);
        }
        else {
            $('#channel-scan-results').hide();
            $('#channel-scan-results-none').show();
        }
    },

    'adjustScan':function() {
        if ($("#channel-scan-modal").length) {
            var h = $(window).height() - $("#channel-scan-modal").position().top - 180;
            $("#channel-scan-body").height(h);
        }
    },

    'registerScanEvents':function() {

        $('#channel-scan-device-select').off('change').on('change', function(){
            channel_dialog.ctrlid = $('option:selected', this).attr('ctrlid');
            channel_dialog.driverid = $('option:selected', this).attr('driverid');
            channel_dialog.deviceid = this.value;
            channel_dialog.scanSettings = null;
            
            channel_dialog.drawPreferences('scan');
            
            $('#channel-scan-results').hide();
            $('#channel-scan-results-none').hide();
        });

        $("#channel-scan-start").off('click').on('click', function () {
            if (channel_dialog.deviceid == null) {
                alert('Device needs to be configured first.');
                return false;
            }
            if (!config.valid()) {
                alert('Required parameters need to be configured first.');
                return false;
            }
            $('#channel-scan-loader').show();
            
            var settings = config.encode('scanSettings');
            
            channel.scan(channel_dialog.ctrlid, channel_dialog.driverid, channel_dialog.deviceid, settings, function(result) {
                $('#channel-scan-loader').hide();
                
                if (typeof result.success !== 'undefined' && !result.success) {
                    alert('Channel scan failed:\n'+result.message);
                    return false;
                }
                channel_dialog.scanChannels = result;
                channel_dialog.drawScanProgress();
            });
        });

        $('#channel-scan-results').on('click', '.channel-scan-row', function() {
            var row = $(this).data('row');
            var channel = channel_dialog.scanChannels[row];
            channel['id'] = channel_dialog.deviceid + '_channel';
            channel['driverid'] = channel_dialog.driverid;
            channel['scanned'] = true;
            
            $("#channel-scan-modal").modal('hide');
            channel_dialog.channel = channel;
            channel_dialog.drawConfig();
        });
    },

    'loadDelete': function(channel, tablerow) {
        this.ctrlid = null;
        this.driverid = null;
        this.deviceid = null;
        this.channel = channel;
        
        $('#channel-delete-modal').modal('show');
        $('#channel-delete-label').html('Delete Channel: <b>'+channel.id+'</b>');
        
        this.registerDeleteEvents(tablerow);
    },

    'closeDelete':function(result) {
        $('#channel-delete-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Unable to delete channel:\n'+result.message);
            return false;
        }
        
        update();
        $('#channel-delete-modal').modal('hide');
    },

    'registerDeleteEvents':function(row) {
        
        $("#channel-delete-confirm").off('click').on('click', function() {
            $('#channel-delete-loader').show();
            channel.remove(channel_dialog.channel.ctrlid, channel_dialog.channel.id,
                    channel_dialog.closeDelete);
            
            if (typeof table !== 'undefined' && row != null) table.remove(row);
        });
    }
}
