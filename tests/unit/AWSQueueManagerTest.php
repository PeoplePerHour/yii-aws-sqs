<?php
class AWSQueueManagerTest extends CTestCase
{
    private $_sqs;

    private function sqs()
    {
        if($this->_sqs===null) {
            $this->_sqs = new AWSQueueManager();
            $this->_sqs->accessKey = (defined('SQS_ACCESS_KEY')) ? defined('SQS_ACCESS_KEY') : null;
            $this->_sqs->secretKey = (defined('SQS_SECRET_KEY')) ? defined('SQS_SECRET_KEY') : null;

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
        $sqs=new AWSQueueManager();
        $sqs->accessKey = 'access';
        $sqs->secretKey = 'secret';

        $sqs->init();
        $this->assertTrue($sqs->isInitialized);
    }

    public function testList()
    {
        $this->sqs();
    }
}
