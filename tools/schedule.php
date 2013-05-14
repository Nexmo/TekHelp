<?php
date_default_timezone_set('America/Chicago');
require_once dirname(__FILE__) . '/../library/phpQuery.php';

//utility function
function killSpace($data){
    return trim(preg_replace('#\s+#mi', ' ', $data));
}


$doc = phpQuery::newDocumentFileHTML('http://tek.phparch.com/schedule/');

//init some vars
$title = null;
$date = new DateTime();
$schedule = array();
//loop through schedule rows
foreach(pq('table.day tbody tr') as $row){
    $day = pq($row)->parents('table')->children('caption')->text();
    $day = explode(':', $day);
    
    //get the time
    $time = pq($row)->children('th')->text();
    $time = explode('-', $time);
    
    //odd case
    if(trim($time[0]) == 'noon'){
        $time[0] = '12:00p';
    }
    
    $time = new DateTime($day[1] . ' ' . trim($time[0]) . 'm', new DateTimeZone('America/Chicago'));
    
    //single event type
    if(pq($row)->children('td')->length == 1){
        $schedule[$time->getTimestamp()] = array(
            'title' => killSpace(pq($row)->children('td')->text()),
            'time' => clone $time,
            'type' => 'common',
            'day' => $day[0]
        );
        
        continue;
    //multiple sessions
    } else {
        $talks = array();
        foreach(pq($row)->find('td .t_talk') as $talk){
            //TODO: could use this to grab talk description
            $id = $talk->getAttributeNode('data-talk')->nodeValue;
            $details = phpQuery::newDocumentFileHTML('http://tek.phparch.com/wp-admin/admin-ajax.php?action=talk&id=' . $id);
            $talks[] = array(
                'speaker' => killSpace(pq($talk)->find('.t_speaker')->text()),
                'title' => killSpace(pq($talk)->find('.t_title')->text()),
                'description' => killSpace($details->find('#p_talk p')->text())
            );
        }

        $schedule[$time->getTimestamp()] = array(
            'talks' => $talks,
            'time' => clone $time,
            'type' => 'talks',
            'day' => $day[0]
        );
    }
}

echo "<?php \nreturn ";
var_export($schedule);
echo ";";