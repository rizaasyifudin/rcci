<?php

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace App\Libraries\ModelCustom;

use Closure;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Exceptions\DataException;
use CodeIgniter\Database\Query;
use CodeIgniter\Exceptions\ModelException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Pager\Pager;
use CodeIgniter\Validation\ValidationInterface;
use Config\Services;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use stdClass;

abstract class BaseModelCustom
{
    public $pager;
    protected $insertID = 0;
    protected $DBGroup;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [];

    /**
     * If true, will set created_at, and updated_at
     * values during insert and update routines.
     *
     * @var bool
     */
    protected $useTimestamps = false;

    /**
     * The type of column that created_at and updated_at
     * are expected to.
     *
     * Allowed: 'datetime', 'date', 'int'
     *
     * @var string
     */
    protected $dateFormat = 'datetime';

    /**
     * The column used for insert timestamps
     *
     * @var string
     */
    protected $createdField = 'created_at';

    /**
     * The column used for update timestamps
     *
     * @var string
     */
    protected $updatedField = 'updated_at';

    /**
     * Used by withDeleted to override the
     * model's softDelete setting.
     *
     * @var bool
     */
    protected $tempUseSoftDeletes;

    /**
     * The column used to save soft delete state
     *
     * @var string
     */
    protected $deletedField = 'deleted_at';

    /**
     * Used by asArray and asObject to provide
     * temporary overrides of model default.
     *
     * @var string
     */
    protected $tempReturnType;

    /**
     * Whether we should limit fields in inserts
     * and updates to those available in $allowedFields or not.
     *
     * @var bool
     */
    protected $protectFields = true;

    /**
     * Database Connection
     *
     * @var BaseConnection
     */
    protected $db;

    /**
     * Rules used to validate data in insert, update, and save methods.
     * The array must match the format of data passed to the Validation
     * library.
     *
     * @var array|string
     */
    protected $validationRules = [];

    /**
     * Contains any custom error messages to be
     * used during data validation.
     *
     * @var array
     */
    protected $validationMessages = [];

    /**
     * Skip the model's validation. Used in conjunction with skipValidation()
     * to skip data validation for any future calls.
     *
     * @var bool
     */
    protected $skipValidation = false;

    /**
     * Whether rules should be removed that do not exist
     * in the passed data. Used in updates.
     *
     * @var bool
     */
    protected $cleanValidationRules = true;

    /**
     * Our validator instance.
     *
     * @var ValidationInterface
     */
    protected $validation;

    /*
     * Callbacks.
     *
     * Each array should contain the method names (within the model)
     * that should be called when those events are triggered.
     *
     * "Update" and "delete" methods are passed the same items that
     * are given to their respective method.
     *
     * "Find" methods receive the ID searched for (if present), and
     * 'afterFind' additionally receives the results that were found.
     */

    /**
     * Whether to trigger the defined callbacks
     *
     * @var bool
     */
    protected $allowCallbacks = true;

    /**
     * Used by allowCallbacks() to override the
     * model's allowCallbacks setting.
     *
     * @var bool
     */
    protected $tempAllowCallbacks;

    /**
     * Callbacks for beforeInsert
     *
     * @var array
     */
    protected $beforeInsert = [];

    /**
     * Callbacks for afterInsert
     *
     * @var array
     */
    protected $afterInsert = [];

    /**
     * Callbacks for beforeUpdate
     *
     * @var array
     */
    protected $beforeUpdate = [];

    /**
     * Callbacks for afterUpdate
     *
     * @var array
     */
    protected $afterUpdate = [];

    /**
     * Callbacks for beforeInsertBatch
     *
     * @var array
     */
    protected $beforeInsertBatch = [];

    /**
     * Callbacks for afterInsertBatch
     *
     * @var array
     */
    protected $afterInsertBatch = [];

    /**
     * Callbacks for beforeUpdateBatch
     *
     * @var array
     */
    protected $beforeUpdateBatch = [];

    /**
     * Callbacks for afterUpdateBatch
     *
     * @var array
     */
    protected $afterUpdateBatch = [];

    /**
     * Callbacks for beforeFind
     *
     * @var array
     */
    protected $beforeFind = [];

    /**
     * Callbacks for afterFind
     *
     * @var array
     */
    protected $afterFind = [];

    /**
     * Callbacks for beforeDelete
     *
     * @var array
     */
    protected $beforeDelete = [];

    /**
     * Callbacks for afterDelete
     *
     * @var array
     */
    protected $afterDelete = [];

    /**
     * Whether to allow inserting empty data.
     */
    protected bool $allowEmptyInserts = false;

    public function __construct(?ValidationInterface $validation = null)
    {
        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;

        /**
         * @var ValidationInterface|null $validation
         */
        $validation ??= Services::validation(null, false);
        $this->validation = $validation;

        $this->initialize();
    }

    /**
     * Initializes the instance with any additional steps.
     * Optionally implemented by child classes.
     */
    protected function initialize()
    {
    }

    /**
     * Fetches the row of database.
     * This method works only with dbCalls.
     *
     * @param bool                  $singleton Single or multiple results
     * @param array|int|string|null $id        One primary key or an array of primary keys
     *
     * @return array|object|null The resulting row of data, or null.
     */
    abstract protected function doFind(bool $singleton, $id = null);

    /**
     * Fetches the column of database.
     * This method works only with dbCalls.
     *
     * @param string $columnName Column Name
     *
     * @return array|null The resulting row of data, or null if no data found.
     *
     * @throws DataException
     */
    abstract protected function doFindColumn(string $columnName);

    /**
     * Fetches all results, while optionally limiting them.
     * This method works only with dbCalls.
     *
     * @param int $limit  Limit
     * @param int $offset Offset
     *
     * @return array
     */
    abstract protected function doFindAll(int $limit = 0, int $offset = 0);

    /**
     * Returns the first row of the result set.
     * This method works only with dbCalls.
     *
     * @return array|object|null
     */
    abstract protected function doFirst();

    /**
     * Inserts data into the current database.
     * This method works only with dbCalls.
     *
     * @param array $data Data
     *
     * @return bool
     */
    abstract protected function doInsert(array $data);

    /**
     * Compiles batch insert and runs the queries, validating each row prior.
     * This method works only with dbCalls.
     *
     * @param array|null $set       An associative array of insert values
     * @param bool|null  $escape    Whether to escape values
     * @param int        $batchSize The size of the batch to run
     * @param bool       $testing   True means only number of records is returned, false will execute the query
     *
     * @return bool|int Number of rows inserted or FALSE on failure
     */
    abstract protected function doInsertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false);

    /**
     * Updates a single record in the database.
     * This method works only with dbCalls.
     *
     * @param array|int|string|null $id   ID
     * @param array|null            $data Data
     */
    abstract protected function doUpdate($id = null, $data = null): bool;

    /**
     * Compiles an update and runs the query.
     * This method works only with dbCalls.
     *
     * @param array|null  $set       An associative array of update values
     * @param string|null $index     The where key
     * @param int         $batchSize The size of the batch to run
     * @param bool        $returnSQL True means SQL is returned, false will execute the query
     *
     * @return false|int|string[] Number of rows affected or FALSE on failure, SQL array when testMode
     *
     * @throws DatabaseException
     */
    abstract protected function doUpdateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false);
    abstract protected function except($data = "asdasd");

    /**
     * Deletes a single record from the database where $id matches.
     * This method works only with dbCalls.
     *
     * @param array|int|string|null $id    The rows primary key(s)
     * @param bool                  $purge Allows overriding the soft deletes setting.
     *
     * @return bool|string
     *
     * @throws DatabaseException
     */
    abstract protected function doDelete($id = null, bool $purge = false);

    /**
     * Permanently deletes all rows that have been marked as deleted.
     * through soft deletes (deleted = 1).
     * This method works only with dbCalls.
     *
     * @return bool|string Returns a string if in test mode.
     */
    abstract protected function doPurgeDeleted();

    /**
     * Works with the find* methods to return only the rows that
     * have been deleted.
     * This method works only with dbCalls.
     */
    abstract protected function doOnlyDeleted();

    /**
     * Compiles a replace and runs the query.
     * This method works only with dbCalls.
     *
     * @param array|null $data      Data
     * @param bool       $returnSQL Set to true to return Query String
     *
     * @return BaseResult|false|Query|string
     */
    abstract protected function doReplace(?array $data = null, bool $returnSQL = false);

    /**
     * Grabs the last error(s) that occurred from the Database connection.
     * This method works only with dbCalls.
     *
     * @return array|null
     */
    abstract protected function doErrors();

    /**
     * Returns the id value for the data array or object.
     *
     * @param array|object $data Data
     *
     * @return array|int|string|null
     *
     * @deprecated Add an override on getIdValue() instead. Will be removed in version 5.0.
     */
    abstract protected function idValue($data);

    /**
     * Public getter to return the id value using the idValue() method.
     * For example with SQL this will return $data->$this->primaryKey.
     *
     * @param array|object $data
     *
     * @return array|int|string|null
     *
     * @todo: Make abstract in version 5.0
     */
    public function getIdValue($data)
    {
        return $this->idValue($data);
    }

    /**
     * Override countAllResults to account for soft deleted accounts.
     * This method works only with dbCalls.
     *
     * @param bool $reset Reset
     * @param bool $test  Test
     *
     * @return int|string
     */
    abstract public function countAllResults(bool $reset = true, bool $test = false);

    /**
     * Loops over records in batches, allowing you to operate on them.
     * This method works only with dbCalls.
     *
     * @param int     $size     Size
     * @param Closure $userFunc Callback Function
     *
     * @throws DataException
     */
    abstract public function chunk(int $size, Closure $userFunc);

    /**
     * Fetches the row of database.
     *
     * @param array|int|string|null $id One primary key or an array of primary keys
     *
     * @return array|object|null The resulting row of data, or null.
     */
    public function find($id = null)
    {
        $singleton = is_numeric($id) || is_string($id);

        if ($this->tempAllowCallbacks) {
            // Call the before event and check for a return
            $eventData = $this->trigger('beforeFind', [
                'id'        => $id,
                'method'    => 'find',
                'singleton' => $singleton,
            ]);

            if (!empty($eventData['returnData'])) {
                return $eventData['data'];
            }
        }

        $eventData = [
            'id'        => $id,
            'data'      => $this->doFind($singleton, $id),
            'method'    => 'find',
            'singleton' => $singleton,
        ];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('afterFind', $eventData);
        }

        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['data'];
    }

    /**
     * Fetches the column of database.
     *
     * @param string $columnName Column Name
     *
     * @return array|null The resulting row of data, or null if no data found.
     *
     * @throws DataException
     */
    public function findColumn(string $columnName)
    {
        if (strpos($columnName, ',') !== false) {
            throw DataException::forFindColumnHaveMultipleColumns();
        }

        $resultSet = $this->doFindColumn($columnName);

        return $resultSet ? array_column($resultSet, $columnName) : null;
    }

    /**
     * Fetches all results, while optionally limiting them.
     *
     * @param int $limit  Limit
     * @param int $offset Offset
     *
     * @return array
     */
    public function findAll(int $limit = 0, int $offset = 0)
    {
        if ($this->tempAllowCallbacks) {
            // Call the before event and check for a return
            $eventData = $this->trigger('beforeFind', [
                'method'    => 'findAll',
                'limit'     => $limit,
                'offset'    => $offset,
                'singleton' => false,
            ]);

            if (!empty($eventData['returnData'])) {
                return $eventData['data'];
            }
        }

        $eventData = [
            'data'      => $this->doFindAll($limit, $offset),
            'limit'     => $limit,
            'offset'    => $offset,
            'method'    => 'findAll',
            'singleton' => false,
        ];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('afterFind', $eventData);
        }

        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['data'];
    }

    /**
     * Returns the first row of the result set.
     *
     * @return array|object|null
     */
    public function first()
    {
        if ($this->tempAllowCallbacks) {
            // Call the before event and check for a return
            $eventData = $this->trigger('beforeFind', [
                'method'    => 'first',
                'singleton' => true,
            ]);

            if (!empty($eventData['returnData'])) {
                return $eventData['data'];
            }
        }

        $eventData = [
            'data'      => $this->doFirst(),
            'method'    => 'first',
            'singleton' => true,
        ];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('afterFind', $eventData);
        }

        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['data'];
    }

    /**
     * A convenience method that will attempt to determine whether the
     * data should be inserted or updated. Will work with either
     * an array or object. When using with custom class objects,
     * you must ensure that the class will provide access to the class
     * variables, even if through a magic method.
     *
     * @param array|object $data Data
     *
     * @throws ReflectionException
     */
    public function save($data): bool
    {
        if (empty($data)) {
            return true;
        }
        if ($this->shouldUpdate($data)) {
            $response = $this->update($this->getIdValue($data), $data, null);
        } else {
            $response = $this->insert($data, false, null);

            if ($response !== false) {
                $response = true;
            }
        }

        return $response;
    }

    protected function shouldUpdate($data): bool
    {
        return !empty($this->getIdValue($data));
    }

    public function getInsertID()
    {
        return is_numeric($this->insertID) ? (int) $this->insertID : $this->insertID;
    }

    public function insert($data = null, bool $returnID = true, $except = null)
    {
        if ($data) {
            foreach ($data as $key => $value) {
                if ($except) {
                    if (!in_array($key, $except)) {
                        $data[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                    }
                } else {
                    $data[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                }
            }
        }
        // dd($except);
        $this->insertID = 0;

        // Set $cleanValidationRules to false temporary.
        $cleanValidationRules       = $this->cleanValidationRules;
        $this->cleanValidationRules = false;

        $data = $this->transformDataToArray($data, 'insert');

        // Validate data before saving.
        if (!$this->skipValidation && !$this->validate($data)) {
            // Restore $cleanValidationRules
            $this->cleanValidationRules = $cleanValidationRules;

            return false;
        }

        // Restore $cleanValidationRules
        $this->cleanValidationRules = $cleanValidationRules;

        // Must be called first, so we don't
        // strip out created_at values.
        $data = $this->doProtectFields($data);

        // doProtectFields() can further remove elements from
        // $data so we need to check for empty dataset again
        if (!$this->allowEmptyInserts && empty($data)) {
            throw DataException::forEmptyDataset('insert');
        }

        // Set created_at and updated_at with same time
        $date = $this->setDate();

        if ($this->useTimestamps && $this->createdField && !array_key_exists($this->createdField, $data)) {
            $data[$this->createdField] = $date;
        }

        if ($this->useTimestamps && $this->updatedField && !array_key_exists($this->updatedField, $data)) {
            $data[$this->updatedField] = $date;
        }

        $eventData = ['data' => $data];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('beforeInsert', $eventData);
        }

        $result = $this->doInsert($eventData['data']);

        $eventData = [
            'id'     => $this->insertID,
            'data'   => $eventData['data'],
            'result' => $result,
        ];

        if ($this->tempAllowCallbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterInsert', $eventData);
        }

        $this->tempAllowCallbacks = $this->allowCallbacks;

        // If insertion failed, get out of here
        if (!$result) {
            return $result;
        }

        // otherwise return the insertID, if requested.
        return $returnID ? $this->insertID : $result;
    }
    public function insertBatch(?array $set = null, ?bool $escape = null, int $batchSize = 100, bool $testing = false)
    {
        $except = null;
        $ex = $this->except();
        if (isset($ex->tempData['exceptHtmlspecialChar'])) {
            $except = $ex->tempData['exceptHtmlspecialChar'];
        }

        $cleanValidationRules       = $this->cleanValidationRules;
        $this->cleanValidationRules = false;

        if (is_array($set)) {
            foreach ($set as &$row) {
                if ($row) {
                    foreach ($row as $key => $value) {
                        if ($except) {
                            if (!in_array($key, $except)) {
                                $row[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                            }
                        } else {
                            $row[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                        }
                    }
                }

                if (is_object($row) && !$row instanceof stdClass) {
                    $row = $this->objectToArray($row, false, true);
                }

                if (is_object($row)) {
                    $row = (array) $row;
                }

                if (!$this->skipValidation && !$this->validate($row)) {
                    // Restore $cleanValidationRules
                    $this->cleanValidationRules = $cleanValidationRules;

                    return false;
                }

                $row = $this->doProtectFields($row);

                $date = $this->setDate();

                if ($this->useTimestamps && $this->createdField && !array_key_exists($this->createdField, $row)) {
                    $row[$this->createdField] = $date;
                }

                if ($this->useTimestamps && $this->updatedField && !array_key_exists($this->updatedField, $row)) {
                    $row[$this->updatedField] = $date;
                }
            }
        }

        // Restore $cleanValidationRules
        $this->cleanValidationRules = $cleanValidationRules;

        $eventData = ['data' => $set];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('beforeInsertBatch', $eventData);
        }

        $result = $this->doInsertBatch($eventData['data'], $escape, $batchSize, $testing);

        $eventData = [
            'data'   => $eventData['data'],
            'result' => $result,
        ];

        if ($this->tempAllowCallbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterInsertBatch', $eventData);
        }

        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $result;
    }
    public function update($id = null, $data = null, $except = null): bool
    {
        if ($data) {
            foreach ($data as $key => $value) {
                if ($except) {
                    if (!in_array($key, $except)) {
                        $data[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                    }
                } else {
                    $data[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                }
            }
        }
        // dd($except);
        if (is_bool($id)) {
            throw new InvalidArgumentException('update(): argument #1 ($id) should not be boolean.');
        }

        if (is_numeric($id) || is_string($id)) {
            $id = [$id];
        }

        $data = $this->transformDataToArray($data, 'update');

        // Validate data before saving.
        if (!$this->skipValidation && !$this->validate($data)) {
            return false;
        }

        // Must be called first, so we don't
        // strip out updated_at values.
        $data = $this->doProtectFields($data);

        // doProtectFields() can further remove elements from
        // $data, so we need to check for empty dataset again
        if (empty($data)) {
            throw DataException::forEmptyDataset('update');
        }

        if ($this->useTimestamps && $this->updatedField && !array_key_exists($this->updatedField, $data)) {
            $data[$this->updatedField] = $this->setDate();
        }

        $eventData = [
            'id'   => $id,
            'data' => $data,
        ];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('beforeUpdate', $eventData);
        }

        $eventData = [
            'id'     => $id,
            'data'   => $eventData['data'],
            'result' => $this->doUpdate($id, $eventData['data']),
        ];

        if ($this->tempAllowCallbacks) {
            $this->trigger('afterUpdate', $eventData);
        }

        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['result'];
    }
    public function updateBatch(?array $set = null, ?string $index = null, int $batchSize = 100, bool $returnSQL = false)
    {
        $except = null;
        $ex = $this->except();
        if (isset($ex->tempData['exceptHtmlspecialChar'])) {
            $except = $ex->tempData['exceptHtmlspecialChar'];
        }
        if (is_array($set)) {
            foreach ($set as &$row) {
                if ($row) {
                    foreach ($row as $key => $value) {
                        if ($except) {
                            if (!in_array($key, $except)) {
                                $row[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                            }
                        } else {
                            $row[$key] = htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8', false);
                        }
                    }
                }

                // If $data is using a custom class with public or protected
                // properties representing the collection elements, we need to grab
                // them as an array.
                if (is_object($row) && !$row instanceof stdClass) {
                    $row = $this->objectToArray($row, true, true);
                }

                // If it's still a stdClass, go ahead and convert to
                // an array so doProtectFields and other model methods
                // don't have to do special checks.
                if (is_object($row)) {
                    $row = (array) $row;
                }

                // Validate data before saving.
                if (!$this->skipValidation && !$this->validate($row)) {
                    return false;
                }

                // Save updateIndex for later
                $updateIndex = $row[$index] ?? null;

                // Must be called first so we don't
                // strip out updated_at values.
                $row = $this->doProtectFields($row);

                // Restore updateIndex value in case it was wiped out
                if ($updateIndex !== null) {
                    $row[$index] = $updateIndex;
                }

                if ($this->useTimestamps && $this->updatedField && !array_key_exists($this->updatedField, $row)) {
                    $row[$this->updatedField] = $this->setDate();
                }
            }
        }
        $eventData = ['data' => $set];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('beforeUpdateBatch', $eventData);
        }

        $result = $this->doUpdateBatch($eventData['data'], $index, $batchSize, $returnSQL);

        $eventData = [
            'data'   => $eventData['data'],
            'result' => $result,
        ];

        if ($this->tempAllowCallbacks) {
            // Trigger afterInsert events with the inserted data and new ID
            $this->trigger('afterUpdateBatch', $eventData);
        }

        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $result;
    }
    public function delete($id = null, bool $purge = false)
    {
        if (is_bool($id)) {
            throw new InvalidArgumentException('delete(): argument #1 ($id) should not be boolean.');
        }

        if ($id && (is_numeric($id) || is_string($id))) {
            $id = [$id];
        }

        $eventData = [
            'id'    => $id,
            'purge' => $purge,
        ];

        if ($this->tempAllowCallbacks) {
            $this->trigger('beforeDelete', $eventData);
        }

        $eventData = [
            'id'     => $id,
            'data'   => null,
            'purge'  => $purge,
            'result' => $this->doDelete($id, $purge),
        ];

        if ($this->tempAllowCallbacks) {
            $this->trigger('afterDelete', $eventData);
        }

        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['result'];
    }

    public function purgeDeleted()
    {
        if (!$this->useSoftDeletes) {
            return true;
        }

        return $this->doPurgeDeleted();
    }
    public function withDeleted(bool $val = true)
    {
        $this->tempUseSoftDeletes = !$val;

        return $this;
    }

    public function onlyDeleted()
    {
        $this->tempUseSoftDeletes = false;
        $this->doOnlyDeleted();

        return $this;
    }

    /**
     * Compiles a replace and runs the query.
     *
     * @param array|null $data      Data
     * @param bool       $returnSQL Set to true to return Query String
     *
     * @return BaseResult|false|Query|string
     */
    public function replace(?array $data = null, bool $returnSQL = false)
    {
        // Validate data before saving.
        if ($data && !$this->skipValidation && !$this->validate($data)) {
            return false;
        }

        if ($this->useTimestamps && $this->updatedField && !array_key_exists($this->updatedField, (array) $data)) {
            $data[$this->updatedField] = $this->setDate();
        }

        return $this->doReplace($data, $returnSQL);
    }

    /**
     * Grabs the last error(s) that occurred. If data was validated,
     * it will first check for errors there, otherwise will try to
     * grab the last error from the Database connection.
     *
     * The return array should be in the following format:
     *  ['source' => 'message']
     *
     * @param bool $forceDB Always grab the db error, not validation
     *
     * @return array<string,string>
     */
    public function errors(bool $forceDB = false)
    {
        // Do we have validation errors?
        if (!$forceDB && !$this->skipValidation && ($errors = $this->validation->getErrors())) {
            return $errors;
        }

        return $this->doErrors();
    }

    /**
     * Works with Pager to get the size and offset parameters.
     * Expects a GET variable (?page=2) that specifies the page of results
     * to display.
     *
     * @param int|null $perPage Items per page
     * @param string   $group   Will be used by the pagination library to identify a unique pagination set.
     * @param int|null $page    Optional page number (useful when the page number is provided in different way)
     * @param int      $segment Optional URI segment number (if page number is provided by URI segment)
     *
     * @return array|null
     */
    public function paginate(?int $perPage = null, string $group = 'default', ?int $page = null, int $segment = 0)
    {
        // Since multiple models may use the Pager, the Pager must be shared.
        $pager = Services::pager();

        if ($segment) {
            $pager->setSegment($segment, $group);
        }

        $page = $page >= 1 ? $page : $pager->getCurrentPage($group);
        // Store it in the Pager library, so it can be paginated in the views.
        $this->pager = $pager->store($group, $page, $perPage, $this->countAllResults(false), $segment);
        $perPage     = $this->pager->getPerPage($group);
        $offset      = ($pager->getCurrentPage($group) - 1) * $perPage;

        return $this->findAll($perPage, $offset);
    }

    /**
     * It could be used when you have to change default or override current allowed fields.
     *
     * @param array $allowedFields Array with names of fields
     *
     * @return $this
     */
    public function setAllowedFields(array $allowedFields)
    {
        $this->allowedFields = $allowedFields;

        return $this;
    }

    /**
     * Sets whether or not we should whitelist data set during
     * updates or inserts against $this->availableFields.
     *
     * @param bool $protect Value
     *
     * @return $this
     */
    public function protect(bool $protect = true)
    {
        $this->protectFields = $protect;

        return $this;
    }

    /**
     * Ensures that only the fields that are allowed to be updated
     * are in the data array.
     *
     * Used by insert() and update() to protect against mass assignment
     * vulnerabilities.
     *
     * @param array $data Data
     *
     * @throws DataException
     */
    protected function doProtectFields(array $data): array
    {
        if (!$this->protectFields) {
            return $data;
        }

        if (empty($this->allowedFields)) {
            throw DataException::forInvalidAllowedFields(static::class);
        }

        foreach (array_keys($data) as $key) {
            if (!in_array($key, $this->allowedFields, true)) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Sets the date or current date if null value is passed.
     *
     * @param int|null $userData An optional PHP timestamp to be converted.
     *
     * @return int|string
     *
     * @throws ModelException
     */
    protected function setDate(?int $userData = null)
    {
        $currentDate = $userData ?? Time::now()->getTimestamp();

        return $this->intToDate($currentDate);
    }

    /**
     * A utility function to allow child models to use the type of
     * date/time format that they prefer. This is primarily used for
     * setting created_at, updated_at and deleted_at values, but can be
     * used by inheriting classes.
     *
     * The available time formats are:
     *  - 'int'      - Stores the date as an integer timestamp
     *  - 'datetime' - Stores the data in the SQL datetime format
     *  - 'date'     - Stores the date (only) in the SQL date format.
     *
     * @param int $value value
     *
     * @return int|string
     *
     * @throws ModelException
     */
    protected function intToDate(int $value)
    {
        switch ($this->dateFormat) {
            case 'int':
                return $value;

            case 'datetime':
                return date('Y-m-d H:i:s', $value);

            case 'date':
                return date('Y-m-d', $value);

            default:
                throw ModelException::forNoDateFormat(static::class);
        }
    }

    /**
     * Converts Time value to string using $this->dateFormat.
     *
     * The available time formats are:
     *  - 'int'      - Stores the date as an integer timestamp
     *  - 'datetime' - Stores the data in the SQL datetime format
     *  - 'date'     - Stores the date (only) in the SQL date format.
     *
     * @param Time $value value
     *
     * @return int|string
     */
    protected function timeToDate(Time $value)
    {
        switch ($this->dateFormat) {
            case 'datetime':
                return $value->format('Y-m-d H:i:s');

            case 'date':
                return $value->format('Y-m-d');

            case 'int':
                return $value->getTimestamp();

            default:
                return (string) $value;
        }
    }

    /**
     * Set the value of the skipValidation flag.
     *
     * @param bool $skip Value
     *
     * @return $this
     */
    public function skipValidation(bool $skip = true)
    {
        $this->skipValidation = $skip;

        return $this;
    }

    /**
     * Allows to set validation messages.
     * It could be used when you have to change default or override current validate messages.
     *
     * @param array $validationMessages Value
     *
     * @return $this
     */
    public function setValidationMessages(array $validationMessages)
    {
        $this->validationMessages = $validationMessages;

        return $this;
    }

    /**
     * Allows to set field wise validation message.
     * It could be used when you have to change default or override current validate messages.
     *
     * @param string $field         Field Name
     * @param array  $fieldMessages Validation messages
     *
     * @return $this
     */
    public function setValidationMessage(string $field, array $fieldMessages)
    {
        $this->validationMessages[$field] = $fieldMessages;

        return $this;
    }

    /**
     * Allows to set validation rules.
     * It could be used when you have to change default or override current validate rules.
     *
     * @param array $validationRules Value
     *
     * @return $this
     */
    public function setValidationRules(array $validationRules)
    {
        $this->validationRules = $validationRules;

        return $this;
    }

    public function setValidationRule(string $field, $fieldRules)
    {
        $this->validationRules[$field] = $fieldRules;

        return $this;
    }
    public function cleanRules(bool $choice = false)
    {
        $this->cleanValidationRules = $choice;

        return $this;
    }
    public function validate($data): bool
    {
        $rules = $this->getValidationRules();

        if ($this->skipValidation || empty($rules) || empty($data)) {
            return true;
        }

        // Validation requires array, so cast away.
        if (is_object($data)) {
            $data = (array) $data;
        }

        $rules = $this->cleanValidationRules ? $this->cleanValidationRules($rules, $data) : $rules;

        // If no data existed that needs validation
        // our job is done here.
        if (empty($rules)) {
            return true;
        }

        $this->validation->reset()->setRules($rules, $this->validationMessages);

        return $this->validation->run($data, null, $this->DBGroup);
    }

    public function getValidationRules(array $options = []): array
    {
        $rules = $this->validationRules;

        // ValidationRules can be either a string, which is the group name,
        // or an array of rules.
        if (is_string($rules)) {
            $rules = $this->validation->loadRuleGroup($rules);
        }

        if (isset($options['except'])) {
            $rules = array_diff_key($rules, array_flip($options['except']));
        } elseif (isset($options['only'])) {
            $rules = array_intersect_key($rules, array_flip($options['only']));
        }

        return $rules;
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }
    protected function cleanValidationRules(array $rules, ?array $data = null): array
    {
        if (empty($data)) {
            return [];
        }

        foreach (array_keys($rules) as $field) {
            if (!array_key_exists($field, $data)) {
                unset($rules[$field]);
            }
        }

        return $rules;
    }

    public function allowCallbacks(bool $val = true)
    {
        $this->tempAllowCallbacks = $val;

        return $this;
    }
    protected function trigger(string $event, array $eventData)
    {
        // Ensure it's a valid event
        if (!isset($this->{$event}) || empty($this->{$event})) {
            return $eventData;
        }

        foreach ($this->{$event} as $callback) {
            if (!method_exists($this, $callback)) {
                throw DataException::forInvalidMethodTriggered($callback);
            }

            $eventData = $this->{$callback}($eventData);
        }

        return $eventData;
    }
    public function asArray()
    {
        $this->tempReturnType = 'array';

        return $this;
    }

    public function asObject(string $class = 'object')
    {
        $this->tempReturnType = $class;

        return $this;
    }

    protected function objectToArray($data, bool $onlyChanged = true, bool $recursive = false): array
    {
        $properties = $this->objectToRawArray($data, $onlyChanged, $recursive);

        // Convert any Time instances to appropriate $dateFormat
        if ($properties) {
            $properties = array_map(function ($value) {
                if ($value instanceof Time) {
                    return $this->timeToDate($value);
                }

                return $value;
            }, $properties);
        }

        return $properties;
    }

    protected function objectToRawArray($data, bool $onlyChanged = true, bool $recursive = false): ?array
    {
        if (method_exists($data, 'toRawArray')) {
            $properties = $data->toRawArray($onlyChanged, $recursive);
        } else {
            $mirror = new ReflectionClass($data);
            $props  = $mirror->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

            $properties = [];

            // Loop over each property,
            // saving the name/value in a new array we can return.
            foreach ($props as $prop) {
                // Must make protected values accessible.
                $prop->setAccessible(true);
                $properties[$prop->getName()] = $prop->getValue($data);
            }
        }

        return $properties;
    }

    protected function transformDataToArray($data, string $type): array
    {
        if (!in_array($type, ['insert', 'update'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid type "%s" used upon transforming data to array.', $type));
        }

        if (!$this->allowEmptyInserts && empty($data)) {
            throw DataException::forEmptyDataset($type);
        }

        // If $data is using a custom class with public or protected
        // properties representing the collection elements, we need to grab
        // them as an array.
        if (is_object($data) && !$data instanceof stdClass) {
            // If it validates with entire rules, all fields are needed.
            $onlyChanged = ($this->skipValidation === false && $this->cleanValidationRules === false)
                ? false : ($type === 'update');

            $data = $this->objectToArray($data, $onlyChanged, true);
        }

        // If it's still a stdClass, go ahead and convert to
        // an array so doProtectFields and other model methods
        // don't have to do special checks.
        if (is_object($data)) {
            $data = (array) $data;
        }

        // If it's still empty here, means $data is no change or is empty object
        if (!$this->allowEmptyInserts && empty($data)) {
            throw DataException::forEmptyDataset($type);
        }

        return $data;
    }

    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        return $this->db->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        if (property_exists($this, $name)) {
            return true;
        }

        return isset($this->db->{$name});
    }


    public function __call(string $name, array $params)
    {
        if (method_exists($this->db, $name)) {
            return $this->db->{$name}(...$params);
        }

        return null;
    }

    protected function fillPlaceholders(array $rules, array $data): array
    {
        $replacements = [];

        foreach ($data as $key => $value) {
            $replacements['{' . $key . '}'] = $value;
        }

        if (!empty($replacements)) {
            foreach ($rules as &$rule) {
                if (is_array($rule)) {
                    foreach ($rule as &$row) {
                        // Should only be an `errors` array
                        // which doesn't take placeholders.
                        if (is_array($row)) {
                            continue;
                        }

                        $row = strtr($row, $replacements);
                    }

                    continue;
                }

                $rule = strtr($rule, $replacements);
            }
        }

        return $rules;
    }

    /**
     * Sets $allowEmptyInserts.
     */
    public function allowEmptyInserts(bool $value = true): self
    {
        $this->allowEmptyInserts = $value;

        return $this;
    }
}