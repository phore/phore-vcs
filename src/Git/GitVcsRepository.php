<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 10:27
 */

namespace Phore\VCS\Git;


use Phore\FileSystem\PhoreFile;
use Phore\ObjectStore\Driver\FileSystemObjectStoreDriver;
use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;
use Phore\VCS\VcsRepository;

class GitVcsRepository implements VcsRepository
{

    /**
     * @var \Phore\FileSystem\PhoreDirectory
     */
    private $repoDirectory;
    private $origin;
    private $sshKey;
    
    private $userName;
    private $email;
    /**
     * @var ObjectStore 
     */
    private $objectStore;

    /**
     * @var PhoreFile
     */
    private $savepointFile = null;

    private $currentPulledVersion = null;


    const GIT_STATUS_MAP = [
        "M" => self::STAT_MODIFY,
        "A" => self::STAT_CREATE,
        "D" => self::STAT_DELETE,
        "C" => self::STAT_CREATE,
        "R" => self::STAT_MOVED,
        "T" => self::STAT_MODIFY
    ];


    public function __construct(string $origin, string $repoDirectory, string $userName, string $email, string $sshKey=null)
    {
        $this->repoDirectory = phore_dir($repoDirectory)->assertDirectory(true);
        $this->origin = $origin;
        $this->sshKey = $sshKey;
        $this->userName = $userName;
        $this->email = $email;
        $this->objectStore =  new ObjectStore(new FileSystemObjectStoreDriver($this->repoDirectory));
        if ($this->repoDirectory->withSubPath(".git")->isDirectory()) {
            $savepoint = $this->savepointFile = $this->repoDirectory->withSubPath(".git")->assertDirectory()->withSubPath("phore_savepoint")->asFile();
            if ( ! $savepoint->isFile())
                $savepoint->set_contents("");
        }
    }


    public function setSavepointFile($file)
    {
        if (is_string($file))
            $file = phore_file($file);
        $this->savepointFile = $file;
    }


    public function getChangedFiles() : array
    {
        if ($this->savepointFile === null)
            throw new \InvalidArgumentException("No savepoint file specified. Use setSavepointFile() to select one.");

        $lastRev = $this->savepointFile->get_contents();
        if ($lastRev === "") {
            $lastRev = "4b825dc642cb6eb9a060e54bf8d69288fbee4904"; // <= This is git default for empty tree (before first commit)
        }
        if ($this->currentPulledVersion === null)
            $this->currentPulledVersion = $this->gitCommand("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);

        $changedFiles = $this->gitCommand("git -C :target diff --name-status :lastRev..:curRev", [
            "target" => $this->repoDirectory,
            "lastRev" => $lastRev,
            "curRev" => $this->currentPulledVersion
        ]);

        $changedFiles = explode("\n", $changedFiles);

        $ret = [];
        foreach ($changedFiles as $curLine) {
            $curLine = trim ($curLine);
            $skey = substr($curLine, 0, 1);
            $status = isset (self::GIT_STATUS_MAP[$skey]) ? self::GIT_STATUS_MAP[$skey] : null;

            if ($status === null)
                continue;
            $ret[] = [substr($curLine, 0, 1), trim (substr($curLine, 1))];
        }
        return $ret;
    }

    public function saveSavepoint()
    {
        $this->savepointFile->set_contents($this->currentPulledVersion);
    }

    public function exists()
    {
        return $this->repoDirectory->withSubPath(".git")->isDirectory();
    }

    public function commit(string $message)
    {
        phore_assert_str_alnum($this->userName, [".", "-", "_"]);
        phore_assert_str_alnum($this->email, ["@", ".", "-", "_"]);
        $this->gitCommand("git -C :target add .", ["target"=> $this->repoDirectory]);
        
        $ret = $this->gitCommand("git -C :target diff --name-only --cached", ["target" => $this->repoDirectory]);
        // commit only if files changed.
        if (trim ($ret) !== "") {
            $this->gitCommand("git -C :target -c 'user.name={$this->userName}' -c 'user.email={$this->email}' commit -m :msg ", ["target" => $this->repoDirectory, "msg" => $message]);
        }
    }

    private function gitCommand(string $command, array $params) {
        $cmd = "";
        if ($this->sshKey !== null) {
            $sshKeyFile = "/tmp/id_ssh-".sha1($this->repoDirectory);
            touch($sshKeyFile);
            chmod($sshKeyFile, 0600);
            file_put_contents($sshKeyFile, $this->sshKey);
            $cmd .= 'GIT_SSH_COMMAND="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i ' . $sshKeyFile . '" ';
        }
        $cmd .= $command;
        return phore_exec($cmd, $params);
    }

    public function pull()
    {
        if ( ! $this->exists()) {
            $this->gitCommand("git clone :origin :target", ["origin"=>$this->origin, "target"=>$this->repoDirectory]);
        }
        $this->gitCommand("git -C :target pull -Xtheirs", ["target"=> $this->repoDirectory]);
        $this->currentPulledVersion = $this->gitCommand("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
    }

    public function push()
    {
        $this->gitCommand("git -C :target push", ["target"=> $this->repoDirectory]);
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
