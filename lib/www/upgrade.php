<?php
require_once dirname(__FILE__)."/core.php";
require_once "Lib/dbschemasetup.php";

$result = db_schema_setup($mysqli,load_db_schema(),true);
foreach($result as $operation) {
    print $operation."\n";
}
