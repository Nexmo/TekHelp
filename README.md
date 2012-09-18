# php|tek Schedule Autoresponder
Here’s how I built a simple [Nexmo](http://nexmo.com) powered application that queries the php|tek schedule. If you’re at tek and want to know more, just find me (mention [@tjlytle](https://twitter.com/#!/tjlytle) on twitter). And make sure you get your Nexmo badge as well.

### Examples 
*(because who wants to read a whole readme)*

* Send ‘now’ to get what’s going on currently at php|tek.
* Send ‘tomorrow 11am’, and get a list of talks.
* Send ‘1’, to get details on the first talk (send 1 again to continue the details).

### Getting The Data
Here’s the completely non-Nexmo related part of the project. Before we can query the schedule, we need some data. An actual database seems a bit overkill for this project, so the data is stored in a php array. 

Not wanting to build that array by hand, with the help of [phpquery](https://github.com/TobiaszCudnik/phpquery) (a jquery clone for php) I put together a simple script to parse the [schedule](https://github.com/TobiaszCudnik/phpquery) and [speaker](http://tek12.phparch.com/talks/) pages on the conference site. Check the [`/tools`](https://github.com/Nexmo/TekHelp/blob/master/tools/schedule.php) directory if you’re interested, or blissfully ignore it and note that the generated data is in the appropriately named [`data`](https://github.com/Nexmo/TekHelp/tree/master/data) directory.

### Being Helpful
When an SMS is sent to your Nexmo number, the relevant information is passed to the URL you specify (either account wide, or for the specific number). In this case, the request is routed to public/index.php’. The first few lines just handle some expected setup - timezone, includes, API account info, and check that the request looks to be from Nexmo.

Then things get a little interesting. For talks - which have a longer description, and are in sets of three - I wanted to accept a numeric reply to get more info about a specific talk. So we’ll just abuse php’s session a bit by setting the session key to a hash of the users number. Again, doing my best to avoid any need for a database.

    //hijack the session for our devious purposes make the incoming number the session
    session_id(md5($_REQUEST['msisdn']));
    session_start();

### Doing the Work
All the data assembled by the front script is passed to an object that encapsulates the logic. The object is constructed with the API credentials, along with session data and the schedule array. Then processing a request is done by simply passing the incoming SMS, and the number it was from (so a reply can be sent).

    $help = new Help(NEXMO_KEY, NEXMO_SECRET, NEXMO_FROM, $schedule, $_SESSION);
    $help->process($_REQUEST['msisdn'], $_REQUEST['text']);

That method checks the incoming message for the ‘help’ keyword, a talk number (if the last request was for a set of talks), or just a date to parse. Then the matching schedule, talk description, or help message is sent to the user.

And since I’m going for simple, the [API ‘wrapper’](https://github.com/Nexmo/TekHelp/blob/master/library/help.php#L174) consists of a call to `file_get_contents()` with a formatted URL.

    const API_URI = 'http://rest.nexmo.com/sms/json?username=%1$s&password=%2$s&from=%3$s&to=%4$s&text=%5$s';
    //...//
    $uri = sprintf(self::API_URI, $this->key, $this->secret, $this->from, $to, urlencode($text));
    $result = file_get_contents($uri);
    $result = json_decode($result);

