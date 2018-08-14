<?php

namespace sisius\lib\storageapi;

use uselib3\types\BasicEnum;
use uselib3\util\StringTools;

abstract class StorageBase extends BasicEnum {

    /** @var string stores the last error code generated */
    protected $last_error_code = '';

    /** @var string the remote directory where uploads will be stored */
    protected $remote_upload_location;

    /** @var string the local temporary directory where downloads will be stored */
    protected $local_tmp_dir;

    /** error codes constants */
    const NO_ERRORS                 = 0;
    const DIRECTORY_ALREADY_EXISTS  = 1;
    const FILE_ALREADY_EXISTS       = 2;
    const DIRECTORY_DOES_NOT_EXISTS = 3;
    const FILE_DOES_NOT_EXISTS      = 4;
    const DIRECTORY_EXPECTED        = 5;
    const FILE_EXPECTED             = 6;
    const WRONG_CONFIGURATION_LABEL = 7;

    /** @var array maps error codes constants with their error messages */
    static $constLabelsArray = [
        self::NO_ERRORS => ['es' => 'ST - No Errors: Operación exitosa', 'en' => 'ST - No Errors: Operation successful'],
        self::DIRECTORY_ALREADY_EXISTS => ['es' => 'ST - Excepción: Directorio ya existe', 'en' => 'ST - Exception: Directory already exists'],
        self::FILE_ALREADY_EXISTS => ['es' => 'ST - Excepción: Fichero ya existe', 'en' => 'ST - Exception: File already exists'],
        self::DIRECTORY_DOES_NOT_EXISTS => ['es' => 'ST - Excepción: Directorio no existe', 'en' => 'ST - Exception: Directory does not exists'],
        self::FILE_DOES_NOT_EXISTS => ['es' => 'ST - Excepción: Fichero no existe', 'en' => 'ST - Exception: File does not exists'],
        self::DIRECTORY_EXPECTED => ['es' => 'ST - Excepción: Se esperaba directorio en lugar de fichero', 'en' => 'ST - Exception: Expected a directory instead of a file'],
        self::FILE_EXPECTED => ['es' => 'ST - Excepción: Se esperaba fichero en lugar de directorio', 'en' => 'ST - Exception: Expected a file instead of a directory'],
        self::WRONG_CONFIGURATION_LABEL => ['es' => 'ST - Excepción: Etiquetas de configuración erróneas', 'en' => 'ST - Exception: Wrong configuration label'],
    ];

    /** @var array maps specific error codes constants with their error messages */
    protected static $specificConstLabelsArray = [];

    /**
     * Initializes a configured storage system, making it possible to start exchanging data
     */
    abstract function init();

    /**
     * Sets the remote upload location where uploads will be stored
     *
     * @param string $remote_upload_location <p>
     * The path to the remote upload location.
     * </p>
     * @return mixed
     */
    abstract function setRemoteUploadLocation($remote_upload_location);

    /**
     * Checks if $directory_path is a path to an actual and existent directory
     *
     * @param string $directory_path <p>
     * The path to the directory.
     * </p>
     * @return bool true if $directory_path is a path to a directory or false otherwise.
     */
    protected function checkDirectory($directory_path) {
        if (!file_exists($directory_path)) {
            $this->last_error_code = self::DIRECTORY_DOES_NOT_EXISTS;
            return false;
        }

        if (!is_dir($directory_path)) {
            $this->last_error_code = self::DIRECTORY_EXPECTED;
            return false;
        }

        return true;
    }

    /**
     * Checks if $file_path is a path to an actual and existent file
     *
     * @param string $file_path <p>
     * The path to the file.
     * </p>
     * @return bool true if $file_path is a path to a file or false otherwise.
     */
    protected function checkFile($file_path) {
        if (!file_exists($file_path)) {
            $this->last_error_code = self::FILE_DOES_NOT_EXISTS;
            return false;
        }

        if (!is_file($file_path)) {
            $this->last_error_code = self::FILE_EXPECTED;
            return false;
        }

        return true;
    }

    /**
     * Configures the storage
     *
     * @param array $options {
     *     Configuration options.
     *
     *     @type string $local_tmp_dir the local temporary directory where downloads will be stored.
     * }
     * @return bool true on success or false on failure.
     */
    public function configure(array $options) {
        if (!array_key_exists('local_tmp_dir', $options)) {
            $this->last_error_code = self::WRONG_CONFIGURATION_LABEL;
            return false;
        }

        if (!$this->setLocalTmpDir($options['local_tmp_dir'])) {
            return false;
        }

        $this->last_error_code = self::NO_ERRORS;
        return true;
    }

    /**
     * Generates a random name for a directory or a file, that does not previously exist in $root_path
     *
     * @param string $root_path <p>
     * The root path where the directory or file is about to be created.
     * </p>
     * @return string the path to a valid generated random name.
     */
    protected function generateName($root_path) {
        do {
            $name = $root_path . '/' . StringTools::generateRandomString(12);
        } while (file_exists($name));

        return $name;
    }

    /**
     * Rebuilds the path to a directory or a file
     *
     * @param string $root_path <p>
     * The root path where the directory or file is stored.
     * </p>
     * @param string $url <p>
     * The directory or file name.
     * </p>
     * @return string
     */
    public function rebuildPath($root_path, $url) {
        return empty($url) ? $root_path : (empty($root_path) ? $url : $root_path . '/' . $url);
    }

    /**
     * Return a label from the $constLabelsArray. The value in $constLabelsArray is
     * passed to the translation mechanism, so that either you set a resource name
     * (in file enum.php) in $constLabelsArray, or you define the labels as arrays
     * of the form ['en'=>'example','es'=>'ejemplo'].
     *
     * @param $val
     * @return mixed
     * @throws \Exception
     */
    static function getLabel($val) {
        $constLabelsArraysMixed = static::$constLabelsArray + static::$specificConstLabelsArray;

        if (self::isValidValue($val)) {
            return trans($constLabelsArraysMixed[$val]);
        } else {
            throw new \InvalidArgumentException($val . ' is not a valid EnumApps.');
        }
    }

    /**
     * Obtains last saved error code
     *
     * @return string the last error code.
     */
    public function getLastErrorCode() {
        return $this->last_error_code;
    }

    /**
     * Obtains last saved error message
     *
     * @return string the last error message.
     * @throws \Exception
     */
    public function getLastErrorMessage() {
        return static::getLabel($this->last_error_code);
    }

    /**
     * Checks if storage is properly configured
     *
     * @return bool true in case it is configured success or false otherwise.
     */
    protected function isConfigured() {
        return $this->local_tmp_dir !== null;
    }

    /**
     * Sets the local temporary directory where downloads will be stored
     *
     * @param string $local_tmp_dir <p>
     * The path to the local temporary directory.
     * </p>
     * @return bool true on success or false on failure.
     */
    public function setLocalTmpDir($local_tmp_dir) {
        $this->local_tmp_dir = null;

        if (!$this->checkDirectory($local_tmp_dir)) {
            return false;
        }

        $this->local_tmp_dir = $local_tmp_dir;

        $this->last_error_code = self::NO_ERRORS;
        return true;
    }
}
