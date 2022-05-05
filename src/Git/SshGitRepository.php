<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 10:27
 */

namespace Phore\VCS\Git;


use InvalidArgumentException;
use Phore\Core\Exception\InvalidDataException;
use Phore\FileSystem\Exception\FileAccessException;
use Phore\FileSystem\Exception\FileNotFoundException;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\FileSystem\PhoreDirectory;
use Phore\System\PhoreExecException;

/**
 * Class SshGitRepository
 * @package Phore\VCS\Git
 */
class SshGitRepository extends GitRepository
{

    /**
     * @var PhoreDirectory
     */
    protected $sshKey;

    /**
     * SshGitRepository constructor.
     * @param string $origin
     * @param string $repoDirectory
     * @param string $userName
     * @param string $email
     * @param string|null $sshKey
     * @throws FilesystemException
     * @throws PathOutOfBoundsException
     */
    public function __construct(string $origin, string $repoDirectory, string $userName, string $email, string $sshKey = null)
    {
        parent::__construct($origin, $repoDirectory, $userName, $email);
        $this->sshKey = $sshKey;
    }


    /**
     * Set the ssh private key or specify a file to load it from
     *
     * @param string $sshPrivateKey
     */
    public function setSshPrivateKey(string $sshPrivateKey)
    {
        if (preg_match("|^file:(.*)$|", $sshPrivateKey, $matches)) {
            $keyFile = $matches[1];
            if ( ! is_file($keyFile) || ! is_readable($keyFile))
                throw new InvalidArgumentException("Ssh Keyfile '$keyFile' is not readable");
            $sshPrivateKey = file_get_contents($keyFile);
        }
        $this->sshKey = $sshPrivateKey;
    }

    /**
     * @param string $command
     * @param array $params
     * @return array|string
     * @throws PhoreExecException
     */
    protected function gitCommand(string $command, array $params)
    {
        $cmd = "";
        if ($this->sshKey !== null) {
            $sshKeyFile = "/tmp/id_ssh-" . sha1($this->repoDirectory);
            touch($sshKeyFile);
            chmod($sshKeyFile, 0600);
            file_put_contents($sshKeyFile, $this->sshKey);
            $cmd .= 'GIT_SSH_COMMAND="ssh -o UserKnownHostsFile=/dev/null -o StrictHostKeyChecking=no -i ' . $sshKeyFile . '" ';
        }
        $cmd .= $command;
        return phore_exec($cmd, $params);
    }

    /**
     * @throws PathOutOfBoundsException
     * @throws PhoreExecException
     */
    public function pull()
    {
        if (!$this->exists()) {
            $this->gitCommand("git clone :origin :target", ["origin" => $this->origin, "target" => $this->repoDirectory]);
        }
        $this->gitCommand("git -C :target pull -s recursive -X theirs --ff", ["target" => $this->repoDirectory]);
        $this->currentPulledVersion = $this->gitCommand("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
    }

    /**
     * @throws PhoreExecException
     */
    public function push()
    {
        $this->gitCommand("git -C :target push", ["target" => $this->repoDirectory]);
    }
}
