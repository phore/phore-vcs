<?php

namespace Phore\VCS\Git;

use InvalidArgumentException;
use Phore\Core\Exception\InvalidDataException;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;

class HttpsGitRepository extends GitRepository
{

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
    public function __construct(string $origin, string $repoDirectory, string $userName, string $email,string $gitUser, string $gitPassword)
    {
        $origin = substr_replace($origin,"https://$gitUser:$gitPassword@",0,8);
        parent::__construct($repoDirectory,$userName,$email, $origin);
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
     * @param string $message
     * @throws InvalidDataException
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

    public function pull()
    {
        if (!$this->exists()) {
            phore_exec("git clone :origin :target", ["origin" => $this->origin, "target" => $this->repoDirectory]);
        }
        phore_exec("git -C :target pull -Xtheirs", ["target" => $this->repoDirectory]);
        $this->currentPulledVersion = phore_exec("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
    }

    public function push()
    {
        phore_exec("git -C :target push", ["target" => $this->repoDirectory]);
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

    public function getChangedFiles(): array
    {
        if ($this->savepointFile === null)
            throw new InvalidArgumentException("No savepoint file specified. Use setSavepointFile() to select one.");

        $lastRev = $this->savepointFile->get_contents();
        if ($lastRev === "") {
            $lastRev = "4b825dc642cb6eb9a060e54bf8d69288fbee4904"; // <= This is git default for empty tree (before first commit)
        }
        if ($this->currentPulledVersion === null)
            $this->currentPulledVersion = phore_exec("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);

        $changedFiles = phore_exec("git -C :target diff --name-status :lastRev..:curRev", [
            "target" => $this->repoDirectory,
            "lastRev" => $lastRev,
            "curRev" => $this->currentPulledVersion
        ]);

        $changedFiles = explode("\n", $changedFiles);

        $ret = [];
        foreach ($changedFiles as $curLine) {
            $curLine = trim($curLine);
            $skey = substr($curLine, 0, 1);
            $status = isset (self::GIT_STATUS_MAP[$skey]) ? self::GIT_STATUS_MAP[$skey] : null;

            if ($status === null)
                continue;
            $ret[] = [substr($curLine, 0, 1), trim(substr($curLine, 1))];
        }
        return $ret;
    }

    public function saveSavepoint()
    {
        $this->savepointFile->set_contents($this->currentPulledVersion);
    }
}
