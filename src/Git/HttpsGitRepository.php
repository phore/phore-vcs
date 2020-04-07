<?php

namespace Phore\VCS\Git;

use InvalidArgumentException;
use Phore\Core\Exception\InvalidDataException;
use Phore\FileSystem\Exception\FileAccessException;
use Phore\FileSystem\Exception\FileNotFoundException;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\System\PhoreExecException;

/**
 * Class HttpsGitRepository
 * @package Phore\VCS\Git
 */
class HttpsGitRepository extends GitRepository
{

    /**
     * @var
     */
    private $gitPassword;

    /**
     * HttpsGitRepository constructor.
     * @param string $origin
     * @param string $repoDirectory
     * @param string $userName
     * @param string $email
     * @param string $gitUser
     * @param string $gitPassword
     * @throws FilesystemException
     * @throws PathOutOfBoundsException
     */
    public function __construct(string $origin, string $repoDirectory, string $userName, string $email, string $gitUser, string $gitPassword)
    {

        $origin = substr_replace($origin, "https://$gitUser:$gitPassword@", 0, 8);
        parent::__construct($repoDirectory, $userName, $email, $origin);
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
        phore_exec("git -C :target add .", ["target" => $this->repoDirectory]);

        $ret = phore_exec("git -C :target diff --name-only --cached", ["target" => $this->repoDirectory]);
        // commit only if files changed.
        if (trim($ret) !== "") {
            phore_exec("git -C :target -c 'user.name={$this->userName}' -c 'user.email={$this->email}' commit -m :msg ", ["target" => $this->repoDirectory, "msg" => $message]);
        }
    }

    /**
     * @throws PathOutOfBoundsException
     * @throws PhoreExecException
     */
    public function pull()
    {
        if (!$this->exists()) {
            try {
                phore_exec("git clone :origin :target", ["origin" => $this->origin, "target" => $this->repoDirectory]);
            } catch (PhoreExecException $e) {
                $msg = str_replace($this->gitPassword, "[MASKED]", $e->getMessage());
                $e->setMessage($msg);
                throw $e;
            }
        }
        phore_exec("git -C :target pull -Xtheirs", ["target" => $this->repoDirectory]);
        try {
            $this->currentPulledVersion = phore_exec("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
        } catch (PhoreExecException $e) {
            $msg = str_replace($this->gitPassword, "[MASKED]", $e->getMessage());
            $e->setMessage($msg);
            throw $e;
        }
    }

    /**
     * @throws PhoreExecException
     */
    public function push()
    {
        try {
            phore_exec("git -C :target push", ["target" => $this->repoDirectory]);
        } catch (PhoreExecException $e) {
            $msg = str_replace($this->gitPassword, "[MASKED]", $e->getMessage());
            $e->setMessage($msg);
            throw $e;
        }
    }

    /**
     * @return array
     * @throws PhoreExecException
     * @throws FileAccessException
     * @throws FileNotFoundException
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
            try {
                $this->currentPulledVersion = phore_exec("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
            } catch (PhoreExecException $e) {
                $msg = str_replace($this->gitPassword, "[MASKED]", $e->getMessage());
                $e->setMessage($msg);
                throw $e;
            }

        try {
            $changedFiles = phore_exec("git -C :target diff --name-status :lastRev..:curRev", [
                "target" => $this->repoDirectory,
                "lastRev" => $lastRev,
                "curRev" => $this->currentPulledVersion
            ]);
        } catch (PhoreExecException $e) {
            $msg = str_replace($this->gitPassword, "[MASKED]", $e->getMessage());
            $e->setMessage($msg);
            throw $e;
        }

        return $this->modifyChangedFiles($changedFiles);
    }
}
