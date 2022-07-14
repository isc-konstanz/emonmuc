<link rel="stylesheet" href="<?php echo $path?>Modules/admin/static/admin_styles.css?v=1">
<div class="admin-container">
    <section class="d-md-flex justify-content-between align-items-center pb-md-2 text-right px-1">
        <div class="text-left">
            <h3 class="mt-1 mb-0"><?php echo _('EmonMUC Log'); ?></h3>
            <p><?php
            if(is_readable($log_file)) {
                echo sprintf("%s <code>%s</code>",_('View last entries on the logfile:'),$log_file);
            } else {
                echo '<div class="alert alert-warn">';
                echo "The log file has no write permissions or does not exists. To fix, log-on on shell and do:<br><pre>touch $log_file<br>chmod 666 $log_file</pre>";
                echo '<small></div>';
            } ?></p>
        </div>
        <div>
            <?php if(is_readable($log_file)) { ?>
                <button id="getlog" type="button" class="btn btn-info mb-1" data-toggle="button" aria-pressed="false" autocomplete="off">
                    <?php echo _('Auto refresh'); ?>
                </button>
                <a href="<?php echo $path; ?>muc/log/download" class="btn btn-info mb-1"><?php echo _('Download Log'); ?></a>
                <button class="btn btn-info mb-1" id="copylogfile" type="button"><?php echo _('Copy Log to clipboard'); ?></button>
            <?php } ?>
        </div>
    </section>
    <section>
        <pre id="logreply-bound" class="log" style="min-height:320px; height:calc(100vh - 220px); display:none">
            <div id="logreply"></div>
        </pre>
    </section>
</div>
<div id="snackbar" class=""></div>
<script>

$("#logreply-bound").slideDown();

var logFileDetails;
$("#copylogfile").on('click', function(event) {
    logFileDetails = $("#logreply").text();
    if ( event.ctrlKey ) {
        copyTextToClipboard('LAST ENTRIES ON THE LOG FILE\n'+logFileDetails,
        event.target.dataset.success);
    } else {
        copyTextToClipboard('<details><summary>LAST ENTRIES ON THE LOG FILE</summary><br />\n'+ logFileDetails.replace(/\n/g,'<br />\n').replace(/API key '[\s\S]*?'/g,'API key \'xxxxxxxxx\'') + '</details><br />\n',
        event.target.dataset.success);
    }
} );

var logrunning = false;

// setInterval() markers
var emonmuc_log_interval;

// stop updates if interval == 0
function refresherStart(func, interval){
    if (interval > 0) return setInterval(func, interval);
}

// push value to emoncms logfile viewer
function refresh_log(result) {
    $("#logreply").html(result);
    scrollable = $("#logreply").parent('pre')[0];
    if(scrollable) scrollable.scrollTop = scrollable.scrollHeight;
}

getLog();

// use the api to get the latest value from the logfile
function getLog() {
    $.ajax({ url: path+"muc/log/get", async: true, dataType: "text", success: refresh_log });
}

// auto refresh the updates logfile
$("#getlog").click(function() {
    $this = $(this)
    if ($this.is('.active')) {
        clearInterval(emonmuc_log_interval);
    } else {
        emonmuc_log_interval = refresherStart(getLog, 1000); 
    }
});
function copyTextToClipboard(text, message) {
    var textArea = document.createElement("textarea");
    textArea.style.position = 'fixed';
    textArea.style.top = 0;
    textArea.style.left = 0;
    textArea.style.width = '2em';
    textArea.style.height = '2em';
    textArea.style.padding = 0;
    textArea.style.border = 'none';
    textArea.style.outline = 'none';
    textArea.style.boxShadow = 'none';
    textArea.style.background = 'transparent';
    textArea.value = text;
    document.body.appendChild(textArea);
    textArea.select();
      try {
        var successful = document.execCommand('copy');
        var msg = successful ? 'successful' : 'unsuccessful';
        // console.log('Copying text command was ' + msg);
        snackbar(message || 'Copied to clipboard');
    } 
    catch(err) {
        window.prompt("<?php echo _('Copy to clipboard: Ctrl+C, Enter'); ?>", text);
    }
    document.body.removeChild(textArea);
}
function snackbar(text) {
    var snackbar = document.getElementById("snackbar");
    snackbar.innerHTML = text;
    snackbar.className = "show";
    setTimeout(function () {
        snackbar.className = snackbar.className.replace("show", "");
    }, 3000);
}
</script>
