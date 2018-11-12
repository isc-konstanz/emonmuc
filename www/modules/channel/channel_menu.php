<?php

    $domain = "messages";
    bindtextdomain($domain, "Modules/channel/locale");
    bind_textdomain_codeset($domain, 'UTF-8');

    $menu_dropdown_config[] = array(
            'name'=> dgettext($domain, "Channels"),
            'icon'=>'icon-random',
            'path'=>"channel/view" ,
            'session'=>"write",
            'order' => 5
    );
