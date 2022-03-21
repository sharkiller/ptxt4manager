<?php
require("class.pakmanager.php");


// Change to FALSE to pack folder into pak file.
// Change to TRUE to unpack pak file into folder.
$unpack = true;


// First boolean parameter if want debug print on the script.
// Second boolean parameter if want to print debug on console mode instead of html.
$pak = new pakmanager(true, true);
try {
    if($unpack){
        $pak->unpack(
            dirname(__FILE__) . '/_awakened/_input/en.pak', // pak path of file to unpack.
            dirname(__FILE__) . '/_awakened/', // Destination folder for the unpacked folder.
            true, false);
    }else{
        $pak->pack(
            dirname(__FILE__) . '/_awakened/en/', // folder path to pack into ptxt4.
            dirname(__FILE__) . '/_awakened/', // Destination folder for the pak file.
            false, false);
    }
} catch (Exception $message) {
    die($message);
}
