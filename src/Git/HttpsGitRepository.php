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

    private $gitUser;

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
        $this->gitPassword = $gitPassword;
        $this->gitUser = $gitUser;

        $url = phore_parse_url($origin);
        if ( ! empty($gitUser) || !empty($gitPassword)) {
            $url = $url->withUserPass($gitUser, $gitPassword);
        }
        $this->gitPassword = $url->pass;
        $this->gitUser = $url->user;

        parent::__construct((string)$url, $repoDirectory, $userName, $email);
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
                $msg = str_replace(urlencode($this->gitPassword), "[MASKED]", $e->getMessage());
                $msg = str_replace(urlencode($this->gitUser), "[MASKED]", $msg);
                $e->setMessage($msg);
                throw $e;
            }
        }
        phore_exec("git -C :target pull -Xtheirs", ["target" => $this->repoDirectory]);
        try {
            $this->currentPulledVersion = phore_exec("git -C :target rev-parse HEAD", ["target" => $this->repoDirectory]);
        } catch (PhoreExecException $e) {
            $msg = str_replace(urlencode($this->gitPassword), "[MASKED]", $e->getMessage());
            $msg = str_replace(urlencode($this->gitUser), "[MASKED]", $msg);
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
            $msg = str_replace(urlencode($this->gitPassword), "[MASKED]", $e->getMessage());
            $msg = str_replace(urlencode($this->gitUser), "[MASKED]", $msg);
            $e->setMessage($msg);
            throw $e;
        }
    }

    
}
