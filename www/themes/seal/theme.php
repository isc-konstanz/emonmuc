<!doctype html>
<?php
/*
  All Emoncms code is released under the GNU Affero General Public License.
  See COPYRIGHT.txt and LICENSE.txt.

  ---------------------------------------------------------------------
  Emoncms - open source energy visualisation
  Part of the OpenEnergyMonitor project:
  http://openenergymonitor.org
*/
global $ltime,$path,$fullwidth,$emoncms_version,$theme,$themecolor,$favicon,$menu,$menucollapses;

$v = 2;

//compute dynamic @media properties depending on numbers and lengths of shortcuts
$maxwidth1=1200;
$maxwidth2=480;
$maxwidth3=340;
$sumlength1 = 0;
$sumlength2 = 0;
$sumlength3 = 0;
$sumlength4 = 0;
$sumlength5 = 0;
$nbshortcuts1 = 0;
$nbshortcuts2 = 0;
$nbshortcuts3 = 0;
$nbshortcuts4 = 0;
$nbshortcuts5 = 0;

foreach($menu['dashboard'] as $item){
    if(isset($item['name'])){$name = $item['name'];}
    if(isset($item['published'])){$published = $item['published'];} //only published dashboards
    if($name && $published){
        $sumlength1 += strlen($name);
        $nbshortcuts1 ++;
    }
}
foreach($menu['left'] as $item){
    if(isset($item['name'])) {$name = $item['name'];}
    $sumlength2 += strlen($name);
    $nbshortcuts2 ++;
}
if(count($menu['dropdown']) && $session['read']){
    $extra['name'] = 'Extra';
    $sumlength3 = strlen($extra['name']);
    $nbshortcuts3 ++;
}
if (count($menu['dropdownconfig'])){
    $setup['name'] = 'Setup';
    $sumlength4 = strlen($setup['name']);
    $nbshortcuts4 ++;
}
foreach ($menu['right'] as $item) {
    if (isset($item['name'])){$name = $item['name'];}
    $sumlength5 += strlen($name);
    $nbshortcuts5 ++;
}
$maxwidth1=intval((($sumlength1+$sumlength2+$sumlength3+$sumlength4+$sumlength5)+($nbshortcuts1+$nbshortcuts2+$nbshortcuts3+$nbshortcuts4+$nbshortcuts5+1)*6)*85/9);
$maxwidth2=intval(($nbshortcuts1+$nbshortcuts2+$nbshortcuts3+$nbshortcuts4+$nbshortcuts5+3)*6*75/9);
if($maxwidth2>$maxwidth1){$maxwidth2=$maxwidth1-1;}
if($maxwidth3>$maxwidth2){$maxwidth3=$maxwidth2-1;}

if (!is_dir("Theme/".$theme)) {
    $theme = "basic";
}
if (!in_array($themecolor, ["blue", "sun", "standard"])) {
    $themecolor = "standard";
}
?>

<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emoncms - <?php echo $route->controller.' '.$route->action.' '.$route->subaction; ?></title>
    <link rel="shortcut icon" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/<?php echo $favicon; ?>" />
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-startup-image" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/ios_load.png">
    <link rel="apple-touch-icon" href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/logo_normal.png">

    <link href="<?php echo $path; ?>Lib/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $path; ?>Lib/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
    <link href="<?php echo $path; ?>Theme/<?php echo $theme; ?>/emon-<?php echo $themecolor; ?>.css?v=<?php echo $v; ?>" rel="stylesheet">

<?php if ($menucollapses) { ?>
    <style>
        /* this is menu colapsed */
        @media (max-width: 979px){
            .menu-description {
                display: inherit !important ;
            }
        }
        @media (min-width: 980px) and (max-width: <?php if($maxwidth1<981){$maxwidth1=981;} echo $maxwidth1; ?>px){
            .menu-text {
                display: none !important;
            }
        }
    </style>
<?php } else { ?>
    <style>
        @media (max-width: <?php echo $maxwidth1; ?>px){
            .menu-text {
                display: none !important;
            }
        }
        @media (max-width: <?php echo $maxwidth2; ?>px){
            .menu-dashboard {
                display: none !important;
            }
        }
        @media (max-width: <?php echo $maxwidth3; ?>px){
            .menu-extra {
                display: none !important;
            }
        }
    </style>
<?php } ?>

    <script type="text/javascript" src="<?php echo $path; ?>Lib/jquery-1.11.3.min.js"></script>
    <script>
        window.onerror = function(msg, source, lineno, colno, error) {
            if (msg.toLowerCase().indexOf("script error") > -1) {
                alert('Script Error: See Browser Console for Detail');
            }
            else {
                var messages = [
                    'EmonCMS Error',
                    '-------------',
                    'Message: ' + msg,
                    'Route: ' + source.replace('<?php echo $path; ?>',''),
                    'Line: ' + lineno,
                    'Column: ' + colno
                ];
                if (Object.keys(error).length > 0) {
                    messages.push('Error: ' + JSON.stringify(error));
                }
                alert(messages.join("\n"));
            }
            return true; // true == prevents the firing of the default event handler.
        }
    </script>
</head>
<body>
    <div id="wrap">
    <div id="emoncms-navbar" class="navbar navbar-inverse navbar-fixed-top">
        <div class="navbar-inner">
        <?php if ($menucollapses) { ?>
            <button type="button" class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <img src="<?php echo $path; ?>Theme/<?php echo $theme; ?>/favicon.png" style="width:28px;"/>
            </button>
            <div class="nav-collapse collapse">
        <?php } ?>

            <?php echo $mainmenu; ?>

        <?php if ($menucollapses) { ?>
            </div>
        <?php } ?>

        </div>
    </div>

    <div id="topspacer"></div>
<?php if (isset($submenu) && ($submenu)) { ?>
    <div id="submenu">
        <div class="container">
            <?php echo $submenu; ?>
        </div>
    </div>
    <br>
<?php } ?>

<?php if ($fullwidth && $route->controller=="dashboard") { ?>
    <div>
        <?php echo $content; ?>
    </div>
<?php } else if ($fullwidth) { ?>
    <div class = "container-fluid"><div class="row-fluid"><div class="span12">
        <?php echo $content; ?>
    </div></div></div>
<?php } else { ?>
    <div class="container">
        <?php echo $content; ?>
    </div>
<?php } ?>

    <div style="clear:both; height:60px;"></div>
    </div>

    <div id="footer">
        <?php echo _('Powered by '); ?><a href="http://openenergymonitor.org">OpenEnergyMonitor.org</a>
        <span> | <a href="https://github.com/emoncms/emoncms/releases"><?php echo $emoncms_version; ?></a></span>
    </div>

    <script type="text/javascript" src="<?php echo $path; ?>Lib/bootstrap/js/bootstrap.js"></script>
<?php if (isset($ui_version_2) && $ui_version_2) { ?>
    <script type="text/javascript" src="<?php echo $path; ?>Lib/hammer.min.js"></script>
    <script>
        // only use hammerjs on the relevent pages
        // CSV list of pages in the navigation
        var pages = ['feed/list','input/view'],
        // strip off the domain/ip and just get the path
        currentPage = (""+window.location).replace(path,''),
        // find where in the list the current page is
        currentIndex = pages.indexOf(currentPage)

        if (currentIndex > -1) {
            // uses hammerjs to detect mobile gestures. navigates between input and feed view
            
            // allow text on page to be highlighted. 
            delete Hammer.defaults.cssProps.userSelect

            // SETUP VARIABLES:
            var container = document.getElementById('wrap'),
                // get the path as reported by server
                path = "<?php echo $path; ?>",
                // create a new instance of the hammerjs api
                mc = new Hammer.Manager(container, {
                    inputClass: Hammer.TouchInput
                }),
                // make swipes require more velocity
                swipe = new Hammer.Swipe({ velocity: 1.1, direction: Hammer.DIRECTION_HORIZONTAL }) // default velocity 0.3
            
            // enable the altered swipe gesture
            mc.add([swipe]);

            // CREATE EVENT LIST:
            // add a callback function on the swipe gestures
            mc.on("swipeleft swiperight", function(event) {              
                    // increase or decrease the currentIndex
                    index = event.type=='swipeleft' ? currentIndex+1 : currentIndex-1;
                    // wrap back to start if beyond end
                    index = index > pages.length-1 ? 0 : index
                    // wrap forward to end if beyond start
                    index = index < 0 ? pages.length-1 : index
                    // get the page to load
                    url = path+pages[index]
                    // load the page
                    window.location.href = url
            });
        }
    </script>
<?php } ?>
</body>
</html>
