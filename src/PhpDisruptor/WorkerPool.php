<?php

namespace PhpDisruptor;

use PhpDisruptor\EventProcessor\WorkProcessor;
use PhpDisruptor\ExceptionHandler\ExceptionHandlerInterface;
use PhpDisruptor\Pthreads\AtomicStackableTrait;
use PhpDisruptor\Pthreads\StackableArray;
use PhpDisruptor\Util\Util;
use Stackable;

final class WorkerPool extends Stackable implements EventClassCapableInterface
{
    use AtomicStackableTrait;

    /**
     * @var bool
     */
    public $started;

    /**
     * @var Sequence
     */
    public $workSequence;

    /**
     * @var RingBuffer
     */
    public $ringBuffer;

    /**
     * @var WorkProcessor[]
     */
    public $workProcessors;

    /**
     * @var string
     */
    public $eventClass;

    /**
     * Protected Constructor, use createFromRingBuffer or createFromEventFactory instead
     *
     * @param RingBuffer $ringBuffer
     * @param SequenceBarrierInterface $sequenceBarrier
     * @param ExceptionHandlerInterface $exceptionHandler
     * @param WorkHandlerInterface[] $workHandlers
     */
    protected function __construct(
        RingBuffer $ringBuffer,
        SequenceBarrierInterface $sequenceBarrier,
        ExceptionHandlerInterface $exceptionHandler,
        StackableArray $workHandlers
    ) {
        $this->started = false;
        $this->workSequence = new Sequence(SequencerInterface::INITIAL_CURSOR_VALUE);
        $this->ringBuffer = $ringBuffer;
        $this->eventClass = $ringBuffer->getEventClass();
        $this->workProcessors = new StackableArray();
        foreach ($workHandlers as $workHandler) {
            $this->workProcessors[] = new WorkProcessor(
                $ringBuffer,
                $sequenceBarrier,
                $workHandler,
                $exceptionHandler,
                $this->workSequence
            );
        }
    }

    /**
     * @return string
     */
    public function getEventClass()
    {
        return $this->eventClass;
    }

    /**
     * Create worker pool from ring buffer
     *
     * @param RingBuffer $ringBuffer
     * @param SequenceBarrierInterface $sequenceBarrier
     * @param ExceptionHandlerInterface $exceptionHandler
     * @param WorkHandlerInterface[] $workHandlers
     * @return WorkerPool
     */
    public static function createFromRingBuffer(
        RingBuffer $ringBuffer,
        SequenceBarrierInterface $sequenceBarrier,
        ExceptionHandlerInterface $exceptionHandler,
        StackableArray $workHandlers
    ) {
        return new self($ringBuffer, $sequenceBarrier, $exceptionHandler, $workHandlers);
    }

    /**
     * Constructor
     *
     * @param EventFactoryInterface $eventFactory
     * @param ExceptionHandlerInterface $exceptionHandler
     * @param WorkHandlerInterface[] $workHandlers
     * @return WorkerPool
     */
    public static function createFromEventFactory(
        EventFactoryInterface $eventFactory,
        ExceptionHandlerInterface $exceptionHandler,
        StackableArray $workHandlers
    ) {
        $ringBuffer = RingBuffer::createMultiProducer($eventFactory, 1024);
        $sequenceBarrier = $ringBuffer->newBarrier();

        $workerPool = new self($ringBuffer, $sequenceBarrier, $exceptionHandler, $workHandlers);

        $ringBuffer->addGatingSequences($workerPool->getWorkerSequences());

        return $workerPool;
    }

    /**
     * @return Sequence[]
     */
    public function getWorkerSequences()
    {
        $sequences = new StackableArray();
        foreach ($this->workProcessors as $workProcessor) {
            $sequences[] = $workProcessor->getSequence();
        }
        return $sequences;
    }

    /**
     * Start the worker pool processing events in sequence
     *
     * @param ExecutorInterface $executor
     * @return RingBuffer
     * @throws Exception\InvalidArgumentException
     */
    public function start(ExecutorInterface $executor) // @todo: refactor !!! we have no executor interface !!
    {
        if (!$this->casMember('started', false, true)) {
            throw new Exception\InvalidArgumentException(
                'WorkerPool has already been started and cannot be restarted until halted'
            );
        }

        $cursor = $this->ringBuffer->getCursor();
        $this->workSequence->set($cursor);

        foreach ($this->workProcessors as $workProcessor) {
            $workProcessor->getSequence()->set($cursor);
            $executor->execute($workProcessor);
        }

        return $this->ringBuffer;
    }

    /**
     * Wait for the RingBuffer to drain of published events then halt the workers
     *
     * @return void
     */
    public function drainAndHalt()
    {
        $workerSequences = $this->getWorkerSequences();

        while ($this->ringBuffer->getCursor() > Util::getMinimumSequence($workerSequences)) {
            time_nanosleep(0, 1); // todo: thread::yield()
        }

        $this->halt();
    }

    /**
     * Halt all workers immediately at the end of their current cycle
     *
     * @return void
     */
    public function halt()
    {
        foreach ($this->workProcessors as $workProcessor) {
            $workProcessor->halt();
        }
        $this->started = false;
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        return $this->started;
    }
}
