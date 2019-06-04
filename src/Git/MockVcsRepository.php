<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 28.05.19
 * Time: 10:36
 */

namespace Phore\VCS\Git;


use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Phore\VCS\VcsRepository;

class MockVcsRepository implements VcsRepository
{

    /**
     * @var \Phore\FileSystem\PhoreDirectory 
     */
    private $repoDirectory;
    private $origin;
    /**
     * @var ObjectStore
     */
    private $objectStore;
    public function __construct(string $origin, string $repoDirectory)
    {
        $this->repoDirectory = phore_dir($repoDirectory)->assertDirectory(true);
        $this->origin = $origin;
        $this->objectStore = new ObjectStore(new FileSystemObjectStoreDriver($repoDirectory));
    }

    public function exists()
    {
        return $this->repoDirectory->withSubPath("")->isDirectory();
    }

    public function commit(string $message)
    {
        
    }
    

    public function pull()
    {
        if ($this->exists())
            $this->repoDirectory->mkdir();
        phore_exec("rsync -a :origin :target", ["origin" => $this->origin . "/", "target" => $this->repoDirectory->getUri() . "/"]);
    }

    public function push()
    {
        
    }


    public function getObjectstore(): ObjectStore
    {
        return $this->objectStore;
    }

    public function object(string $name): ObjectStoreObject
    {
        return $this->objectStore->object($name);
    }

}
