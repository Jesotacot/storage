<?php

namespace sisius\lib\storageapi;

class DiskStorage extends StorageBase implements DirectoryStorageInterface, FileStorageInterface {

    const DISK_ERROR_DELETE     = 100;
    const DISK_ERROR_SAVE       = 101;
    const DISK_ERROR_RENAME     = 102;
    const DISK_ERROR_MKDIR      = 103;
    const DISK_ERROR_RMDIR      = 104;
    const DISK_ERROR_DIROPEN    = 105;

    static $constLabelsArraySpecif = [
        self::DISK_ERROR_DELETE => ['es' => 'Disk: Excepción: Error borrando fichero en disco', 'en' => 'Disk: Exception: Error deleting file in disk'],
        self::DISK_ERROR_SAVE => ['es' => 'Disk: Excepción: Error guardando fichero en disco', 'en' => 'Disk: Exception: Error saving file in disk'],
        self::DISK_ERROR_RENAME => ['es' => 'Disk: Excepción: Error intentando renombrar directorio/fichero en disco', 'en' => 'Disk: Exception: Error trying to rename directory/file in disk'],
        self::DISK_ERROR_MKDIR => ['es' => 'Disk: Excepción: Error creando directorio en disco', 'en' => 'Disk: Exception: Error making directory in disk'],
        self::DISK_ERROR_RMDIR => ['es' => 'Disk: Excepción: Error borrando directorio en disco', 'en' => 'Disk: Exception: Error deleting directory in disk'],
        self::DISK_ERROR_DIROPEN => ['es' => 'Disk: Excepción: Error abriendo directorio en disco', 'en' => 'Disk: Exception: Error opening directory in disk'],
    ];
    /**
     * Sets the remote upload location
     * 
     * @param string type $remote_upload_location
     * @return bool true on success or false on failure
     */
    public function setRemoteUploadLocation($remote_upload_location) {
        $this->remote_upload_location = null;
        
        //check if $remote_upload_location exists
        if (!$this->checkDirectory($remote_upload_location)) {
            return false;
        }

        $this->remote_upload_location = $remote_upload_location;
        //no errors
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Configures the storage  
     * 
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $local_tmp_dir the local temporary directory where downloads will be stored
     *     @type string $remote_upload_location the remote directory where uploads will be stored
     * }
     * @return bool true on success or false on failure
     */
    public function configure(array $options) {
        if (!parent::configure($options)) {
            return false;
        }
        //check if the label exists
        if (!array_key_exists('remote_upload_location', $options)) {
            $this->last_error_code = self::WRONG_CONFIGURATION_LABEL;
            return false;
        }
        //sets the remote upload location
        if (!$this->setRemoteUploadLocation($options['remote_upload_location'])) {
            return false;
        }
        //no errors
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Check if the storage is configured
     * 
     * @return bool true on success or false on failure
     */
    protected function isConfigured() {
        if (!parent::isConfigured()) {
            return false;
        }
        return ($this->remote_upload_location !== null);
    }

    /**
     * Deletes remote file $url
     * 
     * @param string $url the remote url where the file is stored
     * @return bool true on success or false on failure
     */
    public function deleteFile($url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $file_path = $this->rebuildPath($this->remote_upload_location, $url);
            //check if file $url exists 
            if (!$this->checkFile($file_path)) {
                return false;
            } else {
                //deletes the file
                if (!@unlink($file_path)) {
                    $this->last_error_code = self::DISK_ERROR_DELETE;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Retrieves remote file $url to local file $tmp_file
     * 
     * @param string $tmp_file the url where the file that will be downloaded will be stored
     * @param string $url the remote url where the file is stored
     * @return bool true on success or false on failure
     */
    public function getFile(&$tmp_file, $url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $file_path = $this->rebuildPath($this->remote_upload_location, $url);
            //check if file $url exists 
            if (!$this->checkFile($file_path)) {
                return false;
            } else {
                //generate a new file name 
                $tmp_file = $this->generateName($this->local_tmp_dir);
                //gets the file
                if (!@copy($file_path, $tmp_file)) {
                    $this->last_error_code = self::DISK_ERROR_SAVE;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Saves local file $tmp_file to remote file $url
     * 
     * @param string $tmp_file the url where the file that will be uploaded is stored
     * @param string $url path where the file will be saved ,once the function has ended it will contain the remote url where the file has been stored
     * @return bool true on success or false on failure
     */
    public function saveFile($tmp_file, &$url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            //check if file $tmp_file exists 
            if (!$this->checkFile($tmp_file)) {
                return false;
            } else {
                $directory_path = $this->rebuildPath($this->remote_upload_location, $url);
                //check if directory $url exists
                if (!$this->checkDirectory($directory_path)) {
                    return false;
                }
                //generate a new file name 
                $new_file_path = $this->generateName($directory_path);
                $url = $this->rebuildPath($url, basename($new_file_path));
                //saves tmp_file to new file $url 
                if (!@copy($tmp_file, $new_file_path)) {
                    $this->last_error_code = self::DISK_ERROR_SAVE;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Moves file $source_url to remote path $target_url 
     * 
     * @param string $source_url the remote url where the file that will be moved is stored
     * @param string $target_url path where the file will be moved ,once the function has ended it will contain the remote url where the file was moved to
     * @return bool true on success or false on failure
     */
    public function moveFile($source_url, &$target_url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $source_path = $this->rebuildPath($this->remote_upload_location, $source_url);
            $target_path = $this->rebuildPath($this->remote_upload_location, $target_url);
            //check if file $source_url exists and if  path $target_url exists
            if (!$this->checkFile($source_path) || !$this->checkDirectory($target_path)) {
                return false;
            } else {
                $name = basename($source_url);
                $target_url = $this->rebuildPath($target_url, $name);
                $target_path = $this->rebuildPath($target_path, $name);
                //moves the source file
                if (!@rename($source_path, $target_path)) {
                    $this->last_error_code = self::DISK_ERROR_RENAME;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Creates remote directory $url
     * 
     * @param string $url path where the directory will be created, once the function has ended it will contain the remote url where the directory has been created
     * @return bool true on success or false on failure
     */
    public function createDirectory(&$url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $directory_path = $this->rebuildPath($this->remote_upload_location, $url);
            //check if directory $url exists
            if (!$this->checkDirectory($directory_path)) {
                return false;
            }
            //generate a new directory name 
            $new_directory_path = $this->generateName($directory_path);
            $url = $this->rebuildPath($url, basename($new_directory_path));
            //creates a new directory
            if (!@mkdir($new_directory_path)) {
                $this->last_error_code = self::DISK_ERROR_MKDIR;
                return false;
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Moves $source_url directory to remote path $target_url 
     * 
     * @param string $source_url the remote url where the directory that will be moved is stored
     * @param string $target_url path where the file will be moved, once the function has ended it will contain the remote url where the directory was moved to
     * @return bool true on success or false on failure
     */
    public function moveDirectory($source_url, &$target_url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $source_path = $this->rebuildPath($this->remote_upload_location, $source_url);
            $target_path = $this->rebuildPath($this->remote_upload_location, $target_url);
            //check if directory $source_url exists and if path $target_url exists
            if (!$this->checkDirectory($source_path) || !$this->checkDirectory($target_path)) {
                return false;
            } else {
                $target_url = $this->rebuildPath($target_url, basename($source_path));
                $target_path = $this->rebuildPath($target_path, basename($source_path));
                //moves the source directory 
                if (!@rename($source_path, $target_path)) {
                    $this->last_error_code = self::DISK_ERROR_RENAME;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Deletes remote directory $url
     * 
     * @param string $url the remote url where the directory is stored
     * @return bool true on success or false on failure
     */
    public function deleteDirectory($url) {
        //check if the storage is configured
        if (!$this->isConfigured()) {
            return false;
        } else {
            $directory_path = $this->rebuildPath($this->remote_upload_location, $url);
            //check if directory $url exists
            if (!$this->checkDirectory($directory_path)) {
                return false;
            } else {
                //deletes the file
                if (!$this->rrmdir($directory_path)) {
                    $this->last_error_code = self::DISK_ERROR_RMDIR;
                    return false;
                }
            }
        }
        //no error
        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Delete recursively a directory
     * 
     * @param string $source_directory  the remote url where the directory is stored
     * @return bool true on success or false on failure
     */
    public function rrmdir($source_directory) {
        //check that is a directory 
        if (!is_dir($source_directory)) {
            return false;
        } else {
            //open the directory
            $dir = @opendir($source_directory);
            if ($dir === false) {
                return false;
            } else {
                //check if the directory contains directories or files
                while (false !== ( $file = @readdir($dir))) {
                    if (( $file != '.' ) && ( $file != '..' )) {
                        $full = $source_directory . '/' . $file;
                        //delete recursively the directories 
                        if (@is_dir($full)) {
                            $this->rrmdir($full);
                        } else {
                            if (!@unlink($full)) {
                                return false;
                            }
                        }
                    }
                }
                //close the directory
                closedir($dir);
                //delete the directory
                if (!@rmdir($source_directory)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function init() {
        return true;
    }

}
