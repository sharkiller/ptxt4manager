<?php

$key_name = 'root/register/pages/page308/choiseSet/set';
$words = [
    'beber',
    'bebido',
    'beber o estar bebido',
    'pagina 308',
    '308'
];

foreach($words as $key => $word){
    echo "[$key_name".($key+1)."]\n";
    $word_split = str_split(strtoupper($word));
    foreach($word_split as $num => $char){
		if($char == ' '){
			$k = 'SPACE';
		}else{
			$k = 'KEY_'.$char;
		}
        echo ($num+1).'-'.$k.'='.$char."\n";
    }
}