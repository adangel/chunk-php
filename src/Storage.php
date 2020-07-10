<?php
namespace com\github\adangel\chunkphp;

class Storage {
    private $datadir;
    private $baseurl;

    function __construct($url, $dir) {
        $this->baseurl = $url;
        $this->datadir = $dir;
    }

    function findFile($username, $id) : ?File {
        $user = new User($username);
        $uuid = new Uuid($id);
        $realPath = $this->createRealPath($user, $uuid);

        # the file might have an extension...
        $realPaths = glob($realPath . "*");
        if ($realPaths !== FALSE && count($realPaths) > 0) {
            $realPath = $realPaths[0];
            return new File($realPath, $this->baseurl, $user, $uuid);
        }
        return NULL;
    }

    function createFile($username, $originalFileName = NULL) : File {
        $user = new User($username);
        $uuid = new Uuid();
        $extension = '';

        if (isset($originalFileName)) {
            $path_extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
            if (isset($path_extension)) {
                $extension = '.' . $path_extension;
            }
        }

        return new File($this->createRealPath($user, $uuid, $extension), $this->baseurl, $user, $uuid);
    }

    private function createRealPath($user, $uuid, $extension = '') : string {
        return $this->datadir . '/' . $user . '/' . $uuid . $extension;
    }
}
