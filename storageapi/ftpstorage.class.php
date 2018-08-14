<?php

namespace sisius\lib\storageapi;

use uselib3\util\StringTools;

class FTPStorage extends StorageBase implements DirectoryStorageInterface, FileStorageInterface {

    /** @var string the FTP server address */
    protected $ftp_server_address;

    /** @var string the FTP username (USER) */
    protected $ftp_user_name;

    /** @var string the FTP password (PASS) */
    protected $ftp_user_pass;

    /** @var resource stores the FTP stream or false in case of failure */
    protected $conn_id;

    /** @var boolean stores the result of the login operation */
    protected $login_result;

    /** specific error codes constants */
    const FTP_ERROR_OPEN_CONN   = 100;
    const FTP_ERROR_LOGIN       = 101;
    const FTP_ERROR_DELETE      = 102;
    const FTP_ERROR_GET         = 103;
    const FTP_ERROR_PUT         = 104;
    const FTP_ERROR_RENAME      = 105;
    const FTP_ERROR_MKDIR       = 106;
    const FTP_ERROR_RMDIR       = 107;
    const FTP_ERROR_CLOSE_CONN  = 108;

    /** @var array maps specific error codes constants with their error messages */
    static $specificConstLabelsArray = [
        self::FTP_ERROR_OPEN_CONN => ['es' => 'FTP - Excepción: Error abriendo conexión con el servidor FTP', 'en' => 'FTP - Exception: Error opening conection with FTP server'],
        self::FTP_ERROR_LOGIN => ['es' => 'FTP - Excepción: Error logueando al usuario en el servidor FTP', 'en' => 'FTP - Exception: Error login user into FTP server'],
        self::FTP_ERROR_DELETE => ['es' => 'FTP - Excepción: Error intentando borrar fichero en el servidor FTP', 'en' => 'FTP - Exception: Error trying to delete file in FTP server'],
        self::FTP_ERROR_GET => ['es' => 'FTP - Excepción: Error intentando descargar fichero del servidor FTP', 'en' => 'FTP - Exception: Error trying to download file from FTP server'],
        self::FTP_ERROR_PUT => ['es' => 'FTP - Excepción: Error intentando subir fichero al servidor FTP', 'en' => 'FTP - Exception: Error trying to upload file to FTP server'],
        self::FTP_ERROR_RENAME => ['es' => 'FTP - Excepción: Error intentando renombrar directorio/fichero en el servidor FTP', 'en' => 'FTP - Exception: Error trying to rename directory/file in FTP server'],
        self::FTP_ERROR_MKDIR => ['es' => 'FTP - Excepción: Error intentando crear directorio en el servidor FTP', 'en' => 'FTP - Exception: Error trying to create directory in FTP server'],
        self::FTP_ERROR_RMDIR => ['es' => 'FTP - Excepción: Error intentando borrar directorio en el servidor FTP', 'en' => 'FTP - Exception: Error trying to delete directory in FTP server'],
        self::FTP_ERROR_CLOSE_CONN => ['es' => 'FTP - Excepción: Error cerrando la conexión con el servidor FTP', 'en' => 'FTP - Exception: Error closing conection with FTP server'],
    ];

    /**
     * Returns the FTP stream
     *
     * @return resource the FTP stream.
     */
    public function getConnID() {
        return $this->conn_id;
    }

    /**
     * Removes recursively a directory and its contents
     *
     * @param string $directory <p>
     * The directory to be removed.
     * </p>
     * @return bool true on success or false on failure.
     */
    function ftp_rmdir_recursive($directory) {
        $result = false;

        if (!(@ftp_rmdir($this->conn_id, $directory) || @ftp_delete($this->conn_id, $directory))) {
            $filelist = @ftp_nlist($this->conn_id, $directory);

            foreach ($filelist as $file) {
                $result = $this->ftp_rmdir_recursive($file);
            }

            $result = $this->ftp_rmdir_recursive($directory);
        } else {
            return true;
        }

        return $result;
    }

    /**
     * Deletes remote file $url
     *
     * @param string $url <p>
     * The remote url where the file is stored.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function deleteFile($url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $file_path = $this->rebuildPath($this->remote_upload_location, $url);

            if (!@ftp_delete($this->conn_id, $file_path)) {
                $this->last_error_code = self::FTP_ERROR_DELETE;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Gets remote file $url and stores it in local $tmp_file
     *
     * @param string $tmp_file <p>
     * Once the function has ended it will contain the local url where the remote file was stored.
     * </p>
     * @param string $url <p>
     * The remote url where the file is stored.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function getFile(&$tmp_file, $url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $file_path = $this->rebuildPath($this->remote_upload_location, $url);

            $tmp_file = $this->generateName($this->local_tmp_dir);

            if (!@ftp_get($this->conn_id, $tmp_file, $file_path, FTP_BINARY)) {
                $this->last_error_code = self::FTP_ERROR_GET;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Saves local file $tmp_file to remote file $url
     *
     * @param string $tmp_file <p>
     * The url where the local file that will be uploaded is stored.
     * </p>
     * @param string $url <p>
     * Once the function has ended it will contain the remote url where the local file has been stored.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function saveFile($tmp_file, &$url) {
        if ($this->isConfigured() && $this->isConnected()) {
            if (!$this->checkFile($tmp_file)) {
                return false;
            }

            $root_path = $this->rebuildPath($this->remote_upload_location, $url);

            $new_file_path = $this->generateNameInFTP($root_path);

            $url = $this->rebuildPath($url, basename($new_file_path));

            if (!@ftp_put($this->conn_id, $new_file_path, $tmp_file, FTP_BINARY)) {
                $this->last_error_code = self::FTP_ERROR_PUT;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Moves $source_url file to remote $target_url file
     *
     * @param string $source_url <p>
     * The remote url where the remote file that will be moved is stored.
     * </p>
     * @param string $target_url <p>
     * Once the function has ended it will contain the remote url where the remote file was moved to.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function moveFile($source_url, &$target_url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $source_path = $this->rebuildPath($this->remote_upload_location, $source_url);
            $target_url = $this->rebuildPath($target_url, basename($source_url));
            $target_path = $this->rebuildPath($this->remote_upload_location, $target_url);

            if (!@ftp_rename($this->conn_id, $source_path, $target_path)) {
                $this->last_error_code = self::FTP_ERROR_RENAME;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Creates remote directory $url
     *
     * @param string $url <p>
     * Once the function has ended it will contain the remote url where the remote directory has been created.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function createDirectory(&$url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $root_path = $this->rebuildPath($this->remote_upload_location, $url);

            $new_directory_path = $this->generateNameInFTP($root_path);

            $url .= basename($new_directory_path);

            if (!@ftp_mkdir($this->conn_id, $new_directory_path)) {
                $this->last_error_code = self::FTP_ERROR_MKDIR;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Deletes remote directory $url
     *
     * @param string $url <p>
     * The remote url where the directory is stored.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function deleteDirectory($url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $directory_path = $this->rebuildPath($this->remote_upload_location, $url);

            if (!is_dir('ftp://' . $this->ftp_user_name . ':' . $this->ftp_user_pass . '@' . $this->ftp_server_address . $directory_path)) {
                $this->last_error_code = self::DIRECTORY_DOES_NOT_EXISTS;
                return false;
            }

            if (!$this->ftp_rmdir_recursive($directory_path)) {
                $this->last_error_code = self::FTP_ERROR_RMDIR;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Moves $source_url directory to remote $target_url directory
     *
     * @param string $source_url <p>
     * The remote url where the remote directory that will be moved is stored.
     * </p>
     * @param string $target_url <p>
     * Once the function has ended it will contain the remote url where the remote directory was moved to.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function moveDirectory($source_url, &$target_url) {
        if ($this->isConfigured() && $this->isConnected()) {
            $source_path = $this->rebuildPath($this->remote_upload_location, $source_url);
            $target_url = $this->rebuildPath($target_url, basename($source_url));
            $target_path = $this->rebuildPath($this->remote_upload_location, $target_url);

            if (!@ftp_rename($this->conn_id, $source_path, $target_path)) {
                $this->last_error_code = self::FTP_ERROR_RENAME;
                return false;
            }

            $this->last_error_code = self::NO_ERRORS;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Opens a connection with the FTP server and logs in
     *
     * @return bool true on success or false on failure.
     */
    public function init() {
        $this->conn_id = @ftp_connect($this->ftp_server_address);

        if ($this->conn_id === false) {
            $this->last_error_code = self::FTP_ERROR_OPEN_CONN;
            return false;
        }

        $this->login_result = @ftp_login($this->conn_id, $this->ftp_user_name, $this->ftp_user_pass);

        if (!$this->login_result) {
            $this->last_error_code = self::FTP_ERROR_LOGIN;
            return false;
        }

        return true;
    }

    /**
     * Checks if there is a connected resource to the FTP
     *
     * @return bool true on success or false on failure.
     */
    public function isConnected() {
        return $this->login_result;
    }

    /**
     * Sets the remote upload location where uploads will be stored
     *
     * @param string $remote_upload_location <p>
     * The path to the remote upload location.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function setRemoteUploadLocation($remote_upload_location) {
        $this->remote_upload_location = null;

        if (!is_dir('ftp://' . $this->ftp_user_name . ':' . $this->ftp_user_pass . '@' . $this->ftp_server_address . $remote_upload_location)) {
            return false;
        }

        $this->remote_upload_location = $remote_upload_location;
        return true;
    }

    /**
     * Configures the FTP server
     *
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $ftp_server_address the FTP server address.
     *     @type string $ftp_user_name the FTP username (USER).
     *     @type string $ftp_user_pass the FTP password (PASS).
     *     @type string $remote_upload_location the remote directory where uploads will be stored.
     *     @type string $local_tmp_dir the local temporary directory where downloads will be stored.
     * }
     * @return bool true on success or false on failure.
     */
    public function configure(array $options) {
        if (!parent::configure($options)) {
            return false;
        }

        if (!array_key_exists('ftp_server_address', $options) ||
            !array_key_exists('ftp_user_name', $options) ||
            !array_key_exists('ftp_user_pass', $options) ||
            !array_key_exists('remote_upload_location', $options)) {
            $this->last_error_code = self::WRONG_CONFIGURATION_LABEL;
            return false;
        }

        $this->setFTPServer($options['ftp_server_address'], $options['ftp_user_name'], $options['ftp_user_pass']);
        $this->setRemoteUploadLocation($options['remote_upload_location']);

        return true;
    }

    /**
     * Checks if needed variables to configure an FTP object are set
     *
     * @return bool true on success or false on failure.
     */
    public function isConfigured() {
        if (!parent::isConfigured()) {
            return false;
        }

        return $this->ftp_server_address !== null && $this->ftp_user_name !== null && $this->ftp_user_pass !== null && $this->remote_upload_location !== null;
    }

    /**
     * Generates a random name in FTP for a directory or a file, that does not previously exist in $root_path
     *
     * @param string $root_path <p>
     * The root path where the directory or file is about to be created.
     * </p>
     * @return string the path to a valid generated random name.
     */
    public function generateNameInFTP($root_path) {
        do {
            $name = $root_path . '/' . StringTools::generateRandomString(12);
        } while (in_array($name, @ftp_nlist($this->conn_id, dirname($name))));

        return $name;
    }

    /**
     * Sets FTP's needed variables to set it up
     *
     * @param string $ftp_server_address <p>
     * The FTP server address.
     * </p>
     * @param string $ftp_user_name <p>
     * The FTP username (USER).
     * </p>
     * @param string $ftp_user_pass <p>
     * The FTP password (PASS).
     * </p>
     * @return bool true on success or false on failure.
     */
    public function setFTPServer($ftp_server_address, $ftp_user_name, $ftp_user_pass) {
        $this->ftp_server_address = null;
        $this->ftp_user_name = null;
        $this->ftp_user_pass = null;

        if (!filter_var($ftp_server_address, FILTER_VALIDATE_IP)) {
            return false;
        }

        $this->ftp_server_address = $ftp_server_address;
        $this->ftp_user_name = $ftp_user_name;
        $this->ftp_user_pass = $ftp_user_pass;
        return true;
    }
}
