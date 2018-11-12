<?php global $path, $session, $user; ?>
<style>
    a.anchor {
        display: block;
        position: relative;
        top: -50px;
        visibility: hidden;
    }
    .table td:nth-of-type(1) { width:25%; }
</style>

<h2><?php echo _('Device connections API'); ?></h2>
<h3><?php echo _('Apikey authentication'); ?></h3>
<p><?php echo _('If you want to call any of the following actions when your not logged in you have this options to authenticate with the API key:'); ?></p>
<ul><li><?php echo _('Append on the URL of your request: &apikey=APIKEY'); ?></li>
<li><?php echo _('Use POST parameter: "apikey=APIKEY"'); ?></li>
<li><?php echo _('Add the HTTP header: "Authorization: Bearer APIKEY"'); ?></li></ul>
<p><b><?php echo _('Read only:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_read($session['userid']); ?>" />
</p>
<p><b><?php echo _('Read & Write:'); ?></b><br>
<input type="text" style="width:255px" readonly="readonly" value="<?php echo $user->get_apikey_write($session['userid']); ?>" />
</p>

<h3><?php echo _('Available HTML URLs'); ?></h3>
<table class="table">
    <tr><td><?php echo _('The Device list view'); ?></td><td><a href="<?php echo $path; ?>muc/device/view"><?php echo $path; ?>muc/device/view</a></td></tr>
    <tr><td><?php echo _('This page'); ?></td><td><a href="<?php echo $path; ?>muc/device/api"><?php echo $path; ?>muc/device/api</a></td></tr>
</table>

<h3><?php echo _('Available JSON commands'); ?></h3>
<p><?php echo _('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<p><b><?php echo _('Device actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Create new device'); ?></td><td><a href="<?php echo $path; ?>muc/device/create.json?ctrlid=1&driverid=csv&configs={%22id%22:%22Home%22,%22description%22:%22Virtual home%22}"><?php echo $path; ?>muc/device/create.json?ctrlid=1&driverid=csv&configs={"id":"Home","description":"Virtual home"}</a></td></tr>
    <tr><td><?php echo _('List devices'); ?></td><td><a href="<?php echo $path; ?>muc/device/list.json"><?php echo $path; ?>muc/device/list.json</a></td></tr>
    <tr><td><?php echo _('List device states'); ?></td><td><a href="<?php echo $path; ?>muc/device/states.json"><?php echo $path; ?>muc/device/states.json</a></td></tr>
    <tr><td><?php echo _('Get device information'); ?></td><td><a href="<?php echo $path; ?>muc/device/info.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/info.json?ctrlid=1&driverid=csv</a></td></tr>
    <tr><td><?php echo _('Get device details'); ?></td><td><a href="<?php echo $path; ?>muc/device/get.json?ctrlid=1&id=Home"><?php echo $path; ?>muc/device/get.json?ctrlid=1&id=Home</a></td></tr>
    <tr><td><?php echo _('Update device fields'); ?></td><td><a href="<?php echo $path; ?>muc/device/update.json?ctrlid=1&id=Home&configs={%22id%22:%22Home%22,%22description%22:%22Virtual%22}"><?php echo $path; ?>muc/device/update.json?ctrlid=1&id=Home&configs={"id":"Home","description":"Virtual"}</a></td></tr>
    <tr><td><?php echo _('Delete existing device'); ?></td><td><a href="<?php echo $path; ?>muc/device/delete.json?ctrlid=1&id=Home"><?php echo $path; ?>muc/device/delete.json?ctrlid=1&id=Home</a></td></tr>
</table>

<p><b><?php echo _('Device scan actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Start scan for devices'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/start.json?ctrlid=1&driverid=csv&settings=path=lib/driver/"><?php echo $path; ?>muc/device/scan/start.json?ctrlid=1&driverid=csv&settings=path=lib/driver/csv</a></td></tr>
    <tr><td><?php echo _('Get progress information of the running device scan'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/progress.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/scan/progress.json?ctrlid=1&driverid=csv</a></td></tr>
    <tr><td><?php echo _('Cancel the running device scan'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/cancel.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/scan/cancel.json?ctrlid=1&driverid=csv</a></td></tr>
</table>
