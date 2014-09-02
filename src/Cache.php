<?php
/**
 * Cache results on disk to make future queries faster
 */

namespace duncan3dc\SqlClass;

use duncan3dc\Helpers\Helper;
use duncan3dc\Helpers\Json;

class Cache extends ResultInterface
{
    const MINUTE = 60;
    const HOUR = 3600;
    const DAY = 86400;

    public $sql;            # A reference to an sql class instance to execute the query over

    public $query;          # The query to be executed
    public $params;         # The parameters to be used in the query
    public $hash;           # The hash key of the query

    public $dir;            # The location of the cache storage

    protected $totalRows;   # The total number of rows
    protected $columnCount; # The number of columns in each row
    protected $rowLimit;    # The maximum number of rows that we permit to cache

    public  $timeout;       # How long the data should be cached for
    public  $cacheTime;     # The time that the data was cached at

    public  $indexMap;      # An array that maps the row index to it's position in the sorted array


    public function __construct(array $options = null)
    {
        $options = Helper::getOptions($options, [
            "dir"           =>  "/tmp/query_cache",
            "sql"           =>  false,
            "query"         =>  false,
            "params"        =>  false,
            "timeout"       =>  static::DAY,
            "limit"         =>  10000,
            "directories"   =>  3,
        ]);

        $this->sql = $options["sql"];

        # Store the query for other methods to use
        $this->query = $options["query"];
        $this->params = $options["params"];

        # Create the hash of the query to use as an identifier
        $this->hash = sha1($this->query . print_r($this->params, true));

        /**
         * Create the path to the cache directory
         * Adding the number of directories specified in the options
         * This is because most filesystems place a limit on how many links you can have within a directory,
         * so this reduces that problem by spliting the cache directories into subdirectories
         */
        $this->dir = $options["dir"] . "/";
        for ($i = 0; $i < $options["directories"]; $i++) {
            $this->dir .= $this->hash[$i] . "/";
        }
        $this->dir .= $this->hash;

        $this->timeout = $options["timeout"];
        $this->rowLimit = round($options["limit"]);

        # Ensure a cache directory exists for this query
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }

        # If cache doesn't exist for this query then create it now
        if (!$this->isCached()) {
            $this->createCache();
        }

        $data = Json::decodeFromFile($this->dir . "/.data");
        $this->totalRows = $data["totalRows"];
        $this->columnCount = $data["columnCount"];
        $this->position = 0;

        $this->indexMap = false;
    }


    public function isCached()
    {
        if ($this->sql->output) {
            echo "checking the cache (" . $this->dir . ")";
            echo ($this->sql->htmlMode) ? "<br>" : "\n";
        }

        # If no status file exists for this cache then presume there isn't any valid data
        if (!file_exists($this->dir . "/.data")) {
            if ($this->sql->output) {
                echo "no status file found";
                echo ($this->sql->htmlMode) ? "<hr>" : "\n";
            }
            return false;
        }

        # If the status file is older than the specified timeout then force a refresh
        $this->cacheTime = filectime($this->dir . "/.data");
        if ($this->sql->output) {
            echo "cache found (" . date("Y-m-d H:i:s", $this->cacheTime) . ")";
        }

        if ($this->cacheTime < (time() - $this->timeout)) {
            if ($this->sql->output) {
                echo ($this->sql->htmlMode) ? "<br>" : "\n";
                echo "cache is too old to use";
            }
            return false;
        }

        if ($this->sql->output) {
            echo ($this->sql->htmlMode) ? "<hr>" : "\n";
        }

        return true;
    }


    public function createCache()
    {
        $this->result = $this->sql->query($this->query, $this->params);

        $this->cacheTime = time();

        Json::encodeToFile($this->dir . "/.data", []);
        Json::encodeToFile($this->dir . "/.sorted", []);

        $rowNum = 0;
        $columnCount = 0;
        while ($row = $this->sql->fetch($this->result)) {
            if (!$rowNum) {
                $columnCount = count($row);
            }

            Json::encodeToFile($this->dir . "/" . $rowNum . ".row", $row);

            $rowNum++;

            if ($this->rowLimit && $rowNum > $this->rowLimit) {
                break;
            }
        }

        Json::encodeToFile($this->dir . "/.data", [
            "totalRows"     =>  $rowNum,
            "columnCount"   =>  $columnCount,
        ]);
    }


    /**
     * Fetch the next row from the result set and clean it up
     *
     * All field values have rtrim() called on them to remove trailing space
     * All column keys have strtolower() called on them to convert them to lowercase (for consistency across database engines)
     *
     * @param int $style One of the fetch style constants from the Sql class (Sql::FETCH_ROW or Sql::FETCH_ASSOC)
     *
     * @return array|null
     */
    public function fetch($style = null)
    {
        if ($this->position >= $this->totalRows) {
            return;
        }

        if ($this->indexMap) {
            $rowIndex = $this->indexMap[$this->position];
        } else {
            $rowIndex = $this->position;
        }

        $row = Json::decodeFromFile($this->dir . "/" . $rowIndex . ".row");

        $this->position++;

        # If no style was specified then use the current setting
        if (!$style) {
            $style = $this->fetchStyle;
        }

        if ($style !== Sql::FETCH_ASSOC) {
            $new = [];
            foreach ($row as $val) {
                $new[] = $val;
            }
            $row = $new;
        }

        return $row;
    }


    /**
     * Fetch an indiviual value from the result set
     *
     * @param int $row The index of the row to fetch (zero-based)
     * @param int $col The index of the column to fetch (zero-based)
     *
     * @return string
     */
    public function result($row, $col)
    {
        $this->seek($row);

        $row = $this->fetch(true);

        $val = $row[$col];

        return $val;
    }


    /**
     * Seek to a specific record of the result set
     *
     * @param int $row The index of the row to position to (zero-based)
     *
     * @return void
     */
    public function seek($row)
    {
        $this->position = $row;
    }


    /**
     * Get the number of rows in the result set
     *
     * @return int
     */
    public function count()
    {
        return $this->totalRows;
    }


    /**
     * Get the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        return $this->columnCount;
    }


    public function orderBy($col, $desc = null)
    {
        # Check if the data has already been sorted by this column
        $sorted = Json::decodeFromFile($this->dir . "/.sorted");
        $this->indexMap = $sorted[$col];

        # If the data hasn't already been sorted then create an index map for it now
        if (!is_array($this->indexMap)) {

            $sort = [];
            $pos = 0;
            $this->seek(0);
            while ($row = $this->fetch()) {
                $sort[$pos] = $row[$col];
                $pos++;
            }
            $this->seek(0);

            asort($sort);
            $this->indexMap = array_keys($sort);

            $sorted[$col] = $this->indexMap;
            Json::encodeToFile($this->dir . "/.sorted", $sorted);
        }

        if ($desc) {
            $this->indexMap = array_reverse($this->indexMap);
        }

        return true;
    }
}
