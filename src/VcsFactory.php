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
use Phore\System\PhoreExecException;
use Phore\VCS\Git\HttpsGitRepository;
use Phore\VCS\Git\MockVcsRepository;
use Phore\VCS\Git\SshGitRepository;

/**
 * Class VcsFactory
 * @package Phore\VCS
 */
class VcsFactory
{

    /**
     * @var
     */
    private $sshPrivKey;

    /**
     * @var string
     */
    private $commitUserName = "";
    /**
     * @var string
     */
    private $commitEmail = "";
    /**
     * @var string
     */
    private $gitUser = "";
    /**
     * @var string
     */
    private $gitPassword = "";

    /**
     * @param string $privateKey
     */
    public function setAuthSshPrivateKey(string $privateKey)
    {
        $this->sshPrivKey = $privateKey;
    }

    /**
     * @param $userName
     * @param $email
     */
    public function setCommitUser($userName, $email)
    {
        $this->commitUserName = $userName;
        $this->commitEmail = $email;
    }

    /**
     * @return string
     * @throws FilesystemException
     * @throws PhoreExecException
     */
    public function createSshPublicKey(): string
    {
        $tmpfile = new PhoreTempFile();
        $tmpfile->set_contents($this->sshPrivKey);
        return phore_exec("ssh-keygen -y -f :file", ["file" => $tmpfile->getUri()]);
    }

    /**
     * @param string $gitUser
     * @param string $gitPassword
     */
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
            $opts = phore_parse_url($repoUrl);

            // Load the key from file
            $sshKeyFile = $opts->getQueryVal("ssh_priv_key_file", null);
            if ($sshKeyFile !== null) {
                $this->sshPrivKey = phore_file($sshKeyFile)->get_contents();
            }

            // Load the key directly from parameter
            $sshKey = $opts->getQueryVal("ssh_priv_key", null);
            if ($sshKey !== null) {
                $this->sshPrivKey = $sshKey;
            }

            return new SshGitRepository($repoUrl, $targetPath, $this->commitUserName, $this->commitEmail, $this->sshPrivKey);
        }
        if (preg_match("/^https.*$/", $repoUrl)) {
            $opts = phore_parse_url($repoUrl);

            // Load the key from file
            $authUser = $opts->getQueryVal("auth_user", null);
            if ($authUser !== null) {
                $this->gitUser = phore_file($authUser)->get_contents();
            }

            // Load the key directly from file
            $passFile = $opts->getQueryVal("auth_pass_file", null);
            if ($passFile !== null) {
                $this->gitPassword = phore_file($passFile)->get_contents();
            }

            // Load the key directly from parameter
            $pass = $opts->getQueryVal("auth_pass", null);
            if ($pass !== null) {
                $this->gitPassword = $pass;
            }

            return new HttpsGitRepository($repoUrl, $targetPath, $this->commitUserName, $this->commitEmail, $this->gitUser,$this->gitPassword);
        }
        throw new InvalidArgumentException("Cannot determine repository type: $repoUrl");
    }

}
