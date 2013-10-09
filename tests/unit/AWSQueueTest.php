<?php
class AWSQueueTest extends CTestCase
{
    /**
     * Test validation rules for creating a queue
     */
    public function testNameValidation()
    {
        $qm = new AWSQueueManager();

        $q = new AWSQueue($qm);

        // Allow us to set a private variable for this test
        $class = new ReflectionClass($q);
        $property = $class->getProperty('_name');
        $property->setAccessible(true);

        $this->markTestSkipped('Skipped because we didn\'t implement queue name validation yet');

        $invalidCharacterNames = array(
            'Testing queue', //KO
            'Testing*^quee', //KO
        );

        foreach($invalidCharacterNames as $name)
        {
            $property->setValue($q, $name); // equivalent to $q->name=$name;
            $this->assertFalse($q->validate());
        }

        $validCharacterNames = array(
            'Testing123456', //OK
            'Testing_13-45', //OK
        );

        foreach($validCharacterNames as $name)
        {
            $property->setValue($q, $name); // equivalent to $q->name=$name;
            $this->assertTrue($q->validate());
        }

        $q->name = sha1('just to get').sha1('80 characters string');
        $this->assertTrue($q->validate());

        $q->name .= 'oops';
        $this->assertFalse($q->validate());
    }

    /**
     * Test rest of the attributes
     */
    public function testAttributesValidation()
    {
        $qm = new AWSQueueManager();
        $q = new AWSQueue($qm);

        // Allow us to set a private variable for this test
        $class = new ReflectionClass($q);
        $property = $class->getProperty('_name');
        $property->setAccessible(true);
        $property->setValue($q, 'validname'); // equivalent to $q->name='validname';

        $this->markTestSkipped('Skipped because we didn\'t implement name validation or queue attributes yet');

        /*
        $q->visibilityTimeout = 43201;
        $this->assertFalse($q->validate());

        $q->visibilityTimeout = 0;
        $this->assertTrue($q->validate());

        $q->visibilityTimeout = 43200;
        $this->assertTrue($q->validate());

        $q->maximumMessageSize = 70000;
        $this->assertFalse($q->validate());
        */
    }
}
