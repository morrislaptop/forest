<?php

$data = array();

foreach ($nodes as $node){
    $data[] = array(
        "text" => $node['title'],
        "id" => $node['id'],
        "cls" => "folder",
        #"leaf" => $node['lft'] + 1 == $node['rght']
        'leat' => true // set to true so we can always make something a parent
    );
}

echo $javascript->object($data);

?>