<?php
class Help
{
    protected $key;
    protected $secret;
    protected $from;
    protected $schedule;
    protected $session;
    
    const API_URI = 'http://rest.nexmo.com/sms/json?username=%1$s&password=%2$s&from=%3$s&to=%4$s&text=%5$s';
    const HELP_TEXT = "SMS a parsable date (4pm, tommorow 10am) to see what's on the schedule at tek12. For details on talks reply with the number to get/continue details. - nexmo.com";
    
    //setup a help object
    public function __construct($key, $secret, $from, $schedule, $session)
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->from = $from;
        $this->schedule = $schedule;    
        $this->session = $session;    
    }
    
    //get session array
    public function getSession()
    {
        return $this->session;
    }
    
    //process a message (text) from a number
    public function process($from, $text)
    {
        if(strtolower($text) == 'help'){
            $this->sendSms($from, self::HELP_TEXT);
            return;
        }
        
        //check if there's an event in session
        if(!empty($this->session['event'])){
            //could be a detail request, let's try
            try{
                $this->sendDetails($text, $from);
                return;
            } catch (Exception $e) {
                //guess not
            }
        }
        
        //send date search results
        $event = $this->getEvent($text);
        $this->storeEvent($event);        
        $this->sendEvent($event, $from);
    }
    
    //store an event (with talks) so details can be requested, or clear storage
    //if there's no reason to store
    public function storeEvent($event)
    {
        $this->session['event'] = null;
        
        if('talks' == $event['type']){
            foreach($event['talks'] as $index => $talk){
                $event['talks'][$index]['parts'] = array();
                foreach(str_split($talk['description'], 160) as $sms){
                    $event['talks'][$index]['parts'][] = $sms;
                }
            }
            
            $this->session['event'] = $event;
        }        
    }
    
    public function getEvent($date)
    {
        //try to parse a data
        try{
            $date = new DateTime($date);
        } catch(Exception $e){
            //default to now
            $date = new DateTime();   
        }

        //find that portion of the schedule
        $match = null;
        foreach($this->schedule as $event){
            //check if the event is after the search date
            if($event['time'] > $date){
                //then the current event is the one directly before
                break;
            }
            //track the previous event
            $match = $event;
        }
        
        //if no event was found, then the search was before the first event
        if(is_null($match)){
            $match = $event;
        }

        return $match;
    }   

    public function formatEvent($event)
    {
        //format a response SMS
        switch ($event['type']) {
            case 'common':
                $sms = "[{$event['day']}] {$event['title']}: " . $event['time']->format('g:i a');
                break;
            case 'talks':
                $sms = "[{$event['day']}]\n";
                foreach ($event['talks'] as $index => $talk){
                    $sms .=  ($index + 1) . ": {$talk['speaker']}: {$talk['title']} \n";
                }
                break;                
            case 'keynote':
                $sms = "[{$event['day']}] {$event['keynote']}: " . $event['time']->format('g:i a');
                break;
        }
        
        return $sms;
    }
    
    public function sendEvent($event, $to)
    {
        $sms = $this->formatEvent($event);
        $this->sendSms($to, $sms);
    }
    
    public function sendDetails($index, $to)
    {
        if(!is_numeric($index)){
            throw new Exception('index not numeric');
        }
        
        //convert human index to something more computer friendly
        $index--;
        
        if(!isset($this->session['event'])){
            throw new Exception('details require event to be in session');
        }
        
        if(!isset($this->session['event']['talks'])){
            throw new Exception('event has no talks');
        }
        
        if(!isset($this->session['event']['talks'][$index])){
            throw new Exception('invalid talk index');
        }
        
        //feels better right?
        $talk = $this->session['event']['talks'][$index];
        
        //find if this is a continuation
        if(!isset($talk['last'])){
            //'cause we're gonna increment this
            $talk['last'] = -1;
        }
        
        //send the next part
        $talk['last']++;
        
        //make sure the part if valid
        if(!isset($talk['parts'][$talk['last']])){
            //start over
            $talk['last'] = 0;
        }
        
        $this->sendSms($to, $talk['parts'][$talk['last']]);
        
        //put the talk back in storage
        $this->session['event']['talks'][$index] = $talk;
    }
    
    protected function sendSms($to, $text)
    {
        $uri = sprintf(self::API_URI, $this->key, $this->secret, $this->from, $to, urlencode($text));
        $result = file_get_contents($uri);
        $result = json_decode($result);
        foreach($result->messages as $message){
            if(isset($message->{'error-text'})){
                throw new Exception($message->{'error-text'}, 500);
            }
        }
    }
    
    
}