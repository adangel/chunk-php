<?php
namespace com\github\adangel\chunkphp;

class Storage {
    private $datadir;
    private $baseurl;

    function __construct($url, $dir) {
        $this->baseurl = $url;
        $this->datadir = $dir;
    }

    function findFile($user, $uuid) : ?File {
        $realPath = $this->createRealPath($user, $uuid);

        # the file might have an extension...
        $realPaths = glob($realPath . "*");
        if ($realPaths !== FALSE && count($realPaths) > 0) {
            $realPath = $realPaths[0];
            return new File($realPath, $this->baseurl, $user, $uuid);
        }
        return null;
    }

    function createFile($user) : File {
        $uuid = Storage::guidv4();
        return new File($this->createRealPath($user, $uuid), $this->baseurl, $user, $uuid);
    }

    private function createRealPath($user, $uuid) : string {
        return $this->datadir . '/' . $user . '/' . $uuid;
    }

    # https://stackoverflow.com/a/15875555/1169968
    private static function guidv4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

}
