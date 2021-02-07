<?php
ob_start();
include (dirname(__DIR__, 2).'/index.html');
$content = ob_get_contents();
ob_get_clean();
echo str_replace('loading.css', '../../loading.css', $content);
?>