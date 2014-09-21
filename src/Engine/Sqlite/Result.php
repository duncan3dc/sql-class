<?php

namespace duncan3dc\SqlClass\Engine\Sqlite;

use duncan3dc\SqlClass\Engine\AbstractResult;

class Result extends AbstractResult
{

    public function getNextRow()
    {
        return $this->result->fetchArray(\SQLITE3_ASSOC);
    }


    public function seek($row)
    {
        $this->result->reset();
        for ($i = 0; $i < $row; $i++) {
            $this->result->fetchArray();
        }
        return true;
    }


    public function count()
    {
        $rows = 0;
        while ($this->result->fetchArray()) {
            $rows++;
        }
        $this->seek($this->position);
        return $rows;
    }


    public function columnCount()
    {
        return $this->result->numColumns();
    }


    public function free()
    {
        return $this->result->finalize();
    }
}
