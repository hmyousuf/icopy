<?php
function test($_) {
    return $_ ? 'OK' : 'ERROR';
}


function pretty_byte($bytes, $decimals = 2) {
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1000, $factor)) . @$size[$factor];
}

function bd_date($raw_date, $type=0) {
    return(date('d-M-Y H:i:s', strtotime($raw_date)));
}

