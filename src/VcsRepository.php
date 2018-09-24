<?php
/**
 * Created by PhpStorm.
 * User: matthias
 * Date: 12.09.18
 * Time: 10:00
 */

namespace Phore\VCS;


use Phore\ObjectStore\ObjectStore;
use Phore\ObjectStore\Type\ObjectStoreObject;

interface VcsRepository
{

    public function exists();

    public function commit(string $message);

    public function pull();

    public function push();

    public function getObjectstore() : ObjectStore;

    public function object(string $name) : ObjectStoreObject;

}
