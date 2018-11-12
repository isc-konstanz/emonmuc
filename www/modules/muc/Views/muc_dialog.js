var muc_dialog = {
    'ctrlid': null,

    'loadConfig': function() {
        this.ctrlid = null;
        
        $("#ctrl-config-modal").modal('show');
        
        // Initialize callbacks
        this.registerConfigEvents();
    },

    'registerConfigEvents':function() {
        
        $("#ctrl-config-save").off('click').on('click', function () {
            $('#ctrl-config-loader').show();
            
            var type = $('#ctrl-config-type').val();
            var address = $('#ctrl-config-address').val();
            var description = $('#ctrl-config-description').val();
            
            var result = muc.create(type, address, description);
            $('#ctrl-config-loader').hide();
            
            if (typeof result.success !== 'undefined' && (!result.success || result.id < 1)) {
                alert('MUC could not be created:\n'+result.message);
                return false;
            }
            else {
                update();
                $('#ctrl-config-modal').modal('hide');
            }
        });
    },

    'loadDelete': function(ctrlid, tablerow) {
        this.ctrlid = ctrlid;
        
        $("#ctrl-modal-delete").modal('show');

        // Initialize callbacks
        this.registerDeleteEvents(tablerow);
    },

    'registerDeleteEvents':function(row) {
        
        $("#ctrl-delete-confirm").off('click').on('click', function() {
            
            $('#ctrl-delete-loader').show();
            var result = muc.remove(muc_dialog.ctrlid);
            $('#ctrl-delete-loader').hide();
            
            if (!result.success) {
                alert('Unknown error while deleting muc');
                return false;
            }
            else {
                table.remove(row);
                update();
                $('#ctrl-modal-delete').modal('hide');
            }
        });
    }
}
