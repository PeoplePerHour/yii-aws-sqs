<?php
Yii::import('ext.yii-aws-sqs.*');

/**
 * AWSQueueManager
 */
class AWSQueueManager extends CApplicationComponent
{
    /**
     * @var string SQS access key (a.k.a. AWS_KEY)
     */
    public $accessKey;

    /**
     * @var string SQS secret key (a.k.a. AWS_SECRET_KEY)
     */
    public $secretKey;

    /**
     * @var string The AWS region where this SQS account lives
     */
    public $region;

    /**
     * @var AmazonSQS
     */
    private $_sqs;

    /**
     * @var CList queues list
     */
    private $_queues;

    /**
     * @var string Optional prefix to add to the queue name.
     */
    public $tablePrefix;

    /**
     * Initializes the application component.
     */
    public function init()
    {
        if($this->accessKey===null || $this->secretKey===null)
            throw new CException(__CLASS__.' $accessKey and $secretKey must be set');

        if ($this->region === null) {
            $this->region = 'us-east-1'; // set default, so don't need any config for normal case.
        }

        $this->_sqs = Aws\Sqs\SqsClient::factory(array(
            'key'    => $this->accessKey,
            'secret' => $this->secretKey,
            'region' => $this->region,
            'credentials.cache' => true  // Utilize the Doctrine Cache PHP library to cache credentials with APC. Avoids the cost of sending an HTTP request to the IMDS each time the SDK is utilized.
        ));

        parent::init();
    }

    /**
     * Returns a queue, property value, an event handler list or a behavior based on its name.
     * Do not call this method.
     */
    public function __get($name)
    {
        if($this->getQueues()->itemAt($name)!==null)
            return $this->queues->{$name};
        else
            return parent::__get($name);
    }

    /**
     * @return CList queues list
     */
    public function getQueues($refresh=false)
    {
        if($this->_queues===null || $refresh) {
            $this->_queues = new AWSQueueList();
            $this->_queues->caseSensitive = true;

            $result = $this->_sqs->listQueues();
            $list = $result->get('QueueUrls');
            if(!empty($list)) {
                foreach($list as $qUrl)
                {
                    $q = new AWSQueue($this, $qUrl);
                    $this->_queues->add($q->name,$q);
                }
                unset($list);
            }
        }
        return $this->_queues;
    }

    /**
     * @param string $url     url of the queue to send message
     * @param string $message message to send
     * @param array  $options extra options for the message
     * @return boolean message was succesfull
     */
    public function send($url, $message, $options=array())
    {
        $this->_sqs->sendMessage(array_merge(array(
            'QueueUrl'    => $url,
            'MessageBody' => $message,
        ),$options));

        return true; // If delete failed the above would throw an exception
    }

    /**
     * Send a batch of messages. AWS SQS limits the message batches
     * with a limit of 10 per request. If $messageArray has more than 10 messages
     * then 2 requests will be triggered.
     *
     * @param string $url          url of the queue to send message
     * @param string $messageArray message to send
     * @param array  $options      extra options for the message
     * @return boolean message was successful
     */
    public function sendBatch($url, $messageArray, $options=array())
    {
        $r=true;
        foreach(array_chunk($messageArray,10) as $batch)
        {
            $messages=array();
            foreach($batch as $i=>$message)
            {
                $messages[]=array(
                    'Id'          => $i,
                    'MessageBody' => (string)$message,
                );
            }
            $result = $this->_sqs->sendMessageBatch(array_merge(array(
                'QueueUrl' => $url,
                'Entries'  => $messages,
            ),$options));

            $fails = $result->get('Failed');

            $r=$r&&!$fails;
        }
        return $r;
    }

    /**
     * Receive messages from the queue
     * If there is no message returned then this function returns null.
     * In case of one message then a AWSMessage is returned for convenience, if more
     * then an array of AWSMessage objects is returned.
     *
     * @param string $url     url of the queue to send message
     * @param array  $options extra options for the message
     * @return mixed
     */
    public function receive($url, $options=array())
    {
        $msgs=array();

        $result = $this->_sqs->receiveMessage(array_merge(array(
            'QueueUrl' => $url,
        ),$options));

        $hasMsg = ($result['Messages'] !== null);

        if ($hasMsg) {
            foreach ($result['Messages'] as $message) {
                $m = new AWSMessage();
                $m->id            = (string)$message['MessageId'];
                $m->body          = (string)$message['Body'];
                $m->md5           = (string)$message['MD5OfBody'];
                $m->receiptHandle = (string)$message['ReceiptHandle'];
                if (isset($message['Attributes'])) {
                    foreach ($message['Attributes'] as $value) {
                        $name = lcfirst((string)$value['Name']);
                        $value = (string)$value['Value'];
                        if(in_array($name, $m->attributeNames())){
                            $m->$name = $value;
                        }
                    }
                }
                $msgs[]=$m;
            }
        }

        if(isset($options['MaxNumberOfMessages'])){
            return $msgs;
        } else {
            return empty($msgs) ? null : array_pop($msgs);
        }
    }

    /**
     * Delete a message from a queue
     *
     * @param string $url           url of the queue
     * @param mixed  $receiptHandle AWSMessage contain the receiptHandle or the receipthandle for the message
     * @return boolean if message was delete succesfull
     */
    public function delete($url, $handle, $options=array())
    {
        $this->_sqs->deleteMessage(array_merge(array(
            'QueueUrl'    => $url,
            'ReceiptHandle' => $handle,
        ),$options));

        return true; // If delete failed the above would throw an exception
    }

    /**
     * Deletes a batch of messages
     * @param type $url The url of the queue
     * @param array $handles An array of messages or handles to delete
     * @param type $options
     * @return boolean if the delete was sucessful or not
     */
    public function deleteBatch($url, $handles, $options=array())
    {
        $deleteRequest = array();
        foreach ($handles as $key => $handle) {
            $receiptHandle = ($handle instanceof AWSMessage) ? $handle->receiptHandle : $handle;
            $req = array('Id'=>$key,'ReceiptHandle'=>$receiptHandle);
            array_push($deleteRequest, $req);
        }

        $result = $this->_sqs->deleteMessageBatch(array_merge(array(
            'QueueUrl' => $url,
            'Entries'  => $deleteRequest,
        ),$options));

        $fails = $result->get('Failed');
        return empty($fails);
    }

    /**
     * Create a new queue
     *
     * @return mixed AWSQueue object if creation was succesfull, null else
     */
    public function createQueue($name)
    {
        $result = $this->_sqs->createQueue(array('QueueName' => $name));
        $queueUrl = $result->get('QueueUrl');

        $q=new AWSQueue($this, $queueUrl);
        $this->queues->add($q->name, $q);
        return $q;
    }

    /**
     * Delete a queue
     */
    public function deleteQueue($url)
    {
        $this->_sqs->deleteQueue(array('QueueUrl' => $url));
        return true; // If delete failed the above would throw an exception
    }
}
