<?php

namespace PhpDisruptorTest\TestAsset;

use PhpDisruptor\EventHandlerInterface;
use PhpDisruptor\Exception;
use PhpDisruptor\LifecycleAwareInterface;
use Stackable;

class EventHandler extends Stackable implements EventHandlerInterface, LifecycleAwareInterface
{
    public $eventClass;

    public $output;

    public function __construct($eventClass, $output = null)
    {
        $this->eventClass = $eventClass;
        if (null !== $output) {
            $this->output = $output;
        }
    }

    public function run()
    {
    }

    /**
     * Return the used event class name
     *
     * @return string
     */
    public function getEventClass()
    {
        return $this->eventClass;
    }

    /**
     * Called when a publisher has published an event to the RingBuffer
     *
     * @param object $event published to the RingBuffer
     * @param int $sequence of the event being processed
     * @param bool $endOfBatch flag to indicate if this is the last event in a batch from the RingBuffer
     * @return void
     * @throws Exception\ExceptionInterface if the EventHandler would like the exception handled further up the chain.
     */
    public function onEvent($event, $sequence, $endOfBatch)
    {
        if (null !== $this->output) {
            echo $this->output;
        } else {
            echo get_class($event) . '-' . $sequence . '-' . (string) (int) $endOfBatch;
        }
    }

    /**
     * Called once on thread start before first event is available.
     *
     * @return void
     */
    public function onStart()
    {
        echo $this->output;
    }

    /**
     * Called once just before the thread is shutdown.
     *
     * Sequence event processing will already have stopped before this method is called. No events will
     * be processed after this message.
     *
     * @return void
     */
    public function onShutdown()
    {
        echo $this->output;
    }
}
