<?php
const BR = '</br>';
function pr($arr){
    echo('<pre>');
    var_dump($arr);
    echo('</pre>');
}
function prs($arr){
    echo('<pre>');
    var_dump($arr);
    echo('</pre>');
    die();
}
function included($path, $data){
    include $path;
}