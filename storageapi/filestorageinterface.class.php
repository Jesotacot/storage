<?php

namespace sisius\lib\storageapi;

interface FileStorageInterface {

    /**
     * Deletes remote file $url
     *
     * @param string $url
     * @return mixed
     */
    function deleteFile($url);

    /**
     * Gets remote file $url and stores it in local $tmp_file
     *
     * @param string $tmp_file
     * @param string $url
     * @return mixed
     */
    function getFile(&$tmp_file, $url);

    /**
     * Saves local file $tmp_file to remote file $url
     *
     * @param string $tmp_file
     * @param string $url
     * @return mixed
     */
    function saveFile($tmp_file, &$url);

    /**
     * Moves $source_url file to remote $target_url file
     *
     * @param string $source_url
     * @param string $target_url
     * @return mixed
     */
    function moveFile($source_url, &$target_url);
}
