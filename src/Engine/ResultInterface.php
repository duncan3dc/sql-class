<?php

namespace duncan3dc\SqlClass\Engine;

interface ResultInterface
{

    public function getNextRow();


    public function seek($row);


    public function count();


    public function columnCount();


    public function result($row, $col);


    public function free();
}
