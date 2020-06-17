<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 17.06.20
 * Time: 16:46
 */

namespace Test;


use Phore\VCS\Git\HttpsGitRepository;
use Phore\VCS\Git\SshGitRepository;
use Phore\VCS\VcsFactory;
use PHPUnit\Framework\TestCase;

class VcsFactoryTest extends TestCase
{


    public function testHttpsCon()
    {
        $factory = new VcsFactory();

        $t = $factory->repository("/tmp", "https://some.tld/path/to/repo?auth_user=user&auth_pass=pass");

        $this->assertInstanceOf(HttpsGitRepository::class, $t);

        $this->assertEquals("https://user:pass@some.tld/path/to/repo", $t->getOrigin());
    }


    public function testHttpsConWoParams()
    {
        $factory = new VcsFactory();

        $t = $factory->repository("/tmp", "https://user:pass@some.tld/path/to/repo.git");

        $this->assertInstanceOf(HttpsGitRepository::class, $t);

        $this->assertEquals("https://user:pass@some.tld/path/to/repo.git", $t->getOrigin());
    }


    public function testSSHConWithParams()
    {
        $factory = new VcsFactory();

        $t = $factory->repository("/tmp", "git@some.tld:path/to/repo?ssh_priv_key=privkey");

        $this->assertInstanceOf(SshGitRepository::class, $t);

        $this->assertEquals("git@some.tld:path/to/repo", $t->getOrigin());
    }

    public function testSSHConWoParams()
    {
        $factory = new VcsFactory();

        $t = $factory->repository("/tmp", "git@some.tld:path/to/repo");

        $this->assertInstanceOf(SshGitRepository::class, $t);

        $this->assertEquals("git@some.tld:path/to/repo", $t->getOrigin());
    }
}