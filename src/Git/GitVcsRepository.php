<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 10:27
 */

namespace Phore\VCS\Git;


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

    public function __construct(string $origin, string $repoDirectory, string $userName, string $email, string $sshKey=null)
    {
        $this->repoDirectory = phore_dir($repoDirectory)->assertDirectory(true);
        $this->origin = $origin;
        $this->sshKey = $sshKey;
        $this->userName = $userName;
        $this->email = $email;
        $this->objectStore =  new ObjectStore(new FileSystemObjectStoreDriver($this->repoDirectory));
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
