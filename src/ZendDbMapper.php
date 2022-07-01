<?php
/**
 * User: Nick Rezun
 * Date: 15.10.2017
 * Time: 19:25
 */

namespace Ctrlweb\Expressive\ZendDbWrapper;

use Arosa\Core\Entity\Product;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Exception\RuntimeException;
use Zend\Db\ResultSet\AbstractResultSet;
use Zend\Db\ResultSet\ResultSetInterface;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;
use \Zend\Db\Adapter\Driver\ResultInterface;
use \Zend\Db\ResultSet\HydratingResultSet;
use \Zend\Hydrator\ClassMethods;

class ZendDbMapper
{
    /**
     * @var Adapter
     */
    protected $dbAdapter;

    /**
     * @var Sql
     */
    protected $sql;

    /**
     * @var string Table name
     */
    protected $table;

    /**
     * @var Entity
     */
    protected $objectPrototype = null;


    public function __construct(string $table, Adapter $dbAdapter, string $objectPrototype)
    {
        $this->dbAdapter       = $dbAdapter;
        $this->table           = $table;
        $this->objectPrototype = $objectPrototype;
        $this->sql             = new Sql($dbAdapter, $this->table);
    }

    /**
     * Base method - get element by primary id
     * @param int $id ID
     * @return Entity
     */
    public function getById($id, $primaryName = 'id')
    {
        $select = $this->sql->select()
            ->where([$primaryName => $id]);
        $sql    = $this->sql->buildSqlString($select);
        $res    = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);

        $entity = $this->returnResult($res)->current();
        return $entity;
    }

    /**
     * Get elements using the where statement
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $where
     * @param string|array $order
     * @param int $limit
     * @param int $offset
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getBy($where, $order = false, $limit = false, $offset = false, $join = null)
    {
        $select = $this->sql->select()
            ->where($where);

        if ($order) {
            $select->order($order);
        }

        if ($limit) {
            $select->limit($limit);
        }

        if ($offset) {
            $select->offset($offset);
        }


        $sql = $this->sql->buildSqlString($select);
        $res = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        return $this->returnResult($res);
    }

    public function getArrayBy($where, $order = false, $limit = false, $offset = false, $join = null): array
    {
        $rows = $this->getBy($where, $order, $limit, $offset, $join);
        if ($rows->count()) {
            $result_array = [];
            foreach ($rows as $row) {
                $result_array[] = $row;
            }
            return $result_array;
        } else {
            return [];
        }
    }

    public function getCount($where)
    {
        $select = $this->sql->select()
            ->where($where)->columns(['count' => new Expression("count(*)")], false);

        $sql    = $this->sql->buildSqlString($select);
        $result = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE, null);

        if ($result instanceof AbstractResultSet) {
            $result = $result->getDataSource();
        }

        $count = $result->current();
        if (isset($count['count'])) {
            return (int)$count['count'];
        } else {
            return 0;
        }
    }

    /**
     * Save row to the table
     * @param $row
     * @return bool|Entity
     */
    public function save(Entity $row)
    {
        $fields = $row->getAllProps();

        // build query
        $fieldsToSave = [];
        foreach ($fields as $fieldName => $value) {
            if (is_array($value) or is_object($value)) {
                $value = serialize($value);
            }
            if ($fieldName === $row->primaryName()) {
                continue;
            }
            $fieldsToSave[$fieldName] = $value;
        }

        if (!$id = $row->getId()) {
            // insert
            $insert = $this->sql->insert();
            $insert->values($fieldsToSave, $insert::VALUES_MERGE);
            $statement = $this->sql->prepareStatementForSqlObject($insert);
            $result    = $statement->execute();
            if ($result instanceof ResultInterface) {
                $row->setId($result->getGeneratedValue());
                return $row;
            }
            return false;
        } else {
            // update
            $update    = $this->sql->update()
                ->set($fieldsToSave)
                ->where([$row->primaryName() => $row->getId()]);
            $updateSql = $this->sql->buildSqlString($update);

            $result = $this->dbAdapter->query($updateSql, Adapter::QUERY_MODE_EXECUTE);
            if ($result instanceof ResultInterface) {
                return $row;
            }
            return false;
        }
    }

    /**
     * Delete row from the table
     * @param $row
     * @return bool
     */
    public function delete(Entity $row)
    {
        if (!$id = $row->getId()) {
            throw new RuntimeException("Can not delete - missing primary id");
        }

        $result = $this->dbAdapter->query(
            "DELETE FROM `" . $this->table . "` WHERE " . $row->primaryName() . " = '" . $id . "' LIMIT 1",
            Adapter::QUERY_MODE_EXECUTE
        );

        if ($result instanceof ResultInterface && $result->getAffectedRows()) {
            return true;
        }

        return false;
    }

    /**
     * Update rows
     * @param $where
     * @param $what
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function update($where, $what)
    {
        $delete = $this->sql
            ->update()
            ->where($where)
            ->set($what);

        $sql = $this->sql->buildSqlString($delete);
        $res = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        return $this->returnResult($res);
    }

    /**
     * Delete rows
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $where
     */
    public function deleteBy($where, $order = false, $limit = false, $offset = false, $join = null)
    {
        $delete = $this->sql
            ->delete()
            ->where($where);

        if ($order) {
            $delete->order($order);
        }

        if ($limit) {
            $delete->limit($limit);
        }

        if ($offset) {
            $delete->offset($offset);
        }

        $sql = $this->sql->buildSqlString($delete);
        $res = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
        return $this->returnResult($res);
    }

    /**
     * Make a hydration here (build UserEntity object from result array)
     * @param $result
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function returnResult($result)
    {
        if (!class_exists($this->objectPrototype)) {
            throw new RuntimeException($this->objectPrototype . " entity class not found");
        }

        if ($result instanceof AbstractResultSet) {
            $result = $result->getDataSource();
        }

        if ($result instanceof ResultInterface && $result->isQueryResult()) {
            $resultSet = new HydratingResultSet(new ClassMethods, new $this->objectPrototype);
            return $resultSet->initialize($result);
        }

        return $result;
    }


    /**
     * Get product by his GUID
     * @param $guid
     * @return bool|Entity
     */
    public function getByGuid(string $guid)
    {
        $result = $this->getBy(['guid' => $guid]);
        if ($entity = $result->current()) {
            return $entity;
        }
        return false;
    }
}
