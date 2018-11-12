/*
  groups.js is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  Part of the OpenEnergyMonitor project: http://openenergymonitor.org
  Developed by: Adrian Minde  adrian_minde@live.de
*/
var groups = {
    'container': null,
    'hover': false,

    'actions': {},
    'header': {},
    'body': {},

    'time': {},
    'selected': {},
    'collapsed': {},

    'init':function(container) {
        if (!container) {
            alert('Group container has to be loaded to valid element');
            return false;
        }
        groups.container = container;
        
        hover = false;
        $(document).on('mousemove', function() {
            $(document).off('mousemove');
            hover = true;
        });
        
        var actions = "";
        for (var type in groups.actions) {
            var action = groups.actions[type];
            var title = (typeof action.title !== 'undefined') ? action.title : 'Unknown';
            var icon = (typeof action.icon !== 'undefined') ? action.icon : 'icon-question-sign';
            var hide = (typeof action.hide !== 'undefined') ? action.hide : true;
            
            actions += "<div id='group-action-"+type+"' class='action' title='"+title+"' data-type='"+type+"' "+(hide ? 'style="display:none"' : '')+"><span class='"+icon+"'></span></div>";
        }
        container.html(
            "<div class='group-actions' data-spy='affix' data-offset-top='100'>" +
                "<div class='group-item'>" +
                    "<div id='group-action-select-all' class='group-select group-grow'>" +
                        "<input class='select' type='checkbox'></input>" +
                        "<span><i>Select all</i></span>" +
                    "</div>" +
                    actions +
                    "<div id='group-action-expand-all' class='action' title='Expand' data-type='expand' data-expand='false'><span class='icon icon-resize-full'></span></div>" +
                "</div>" +
            "</div>" +
            "<div class='group-container'></div>"
        );
        
        groups.registerSelectEvents();
    },

    'draw':function(groups, data, callback) {
        
    },

    'registerSelectEvents':function() {
        
        $('.group-actions .action', groups.container).off('click').on('click', function(e) {
            var type = $(this).data('type');
            if (typeof type !== 'undefined') {
                if (type == 'expand') {
                    var expand = !$(this).data('expand');
                    groups.drawExpandAction(expand);
                    groups.expandAllGroups(expand);
                }
            }
        });
    },

    'drawExpandAction':function(state) {
        var expand = $('#group-action-expand-all', groups.container);
        
        // Set the icon and button title based on the state (true == expanded)
        expand.find('.icon')
          .toggleClass('icon-resize-small', state)
          .toggleClass('icon-resize-full', !state);
        
        expand.data('expand', state);
        expand.prop('title', state ? 'Collapse' : 'Expand');
    },

    'expandAllGroups':function(state) {
        var collapses = $('.collapse'); //TODO: , groups.container);
        collapses.each(function(i, collapse) {
            var group = $(collapse);
            if (state != group.hasClass('in')) {
                group.collapse(state ? 'show':'hide');
            }
        });
    },
}
