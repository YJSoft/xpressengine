<?php
if (file_exists($includePath)) {
    include_once $includePath;
} else {
    echo "Can not find {$includePath}";
}
?>
