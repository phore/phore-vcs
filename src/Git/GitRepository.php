<?php


namespace Phore\VCS\Git;


use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\FileSystem\PhoreDirectory;
use Phore\FileSystem\PhoreFile;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Phore\VCS\VcsRepository;

/**
 * Class GitRepository
 * @package Phore\VCS\Git
 */
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
     * @var PhoreFile
     */
    protected $savepointFile = null;

    /**
     * @var null
     */
    protected $currentPulledVersion = null;

    /**
     *
     */
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

    /**
     * @return bool
     * @throws PathOutOfBoundsException
     */
    public function exists()
    {
        return $this->repoDirectory->withSubPath(".git")->isDirectory();
    }

    /**
     * @return ObjectStore
     */
    public function getObjectstore(): ObjectStore
    {
        return $this->objectStore;
    }

    /**
     * @param string $name
     * @return ObjectStoreObject
     */
    public function object(string $name): ObjectStoreObject
    {
        return $this->objectStore->object($name);
    }

    /**
     * @param $file
     */
    public function setSavepointFile($file)
    {
        if (is_string($file))
            $file = phore_file($file);
        $this->savepointFile = $file;
    }

    /**
     * @throws FilesystemException
     */
    public function saveSavepoint()
    {
        $this->savepointFile->set_contents($this->currentPulledVersion);
    }

    /**
     * @param $changedFiles
     * @return array
     */
    protected function modifyChangedFiles($changedFiles): array
    {
        $changedFiles = explode("\n", $changedFiles);

        $ret = [];
        foreach ($changedFiles as $curLine) {
            $curLine = trim($curLine);
            $skey = substr($curLine, 0, 1);
            $status = isset (GitRepository::GIT_STATUS_MAP[$skey]) ? GitRepository::GIT_STATUS_MAP[$skey] : null;

            if ($status === null)
                continue;
            $ret[] = [substr($curLine, 0, 1), trim(substr($curLine, 1))];
        }
        return $ret;
    }
}