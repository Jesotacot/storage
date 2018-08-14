<?php

namespace sisius\lib\storageapi;

interface DirectoryStorageInterface {

    /**
     * Creates remote directory $url
     *
     * @param string $url
     * @return mixed
     */
    function createDirectory(&$url);

    /**
     * Deletes remote directory $url
     *
     * @param string $url
     * @return mixed
     */
    function deleteDirectory($url);

    /**
     * Moves $source_url directory to remote $target_url directory
     *
     * @param string $source_url
     * @param string $target_url
     * @return mixed
     */
    function moveDirectory($source_url, &$target_url);
}
