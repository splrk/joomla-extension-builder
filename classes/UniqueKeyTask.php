<?php

include_once "phing/Task.php";

class UniqueKeyTask extends Task
{
    private $property;
    private $length;
    
    public function setProperty($property)
    {
        $this->property = $property;
    }
    
    public function setLength($length)
    {
        $this->length = $length;
    }
    
    public function init()
    {
        $this->project = $this->getOwningTarget()->getProject();
    }
    
    public function main()
    {
        $key = '';
        for($i = 0; $i < $this->length; $i ++) {
            $key .= chr(mt_rand(48, 90));
        }
            
        $this->project->setUserProperty($this->property, $key);
    }	
}
