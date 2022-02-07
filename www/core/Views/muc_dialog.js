var muc_dialog = {
    ctrl: null,

    loadConfig: function(ctrl) {
        if (ctrl != null) {
            this.ctrl = ctrl;
        }
        else {
            this.ctrl = null;
        }
        this.drawConfig();
        
        // Initialize callbacks
        this.registerConfigEvents();
    },

    drawConfig: function() {
        if (this.ctrl != null) {
            $('#ctrl-config-name').val(this.ctrl.name);
            $('#ctrl-config-description').val(this.ctrl.description);
        }
        var type = this.ctrl != null ? this.ctrl.type : 'http';
        this.drawOptions(type);
        $('#ctrl-config-type').val(type);
        
        $("#ctrl-config-modal").modal('show');
    },

    closeConfig: function(result) {
        $('#ctrl-config-loader').hide();
        
        if (typeof result.success !== 'undefined' && !result.success) {
            alert('Controller could not be configured:\n'+result.message);
            return false;
        }
        update();
        $('#ctrl-config-modal').modal('hide');
    },

    drawOptions: function(type) {
        var options = muc_dialog.ctrl != null ? muc_dialog.ctrl.options : {};
        if (type == 'http' || type == 'https') {
            let address = options.hasOwnProperty('address') ? options.address : 'localhost';
            let port = options.hasOwnProperty('port') ? options.port : (type == 'http' ? 8080 : 8443);
            
            let html = "<table>" +
                    "<tr><th>Address</th><th>Port</th></tr>" +
                    "<tr>" +
                        "<td><input id='ctrl-config-address' class='input-medium' type='text' value='"+address+"' required></input></td>" +
                        "<td><input id='ctrl-config-port' class='input-small' type='number' step='1' value='"+port+"' required></input></td>" +
                    "</tr>" +
                "</table>";
            
            if (options.hasOwnProperty('password')) {
                html += "<label style='color:#888'>Password</label>" +
                        "<input id='ctrl-config-password' class='input-large' style='width:264px;' type='text' value='"+options.password+"'></input>";
            }
            $('#ctrl-config-options').html(html);
        }
        else {
            $('#ctrl-config-options').html('');
        }
    },

    getOptions: function(type) {
        var options = {};
        if (type == 'http' || type == 'https') {
            options['address'] = $('#ctrl-config-address').val();
            options['port'] = $('#ctrl-config-port').val();
            
            if($('#ctrl-config-foo').length == 0) {
                options['password'] = $('#ctrl-config-password').val();
            }
        }
        return options;
    },

    registerConfigEvents:function() {

        $("#ctrl-config-type").off('change').on('change', function () {
            muc_dialog.drawOptions($(this).val());
        });

        $("#ctrl-config-save").off('click').on('click', function () {
            var type = $('#ctrl-config-type').val();
            var name = $('#ctrl-config-name').val();
            var description = $('#ctrl-config-description').val();
            
            if (name == '') {
                alert('Controller needs to be configured first.');
                return false;
            }
            $('#ctrl-config-loader').show();
            
            var options = muc_dialog.getOptions(type);
            if (muc_dialog.ctrl != null) {
                var fields = {};
                if (muc_dialog.ctrl.name != name) {
                    fields['name'] = name;
                }
                if (muc_dialog.ctrl.description != description) {
                    fields['description'] = description;
                }
                if (JSON.stringify(muc_dialog.ctrl.options) != JSON.stringify(options)) {
                    fields['options'] = options;
                }
                muc.update(muc_dialog.ctrl.id, fields,
                    muc_dialog.closeConfig);
            }
            else {
                muc.create(type, name, description, options,
                    muc_dialog.closeConfig);
            }
        });
    },

    loadDelete: function(ctrl, tablerow) {
        this.ctrl = ctrl;
        $("#ctrl-modal-delete").modal('show');
        
        // Initialize callbacks
        this.registerDeleteEvents(tablerow);
    },

    registerDeleteEvents:function(row) {
        
        $("#ctrl-delete-confirm").off('click').on('click', function() {
            $('#ctrl-delete-loader').show();
            muc.remove(muc_dialog.ctrl.id,
                function(result) {
	                $('#ctrl-delete-loader').hide();
	                
	                if (typeof result.success !== 'undefined' && !result.success) {
	                    alert('Controller could not be removed:\n'+result.message);
	                    return false;
	                }
	                update();
	                $('#ctrl-modal-delete').modal('hide');
            });
        });
    }
}
