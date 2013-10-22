<?php

namespace PhpDisruptor\Lists;

use PhpDisruptor\Exception;
use PhpDisruptor\Pthreads\StackableArray;
use PhpDisruptor\Sequence;
use Traversable;

class SequenceList extends StackableArray
{
    /**
     * @var array
     */
    public $list;

    /**
     * Constructor
     *
     * @param Sequence|array|Traversable $entities
     * @throws Exception\InvalidArgumentException
     */
    public function __construct($entities)
    {
        if ($entities instanceof Sequence) {
            $this->add($entities);
        } else if (is_array($entities) || $entities instanceof Traversable) {
            foreach ($entities as $entity) {
                $this->add($entity);
            }
        } else {
            throw new Exception\InvalidArgumentException(sprintf(
                'Parameter provided to %s must be an %s, %s or %s',
                __METHOD__, 'array', 'Traversable', 'PhpDisruptor\Sequence'
            ));
        }
    }

    /**
     * @param Sequence $entity
     */
    public function add(Sequence $entity)
    {
        $this->list[] = $entity;
    }
}
