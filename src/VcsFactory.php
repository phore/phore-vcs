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


    public function setAuthSshPrivateKey(string $privateKey)
    {
        $this->sshPrivKey = $privateKey;
    }


    public function repository(string $targetPath, string $repoUrl) : VcsRepository
    {
        if (preg_match("/^[a-z0-9_\-]+@[a-z0-9\-]+\:.*\.git$/", $repoUrl)) {
            return new GitVcsRepository($repoUrl, $targetPath, $this->sshPrivKey);
        }
        throw new \InvalidArgumentException("Cannot determine repository type: $repoUrl");
    }

}