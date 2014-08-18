<?php

namespace duncan3dc\SqlClass\Engine;

abstract class ResultInterface extends \duncan3dc\SqlClass\AbstractResult
{
    abstract public function free();
}
