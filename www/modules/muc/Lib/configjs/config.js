var config =
{
    'container': null,

    'groups': {},
    'options': null,
    'infos': null,

    'load':function(parent, groups) {
        this.groups = groups;
        this.options = null;
        this.infos = null;
        
        if (!parent) {
            alert('Config has to be loaded to valid container');
            return false;
        }
        this.container = parent;
        $(this.container).load(path+"Modules/muc/Lib/configjs/config.html", function() {
            $('#option-overlay', config.container).show();
            
            var tables = $('#options', config.container);
            for (var group in groups) {
                if (groups.hasOwnProperty(group)) {
                    tables.append(
                        "<div id='option-"+group+"' class='option-group'>" +
                            "<div id='option-"+group+"-header' class='option-header' " +
                                    "data-toggle='collapse' data-target='#option-"+group+"-body' data-group='"+group+"'>" +
                                "<h5><span class='icon-chevron-right icon-collapse'></span>"+groups[group]+"</h5>" +
                            "</div>" +
                            "<div id='option-"+group+"-body' class='collapse'>" +
                                "<table id='option-"+group+"-table' class='table table-option'></table>" +
                                "<div id='option-"+group+"-none' class='alert' style='display:none'>You have no options configured</div>" +
                            "</div>" +
                        "</div>"
                    );
                }
            }
            config.drawOptions();
        });
    },

    'draw':function(options, infos) {
        if (options == null) options = {};
        this.options = options;
        
        if (infos == null) infos = {};
        this.infos = infos;
        
        for (var group in this.groups) {
            if (!this.groups.hasOwnProperty(group) || typeof infos[group] === 'undefined' || infos[group]['options'].length < 1) {
                // When no option infos are configured, the group will be hidden
                $('#option-'+group, config.container).hide();
                continue;
            }
            $('#option-'+group, config.container).show();
            
            // If group option is a String, parse it depending on its syntax info
            if (typeof options[group] === 'string' || options[group] instanceof String) {
                this.decode(group);
            }
        }
        this.drawOptions();
        
        $('#option-overlay', config.container).hide();
    },

    'drawOptions':function() {
        var select = $('#option-select', config.container);
        select.html('<option hidden="true" value="">Select an option</option></optgroup>').val('');
        select.css('color', '#888').css('font-style', 'italic');
        select.on('change', function() {
            select.off('change');
            select.css('color', 'black').css('font-style', 'normal');
        });
        
        // Show the option footer, if at least one group is defined and has options
        var show = false;
        if (config.infos != null) {
            for (var group in config.groups) {
                if (config.groups.hasOwnProperty(group) 
                        && typeof config.infos[group] !== 'undefined' && config.infos[group]['options'].length > 0) {
                    show = true;
                    
                    if (typeof config.options[group] === 'undefined') {
                        config.options[group] = {};
                    }
                    config.drawOptionGroup(group, select);
                }
            }
        }
        if (show) {
            if ($("option", select).length > 1) {
                select.prop("disabled", false).val('');
                $("#option-add", config.container).prop("disabled", false);
            }
            else {
                select.prop("disabled", true).val('');
                $("option-add", config.container).prop("disabled", true);
            }
            $("#option-footer", config.container).show();
        }
        else {
            $('#option-footer', config.container).hide();
        }
        config.registerEvents();
    },

    'drawOptionGroup':function(group, select) {
        $('#option-'+group+'-table', config.container).empty();
        
        // Show options, if at least one of them is defined or mandatory
        var show = false;
        var options = "";
        
        var infos = config.infos[group]['options'];
        for (var i = 0; i < infos.length; i++) {
            var info = infos[i];
            if (info.mandatory || typeof config.options[group][info.key] !== 'undefined') {
                show = true;
                
                config.drawOptionInput(group, info);
            }
            else {
                options += "<option value='"+info.key+"' data-group='"+group+"' style='color:black'>"+info.name+"</option>";
            }
        }
        if (options.length > 0) {
            select.append("<optgroup id='option-select-"+group+"' label='"+config.groups[group]+"'>"+options+"</optgroup>");
        }
        
        if (show) {
            $('#option-'+group+'-none', config.container).hide();
            $('#option-'+group+'-table', config.container).show();
            $('#option-'+group+'-body', config.container).collapse('show');
            $("#option-"+group+"-header .icon-collapse", config.container).removeClass('icon-chevron-right').addClass('icon-chevron-down');
        }
        else {
            $('#option-'+group+'-none', config.container).show();
            $('#option-'+group+'-table', config.container).hide();
            if ($('#option-'+group+'-body', config.container).hasClass('in')) {
                $('#option-'+group+'-body', config.container).collapse('hide').removeClass('in');
                $("#option-"+group+"-header .icon-collapse", config.container).removeClass('icon-chevron-down').addClass('icon-chevron-right');
            }
        }
    },

    'drawOptionInput':function(group, info) {
        var key = info['key'];
        var name = info['name'];
        if (typeof name === 'undefined') {
            name = key;
        }
        var description = info['description'];
        
        var row = "<tr id='option-"+group+"-"+key+"-row' class='option' data-key='"+key+"' data-group='"+group+"'>" +
                "<td>"+name+"</td>" +
            "</tr>";
        
        if (typeof description !== 'undefined' && description != '') {
            row  += "<tr id='option-"+group+"-"+key+"-info' class='option' data-key='"+key+"' data-group='"+group+"' " +
                    "data-show='false' style='display:none'>" +
                "<td colspan='4'>" +
                    "<div class='alert alert-comment hide'>"+description+"</div>" +
                "</td>" +
            "</tr>";
        }
        $('#option-'+group+'-table', config.container).append("<tbody>"+row+"</tbody>");
        
        var row = $('#option-'+group+'-'+key+'-row', config.container);
        
        var value = '';
        if (typeof config.options[group][key] !== 'undefined') {
            value = config.options[group][key];
        }
        else if (typeof info.valueDefault !== 'undefined') {
            value = info.valueDefault;
        }
        var type = info['type'].toUpperCase();
        if (typeof info['valueSelection'] !== 'undefined') {
            row.append("<td><select id='option-"+group+"-"+key+"-input' class='option-input input-large'></select></td>");
            
            var select = $('#option-'+group+'-'+key+'-input', config.container);
            select.append("<option selected hidden value=''>Select a "+name+"</option>");
            for (var val in info.valueSelection) {
                if (info.valueSelection.hasOwnProperty(val)) {
                    var foo = "<option value='"+val+"' style='color:black'>"+info.valueSelection[val]+"</option>";
                    select.append("<option value='"+val+"' style='color:black'>"+info.valueSelection[val]+"</option>");
                }
            }
            
            if (value != null) {
                select.val(value);
            }
            else {
                select.css('color', '#888').css('font-style', 'italic');
                select.on('change', function() {
                    select.off('change');
                    select.css('color', 'black').css('font-style', 'normal');;
                });
            }
        }
        else if (type == 'BOOLEAN') {
            row.append(
                "<td>" +
                    "<div class='option-input checkbox checkbox-slider--b-flat checkbox-slider-info'>" +
                        "<label>" +
                            "<input id='option-"+group+"-"+key+"-input' type='checkbox'><span></span>" +
                        "</label>" +
                    "</div>" +
                "</td>"
            );
            if (value != null) {
                if (typeof value === 'string' || value instanceof String) {
                    value = (value == 'true');
                }
                $('#option-'+group+'-'+key+'-input', config.container).prop('checked', value);
            }
        }
        else {
            row.append("<td><input id='option-"+group+"-"+key+"-input' type='text' class='option-input input-large'></input></td>");
            if (value != null) {
                $('#option-'+group+'-'+key+'-input', config.container).val(value);
            }
        }
        
        if(!info.mandatory) {
            row.append("<td></td>")
            row.append("<td><a id='option-"+group+"-"+key+"-remove' class='option-remove' title='Remove'><i class='icon-trash' style='cursor:pointer'></i></a></td>");
        }
        else {
            row.append("<td><span style='color:#888; font-size:12px'><em>mandatory</em></span></td>")
            row.append("<td><a><i class='icon-trash' style='cursor:not-allowed;opacity:0.3'></i></a></td>");
            
//            $('#option-'+group+'-'+key, config.container).prop('required', true);
        }
    },

    'registerEvents':function() {
        $('#options', config.container).off();

        $('#options', config.container).on("click", '.option-header', function() {
            var group = $(this).data('group');
            if ($('#option-'+group+'-body', config.container).hasClass('in')) {
                $('#option-'+group+'-header .icon-collapse', config.container).removeClass('icon-chevron-down').addClass('icon-chevron-right');
            }
            else {
                $('#option-'+group+'-header .icon-collapse', config.container).removeClass('icon-chevron-right').addClass('icon-chevron-down');
            }
        });

        $('#options', config.container).on('click', '.alert', function(e) {
        	e.stopPropagation();
        });

        $('#options', config.container).on('click', '.option', function() {
            var group = $(this).data('group');
            var key = $(this).data('key');
            
            var info = $('#option-'+group+'-'+key+'-info', config.container);
            if (typeof info !== 'undefined' && info.data('show')) {
                info.data('show', false);
                info.find('td > div').slideUp(function() { info.hide(); });
            }
            else {
                // Hide already shown option infos and open the selected afterwards
                // TODO: find a way to avoid display errors with select inputs if an info above it is collapsed
//                $('.table-option tr[data-show]', '#options', config.container).each(function() {
//                    if ($(this).data('show')) {
//                        info.find('td > div').slideUp(function() { info.hide(); });
//                    }
//                });
                info.data('show', true).show().find('td > div').slideDown();
            }
        });

        $('#options', config.container).on('click', '.option-input', function(e) {
            e.stopPropagation();
            
            var row = $(this).closest('tr');
            var info = $('#option-'+row.data('group')+'-'+row.data('key')+'-info', config.container);
            if (typeof info !== 'undefined' && !info.data('show')) {
                info.data('show', true).show().find('td > div').slideDown();
            }
        });

        $('#options', config.container).on('click', '.option-remove', function(e) {
            e.stopPropagation();
            
            var row = $(this).closest('tr');
            var group = row.data('group');
            var key = row.data('key');
            
            var removeRow = function() {
                $(this).remove(); 
                
                if ($('#option-'+group+'-table tr', config.container).length == 0) {
                    $('#option-'+group+'-table', config.container).hide();
                    $('#option-'+group+'-none', config.container).show();
                }
            }
            $('#option-'+group+'-'+key+'-row', config.container).fadeOut(removeRow);
            $('#option-'+group+'-'+key+'-info', config.container).fadeOut(removeRow);
            
            var info = config.infos[group].options.find(function(result) {
                return result.key === key;
            });
            
            var select = $('#option-select-'+group, config.container);
            if (typeof select === 'undefined') {
                $('#option-select', config.container).append("<optgroup id='option-select-"+group+"' label='"+config.groups[group]+"'></optgroup>");
                
                select = $('#option-select-'+group, config.container);
            }
            select.append("<option value='"+key+"' data-group='"+group+"' style='color:black'>"+info.name+"</option>");
            
            select = $('#option-select', config.container);
            if ($("option", select).length > 1) {
                select.prop("disabled", false).val('');
                $("#option-add", config.container).prop("disabled", false);
            }
            else {
                select.prop("disabled", true).val('');
                $("#option-add", config.container).prop("disabled", true);
            }
        });

        $("#option-add", config.container).off('click').on('click', function() {
            var select = $('#option-select', config.container);
            var value = $('option:selected', select);
            
            var group = value.data('group');
            var key = value.val();
            if (key != "" && $("#option-"+group+"-"+key+"-row").val() === undefined) {
                value.remove();
                
                var info = config.infos[group].options.find(function(result) {
                    return result.key === key;
                });
                config.drawOptionInput(group, info);
                
                if ($('#option-'+group+'-table', config.container).is(":hidden")) {
                    $('#option-'+group+'-table', config.container).show();
                    $('#option-'+group+'-none', config.container).hide();
                }
                if (!$('#option-'+group+'-body', config.container).hasClass('in')) {
                    $('#option-'+group+'-body', config.container).collapse('show');
                    $("#option-"+group+"-header .icon-collapse", config.container).removeClass('icon-chevron-right').addClass('icon-chevron-down');
                }
                
                select.css('color', '#888').css('font-style', 'italic');
                select.on('change', function() {
                    select.off('change');
                    select.css('color', 'black').css('font-style', 'normal');
                });
                if ($("option", select).length > 1) {
                    select.prop("disabled", false).val('');
                    $("#option-add", config.container).prop("disabled", false);
                }
                else {
                    select.prop("disabled", true).val('');
                    $("#option-add", config.container).prop("disabled", true);
                }
            }
        });
    },

    'get':function(group) {
        var options = {};
        if (typeof config.infos[group] !== 'undefined') {
            for (var i = 0; i < config.infos[group].options.length; i++) {
                var info = config.infos[group].options[i];
                var input = $('#option-'+group+'-'+info.key+'-input', config.container);
                var value = null;
                
                if (typeof input.val() !== 'undefined') {
                    var type = info.type.toUpperCase();
                    if (typeof info['valueSelection'] !== 'undefined') {
                        value = $('option:selected', input).val();
                    }
                    else if (type === 'BOOLEAN') {
                        value = input.is(':checked');
                    }
                    else {
                        value = input.val();
                    }
                }
                if (value !== null && value !== "") {
                    options[info.key] = value;
                }
            }
        }
        return options;
    },

    'decode':function(group) {
        if (typeof config.options[group] !== 'undefined') {
            var infos = config.infos[group]['options']
            var syntax = config.infos[group]['syntax']
            
            if (!syntax['keyValue']) {
                var optMandatoryCount = 0;
                for (var i = 0; i < infos.length; i++) {
                    if (infos[i]['mandatory']) optMandatoryCount++;
                }
            }
            
            var optList = {};
            var optArr = config.options[group].split(syntax['separator']);
            for (var p = 0, i = 0; i < infos.length && p < optArr.length; i++) {
                optInfo = infos[i];

                if (syntax['keyValue']) {
                    var keyValue = optArr[p].split(syntax['assignment']);
                    if (optInfo.key === keyValue[0]) {
                        optList[optInfo.key] = keyValue[1];
                        p++;
                    }
                }
                else {
                    if (optInfo['mandatory'] || optArr.length > optMandatoryCount) {
                        optList[optInfo.key] = optArr[p];
                        p++;
                    }
                }
            }
            config.options[group] = optList;
        }
    },

    'encode':function(group) {
        var options = config.get(group);
        if (Object.keys(options).length > 0) {
            var optArr = [];
            // Add options in the defined order of the information
            var infos = config.infos[group]['options'];
            var syntax = config.infos[group]['syntax'];
            
            for (var p = 0, i = 0; i < infos.length; i++) {
                optInfo = infos[i];
                
                if (options.hasOwnProperty(optInfo.key)) {
                    var value = options[optInfo.key];
                    if (syntax['keyValue']) {
                        optArr.push(optInfo.key+syntax['assignment']+value);
                    }
                    else {
                        optArr.push(value);
                    }
                }
            }
            return optArr.join(syntax['separator']);
        }
        return "";
    },

    'valid':function() {
        for (var group in config.groups) {
            if (config.groups.hasOwnProperty(group)) {
                var options = config.get(group);
                if (typeof config.infos[group] !== 'undefined') {
                    var infos = config.infos[group]['options'];
                    for (var i in infos) {
                        if (infos.hasOwnProperty(i)) {
                            var info = infos[i];
                            var key = info['key'];
                            if (info['mandatory']) {
                                if (!(key in options) || options[key].length === 0) {
                                    return false;
                                }
                            }
                        }
                    }
                }
            }
        }
        return true;
    }
}