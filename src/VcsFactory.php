<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 09:59
 */

namespace Phore\VCS;


use Phore\VCS\Git\GitVcsRepository;

class VcsFactory
{

    private $sshPrivKey;

    private $commitUserName;
    private $commitEmail;

    public function setAuthSshPrivateKey(string $privateKey)
    {
        $this->sshPrivKey = $privateKey;
    }

    public function setCommitUser ($userName, $email)
    {
        $this->commitUserName = $userName;
        $this->commitEmail = $email;
    }

    /**
     *
     * Note: public repositories need clone in http-mode if no ssh key is available.
     *
     * @param string $targetPath
     * @param string $repoUrl
     * @return VcsRepository
     */
    public function repository(string $targetPath, string $repoUrl) : VcsRepository
    {
        if (preg_match("/^[a-z0-9_\-]+@[a-z0-9\-\.]+\:.*\.git$/", $repoUrl)) {
            return new GitVcsRepository($repoUrl, $targetPath, $this->sshPrivKey);
        }
        if (preg_match("/^http.*\.git$/", $repoUrl)) {
            return new GitVcsRepository($repoUrl, $targetPath, $this->sshPrivKey);
        }
        throw new \InvalidArgumentException("Cannot determine repository type: $repoUrl");
    }

}
