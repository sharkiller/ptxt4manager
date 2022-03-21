<?php

/**
 * <p>The pakmanager class allows to unpack and pack files of Frogwares games in pak format.</p>
 *
 * @author Sh4rkill3r
 * @version 1.0.0
 * @since 1.0.0  [2021-01-14] Support for Sherlock Holmes: The Awakened - Remastered
 * @created 2021-01-12
 * @see https://github.com/sharkiller/ptxt4manager
 * @license The MIT License
 */
class pakmanager {

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
    private $pak_file = false;

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
     * @var string
     */
    private $input = '';

    /**
     * @var string
     */
    private $output = '';

    /**
     * @var array
     */
    private $files;

    /**
     * @var array
     */
    private $folders;

    /**
     * @var array
     */
    private $files_by_extension;

    /**
     * @var array
     */
    private $files_by_path;

    /**
     * @var array
     */
    private $game_structure = [
        // 0: Sherlock Holmes: The Awakened - Remastered
        [
            []
        ]
    ];


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
                $length = $this->get('short');
                $value = unpack("C$length", $this->binary_data, $this->offset);
                $this->offset += $length;
                $str = '';
                foreach($value as $char){
                    $str .= chr($char);
                }
                return $str;
            case 'unicode_string':
                $length = $this->get('short');
                $str = '';
                if($length == 0) {
                    $this->get('short');
                    return $str;
                }
                for ($i=1; $i<=$length; $i++) {
                    $str .= IntlChar::chr( $this->get('short') );
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
    function set(string $type, $data){

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
                $value = $this->set('short', $length);
                $value .= pack("a{$length}", $data);
                $this->offset += $length;
                return $value;
            case 'unicode_string':
                $length = mb_strlen($data, "UTF-8");;
                $value = $this->set('short', $length);
                for ($i = 0; $i < $length; $i++) {
                    $char = mb_substr($data, $i, 1, "UTF-8");
                    $value .= $this->set('short', IntlChar::ord($char));
                }
                return $value;
            default:
                throw new Exception("Wrong set type: $type");
        }

    }


    /**
     * Write groups on binary data
     * @param string $files_data
     * @return array
     * @throws Exception
     */
    private function parse_files(string $files_data){

        $this->debug("_BLUE_parse_files()_CLOSE_" ,$files_data, false);

        $this->offset = 0;
        $this->binary_data = $files_data;
        $length = strlen($this->binary_data);
        $this->debug('length', $length);

        $files_final = [];
        do{
            $files = [];
            $num_files = $this->get('long');
            for($i = 0; $i < $num_files; $i++){
                $files[] = [
                    'flags' => $this->get('short'),
                    'folder_id' => $this->get('short'),
                    'filename' => $this->get('string'),
                    'offset' => $this->get('long'),
                    'size' => $this->get('long'),
                    'zero' => $this->get('long'),
                    'timestamp' => ($this->get('longlong')/10000000-11644473600),
                ];
            }
            $extension_verbose = $this->get('string');
            $extension = $this->get('string');
            $path = $this->get('string');
            $extra = $this->get('short');

            foreach($files as &$file){
                $file['ext'] = $extension;
                $file['ext_verbose'] = $extension_verbose;
                $file['path'] = $path;
                $file['extra'] = $extra;
            }
            $files_final[$extension_verbose] = $files;

        }while($this->offset < $length);

        $this->debug("_BLUE_files_CLOSE_" ,$files_final);

        return $files_final;
    }


    /**
     * Write groups on binary data
     * @param string $folders_data
     * @return array
     * @throws Exception
     */
    private function parse_folders(string $folders_data){

        $this->debug("_BLUE_parse_folders()_CLOSE_" ,$folders_data, false);

        $this->offset = 0;
        $this->binary_data = $folders_data;
        $length = strlen($this->binary_data);

        $folders = [];
        $num_extensions = $this->get('long');
        $num_folders = $this->get('long');
        for($i = 0; $i < $num_folders; $i++){
            $folders[] = $this->get('string');
        }

        $this->debug("_BLUE_folders_CLOSE_" ,$folders);

        return $folders;
    }


    private function write_files(){

        foreach($this->files as $key => $files_group){
            $this->debug('_BLUE_Files: '.$key.'_CLOSE_', $this->files, false);
            foreach($files_group as $file){
                $this->debug('file', $file);
                $this->offset = $file['offset'];
                fseek($this->pak_file, $file['offset']);
                $binary_data = fread($this->pak_file, $file['size']);

                $folder_name = $this->folders[$file['folder_id']];
                $folderpath = $this->output.'/'.$folder_name;

                $path_extra = explode('\\', $file['filename']);
                if( count($path_extra) > 1){
                    $path_extra2 = explode('\\', $folder_name);
                    if( end($path_extra2) == $path_extra[0]){
                        $folderpath .= '__/';
                    }
                    $filename = explode('\\', $file['filename']);
                    $filename = end($filename);
                }else{
                    $filename = $file['filename'];
                    $folderpath .= '/';
                }
                if(!is_dir($folderpath)){
                    $res = mkdir($folderpath, 0755, true);
                }
                $this->debug(realpath($folderpath).'/'.$filename.$file['ext_verbose'], $file, false);
                $f = fopen(realpath($folderpath).'/'.$filename.$file['ext_verbose'], 'wb');
                fwrite($f, $binary_data);
                fclose($f);
            }
        }
    }



    /**
     * @param string $directory
     * @return array
     */
    private function parse_directory(string $directory) {
        $files_by_path = [];
        $files = scandir($directory);
        foreach ($files as $key => $name){
            if ( !in_array($name, ['.','..','_pakmanager_debug']) ) {
                if (is_dir($directory.'/'.$name)) {
                    $files_by_path[$name] = $this->parse_directory($directory.'/'.$name);
                }else{
                    $extension = pathinfo($directory.'/'.$name, PATHINFO_EXTENSION);
                    $this->files_by_extension[$extension][] = realpath($directory.'/'.$name);
                    $files_by_path[] = $name;
                }
            }
        }

        return $files_by_path;
    }


    /**
     * @param bool $write2disk
     * @throws Exception
     */
    private function pack_files(bool $write2disk){
        $this->debug('_BLUE_pack_files()_CLOSE_', $this->files, false);
        $this->offset = 0;
        $folders = [];
        $files_data = '';

        foreach($this->files_by_extension as $ext => $files){

            $files_data .= $this->set('long', count($files));
            $ext_simple = $ext;
            $ext_verbose = $ext;
            $path = '';

            foreach($files as $filepath){
                $input = realpath($this->input);
                $realpath = substr(str_replace($input, '', $filepath), 1);
                $this->debug('$realpath:', $realpath);
                $pathinfo = pathinfo($realpath);
                $this->debug('$pathinfo:', $pathinfo);
                $ext_data = explode('@', $pathinfo['extension']);
                if( count($ext_data) == 2 ){
                    $ext_simple = $ext_data[0];
                    $path = $ext_data[1];
                }

                $include_path = substr($pathinfo['dirname'], -2);
                if($include_path  == '__'){
                    $pathinfo['dirname'] = str_replace('__', '', $pathinfo['dirname']);
                    $include_path = explode('\\', $pathinfo['dirname']);
                    $include_path = end($include_path).'\\';
                }else{
                    $include_path = '';
                }
                if( !in_array($pathinfo['dirname'], $folders) ){
                    $folders[] = $pathinfo['dirname'];
                }

                // id
                $files_data .= $this->set('short', ($include_path==''?1:6));
                // folder_id
                $files_data .= $this->set('short', array_search($pathinfo['dirname'], $folders));
                // filename
                $files_data .= $this->set('string', $include_path.$pathinfo['filename']);
                // offset
                $files_data .= $this->set('long', ftell($this->pak_file));

                // Read file to pack
                $filesize = filesize($filepath);
                $file = fopen($filepath, 'rb');
                $filedata = fread($file, $filesize);
                fclose($file);

                // Write file to pak
                fwrite($this->pak_file, $filedata);

                // filesize
                $files_data .= $this->set('long', $filesize);
                // include_path
                $files_data .= $this->set('long', ($include_path==''?0:1));
                // Timestamp
                $files_data .= $this->set('longlong', (int)((microtime(true)+11644473600)*10000000));

            }

            // Verbose extension
            $files_data .= $this->set('string', '.'.$ext_verbose);
            // Extension
            $files_data .= $this->set('string', '.'.$ext_simple);
            // Path
            $files_data .= $this->set('string', $path);
            // Extra
            $files_data .= $this->set('short', 0);

        }

        $this->debug('_BLUE_$folders:_CLOSE_', $folders);

        $folders_data = '';
        $folders_data .= $this->set('long', count($this->files_by_extension));
        $folders_data .= $this->set('long', count($folders));
        foreach($folders as $folder){
            $folders_data .= $this->set('string', $folder);
        }

        if( $write2disk ){
            if( !is_dir($this->input.'/_pakmanager_debug/') ){
                mkdir($this->input.'/_pakmanager_debug/', 0755, true);
            }
            $filepath_files = $this->input.'/_pakmanager_debug/files_modified.dat';
            $file_files = fopen($filepath_files, "wb");
            if($file_files === false){
                throw new Exception("The modified data file cannot be written: $filepath_files");
            }
            fwrite($file_files, $files_data);
            fclose($file_files);

            $filepath_folders = $this->input.'/_pakmanager_debug/folder_modified.dat';
            $file_folders = fopen($filepath_folders, "wb");
            if($file_folders === false){
                throw new Exception("The modified data file cannot be written: $filepath_folders");
            }
            fwrite($file_folders, $folders_data);
            fclose($file_folders);
        }

        $offset = ftell($this->pak_file);

        // Write files data
        $uncompressed_size = strlen($files_data);
        $files_data = gzdeflate($files_data, 9,ZLIB_ENCODING_DEFLATE);
        $compressed_size = strlen($files_data);
        fwrite($this->pak_file, $this->set('byte', 1));
        fwrite($this->pak_file, $this->set('long', $compressed_size));
        fwrite($this->pak_file, $this->set('long', $uncompressed_size));
        fwrite($this->pak_file, $files_data);

        // Write folders data
        $uncompressed_size = strlen($folders_data);
        $folders_data = gzdeflate($folders_data, 9,ZLIB_ENCODING_DEFLATE);
        $compressed_size = strlen($folders_data);
        fwrite($this->pak_file, $this->set('byte', 1));
        fwrite($this->pak_file, $this->set('long', $compressed_size));
        fwrite($this->pak_file, $this->set('long', $uncompressed_size));
        fwrite($this->pak_file, $folders_data);

        fwrite($this->pak_file, $this->set('long', $offset));
        fclose($this->pak_file);

    }



    /**
     * Unpack pak file
     *
     * @param string $input Path to original .pak file
     * @param string $output Path to output folder
     * @param bool $backup Backup output folder
     * @param bool $write2disk
     * @throws Exception
     */
    public function unpack(string $input, string $output, $backup=true, $write2disk=false){

        // Verify original file
        if( !is_readable($input) ){
            throw new Exception("The original file does not exist or is not readable: $input");
        }
        $input_pathinfo = pathinfo($input);
        if( $input_pathinfo['extension'] != 'pak') {
            throw new Exception("The original file extension is not valid. Expected: .pak, got .".$input_pathinfo['extension']);
        }

        // Open original file
        $this->pak_file = fopen($input, "rb");
        if($this->pak_file === false){
            throw new Exception("The original file cannot be opened: $input");
        }

        fseek($this->pak_file, -4, SEEK_END);
        $offset_end = ftell($this->pak_file);
        $offset_start = unpack('V', fread($this->pak_file, 4))[1];
        fseek($this->pak_file, $offset_start);

        $this->binary_data = fread($this->pak_file, ($offset_end-$offset_start));

        $this->offset = 0;
        // Get files data
        $is_gz = $this->get('byte');
        $compressed_size = $this->get('long');
        $uncompressed_size = $this->get('long');
        $compressed_binary = substr($this->binary_data, $this->offset, $compressed_size);
        $this->offset += strlen($compressed_binary);

        // Verify text INI file
        if( !is_dir($output) ){
            throw new Exception("The output path is not a folder: $output");
        }
        if( !is_writable($output) ){
            throw new Exception("The output folder is not writable.");
        }

        $output = $output."/".$input_pathinfo['filename'];
        if( is_dir($output) ){
            $output = realpath($output);
            if( !rename($output,$output."_backup_".date("Y-m-d_H-i-s")) ){
                throw new Exception("Files in the output folder are being used by other programs.");
            }
        }
        mkdir($output, 0755, true);
        $this->output = $output;

        // Decompress data
        $files_data = gzuncompress( $compressed_binary );
        if($write2disk){
            mkdir($output.'/_pakmanager_debug/', 0755, true);
            $filepath_files = $output.'/_pakmanager_debug/files_original.dat';
            $file_files = fopen($filepath_files, "wb");
            if($file_files === false){
                throw new Exception("The original data file cannot be written: $filepath_files");
            }
            fwrite($file_files, $files_data);
            fclose($file_files);
        }

        // Get folders data
        $is_gz = $this->get('byte');
        $compressed_size = $this->get('long');
        $uncompressed_size = $this->get('long');
        $compressed_binary = substr($this->binary_data, $this->offset, $compressed_size);
        $this->offset += strlen($compressed_binary);

        // Decompress data
        $folders_data = gzuncompress( $compressed_binary );
        if($write2disk){
            $filepath_folders = $output.'/_pakmanager_debug/folder_original.dat';
            $file_folders = fopen($filepath_folders, "wb");
            if($file_folders === false){
                throw new Exception("The original data file cannot be written: $filepath_folders");
            }
            fwrite($file_folders, $folders_data);
            fclose($file_folders);
        }

        $this->files = $this->parse_files($files_data);
        $this->folders = $this->parse_folders($folders_data);

        if($this->debug){
            foreach($this->files as $key => $files_group){
                $this->debug('_BLUE_Files: '.$key.'_CLOSE_', $this->files, false);
                foreach($files_group as $file){
                    $this->debug($this->folders[$file['folder_id']].'/'.$file['path'].'/'.$file['filename'].$file['ext'], $file, false);
                }
            }

            $this->debug('_BLUE_Files_CLOSE_', $files);
        }

        $this->write_files();

        fclose($this->pak_file);
    }


    /**
     * Pack folder into a .pak file
     *
     * @param string  $input        Path to input folder
     * @param string  $output       Path to output folder
     * @param bool    $backup       Backup modified .pak file
     * @param bool    $write2disk   Write uncompressed packed data to disk. Useful for debugging purposes.
     * @throws Exception
     */
    public function pack(string $input, string $output, $backup=true, $write2disk=false){

        if( !is_dir($input) ){
            throw new Exception("The input path is not a folder: $input");
        }
        $this->input = $input;
        if( !is_dir($output) ){
            throw new Exception("The output path is not a folder: $output");
        }
        $this->output = $output;

        //$this->files_by_extension = ['ogg@voice'=>[],'lip'=>[]];
        $this->files_by_path = $this->parse_directory($input);
        $this->debug('_BLUE_Files by extension_CLOSE_', $this->files_by_extension);
        //$this->debug('_BLUE_Files by path_CLOSE_', $this->files_by_path);

        $pak_name = basename(realpath($input)).'.pak';
        $this->debug('_BLUE_packname_CLOSE_', $pak_name);

        $pak_path = realpath($output).'/'.$pak_name;
        $this->debug('_BLUE_packname_CLOSE_', $pak_path);

        if( file_exists($pak_path) ){
            if( !is_writable($pak_path) ){
                throw new Exception("The output file is not writable.");
            }
            if( $backup ){
                if( !rename($pak_path,$pak_path."_backup_".date("Y-m-d_H-i-s")) ){
                    throw new Exception("The backup could not be made because the file is being used by another program.");
                }
            }
        }

        $this->pak_file = fopen($pak_path, 'wb');

        $this->pack_files($write2disk);

    }

}
