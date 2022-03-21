<?php

$words = [
    'Luego coge el mar y',
    'ponlo sobre las rocas',
    'Y coloca la selva',
    'encima de todo',
    'Ya tienes tu colina',
    'Midela y sigue'
];

$alphabet = [
    'A' => 'symb1',
    'B' => 'symb2',
    'C' => 'symb3',
    'D' => 'symb4',
    'E' => 'symb5',
    'F' => 'symb6',
    'G' => 'symb7',
    'H' => 'symb8',
    'I' => 'symb9',
    'J' => 'symb10',
    'K' => 'symb11',
    'L' => 'symb12',
    'M' => 'symb13',
    'N' => 'symb14',
    'O' => 'symb15',
    'P' => 'symb16',
    'Q' => 'symb17',
    'R' => 'symb18',
    'S' => 'symb19',
    'T' => 'symb20',
    'U' => 'symb21',
    'V' => 'symb22',
    'W' => 'symb23',
    'X' => 'symb24',
    'Y' => 'symb25',
    'Z' => 'symb26',
    ' ' => 's_symb1',
];

foreach($words as $key => $word){
    $key++;
    echo "[root/sf_alphabet/answer/word".$key."]\n";
    $word_split = str_split(strtoupper($word));
    foreach($word_split as $num => $char){
        $num++;
        echo $num.'-'.$alphabet[$char].'='.$char."\n";
    }
}