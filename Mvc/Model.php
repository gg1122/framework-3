<?php

namespace ManaPHP\Mvc;

use ManaPHP\Component;
use ManaPHP\Di;
use ManaPHP\Mvc\Model\Exception as ModelException;
use ManaPHP\Utility\Text;

/**
 * Class ManaPHP\Mvc\Model
 *
 * @package model
 *
 * @method void initialize()
 * @method void onConstruct()
 *
 * method beforeCreate()
 * method afterCreate()
 *
 * method beforeSave()
 * method afterSave()
 *
 * method afterFetch()
 *
 * method beforeUpdate()
 * method afterUpdate()
 *
 * method beforeDelete()
 * method afterDelete()
 *
 */
class Model extends Component implements ModelInterface, \JsonSerializable
{
    /**
     * @var array
     */
    protected $_snapshot = [];

    /**
     * \ManaPHP\Mvc\Model constructor
     *
     * @param array $data
     */
    final public function __construct($data = [])
    {
        if (count($data) !== 0) {
            $this->_snapshot = $data;
            foreach ($data as $field => $value) {
                $this->{$field} = $value;
            }

            if (method_exists($this, 'afterFetch')) {
                $this->afterFetch();
            }
        }
    }

    /**
     * Returns table name mapped in the model
     *
     * @param mixed $context
     *
     * @return string|false
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function getSource($context = null)
    {
        $modelName = get_called_class();
        return Text::underscore(Text::contains($modelName, '\\') ? substr($modelName, strrpos($modelName, '\\') + 1) : $modelName);
    }

    /**
     * Gets the connection used to crud data to the model
     *
     * @param mixed $context
     *
     * @return string|false
     */
    public static function getDb($context = null)
    {
        return 'db';
    }

    /**
     * @param mixed $context
     *
     * @return \ManaPHP\DbInterface|false
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function getConnection($context = null)
    {
        $db = static::getDb($context);
        if ($db === false) {
            return false;
        }

        return Di::getDefault()->getShared($db);
    }

    /**
     * @return array
     */
    public static function getPrimaryKey()
    {
        return Di::getDefault()->modelsMetadata->getPrimaryKeyAttributes(get_called_class());
    }

    /**
     * @return array
     */
    public static function getFields()
    {
        return Di::getDefault()->modelsMetadata->getAttributes(get_called_class());
    }

    /**
     * @return string
     */
    public static function getAutoIncrementField()
    {
        return Di::getDefault()->modelsMetadata->getAutoIncrementAttribute(get_called_class());
    }

    /**
     * @param string|array $fields
     *
     * @return \ManaPHP\Mvc\Model\CriteriaInterface
     */
    public static function createCriteria($fields = null)
    {
        return Di::getDefault()->get('ManaPHP\Mvc\Model\Criteria', [get_called_class(), $fields]);
    }

    /**
     * Allows to query a set of records that match the specified conditions
     *
     * <code>
     *
     * //How many robots are there?
     * $robots = Robots::find();
     * echo "There are ", count($robots), "\n";
     *
     * //How many mechanical robots are there?
     * $robots = Robots::find("type='mechanical'");
     * echo "There are ", count($robots), "\n";
     *
     * //Get and print virtual robots ordered by name
     * $robots = Robots::find(array("type='virtual'", "order" => "name"));
     * foreach ($robots as $robot) {
     *       echo $robot->name, "\n";
     * }
     *
     * //Get first 100 virtual robots ordered by name
     * $robots = Robots::find(array("type='virtual'", "order" => "name", "limit" => 100));
     * foreach ($robots as $robot) {
     *       echo $robot->name, "\n";
     * }
     * </code>
     *
     * @param  string|array $parameters
     *
     * @return  static[]
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function find($parameters = null)
    {
        $criteria = static::createCriteria();
        if (is_string($parameters)) {
            $parameters = [$parameters];
        }

        if (isset($parameters['columns'])) {
            $criteria->select($parameters['columns']);
            unset($parameters['columns']);
        } else {
            $criteria->select(static::getFields());
        }

        if (isset($parameters['in'])) {
            $criteria->inWhere(static::getPrimaryKey()[0], $parameters['in']);
            unset($parameters['in']);
        }

        $criteria->buildFromArray($parameters);

        return $criteria->execute(true);
    }

    /**
     * alias of find
     *
     * @param    string|array $parameters
     *
     * @return  static[]
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    final public static function findAll($parameters = null)
    {
        return self::find($parameters);
    }

    /**
     * Allows to query the first record that match the specified conditions
     *
     * <code>
     *
     * //What's the first robot in robots table?
     * $robot = Robots::findFirst();
     * echo "The robot name is ", $robot->name;
     *
     * //What's the first mechanical robot in robots table?
     * $robot = Robots::findFirst("type='mechanical'");
     * echo "The first mechanical robot name is ", $robot->name;
     *
     * //Get first virtual robot ordered by name
     * $robot = Robots::findFirst(array("type='virtual'", "order" => "name"));
     * echo "The first virtual robot name is ", $robot->name;
     *
     * </code>
     *
     * @param string|array $parameters
     *
     * @return static|false
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function findFirst($parameters = null)
    {
        if (is_scalar($parameters)) {
            return static::findById($parameters);
        }

        $criteria = static::createCriteria();

        if (isset($parameters['columns'])) {
            $criteria->select($parameters['columns']);
            unset($parameters['columns']);
        } else {
            $criteria->select(static::getFields());
        }

        $rs = $criteria->buildFromArray($parameters)->limit(1)->execute(true);
        return isset($rs[0]) ? $rs[0] : false;
    }

    /**
     * @param int|string   $id
     * @param string|array $fields
     *
     * @return static|false
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function findById($id, $fields = null)
    {
        if (!is_scalar($id)) {
            throw new ModelException('`:primaryKey` primaryKey must be a scalar value.', ['primaryKey' => static::getPrimaryKey()[0]]);
        }
        $rs = static::createCriteria()->select($fields ?: static::getFields())->where(static::getPrimaryKey()[0], $id)->execute(true);
        return isset($rs[0]) ? $rs[0] : false;
    }

    /**
     * @param string|array $parameters
     *
     * @return bool
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function exists($parameters = null)
    {
        if (is_scalar($parameters)) {
            $primaryKeys = static::getPrimaryKey();

            if (count($primaryKeys) === 0) {
                throw new ModelException('parameter is scalar, but the primary key of `:model` model is none', ['model' => get_called_class()]);
            }

            if (count($primaryKeys) !== 1) {
                throw new ModelException('parameter is scalar, but the primary key of `:model` model has more than one column'/**m0a5878bf7ea49c559*/,
                    ['model' => get_called_class()]);
            }

            $parameters = [$primaryKeys[0] => $parameters];
        } elseif (is_string($parameters)) {
            $parameters = [$parameters];
        }

        return static::createCriteria()
            ->buildFromArray($parameters)
            ->exists();
    }

    /**
     * alias of createQuery
     *
     * @param string $alias
     *
     * @return \ManaPHP\Mvc\Model\QueryInterface
     * @deprecated
     */
    public static function query($alias = null)
    {
        return static::createQuery($alias);
    }

    /**
     * Create a criteria for a specific model
     *
     * @param string $alias
     *
     * @return \ManaPHP\Mvc\Model\QueryInterface
     */
    public static function createQuery($alias = null)
    {
        return Di::getDefault()->get('ManaPHP\Mvc\Model\Query')->from(get_called_class(), $alias);
    }

    /**
     * Generate a SQL SELECT statement for an aggregate
     *
     * @param string $function
     * @param string $alias
     * @param string $field
     * @param array  $parameters
     *
     * @return mixed
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    protected static function _groupResult($function, $alias, $field, $parameters)
    {
        $criteria = static::createCriteria();

        if ($parameters === null) {
            $parameters = [];
        }

        if (preg_match('#^[a-z_][a-z0-9_]*$#i', $field) === 1) {
            $field = '[' . $field . ']';
        }

        $rs = $criteria->aggregate([$alias => "$function($field)"])->where($parameters)->execute();

        return $rs[0][$alias];
    }

    /**
     * Allows to count how many records match the specified conditions
     *
     * <code>
     *
     * //How many robots are there?
     * $number = Robots::count();
     * echo "There are ", $number, "\n";
     *
     * //How many mechanical robots are there?
     * $number = Robots::count("type='mechanical'");
     * echo "There are ", $number, " mechanical robots\n";
     *
     * </code>
     *
     * @param array  $parameters
     * @param string $field
     *
     * @return int
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function count($parameters = null, $field = null)
    {
        $result = self::_groupResult('COUNT', 'row_count', $field ?: '*', $parameters);
        if (is_string($result)) {
            $result = (int)$result;
        }

        return $result;
    }

    /**
     * Allows to calculate a summary on a column that match the specified conditions
     *
     * <code>
     *
     * //How much are all robots?
     * $sum = Robots::sum(array('column' => 'price'));
     * echo "The total price of robots is ", $sum, "\n";
     *
     * //How much are mechanical robots?
     * $sum = Robots::sum(array("type='mechanical'", 'column' => 'price'));
     * echo "The total price of mechanical robots is  ", $sum, "\n";
     *
     * </code>
     *
     * @param string $field
     * @param array  $parameters
     *
     * @return int|float
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function sum($field, $parameters = null)
    {
        return self::_groupResult('SUM', 'summary', $field, $parameters);
    }

    /**
     * Allows to get the max value of a column that match the specified conditions
     *
     * <code>
     *
     * //What is the max robot id?
     * $id = Robots::max(array('column' => 'id'));
     * echo "The max robot id is: ", $id, "\n";
     *
     * //What is the max id of mechanical robots?
     * $sum = Robots::max(array("type='mechanical'", 'column' => 'id'));
     * echo "The max robot id of mechanical robots is ", $id, "\n";
     *
     * </code>
     *
     * @param string $field
     * @param array  $parameters
     *
     * @return int|float
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function max($field, $parameters = null)
    {
        return self::_groupResult('MAX', 'maximum', $field, $parameters);
    }

    /**
     * Allows to get the min value of a column that match the specified conditions
     *
     * <code>
     *
     * //What is the min robot id?
     * $id = Robots::min(array('column' => 'id'));
     * echo "The min robot id is: ", $id;
     *
     * //What is the min id of mechanical robots?
     * $sum = Robots::min(array("type='mechanical'", 'column' => 'id'));
     * echo "The min robot id of mechanical robots is ", $id;
     *
     * </code>
     *
     * @param string $field
     * @param array  $parameters
     *
     * @return int|float
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function min($field, $parameters = null)
    {
        return self::_groupResult('MIN', 'minimum', $field, $parameters);
    }

    /**
     * Allows to calculate the average value on a column matching the specified conditions
     *
     * <code>
     *
     * //What's the average price of robots?
     * $average = Robots::average(array('column' => 'price'));
     * echo "The average price is ", $average, "\n";
     *
     * //What's the average price of mechanical robots?
     * $average = Robots::average(array("type='mechanical'", 'column' => 'price'));
     * echo "The average price of mechanical robots is ", $average, "\n";
     *
     * </code>
     *
     * @param string $field
     * @param array  $parameters
     *
     * @return double
     * @throws \ManaPHP\Db\Query\Exception
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function avg($field, $parameters = null)
    {
        return (double)self::_groupResult('AVG', 'average', $field, $parameters);
    }

    /**
     * Fires an event, implicitly calls behaviors and listeners in the events manager are notified
     *
     * @param string $eventName
     *
     * @return void
     */
    protected function _fireEvent($eventName)
    {
        if (method_exists($this, $eventName)) {
            $this->{$eventName}();
        }

        $this->fireEvent('model:' . $eventName);
    }

    /**
     * Fires an internal event that cancels the operation
     *
     * @param string $eventName
     *
     * @return bool
     */
    protected function _fireEventCancel($eventName)
    {
        if (method_exists($this, $eventName) && $this->{$eventName}() === false) {
            return false;
        }

        return $this->fireEvent('model:' . $eventName) !== false;
    }

    /**
     * Assigns values to a model from an array
     *
     *<code>
     *$robot->assign(array(
     *  'type' => 'mechanical',
     *  'name' => 'Boy',
     *  'year' => 1952
     *));
     *</code>
     *
     * @param array $data
     * @param array $whiteList
     *
     * @return static
     */
    public function assign($data, $whiteList = null)
    {
        foreach (static::getFields() as $field) {
            if (!isset($data[$field])) {
                continue;
            }

            if ($whiteList !== null && !in_array($field, $whiteList, true)) {
                continue;
            }

            $this->{$field} = $data[$field];
        }

        return $this;
    }

    /**
     * Checks if the current record already exists or not
     *
     * @return bool
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    protected function _exists()
    {
        $primaryKeys = static::getPrimaryKey();
        if (count($primaryKeys) === 0) {
            return false;
        }

        $conditions = [];

        foreach ($primaryKeys as $field) {
            if (!isset($this->{$field})) {
                return false;
            }
            $conditions[$field] = $this->{$field};
        }

        if (is_array($this->_snapshot)) {
            $primaryKeyEqual = true;
            foreach ($primaryKeys as $field) {
                if (!isset($this->_snapshot[$field]) || $this->_snapshot[$field] !== $this->{$field}) {
                    $primaryKeyEqual = false;
                }
            }

            if ($primaryKeyEqual) {
                return true;
            }
        }

        return static::createCriteria()->where($conditions)->exists(false);
    }

    /**
     * Inserts or updates a model instance. Returning true on success or false otherwise.
     *
     *<code>
     *    //Creating a new robot
     *    $robot = new Robots();
     *    $robot->type = 'mechanical';
     *    $robot->name = 'Boy';
     *    $robot->year = 1952;
     *    $robot->save();
     *
     *    //Updating a robot name
     *    $robot = Robots::findFirst("id=100");
     *    $robot->name = "Biomass";
     *    $robot->save();
     *</code>
     *
     * @return void
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public function save()
    {
        if ($this->_exists()) {
            $this->update();
        } else {
            $this->create();
        }
    }

    /**
     * Inserts a model instance. If the instance already exists in the persistence it will throw an exception
     * Returning true on success or false otherwise.
     *
     *<code>
     *    //Creating a new robot
     *    $robot = new Robots();
     *    $robot->type = 'mechanical';
     *    $robot->name = 'Boy';
     *    $robot->year = 1952;
     *    $robot->create();
     *
     *  //Passing an array to create
     *  $robot = new Robots();
     *  $robot->create(array(
     *      'type' => 'mechanical',
     *      'name' => 'Boy',
     *      'year' => 1952
     *  ));
     *</code>
     *
     * @return void
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public function create()
    {
        $fieldValues = [];
        foreach (static::getFields() as $field) {
            if ($this->{$field} !== null) {
                $fieldValues[$field] = $this->{$field};
            }
        }

        if (count($fieldValues) === 0) {
            throw new ModelException('`:model` model is unable to insert without data'/**m020f0d8415e5f94d7*/, ['model' => get_class($this)]);
        }

        if (($db = static::getDb($this)) === false) {
            throw new ModelException('`:model` model db sharding for insert failed',
                ['model' => get_called_class(), 'context' => $this]);
        }

        if (($source = static::getSource($this)) === false) {
            throw new ModelException('`:model` model table sharding for insert failed',
                ['model' => get_called_class(), 'context' => $this]);
        }

        if ($this->_fireEventCancel('beforeSave') === false || $this->_fireEventCancel('beforeCreate') === false) {
            throw new ModelException('`:model` model cannot be created because it has been cancel.'/**m092e54c70ff7ecc1a*/, ['model' => get_class($this)]);
        }

        $connection = $this->_dependencyInjector->getShared($db);
        $connection->insert($source, $fieldValues);

        $autoIncrementAttribute = static::getAutoIncrementField();
        if ($autoIncrementAttribute !== null) {
            $this->{$autoIncrementAttribute} = $connection->lastInsertId();
        }

        $this->_snapshot = $this->toArray();

        $this->_fireEvent('afterCreate');
        $this->_fireEvent('afterSave');
    }

    /**
     * Updates a model instance. If the instance does n't exist in the persistence it will throw an exception
     * Returning true on success or false otherwise.
     *
     *<code>
     *    //Updating a robot name
     *    $robot = Robots::findFirst("id=100");
     *    $robot->name = "Biomass";
     *    $robot->update();
     *</code>
     *
     * @return void
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public function update()
    {
        $conditions = [];
        $primaryKey = static::getPrimaryKey();

        foreach ($primaryKey as $field) {
            if (!isset($this->{$field})) {
                throw new ModelException('`:model` model cannot be updated because some primary key value is not provided'/**m0efc1ffa8444dca8d*/,
                    ['model' => get_class($this)]);
            }

            $conditions[$field] = $this->{$field};
        }

        $fieldValues = [];
        foreach (static::getFields() as $field) {
            if (in_array($field, $primaryKey, true)) {
                continue;
            }

            if (isset($this->{$field})) {
                /** @noinspection NestedPositiveIfStatementsInspection */
                if (!isset($this->_snapshot[$field]) || $this->{$field} !== $this->_snapshot[$field]) {
                    $fieldValues[$field] = $this->{$field};
                }
            }
        }

        if (count($fieldValues) === 0) {
            return;
        }

        if ($this->_fireEventCancel('beforeSave') === false || $this->_fireEventCancel('beforeUpdate') === false) {
            throw new ModelException('`:model` model cannot be updated because it has been cancel.'/**m0634e5c85bbe0b638*/, ['model' => get_class($this)]);
        }

        static::updateAll($fieldValues, $conditions);

        $this->_snapshot = $this->toArray();

        $this->_fireEvent('afterUpdate');
        $this->_fireEvent('afterSave');
    }

    /**
     * @param int|string $id
     * @param array      $data
     * @param array      $whiteList
     *
     * @return int
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function updateById($id, $data, $whiteList = null)
    {
        if (!is_scalar($id)) {
            throw new ModelException('`:primaryKey` primaryKey must be a scalar value for delete.', ['primaryKey' => static::getPrimaryKey()[0]]);
        }

        $fieldValues = [];
        foreach (static::getFields() as $field) {
            if (!isset($data[$field])) {
                continue;
            }

            if ($whiteList !== null && !in_array($field, $whiteList, true)) {
                continue;
            }

            $fieldValues[$field] = $data[$field];
        }

        return static::updateAll($fieldValues, [static::getPrimaryKey()[0] => $id]);
    }

    /**
     * @param array $fieldValues
     * @param array $conditions
     *
     * @return int
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function updateAll($fieldValues, $conditions)
    {
        $wheres = [];
        $bind = [];
        foreach ($conditions as $field => $value) {
            preg_match('#^(\w+)\s*(.*)$#', $field, $matches);
            list(, $column, $op) = $matches;
            if ($op === '') {
                $op = '=';
            }
            $wheres[] = '[' . $column . ']' . $op . ':' . $column;
            $bind[$column] = $value;
        }

        if (($db = static::getDb($bind)) === false) {
            throw new ModelException('`:model` model db sharding for _exists failed updateAll',
                ['model' => get_called_class(), 'context' => $bind]);
        }

        if (($source = static::getSource($bind)) === false) {
            throw new ModelException('`:model` model table sharding for _exists failed updateAll',
                ['model' => get_called_class(), 'context' => $bind]);
        }

        return Di::getDefault()->getShared($db)->update($source, $fieldValues, implode(' AND ', $wheres), $bind);
    }

    /**
     * @param array $conditions
     *
     * @return int
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function deleteAll($conditions)
    {
        $wheres = [];
        $bind = [];
        foreach ($conditions as $field => $value) {
            preg_match('#^(\w+)\s*(.*)$#', $field, $matches);
            list(, $column, $op) = $matches;
            if ($op === '') {
                $op = '=';
            }
            $wheres[] = '[' . $column . ']' . $op . ':' . $column;
            $bind[$column] = $value;
        }

        if (($db = static::getDb($bind)) === false) {
            throw new ModelException('`:model` model db sharding for deleteAll failed',
                ['model' => get_called_class(), 'context' => $bind]);
        }

        if (($source = static::getSource($bind)) === false) {
            throw new ModelException('`:model` model db sharding for deleteAll failed',
                ['model' => get_called_class(), 'context' => $bind]);
        }

        return Di::getDefault()->getShared($db)->delete($source, implode(' AND ', $wheres), $bind);
    }

    /**
     * Deletes a model instance. Returning true on success or false otherwise.
     *
     * <code>
     *$robot = Robots::findFirst("id=100");
     *$robot->delete();
     *
     *foreach (Robots::find("type = 'mechanical'") as $robot) {
     *   $robot->delete();
     *}
     * </code>
     *
     * @return int
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public function delete()
    {
        $primaryKeys = static::getPrimaryKey();

        if (count($primaryKeys) === 0) {
            throw new ModelException('`:model` model must define a primary key in order to perform delete operation'/**m0d826d10544f3a078*/, ['model' => get_class($this)]);
        }

        if ($this->_fireEventCancel('beforeDelete') === false) {
            throw new ModelException('`:model` model cannot be deleted because it has been cancel.'/**m0d51bc276770c0f85*/, ['model' => get_class($this)]);
        }

        $conditions = [];
        foreach ($primaryKeys as $field) {
            if (!isset($this->{$field})) {
                throw new ModelException('`:model` model cannot be deleted because the primary key attribute: `:column` was not set'/**m01dec9cd3b69742a5*/,
                    ['model' => get_class($this), 'column' => $field]);
            }

            $conditions[$field] = $this->{$field};
        }

        $r = static::deleteAll($conditions);

        $this->_fireEvent('afterDelete');

        return $r;
    }

    /**
     * @param int|string $id
     *
     * @return int
     * @throws \ManaPHP\Mvc\Model\Exception
     */
    public static function deleteById($id)
    {
        if (!is_scalar($id)) {
            throw new ModelException('`:primaryKey` primaryKey must be a scalar value for delete.', ['primaryKey' => static::getPrimaryKey()[0]]);
        }

        return static::deleteAll([static::getPrimaryKey()[0] => $id]);
    }

    /**
     * Returns the instance as an array representation
     *
     *<code>
     * print_r($robot->toArray());
     *</code>
     *
     * @return array
     */
    public function toArray()
    {
        $data = [];

        foreach (self::getFields() as $field) {
            $data[$field] = isset($this->{$field}) ? $this->{$field} : null;
        }

        return $data;
    }

    /**
     * Returns the internal snapshot data
     *
     * @return array
     */
    public function getSnapshotData()
    {
        return $this->_snapshot;
    }

    /**
     * Returns a list of changed values
     *
     * @return array
     */
    public function getChangedFields()
    {
        $changed = [];

        foreach (self::getFields() as $field) {
            if (!isset($this->_snapshot[$field]) || $this->{$field} !== $this->_snapshot[$field]) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * Check if a specific attribute has changed
     * This only works if the model is keeping data snapshots
     *
     * @param string|array $fields
     *
     * @return bool
     */
    public function hasChanged($fields)
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }

        /** @noinspection ForeachSourceInspection */
        foreach ($fields as $field) {
            if (!isset($this->_snapshot[$field]) || $this->{$field} !== $this->_snapshot[$field]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}