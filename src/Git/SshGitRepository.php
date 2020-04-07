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
     * @return array
     * @throws FileAccessException
     * @throws FileNotFoundException
     * @throws PhoreExecException
     */
    public function getChangedFiles(): array
    {
        if ($this->savepointFile === null)
            throw new InvalidArgumentException("No savepoint file specified. Use setSavepointFile() to select one.");

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

        return $this->modifyChangedFiles($changedFiles);
    }

    /**
     * @param string $message
     * @throws InvalidDataException
     * @throws PhoreExecException
     */
    public function commit(string $message)
    {
        phore_assert_str_alnum($this->userName, [".", "-", "_"]);
        phore_assert_str_alnum($this->email, ["@", ".", "-", "_"]);
        $this->gitCommand("git -C :target add .", ["target" => $this->repoDirectory]);

        $ret = $this->gitCommand("git -C :target diff --name-only --cached", ["target" => $this->repoDirectory]);
        // commit only if files changed.
        if (trim($ret) !== "") {
            $this->gitCommand("git -C :target -c 'user.name={$this->userName}' -c 'user.email={$this->email}' commit -m :msg ", ["target" => $this->repoDirectory, "msg" => $message]);
        }
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
        $this->gitCommand("git -C :target pull -Xtheirs", ["target" => $this->repoDirectory]);
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
