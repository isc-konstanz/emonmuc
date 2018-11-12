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

<h2><?php echo _('Driver API'); ?></h2>
<h3><?php echo _('Apikey authentication'); ?></h3>
<p><?php echo _('If you want to call any of the following actions when you\'re not logged in you have this options to authenticate with the API key:'); ?></p>
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
    <tr><td><?php echo _('The Driver list view'); ?></td><td><a href="<?php echo $path; ?>muc/driver/view"><?php echo $path; ?>muc/driver/view</a></td></tr>
    <tr><td><?php echo _('This page'); ?></td><td><a href="<?php echo $path; ?>muc/driver/api"><?php echo $path; ?>muc/driver/api</a></td></tr>
</table>

<h3><?php echo _('Available JSON commands'); ?></h3>
<p><?php echo _('To use the json api the request url needs to include <b>.json</b>'); ?></p>

<p><b><?php echo _('Driver actions'); ?></b></p>
<table class="table">
    <tr><td><?php echo _('Create new driver'); ?></td><td><a href="<?php echo $path; ?>muc/driver/create.json?ctrlid=1&id=csv"><?php echo $path; ?>muc/driver/create.json?ctrlid=1&id=csv</a></td></tr>
    <tr><td><?php echo _('List drivers'); ?></td><td><a href="<?php echo $path; ?>muc/driver/list.json"><?php echo $path; ?>muc/driver/list.json</a></td></tr>
    <tr><td><?php echo _('List registered drivers'); ?></td><td><a href="<?php echo $path; ?>muc/driver/registered.json"><?php echo $path; ?>muc/driver/registered.json</a></td></tr>
    <tr><td><?php echo _('List configured drivers'); ?></td><td><a href="<?php echo $path; ?>muc/driver/configured.json"><?php echo $path; ?>muc/driver/configured.json</a></td></tr>
    <tr><td><?php echo _('List unconfigured drivers'); ?></td><td><a href="<?php echo $path; ?>muc/driver/unconfigured.json"><?php echo $path; ?>muc/driver/unconfigured.json</a></td></tr>
    <tr><td><?php echo _('Get driver information'); ?></td><td><a href="<?php echo $path; ?>muc/driver/info.json?ctrlid=1&id=csv"><?php echo $path; ?>muc/driver/info.json?ctrlid=1&id=csv</a></td></tr>
    <tr><td><?php echo _('Get driver details'); ?></td><td><a href="<?php echo $path; ?>muc/driver/get.json?ctrlid=1&id=csv"><?php echo $path; ?>muc/driver/get.json?ctrlid=1&id=csv</a></td></tr>
    <tr><td><?php echo _('Update driver configuration'); ?></td><td><a href="<?php echo $path; ?>muc/driver/update.json?ctrlid=1&id=csv&config={%22disabled%22:%22true%22}"><?php echo $path; ?>muc/driver/update.json?ctrlid=1&id=csv&config={"disabled":"true"}</a></td></tr>
    <tr><td><?php echo _('Delete existing driver'); ?></td><td><a href="<?php echo $path; ?>muc/driver/delete.json?ctrlid=1&id=csv"><?php echo $path; ?>muc/driver/delete.json?ctrlid=1&id=csv</a></td></tr>
</table>
