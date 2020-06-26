<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 26.06.20
 * Time: 13:15
 */

namespace Test;


use Phore\System\PhoreExecException;
use Phore\VCS\VcsFactory;
use PHPUnit\Framework\TestCase;

class HttpsVcsTest extends TestCase
{

    public function testSecretNotBleeded()
    {
        $factory = new VcsFactory();

        $this->expectException(PhoreExecException::class, "Command 'git clone 'https://[MASKED]:[MASKED]@hostname.host/git' '/tmp/repo'' returned with code 128. Cloning into '/tmp/repo'...
fatal: unable to access 'https://[MASKED]:[MASKED]@hostname.host/git/': Could not resolve host: hostname.host
");
        $repo = $factory->repository("/tmp/repo", "https://some:pa*`ss@hostname.host/git");
        $repo->pull();
    }

}