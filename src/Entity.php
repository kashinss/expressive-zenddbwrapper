<?php
/**
 * User: Nick
 * Date: 17.10.2017
 * Time: 17:17
 */

namespace Ctrlweb\Expressive\ZendDbWrapper;


abstract class Entity
{
    /**
     * Entity constructor. Has option to load object with values
     * @param array $data
     */
    public function __construct($data=[])
    {
        foreach ($data as $prop => $value) {
            $methodName = 'set'.ucfirst($prop);
            if (method_exists($this, $methodName)){
                $this->$methodName($value);
            }
        }
    }

    /**
     * Primary key name
     * @return string
     */
    public function primaryName()
    {
        return 'id';
    }

    /**
     * Return array of all class properties - aka table row fields
     * @return array
     */
    abstract  public function getAllProps() : array;

    /**
     * Return primary key value
     */
    abstract public function getId();
}
