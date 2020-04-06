<?php


namespace Phore\VCS\Git;


use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\FileSystem\PhoreDirectory;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Phore\VCS\VcsRepository;

abstract class GitRepository implements VcsRepository
{
    /**
     * @var PhoreDirectory
     */
    protected $repoDirectory;
    /**
     * @var string
     */
    protected $origin;
    /**
     * @var string
     */
    protected $userName;
    /**
     * @var string
     */
    protected $email;
    /**
     * @var ObjectStore
     */
    protected $objectStore;
    /**
     * @var string
     */
    protected $savepointFile = null;

    protected $currentPulledVersion = null;

    protected const GIT_STATUS_MAP = [
        "M" => self::STAT_MODIFY,
        "A" => self::STAT_CREATE,
        "D" => self::STAT_DELETE,
        "C" => self::STAT_CREATE,
        "R" => self::STAT_MOVED,
        "T" => self::STAT_MODIFY
    ];

    /**
     * GitRepository constructor.
     * @param string $repoDirectory
     * @param string $userName
     * @param string $email
     * @param string $origin
     * @throws FilesystemException
     * @throws PathOutOfBoundsException
     */
    public function __construct(string $origin, string $repoDirectory, string $userName, string $email)
    {
        $this->repoDirectory = phore_dir($repoDirectory)->assertDirectory(true);
        $this->userName = $userName;
        $this->email = $email;
        $this->origin = $origin;
        $this->objectStore = new ObjectStore(new FileSystemObjectStoreDriver($this->repoDirectory));
        if ($this->repoDirectory->withSubPath(".git")->isDirectory()) {
            $savepoint = $this->savepointFile = $this->repoDirectory->withSubPath(".git")->assertDirectory()->withSubPath("phore_savepoint")->asFile();
            if (!$savepoint->isFile())
                $savepoint->set_contents("");
        }
    }

    public function exists()
    {
        return $this->repoDirectory->withSubPath(".git")->isDirectory();
    }

    public function commit(string $message)
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

    public function setSavepointFile($file)
    {
        if (is_string($file))
            $file = phore_file($file);
        $this->savepointFile = $file;
    }

    public function saveSavepoint()
    {
        $this->savepointFile->set_contents($this->currentPulledVersion);
    }
}