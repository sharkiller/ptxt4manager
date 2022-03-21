<?php
require("class.ptxt4manager.php");


// Change to FALSE to pack ptxt4 from ini.
// Change to TRUE to unpack ptxt4 to ini.
$unpack = true;


// First boolean parameter if want debug print on the script.
// Second boolean parameter if want to print debug on console mode instead of html.
$ptxt4 = new ptxt4manager(true, true); 
try {
    if($unpack){
        $ptxt4->unpack(
            dirname(__FILE__) . '/_awakened/input/locale_orig.ptxt4@loc', // ptxt4 path of file to unpack.
            dirname(__FILE__) . '/_awakened/', // Destination folder for the ini file.
            true, false);
    }else{
        $ptxt4->pack(
            dirname(__FILE__) . '/_awakened/locale.ini', // ini path of file to pack into ptxt4.
            dirname(__FILE__) . '/_awakened/', // Destination folder for the ptxt4 file.
            true, false);
    }
} catch (Exception $message) {
    die($message);
}
