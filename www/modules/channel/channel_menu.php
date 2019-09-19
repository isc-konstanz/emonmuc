<?php

    $menu['sidebar']['emoncms'][] = array(
        'text' => _("Channels"),
        'path' => 'channel/view',
        'icon' => 'channel',
        'order' => 0
    );

    $menu['includes']['icons'][] = <<< icon
            <symbol id="icon-channel" viewBox="0 0 32 32">
                <!-- <title>channels</title> -->
                <path d="M32 21.152v3.424q0 0.224-0.16 0.384t-0.416 0.192h-24.576v3.424q0 0.224-0.16 0.384t-0.416 0.192q-0.192 0-0.416-0.192l-5.696-5.696q-0.16-0.192-0.16-0.416 0-0.256 0.16-0.416l5.728-5.696q0.16-0.16 0.384-0.16 0.256 0 0.416 0.16t0.16 0.416v3.424h24.576q0.224 0 0.416 0.16t0.16 0.416zM32 11.424q0 0.256-0.16 0.416l-5.728 5.696q-0.16 0.192-0.384 0.192-0.256 0-0.416-0.192t-0.16-0.384v-3.424h-24.576q-0.224 0-0.416-0.192t-0.16-0.384v-3.424q0-0.256 0.16-0.416t0.416-0.16h24.576v-3.424q0-0.256 0.16-0.416t0.416-0.16q0.192 0 0.416 0.16l5.696 5.696q0.16 0.16 0.16 0.416z"></path>
            </symbol>
icon;
