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

<h2><?php echo _('Channel API'); ?></h2>
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
    <tr><td><?php echo _('The channel list view'); ?></td><td><a href="<?php echo $path; ?>channel/view"><?php echo $path; ?>channel/view</a></td></tr>
    <tr><td><?php echo _('This page'); ?></td><td><a href="<?php echo $path; ?>channel/api"><?php echo $path; ?>channel/api</a></td></tr>
</table>

<h3><?php echo _('Available JSON commands'); ?></h3>
<p><?php echo _('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<p><b><?php echo _('Channel actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Create new channel'); ?></td><td><a href="<?php echo $path; ?>channel/create.json?ctrlid=1&driverid=csv&deviceid=Home&configs={%22id%22:%22Power%22,%22nodeid%22:%22Home%22}"><?php echo $path; ?>channel/create.json?ctrlid=1&driverid=csv&deviceid=Home&configs={"id":"Power","nodeid":"Home"}</a></td></tr>
    <tr><td><?php echo _('Load channels'); ?></td><td><a href="<?php echo $path; ?>channel/load.json"><?php echo $path; ?>channel/load.json</a></td></tr>
    <tr><td><?php echo _('List channels'); ?></td><td><a href="<?php echo $path; ?>channel/list.json"><?php echo $path; ?>channel/list.json</a></td></tr>
    <tr><td><?php echo _('Get channel details'); ?></td><td><a href="<?php echo $path; ?>channel/get.json?ctrlid=1&id=Power"><?php echo $path; ?>channel/get.json?ctrlid=1&id=Power</a></td></tr>
    <tr><td><?php echo _('Get channel information'); ?></td><td><a href="<?php echo $path; ?>muc/channel/info.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/channel/info.json?ctrlid=1&driverid=csv</a></td></tr>
    <tr><td><?php echo _('List channel states'); ?></td><td><a href="<?php echo $path; ?>muc/channel/states.json"><?php echo $path; ?>muc/channel/states.json</a></td></tr>
    <tr><td><?php echo _('List channel records'); ?></td><td><a href="<?php echo $path; ?>muc/channel/records.json"><?php echo $path; ?>muc/channel/records.json</a></td></tr>
    <tr><td><?php echo _('Start scan for channels'); ?></td><td><a href="<?php echo $path; ?>muc/channel/scan/list.json?ctrlid=1&driverid=csv&deviceid=Home"><?php echo $path; ?>muc/channel/scan/list.json?ctrlid=1&driverid=csv&deviceid=Home</a></td></tr>
    <tr><td><?php echo _('Set latest channel value'); ?></td><td><a href="<?php echo $path; ?>muc/channel/set.json?ctrlid=1&id=Switch&value=false&valueType=boolean"><?php echo $path; ?>muc/channel/set.json?ctrlid=1&id=Switch&value=false&valueType=boolean</a></td></tr>
    <tr><td><?php echo _('Write value to channel'); ?></td><td><a href="<?php echo $path; ?>muc/channel/write.json?ctrlid=1&id=Switch&value=false&valueType=boolean"><?php echo $path; ?>muc/channel/write.json?ctrlid=1&id=Switch&value=false&valueType=boolean</a></td></tr>
    <tr><td><?php echo _('Update channel configuration'); ?></td><td><a href="<?php echo $path; ?>channel/update.json?ctrlid=1&nodeid=Home&id=Power&configs={%22id%22:%22Power%22,%22disabled%22:%22true%22}"><?php echo $path; ?>channel/update.json?ctrlid=1&nodeid=Home&id=Power&configs={"id":"Power","disabled":"true"}</a></td></tr>
    <tr><td><?php echo _('Delete existing channel'); ?></td><td><a href="<?php echo $path; ?>channel/delete.json?ctrlid=1&id=Power"><?php echo $path; ?>channel/delete.json?ctrlid=1&id=Power</a></td></tr>
</table>

<p><b><?php echo _('Device actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Create new device'); ?></td><td><a href="<?php echo $path; ?>channel/connect/create.json?ctrlid=1&driverid=csv&configs={%22id%22:%22Home%22,%22description%22:%22Virtual home%22}"><?php echo $path; ?>channel/connect/create.json?ctrlid=1&driverid=csv&configs={"id":"Home","description":"Virtual home"}</a></td></tr>
    <tr><td><?php echo _('Load devices'); ?></td><td><a href="<?php echo $path; ?>channel/connect/load.json"><?php echo $path; ?>channel/connect/load.json</a></td></tr>
    <tr><td><?php echo _('List devices'); ?></td><td><a href="<?php echo $path; ?>channel/connect/list.json"><?php echo $path; ?>channel/connect/list.json</a></td></tr>
    <tr><td><?php echo _('List device states'); ?></td><td><a href="<?php echo $path; ?>muc/device/states.json"><?php echo $path; ?>muc/device/states.json</a></td></tr>
    <tr><td><?php echo _('Get device information'); ?></td><td><a href="<?php echo $path; ?>muc/device/info.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/info.json?ctrlid=1&driverid=csv</a></td></tr>
    <tr><td><?php echo _('Get device details'); ?></td><td><a href="<?php echo $path; ?>channel/connect/get.json?ctrlid=1&id=Home"><?php echo $path; ?>channel/connect/get.json?ctrlid=1&id=Home</a></td></tr>
    <tr><td><?php echo _('Update device fields'); ?></td><td><a href="<?php echo $path; ?>channel/connect/update.json?ctrlid=1&id=Home&configs={%22id%22:%22Home%22,%22description%22:%22Virtual%22}"><?php echo $path; ?>channel/connect/update.json?ctrlid=1&id=Home&configs={"id":"Home","description":"Virtual"}</a></td></tr>
    <tr><td><?php echo _('Delete existing device'); ?></td><td><a href="<?php echo $path; ?>channel/connect/delete.json?ctrlid=1&id=Home"><?php echo $path; ?>channel/connect/delete.json?ctrlid=1&id=Home</a></td></tr>
</table>

<p><b><?php echo _('Device scan actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Start scan for devices'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/start.json?ctrlid=1&driverid=csv&settings=path=lib/driver/"><?php echo $path; ?>muc/device/scan/start.json?ctrlid=1&driverid=csv&settings=path=lib/driver/csv</a></td></tr>
    <tr><td><?php echo _('Get progress information of the running device scan'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/progress.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/scan/progress.json?ctrlid=1&driverid=csv</a></td></tr>
    <tr><td><?php echo _('Cancel the running device scan'); ?></td><td><a href="<?php echo $path; ?>muc/device/scan/cancel.json?ctrlid=1&driverid=csv"><?php echo $path; ?>muc/device/scan/cancel.json?ctrlid=1&driverid=csv</a></td></tr>
</table>
