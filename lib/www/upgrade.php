<?php
require_once dirname(__FILE__)."/core.php";
require_once "Lib/dbschemasetup.php";
print json_encode(db_schema_setup($mysqli,load_db_schema(),true))."\n";
