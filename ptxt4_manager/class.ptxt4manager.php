<?php

/**
 * <p>The ptxt4manager class allows to unpack and pack language files of Frogwares games in ptxt4 format.</p>
 * <p>Create a text INI file for easy translations.</p>
 *
 * @author Sh4rkill3r
 * @version 1.0.0
 * @since 1.0.0  [2021-01-14] Support for Sherlock Holmes: The Awakened - Remastered
 * @created 2021-01-12
 * @see https://github.com/sharkiller/ptxt4manager
 * @license The MIT License
 */
class ptxt4manager {

    const VERSION = "1.0.0";

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var bool
     */
    private $console_mode = false;

    /**
     * INI file pointer
     * @var resource|false
     */
    private $ini_file = false;

    /**
     * Uncompressed binary data
     * @var string
     */
    private $binary_data = '';

    /**
     * Offset pointer
     * @var int
     */
    private $offset = 0;

    /**
     * @var array<string>
     */
    private $parent_groups;

    /**
     * Last group header on INI file
     * @var string
     */
    private $last_group = '';

    /**
     * @var array
     */
    private $locale;


    /**
     * ptxt4manager Constructor
     * @param bool $debug Print debug info.
     * @param bool $console_mode Print debug on console mode instead of HTML.
     */
    public function __construct($debug = false, $console_mode=false) {
        if($debug !== false)
            $this->debug = true;

        if($console_mode !== false){
            ini_set("xdebug.overload_var_dump", "off");
            $this->console_mode = true;
        }
    }


    /**
     * @param string $message
     * @param mixed $variable
     * @param bool $var_dump
     */
    private function debug(string $message, &$variable, $var_dump=true){
        $message .= "_NL_";
        if($this->debug){
            if($this->console_mode){
                $replace = [
                    "\n",
                    "\e[1;33m",
                    "\e[1;31m",
                    "\e[1;32m",
                    "\e[1;34m",
                    "",
                    "\e[0m",
                    "\e[1;36m-----------------------------------------------------\e[0m"
                ];
            }else{
                $replace = [
                    "<br>",
                    "<b style='color:#ffff00'>",
                    "<b style='color:#ff0000'>",
                    "<b style='color:#00ff00'>",
                    "<b style='color:#0000ff'>",
                    "<b>",
                    "</b>",
                    "<hr>"
                ];
            }
            $search = ["_NL_","_YELLOW_","_RED_","_GREEN_","_BLUE_","_BOLD_","_CLOSE_","_SEP_"];
            echo str_replace($search, $replace, $message);
            if($var_dump) var_dump($variable);
        }
    }


    /**
     * Replace or restore reserved characters used on INI files values
     * @param int $chr Decimal representation of the character
     * @param bool $replace Replace or restore. true=replace | false=restore
     * @return int
     */
    private function sanitize_string(int $chr, $replace=true){
        if($replace){
            $sanitize = [
                '10' => 449, // new line
                '33' => 451, // !
                '34' => 698, // "
                '59' => 450, // ;
            ];
        }else{
            $sanitize = [
                '449' => 10, // new line
                '451' => 33, // !
                '698' => 34, // "
                '450' => 59, // ;
            ];
        }
        if( array_key_exists("$chr",$sanitize) ){
            return $sanitize[$chr];
        }else{
            return $chr;
        }
    }



    /**
     * Read desired variable type from binary data
     * @param string $type Variable type to read
     * @return mixed
     * @throws Exception
     * @uses IntlChar
     */
    private function get(string $type) {

        if( $this->binary_data === false || $this->binary_data == '')
            throw new Exception("Something wrong with binary data.");

        switch($type){
            case 'byte':
                $value = unpack('C', $this->binary_data, $this->offset);
                $this->offset += 1;
                return $value[1];
            case 'short':
                $value = unpack('v', $this->binary_data, $this->offset);
                $this->offset += 2;
                return $value[1];
            case 'long':
                $value = unpack('V', $this->binary_data, $this->offset);
                $this->offset += 4;
                return $value[1];
            case 'longlong':
                $value = unpack('P', $this->binary_data, $this->offset);
                $this->offset += 8;
                return $value[1];
            case 'string':
                $length = $this->get('byte');
                $str = '';
                if($length > 0){
                    $value = unpack("C$length", $this->binary_data, $this->offset);
                    $this->offset += $length;
                    foreach($value as $char){
                        $str .= chr($char);
                    }
                }
                return $str;
            case 'unicode_string':
                $length = $this->get('short');
                $str = '';
                if($length > 0) {
                    for ($i = 1; $i <= $length; $i++) {
                        $str .= IntlChar::chr($this->sanitize_string($this->get('short')));
                    }
                }
                return $str;
            default:
                throw new Exception("Wrong get type: $type");
        }

    }

    /**
     * Translate variable to desired binary data type
     * @param string $type Variable type to write
     * @param mixed $data Data to write
     * @return mixed
     * @throws Exception
     * @uses IntlChar
     */
    private function set(string $type, $data){

        switch($type){
            case 'byte':
                $value = pack('C', $data);
                $this->offset += 1;
                return $value;
            case 'short':
                $value = pack('v', $data);
                $this->offset += 2;
                return $value;
            case 'long':
                $value = pack('V', $data);
                $this->offset += 4;
                return $value;
            case 'longlong':
                $value = pack('P', $data);
                $this->offset += 8;
                return $value;
            case 'string':
                $length = strlen($data);
                $value = $this->set('byte', $length);
                $value .= pack("a{$length}", $data);
                $this->offset += $length;
                return $value;
            case 'unicode_string':
                $length = mb_strlen($data, "UTF-8");;
                $value = $this->set('short', $length);
                for ($i = 0; $i < $length; $i++) {
                    $char = mb_substr($data, $i, 1, "UTF-8");
                    $value .= $this->set('short', $this->sanitize_string(IntlChar::ord($char), false));
                }
                return $value;
            default:
                throw new Exception("Wrong set type: $type");
        }

    }


    /**
     * Read binary data
     * @throws Exception
     */
    private function read_data(){
        $this->offset = 0;

        // Main path root
        $main_path = $this->get("string");
        $this->parent_groups = [$main_path];

        // Unknown data
        $this->get("long");

        $this->read_groups();
    }


    /**
     * Parse groups on binary data
     * @throws Exception
     */
    private function read_groups(){
        $this->debug("_BOLD_parse_groups() offset= ".str_pad(dechex($this->offset), 8, '0', STR_PAD_LEFT)."_CLOSE_", $this, false);

        $group_count = $this->get("long");
        $this->debug('_BLUE_Group count_CLOSE_', $group_count);

        for ($i = 1; $i <= $group_count; $i++) {

            $this->debug("_SEP__SEP__SEP__SEP_", $this, false);
            $group_name = $this->get("string");
            $this->debug("_BLUE_Group name_CLOSE_", $group_name);

            $unknown = $this->get("long");
            $this->debug("_BLUE_Group Unknown Value_CLOSE_", $unknown);

            $this->parent_groups[] = $group_name;

            $this->read_items();

            array_pop($this->parent_groups);

            $this->debug("_BLUE_GROUP COUNTER_CLOSE_",$i);
        }
    }


    /**
     * Parse items on binary data
     * @throws Exception
     */
    private function read_items(){
        $this->debug("_BOLD_parse_items() offset= ".str_pad(dechex($this->offset), 8, '0', STR_PAD_LEFT)."_CLOSE_", $this, false);

        $group_items = $this->get("long");
        $this->debug("_BLUE_Group item count_CLOSE_", $group_items);

        for ($order = 1; $order <= $group_items; $order++){
            $this->read_item($order);

            $this->debug("_BLUE_ITEM COUNTER_CLOSE_",$order);
            $this->debug("_SEP_", $this, false);
        }
    }


    /**
     * Parse item on binary data
     * @param int $order
     * @throws Exception
     */
    private function read_item(int $order){
        $this->debug("_BOLD_parse_item() offset= ".str_pad(dechex($this->offset), 8, '0', STR_PAD_LEFT)."_CLOSE_", $this, false);

        $item_name = $this->get("string");
        $this->debug("_BLUE_Item name_CLOSE_", $item_name);

        /**
         * Possible type var
         * 6=group
         * 3=translated
         * 0=untranslated
         */
        $type = $this->get("long");
        $this->debug("_BLUE_Item type value_CLOSE_", $type);

        if($type == 6){
            $this->debug("_RED_IS GROUP_CLOSE_", $this, false);
            $this->parent_groups[] = $item_name;
            $this->read_items();
            array_pop($this->parent_groups);
            return;
        }

        if($type == 0){
            $item_translation = '';
        }else{
            $item_translation = $this->get("unicode_string");
        }
        $this->debug("_BLUE_Translation_CLOSE_", $item_translation);
        $this->get("long");

//        if(strlen($item_translation) > 0){ TODO TEST

        $group = implode('/', $this->parent_groups);
        if($this->last_group != $group){
            $this->last_group = $group;
            fwrite($this->ini_file, "[$group]".PHP_EOL);
        }
        fwrite($this->ini_file, "$order-$item_name=$item_translation".PHP_EOL);

        $this->debug("_YELLOW_"."$group/$item_name"."_CLOSE_", $this, false);
        $this->debug("_RED_".$item_name."_CLOSE_ = _GREEN_$item_translation"."_CLOSE_", $this, false);
    }


    /**
     * Create a proper array separating the INI headers
     * @param array $locale
     * @return array
     */
    private function cleanup_locale(array $locale){
        $clean_locale = [];
        foreach($locale as $key => $value){
            $groups = explode('/', $key);
            // TODO REMOVE?
            //$value = count($value);
            foreach(array_reverse($groups) as $group_key){
                $value = [$group_key => $value];
            }
            $clean_locale = array_merge_recursive($clean_locale, $value);
        }
        return $clean_locale;
    }


    /**
     * Generate binary data
     * @throws Exception
     */
    private function write_data(){
        $this->offset = 0;
        $this->binary_data = '';

        $main_group = key($this->locale);

        $this->binary_data .= $this->set('string', $main_group);
        $this->binary_data .= $this->set('long', 0);
        $this->binary_data .= $this->set('long', count($this->locale[$main_group]));

        $this->write_groups($this->locale[$main_group]);
    }

    /**
     * Write groups on binary data
     * @param array $locale Array containing a group
     * @throws Exception
     */
    private function write_groups(array $locale){

        foreach($locale as $key => $value){

            if( is_array($value) ){
                $this->binary_data .= $this->set('string', $key);
                $this->binary_data .= $this->set('long', 6);
                $this->binary_data .= $this->set('long', count($value));
                $this->write_groups($value);
                continue;
            }

            $real_key = explode('-', $key, 2);
            $this->binary_data .= $this->set('string', $real_key[1]);
            if( strlen($value) > 0 ){
                $this->binary_data .= $this->set('long', 3);
                $this->binary_data .= $this->set('unicode_string', $value);
                $this->binary_data .= $this->set('long', 0);
            }else{
                $this->binary_data .= $this->set('longlong', 0);
            }
        }

    }


    /**
     * Unpack ptxt4 file and create translation INI file
     *
     * @param string  $input        Path to original .ptxt4 file
     * @param string  $output       Path to output folder
     * @param bool    $backup       Backup translation INI file
     * @param bool    $write2disk   Write uncompressed data to disk. Useful for debugging purposes.
     * @throws Exception
     */
    public function unpack(string $input, string $output, $backup=true, $write2disk=false){

        // Verify original file
        if( !is_readable($input) ){
            throw new Exception("The original file does not exist or is not readable: $input");
        }
        $input_pathinfo = pathinfo($input);
        if( !in_array($input_pathinfo['extension'], ['ptxt4','ptxt4@loc']) ) {
            throw new Exception("The original file extension is not valid: 'ptxt4' or 'ptxt4@loc'");
        }
        if( realpath($input_pathinfo['dirname']) == realpath($output) ){
            throw new Exception("The output folder should be different from input file.");
        }

        // Open original file
        $original_file = fopen($input, "rb");
        if($original_file === false){
            throw new Exception("The original file cannot be opened: $input");
        }

        // Get compressed data
        $is_gz = unpack('C', fread($original_file, 1))[1];
        if($is_gz !== 1){
            throw new Exception("The original file looks wrong.");
        }
        $compressed_size = unpack('V', fread($original_file, 4))[1];
        $uncompressed_size = unpack('V', fread($original_file, 4))[1];
        $compressed_binary = fread($original_file, $compressed_size);
        fclose($original_file);

        // Decompress data
        $binary_data = gzuncompress( $compressed_binary );
        if($binary_data === false){
            throw new Exception("The original file data cannot be uncompressed.");
        }
        if($uncompressed_size != strlen($binary_data)){
            throw new Exception("The original file uncompressed data size does not match with header.");
        }
        $this->binary_data = $binary_data;

        // Verify text INI file
        if( !is_dir($output) ){
            throw new Exception("The output path is not a folder: $output");
        }
        if( !is_writable($output) ){
            throw new Exception("The output folder is not writable.");
        }

        // Write uncompressed data to disk if specified
        if($write2disk){
            $filepath_data = $output."/".$input_pathinfo['filename']."_original.dat";

            $locale_original = fopen($filepath_data, "wb");
            if($locale_original === false){
                throw new Exception("The original data file cannot be written: $filepath_data");
            }
            fwrite($locale_original, $binary_data);
            fclose($locale_original);
        }

        $filepath_ini = $output."/".$input_pathinfo['filename'].".ini";
        if( file_exists($filepath_ini)){
            if( !is_writable($filepath_ini) ){
                throw new Exception("The text INI file is not writable: $filepath_ini");
            }
            // Create backup of text INI file if set
            if($backup){
                copy($filepath_ini, $filepath_ini."_backup_".date("Y-m-d_H-i-s"));
            }
        }
        $this->ini_file = fopen($filepath_ini, "wb");
        if($this->ini_file === false){
            throw new Exception("The text INI file cannot be written: $filepath_ini");
        }
        fwrite($this->ini_file, "; =============================================================".PHP_EOL);
        fwrite($this->ini_file, "; File created by ptxt4manager v".$this::VERSION.PHP_EOL);
        fwrite($this->ini_file, "; https://github.com/sharkiller/ptxt4manager".PHP_EOL);
        fwrite($this->ini_file, "; Developed by Sh4rkill3r".PHP_EOL);
        fwrite($this->ini_file, "; =============================================================".PHP_EOL);
        fwrite($this->ini_file, "; Some characters are reserved on INI files and need to be replaced by a wildcard.".PHP_EOL);
        fwrite($this->ini_file, "; The wildcards are replaced with the real characters when packing the final file.".PHP_EOL);
        fwrite($this->ini_file, "; Copy and paste to replace desired characters.".PHP_EOL);
        fwrite($this->ini_file, "; IMPORTANT: Some characters looks similar but are different.".PHP_EOL);
        fwrite($this->ini_file, ";   ǁ <- New line".PHP_EOL);
        fwrite($this->ini_file, ";   ǃ <- Exclamation mark".PHP_EOL);
        fwrite($this->ini_file, ";   ʺ <- Quotation mark".PHP_EOL);
        fwrite($this->ini_file, ";   ǂ <- Semicolon".PHP_EOL);
        fwrite($this->ini_file, "; =============================================================".PHP_EOL.PHP_EOL);

        $this->read_data();

        fclose($this->ini_file);
    }


    /**
     * Pack the translation INI file into a .ptxt4
     *
     * @param string  $input        Path to translation INI file
     * @param string  $output       Path to output folder
     * @param bool    $backup       Backup modified .ptxt4 file
     * @param bool    $write2disk   Write uncompressed packed data to disk. Useful for debugging purposes.
     * @throws Exception
     */
    public function pack(string $input, string $output, $backup=true, $write2disk=false){

        // Verify original file
        if( !is_readable($input) ){
            throw new Exception("The translation INI file does not exist or is not readable: $input");
        }
        $input_pathinfo = pathinfo($input);
        if( $input_pathinfo['extension'] != 'ini' ) {
            throw new Exception("The translation file extension is not valid: '.ini'");
        }

        $locale = parse_ini_file($input, true, INI_SCANNER_RAW);
        $locale = $this->cleanup_locale($locale);
        // TODO REMOVE?
        $this->debug('clean_locale',$locale);
        $this->locale = $locale;

        if( !is_dir($output) ){
            throw new Exception("The output path is not a folder: $output");
        }
        if( !is_writable($output) ){
            throw new Exception("The output folder is not writable.");
        }

        $this->write_data();

        // Write uncompressed data to disk if specified
        if($write2disk){
            $filepath_data = $output."/".$input_pathinfo['filename']."_modified.dat";

            $locale_modified = fopen($filepath_data, "wb");
            if($locale_modified === false){
                throw new Exception("The modified data file cannot be written: $filepath_data");
            }
            fwrite($locale_modified, $this->binary_data);
            fclose($locale_modified);
        }

        // Verify packed file
        $filepath_pack = $output."/".$input_pathinfo['filename'].".ptxt4@loc";
        if( file_exists($filepath_pack)){
            if( !is_writable($filepath_pack) ){
                throw new Exception("The packed file is not writable: $filepath_pack");
            }
            // Create backup of packed file if set
            if($backup){
                copy($filepath_pack, $filepath_pack."_backup_".date("Y-m-d_H-i-s"));
            }
        }

        $file_pack = fopen($filepath_pack, "wb");
        if($file_pack === false){
            throw new Exception("The packed file cannot be written: $file_pack");
        }
        $binary_data_compressed = gzdeflate($this->binary_data, 9,ZLIB_ENCODING_DEFLATE);

        fwrite($file_pack, $this->set('byte', 1));
        fwrite($file_pack, $this->set('long', strlen($binary_data_compressed)));
        fwrite($file_pack, $this->set('long', strlen($this->binary_data)));
        fwrite($file_pack, $binary_data_compressed);
        fclose($file_pack);

    }

}
