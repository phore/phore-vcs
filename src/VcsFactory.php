<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 09:59
 */

namespace Phore\VCS;


use InvalidArgumentException;
use Phore\FileSystem\Exception\FilesystemException;
use Phore\FileSystem\Exception\PathOutOfBoundsException;
use Phore\FileSystem\PhoreTempFile;
use Phore\VCS\Git\HttpsGitRepository;
use Phore\VCS\Git\MockVcsRepository;
use Phore\VCS\Git\SshGitRepository;

class VcsFactory
{

    private $sshPrivKey;

    private $commitUserName = "";
    private $commitEmail = "";
    private $gitUser = "";
    private $gitPassword = "";

    public function setAuthSshPrivateKey(string $privateKey)
    {
        $this->sshPrivKey = $privateKey;
    }

    public function setCommitUser($userName, $email)
    {
        $this->commitUserName = $userName;
        $this->commitEmail = $email;
    }

    public function createSshPublicKey(): string
    {
        $tmpfile = new PhoreTempFile();
        $tmpfile->set_contents($this->sshPrivKey);
        return phore_exec("ssh-keygen -y -f :file", ["file" => $tmpfile->getUri()]);
    }

    public function setGitHttpsAuth(string $gitUser, string $gitPassword)
    {
        $this->gitUser = $gitUser;
        $this->gitPassword = $gitPassword;
    }

    /**
     *
     * Note: public repositories need clone in http-mode if no ssh key is available.
     *
     * @param string $targetPath
     * @param string $repoUrl
     * @return VcsRepository
     * @throws FilesystemException
     * @throws PathOutOfBoundsException
     */
    public function repository(string $targetPath, string $repoUrl): VcsRepository
    {
        if (preg_match("/^mock\@(.*)$/", $repoUrl, $matches)) {
            if (!DEV_MODE) {
                throw new InvalidArgumentException("Mock repositories '$repoUrl' are allowed only in dev-mode");
            }
            return new MockVcsRepository($matches[1], $targetPath);
        }
        if (preg_match("/^[a-z0-9_\-]+@[a-z0-9\-\.]+\:.*$/", $repoUrl)) {
            return new SshGitRepository($repoUrl, $targetPath, $this->commitUserName, $this->commitEmail, $this->sshPrivKey);
        }
        if (preg_match("/^https.*$/", $repoUrl)) {
            return new HttpsGitRepository($repoUrl, $targetPath, $this->commitUserName, $this->commitEmail, $this->gitUser,$this->gitPassword);
        }
        throw new InvalidArgumentException("Cannot determine repository type: $repoUrl");
    }

}
