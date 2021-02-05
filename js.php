<?php
switch ($_REQUEST['op']){
    case 'frontend':
        $handle = fopen('functions.js', 'r');
        $content = fread($handle, filesize('functions.js'));
        $content = str_replace('{ecwid_app}', $AppConfig['ecwid_app'], $content);
        fclose($handle);
        header('content-type: text/javascript');
        echo $content;
        break;
}