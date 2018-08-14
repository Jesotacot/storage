<?php

use sisius\lib\storageapi\FTPStorage1;

require_once(dirname(dirname(__DIR__)) . "/init.php"); // prepare environment for tests

class FTPStorage1Test extends PHPUnit_Framework_TestCase {

    protected $local_tmp_dir = '';
    protected $remote_upload_location = '';
    protected $ftp_remote_upload_location = '';
    protected $directory_path_tmp = '';
    protected $file_path_tmp = '';
    protected $directory_path_upload = '';
    protected $file_path_upload = '';
    protected $options = [];
    protected $ftp_path = '';
    static $noexisto = "no existo";
    static $soyunfichero = "soyunfichero.txt";
    static $soyunacarpeta = "soyunacarpeta";
    public function environment_setup() {

        $this->local_tmp_dir = sys_get_temp_dir() . "/local";
        $this->remote_upload_location = "/upload";

        $this->options = ['ftp_server_address' => '192.168.1.46',
            'ftp_user_name' => 'ftpuser',
            'ftp_user_pass' => 'ftpuser',
            'remote_upload_location' => $this->remote_upload_location,
            'local_tmp_dir' => $this->local_tmp_dir];

        $this->ftp_path = 'ftp://' . $this->options['ftp_user_name'] . ':' . $this->options['ftp_user_pass'] . '@' . $this->options['ftp_server_address'];

        if (!file_exists($this->local_tmp_dir)) {
            @mkdir($this->local_tmp_dir);
        }
        $this->directory_path_tmp = $this->local_tmp_dir . "/soyunacarpeta";
        if (!file_exists($this->directory_path_tmp)) {
            @mkdir($this->directory_path_tmp);
        }
        $this->file_path_tmp = $this->local_tmp_dir . "/soyunfichero.txt";
        if (!file_exists($this->file_path_tmp)) {
            file_put_contents($this->file_path_tmp, "Pepe Pepito");
        }

        $this->ftp_remote_upload_location = $this->ftp_path . $this->remote_upload_location;
        if (!file_exists($this->ftp_remote_upload_location)) {
            @mkdir($this->ftp_remote_upload_location);
        }

        $this->directory_path_upload = $this->ftp_remote_upload_location . "/soyunacarpeta";
        if (!file_exists($this->directory_path_upload)) {
            @mkdir($this->directory_path_upload);
        }
        $this->file_path_upload = $this->ftp_remote_upload_location . "/soyunfichero.txt";
        if (!file_exists($this->file_path_upload)) {
            file_put_contents($this->file_path_upload, "Pepe Pepito");
        }
    }

    public function environment_destroy() {

        if (file_exists($this->local_tmp_dir)) {
            $this->rrmdir($this->local_tmp_dir);
        }

        if (file_exists($this->ftp_remote_upload_location)) {
            $this->rrmdir($this->ftp_remote_upload_location);
        }
    }

    public function rrmdir($src) {
        $dir = opendir($src);
        while (false !== ( $file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    @unlink($full);
                }
            }
        }
        closedir($dir);
        @rmdir($src);
    }

    public function test_connection() {
        $this->environment_setup();

        $ftp_storage = new FTPStorage1();
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->assertSame(false, $ftp_storage->setFTPServer('address', 'ftpuser', 'ftpuser'));
        $this->assertSame(false, $ftp_storage->init());
        $this->assertSame(FTPStorage1::FTP_ERROR_OPEN_CONN, $ftp_storage->getLastErrorCode());

        $this->assertSame(true, $ftp_storage->setFTPServer('192.168.1.46', 'nonftpuser', 'nonftpuser'));
        $this->assertSame(false, $ftp_storage->init());
        $this->assertSame(FTPStorage1::FTP_ERROR_LOGIN, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

    public function test_configure_storage() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        $this->assertSame(false, $ftp_storage->configure([
                    'ftp_server_addresskk' => '192.168.1.46', 
                    'ftp_user_name' => 'ftpuser',
                    'ftp_user_pass' => 'ftpuser',
                    'remote_upload_location' => $this->remote_upload_location,
                    'local_tmp_dir' => $this->local_tmp_dir
        ]));
        $this->assertSame(FTPStorage1::WRONG_CONFIGURATION_LABEL, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // directories dont exist
        $this->assertSame(false, $ftp_storage->setRemoteUploadLocation($this->remote_upload_location . 'kk'));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        $this->assertSame(false, $ftp_storage->setLocalTmpDir($this->local_tmp_dir . 'kk'));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // they exist
        $this->assertSame(true, $ftp_storage->setRemoteUploadLocation($this->remote_upload_location));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->setLocalTmpDir($this->local_tmp_dir));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

    public function test_disk_save_file() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // file to upload does not exist
        $url = "";
        $this->assertSame(false, $ftp_storage->saveFile('noexisto', $url));
        $this->assertSame(FTPStorage1::FILE_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // file to upload is a directory
        $url = "";
        $this->assertSame(false, $ftp_storage->saveFile($this->directory_path_tmp, $url));
        $this->assertSame(FTPStorage1::FILE_EXPECTED, $ftp_storage->getLastErrorCode());
        // directory target does not exist
        $url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // directory target is a file
        $url = self::$soyunfichero;
        $this->assertSame(false, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());
        // now it exists and we can upload
        $url = "";
        $md5 = md5_file($this->file_path_tmp);
        $this->assertSame(true, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // to check, we download file and compare md5
        $tmp_download = "";
        $this->assertSame(true, $ftp_storage->getFile($tmp_download, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame($md5, md5_file($tmp_download));
        //we create a new directory
        $url = "";
        $this->assertSame(true, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // now directory target exists and we can upload
        $this->assertSame(true, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        //check it 
        $tmp_download = "";
        $this->assertSame(true, $ftp_storage->getFile($tmp_download, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame($md5, md5_file($tmp_download));

        $this->environment_destroy();
    }

    public function test_disk_move_file() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

       //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // file to move does not exist
        $source_url = self::$noexisto;
        $target_url = self::$soyunacarpeta;
        $this->assertSame(false, $ftp_storage->moveFile($source_url, $target_url));
        $this->assertSame(FTPStorage1::FILE_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // file to move is not a file
        $source_url = self::$soyunacarpeta;
        $target_url = self::$soyunacarpeta;
        $this->assertSame(false, $ftp_storage->moveFile($source_url, $target_url));
        $this->assertSame(FTPStorage1::FILE_EXPECTED, $ftp_storage->getLastErrorCode());
        // directory target does not exist
        $source_url = self::$soyunfichero;
        $target_url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->moveFile($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // directory target is not a directory
        $source_url = self::$soyunfichero;
        $target_url = self::$soyunfichero;
        $this->assertSame(false, $ftp_storage->moveFile($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());

        // now it exists and we can move
        $md5 = md5_file($this->file_path_upload);
        $source_url = self::$soyunfichero;
        $target_url = self::$soyunacarpeta;
        $this->assertSame(true, $ftp_storage->moveFile($source_url, $target_url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // to check, we download file and compare md5
        $tmp_download = "";
        $this->assertSame(true, $ftp_storage->getFile($tmp_download, $target_url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame($md5, md5_file($tmp_download));

        $this->environment_destroy();
    }

    public function test_disk_delete_file() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // file to delete does not exist
        $url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->deleteFile($url));
        $this->assertSame(FTPStorage1::FILE_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // file to delete is a directory
        $url = self::$soyunacarpeta;
        $this->assertSame(false, $ftp_storage->deleteFile($url));
        $this->assertSame(FTPStorage1::FILE_EXPECTED, $ftp_storage->getLastErrorCode());

        //save a tmp file
        $url = "";
        $md5 = md5_file($this->file_path_tmp);
        $this->assertSame(true, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // now it exists we can delete
        $this->assertSame(true, $ftp_storage->deleteFile($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

    public function test_disk_get_file() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        
        // file to download does not exist
        $url = self::$noexisto;
        $tmp_file = "";
        $this->assertSame(false, $ftp_storage->getFile($tmp_file, $url));
        $this->assertSame(FTPStorage1::FILE_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // file to download is a directory
        $url = self::$soyunacarpeta;
        $tmp_file = "";
        $this->assertSame(false, $ftp_storage->getFile($tmp_file, $url));
        $this->assertSame(FTPStorage1::FILE_EXPECTED, $ftp_storage->getLastErrorCode());
        //save a tmp file
        $url = "";
        $md5 = md5_file($this->file_path_tmp);
        $this->assertSame(true, $ftp_storage->saveFile($this->file_path_tmp, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        // now it exists we can download
        $tmp_download = "";
        $this->assertSame(true, $ftp_storage->getFile($tmp_download, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame($md5, md5_file($tmp_download));
        // now tmp_download is not empty
        $tmp_download = $this->directory_path_tmp;
        $this->assertSame(true, $ftp_storage->getFile($tmp_download, $url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame($md5, md5_file($tmp_download));

        $this->environment_destroy();
    }

    public function test_disk_create_directory() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        //directory target does not exist
        $url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // directory target is a file
        $url = self::$soyunfichero;
        $this->assertSame(false, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());
        // now it exists we can create a new directory
        $url = "";
        $this->assertSame(true, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $url = self::$soyunacarpeta;
        $this->assertSame(true, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

    public function test_disk_delete_directory() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // file to delete does not exist
        $url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->deleteDirectory($url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // file to delete is a directory
        $url = self::$soyunfichero;
        $this->assertSame(false, $ftp_storage->deleteDirectory($url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());

        //create a directory
        $url = "";
        $this->assertSame(true, $ftp_storage->createDirectory($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // now it exists we can delete
        $this->assertSame(true, $ftp_storage->deleteDirectory($url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

    public function test_disk_move_directory() {

        $this->environment_setup();

        $ftp_storage = new FTPStorage1();

        //configure storage
        $this->assertSame(true, $ftp_storage->configure($this->options));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());
        $this->assertSame(true, $ftp_storage->init());
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        // directory to move does not exist
        $source_url = self::$noexisto;
        $target_url = self::$soyunacarpeta;
        $this->assertSame(false, $ftp_storage->moveDirectory($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // directory to move is not a directory
        $source_url = self::$soyunfichero;
        $target_url = self::$soyunacarpeta;
        $this->assertSame(false, $ftp_storage->moveDirectory($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());
        // directory target does not exist
        $source_url = self::$soyunacarpeta;
        $target_url = self::$noexisto;
        $this->assertSame(false, $ftp_storage->moveDirectory($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_DOES_NOT_EXISTS, $ftp_storage->getLastErrorCode());
        // directory target is not a directory
        $source_url = self::$soyunacarpeta;
        $target_url = self::$soyunfichero;
        $this->assertSame(false, $ftp_storage->moveDirectory($source_url, $target_url));
        $this->assertSame(FTPStorage1::DIRECTORY_EXPECTED, $ftp_storage->getLastErrorCode());

        // now it exists and we can move
        $source_url = self::$soyunacarpeta;
        $target_url = "";
        $this->assertSame(true, $ftp_storage->moveDirectory($source_url, $target_url));
        $this->assertSame(FTPStorage1::NO_ERRORS, $ftp_storage->getLastErrorCode());

        $this->environment_destroy();
    }

}
