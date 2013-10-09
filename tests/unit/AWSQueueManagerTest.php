<?php
class AWSQueueManagerTest extends CTestCase
{
    private $_sqs;

    CONST QUEUENAME = 'phpunit_yiiawssqs_testqueue';

    private function sqs()
    {
        if($this->_sqs===null) {
            $this->_sqs = new AWSQueueManager();
            $this->_sqs->accessKey = (defined('SQS_ACCESS_KEY')) ? SQS_ACCESS_KEY : null;
            $this->_sqs->secretKey = (defined('SQS_SECRET_KEY')) ? SQS_SECRET_KEY : null;

            try {
                $this->_sqs->init();
            } catch(Exception $e) {
                $this->markTestSkipped('You have not defined SQS_ACCESS_KEY & SQS_SECRET_KEY');
            }
        }
        return $this->_sqs;
    }

    public function testExceptionalInit()
    {
        $sqs=new AWSQueueManager(array());

        $this->setExpectedException('CException');
        $sqs->init();
    }

    public function testInit()
    {
        $qm=new AWSQueueManager();
        $qm->accessKey = 'access';
        $qm->secretKey = 'secret';

        $qm->init();

        $this->assertEquals('secret', $qm->secretKey);

        $this->assertTrue($qm->getIsInitialized());
    }


    public function testCreateQueue()
    {
        if (!$this->isQueueExists(self::QUEUENAME,1)) {
            $this->sqs()->createQueue(self::QUEUENAME);
            $this->assertTrue($this->isQueueExists(self::QUEUENAME,30), 'Expected the queue to be created successfully. Name: '.self::QUEUENAME.'.');
            $this->assertEquals(self::QUEUENAME,$this->sqs()->{self::QUEUENAME}->name,'Queue is not names as expected.');
        }
    }

    /**
     * @depends testCreateQueue
     */
    public function testSend()
    {
        $testQueue = $this->sqs()->{self::QUEUENAME};
        $result = $this->sqs()->send($testQueue->url,'msg');
        $this->assertTrue($result);
    }

    /**
     * @depends testCreateQueue
     */
    public function testSendBatch()
    {
        $testQueue = $this->sqs()->{self::QUEUENAME};
        $result = $this->sqs()->sendBatch($testQueue->url,array('msg1','msg2','msg3'));
        $this->assertTrue($result);
    }

    /**
     * @depends testCreateQueue
     */
    public function testSendBatchMoreThanTen()
    {
        $testQueue = $this->sqs()->{self::QUEUENAME};

        $messages = array();
        for ($i=0;$i<20;$i++) {
            $messages[] = 'msg'.$i;
        }
        $result = $this->sqs()->sendBatch($testQueue->url,$messages);

        $this->assertTrue($result);
    }

    /**
     * @depends testSendBatch
     */
    public function testReceive()
    {
        $testQueue = $this->sqs()->{self::QUEUENAME};
        $message = $this->sqs()->receive($testQueue->url);
        $this->assertTrue(strpos($message->body,'msg') === 0,'Expected the recieived message to be \'msg\'. Actual: '.$message->body);
    }

    /**
     * @depends testSendBatch
     */
    public function testReceiveMultiple()
    {
        $testQueue = $this->sqs()->{self::QUEUENAME};
        $msgs = $this->sqs()->receive($testQueue->url,array('MaxNumberOfMessages'=>2));
        $this->assertEquals(2,count($msgs),'Expected to fetch 2 messages');
    }

    /**
     * @depends testCreateQueue
     */
    public function testDeleteQueue()
    {
        // It's not actually convinient to delete the queue automatically because:
        //  Amazon block it being created within 60s so it makes it hard to re-run the tests in quick succession.
        //  Sometimes you want to examine whats left in the queue after the test.
        //  Using the API, it's hard to judge when the queue is really deleted, there's caching involved on the API requests.
        $this->markTestSkipped('Not deleting Queue - Please delete it manually if you no longer need it.');

        /*
        if ($this->isQueueExists(self::QUEUENAME,1)) {
            $testQueue = $this->sqs()->{self::QUEUENAME};
            $this->sqs()->deleteQueue($testQueue->url);

            for ($i=0;$i<15;$i++) {
                $found = $this->isQueueExists(self::QUEUENAME,1);
                if (!$found) break;
                sleep(1);
            }

            $this->assertFalse($found, 'Expected the queue to be deleted successfully. Name: '.self::QUEUENAME.'.');
        } else {
            $this->markTestSkipped('Not deleting Queue - it doesn\'t exist');
        }
        */
    }

    /**
     * Utility function to check whether a queue exists and wait for a while if it doesn't yet exist.
     */
    private function isQueueExists($name,$timeout=10)
    {
        $found = false;

        for ($i=0;$i<$timeout;$i++) {
            // See if the test queue exists
            $queues = $this->sqs()->getQueues(true);
            foreach ($queues as $q) {
                $found = ($q->name == self::QUEUENAME) || $found;
            }
            if ($found) break;
            sleep(1);
        }

        return $found;
    }
}
