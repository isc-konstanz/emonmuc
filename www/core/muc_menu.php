<?php

global $session;
if ($session["write"] && $session["admin"]) {
    $menu["setup"]["l2"]['muc'] = array(
        "name"=>_("Controllers"),
        "href"=>"muc/view",
        "order"=>190,
        "icon"=>"muc"
    );
}

$menu['includes']['icons'][] = <<< ICON
<symbol id="icon-muc" viewBox="0 0 27 32">
    <!--  <title>muc</title> -->
    <path d="M18.272 16q0 1.888-1.312 3.232t-3.232 1.344-3.232-1.344-1.344-3.232 1.344-3.232 3.232-1.344 3.232 1.344 1.312 3.232zM20.576 16q0-2.848-2.016-4.864t-4.832-1.984-4.864 1.984-2.016 4.864 2.016 4.832 4.864 2.016 4.832-2.016 2.016-4.832zM22.848 16q0 3.776-2.656 6.464t-6.464 2.688-6.464-2.688-2.688-6.464 2.688-6.464 6.464-2.688 6.464 2.688 2.656 6.464zM25.152 16q0-2.336-0.928-4.448t-2.432-3.648-3.648-2.432-4.416-0.896-4.448 0.896-3.648 2.432-2.432 3.648-0.928 4.448 0.928 4.448 2.432 3.616 3.648 2.464 4.448 0.896 4.416-0.896 3.648-2.464 2.432-3.616 0.928-4.448zM27.424 16q0 3.744-1.824 6.88t-4.992 4.992-6.88 1.856-6.912-1.856-4.96-4.992-1.856-6.88 1.856-6.88 4.96-4.992 6.912-1.856 6.88 1.856 4.992 4.992 1.824 6.88z"></path>
</symbol>
ICON;
