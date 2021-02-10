<?php
switch ($_REQUEST['op']){
    case 'frontend':
        $file = __DIR__.'/functions.js';
        $handle = fopen($file, 'r');
        $content = fread($handle, filesize($file));
        $content = str_replace('{ecwid_app}', $AppConfig['ecwid_app'], $content);
        fclose($handle);
        header('content-type: text/javascript');
        echo $content;
        break;
}