<?php
date_default_timezone_set('America/Chicago');
require_once dirname(__FILE__) . '/../library/phpQuery.php';

//utility function
function getTime($row, $date){
    $time = phpQuery::pq('td:first', $row)->html();
    //filter any time ranges
    if(($pos = strpos($time, '–')) !== false){
        $time = substr($time, 0, $pos);
    }
    
    //get the time parts
    list($hour, $minute) = explode(':', $time);
    $date->setTime((int)$hour, (int)$minute);
}


$docSchedule = phpQuery::newDocumentFileHTML('http://tek12.phparch.com/schedule/');
$docTalks = phpQuery::newDocumentFileHTML('http://tek12.phparch.com/talks/');

//init some vars
$title = null;
$date = new DateTime();
$schedule = array();
//loop through schedule rows
/* @var $row DOMElement */
foreach(phpQuery::pq('#schedule tr', $docSchedule) as $row){
    switch(strtolower($row->getAttribute('class'))){
        case 'mainheader':
            list($title, $date) = explode('–', phpQuery::pq('td', $row)->html());
            $title = trim($title);
            $date = new DateTime($date);
            break;
        case 'title':
        case 'break':
        case 'lunch':
            getTime($row, $date);
            $schedule[$date->format('U')] = array(
                'title' => phpQuery::pq('td:last', $row)->html(),
                'day' => $title,
                'time' => clone $date,
                'type' => 'common'
            );
            break;
        case 'talks':
            getTime($row, $date);
            $talks = array();
            foreach(phpQuery::pq('td.talk p', $row) as $talk){
                $link = phpQuery::pq('a.talk', $talk)->attr('href');
                $link = substr($link, strpos($link, '#') + 1);
                $description = phpQuery::pq("[name={$link}]")->parents('div:first')->find('div.talk')->text();

                $talks[] = array(
                	'speaker' => phpQuery::pq('span.speaker', $talk)->text(),
                    'title' => phpQuery::pq('a.talk', $talk)->text(),
                    'description' => $description
                );
            }

            if(!empty($talks)){
                $schedule[$date->format('U')] = array(
                    'talks' => $talks,
                    'day' => $title,
                    'time' => clone $date,
                    'type' => 'talks'
                );
            } else {
                $schedule[$date->format('U')] = array(
                    'keynote' => phpQuery::pq('td.keynote', $row)->text(),
                    'day' => $title,
                    'time' => clone $date,
                    'type' => 'keynote'
                );
            }
            break;
    }
}

echo "<?php \nreturn ";
var_export($schedule);
echo ";";