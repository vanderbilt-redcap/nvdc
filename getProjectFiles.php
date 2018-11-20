<?php
$html = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . "html" . DIRECTORY_SEPARATOR . "base.html");

// replace some contents in html head
$html = str_replace("STYLESHEET", $module->getUrl('css' . DIRECTORY_SEPARATOR . 'stylesheet.css') , $html);
$html = str_replace("{TITLE}", "Get Project Files", $html);
$html = str_replace("{BODY}", $module->printall(), $html);

echo $html;