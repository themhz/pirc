<?php
require_once __DIR__ . '\vendor\autoload.php';


class IRC {
    public $socket;
    public $channel;
    public $server;
    public $port;
    public $nick;

    public function __construct($server, $port, $nick, $channel) {
        $this->server = $server;
        $this->port = $port;
        $this->nick = $nick;
        $this->channel = $channel;
    }

    public function connect() {        
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if(!$this->socket) {
            echo "Failed to create socket\n";
            return false;
        }

        if(!socket_connect($this->socket, $this->server, $this->port)) {
            echo "Failed to connect to server\n";
            return false;
        }

        $this->send_data("USER " . $this->nick . " * * :" . $this->nick . "\r\n");
        $this->send_data("NICK " . $this->nick . "\r\n");

        while($data = socket_read($this->socket, 1024)) {            
            echo "here 1";
            $this->handle_messages($data);
            if(strpos($data, "376")) {
                break;
            }
        }

        $this->send_data("JOIN " . $this->channel . "\r\n");

        return true;
    }
    
    public function disconnect() {
        if($this->socket) {
            socket_close($this->socket);
        }
    }

    public function send_data($data) {
        socket_write($this->socket, $data, strlen($data));
    }


    public function send_message($channel, $message) {
        $this->send_data("PRIVMSG $channel :$message\r\n");
    }



    public function handle_messages($data) {
        echo "Received message123: " . $data . PHP_EOL;

        if(strpos($data, "PING") === 0) {
            $pong_response = "PONG " . substr($data, 5) . "\r\n";
            $this->send_data($pong_response);
        } elseif (strpos($data, "PRIVMSG") !== false) {
            // Example message format: ":nick!user@host PRIVMSG #channel :message"
            $parts = explode(" ", $data);
            $sender = substr($parts[0], 1);  // remove leading colon
            $channel = $parts[2];
            $message = substr($data, strpos($data, ":", 1) + 1);
            $this->handle_privmsg($sender, $channel, $message);
        } elseif (strpos($data, "JOIN") !== false) {
            // Example message format: ":nick!user@host JOIN :#channel"
            $parts = explode(" ", $data);
            $sender = substr($parts[0], 1);  // remove leading colon
            $channel = $parts[2];
            $this->handle_join($sender, $channel);
        } elseif (strpos($data, "PART") !== false) {
            // Example message format: ":nick!user@host PART #channel :reason"
            $parts = explode(" ", $data);
            $sender = substr($parts[0], 1);  // remove leading colon
            $channel = $parts[2];
            $reason = substr($data, strpos($data, ":", 1) + 1);
            $this->handle_part($sender, $channel, $reason);
        }
        //flush();
    }

    public function handle_privmsg($sender, $channel, $message) {
        //echo "Received PRIVMSG from $sender in $channel: $message" . PHP_EOL;
        // Handle the message here
    }

    public function handle_join($sender, $channel) {
        echo "$sender joined $channel" . PHP_EOL;
        // Handle the join event here
    }

    public function handle_part($sender, $channel, $reason) {
        echo "$sender left $channel with reason: $reason" . PHP_EOL;
        // Handle the part event here
    }


    public function run() {


        while(true) {
            $read = array($this->socket);
            $write = null;
            $except = null;            
            if(socket_select($read, $write, $except, 0) > 0) {
                echo '2';
                foreach($read as $socket) {
                    echo '3';
                    if($socket === $this->socket) {
                        $data = socket_read($socket, 1024);
                        echo '4';
                        if($data) {
                            $this->handle_messages($data);
                        } else {
                            // Server closed the connection
                            $this->disconnect();
                            return;
                        }
                    }
                }
            }
        }
    }

    public function checkUserInput(){
        stream_set_blocking(STDIN, FALSE);
            $input = trim(fgets(STDIN));
            if (!empty($input)) {
                $this->send_message("#greece-cafe", $input);
            }
    }
}

use \parallel\{Runtime, Future, Channel, Events};

// Generate list of items to process with a generator
function generator(int $item_count) {
    for ($i=1; $i <= $item_count; $i++) {
        yield $i;
    }
}

function testConcurrency(int $concurrency, int $item_count) {

    $generator = generator($item_count);

    // Function executing in each thread. Have a snap for a random time for example !
    $producer = function (int $item_id) {
        $seconds = rand(1, 10);
        sleep($seconds);
        return ['item_id' => $item_id, 'sleep_seconds' => $seconds];
    };

    // Fill up threads with initial 'inactive' state
    $threads = array_fill(1, $concurrency, ['is_active' => false]);

    while (true) {
        // Loop through threads until all threads are finished
        foreach ($threads as $thread_id => $thread) {
            if (!$thread['is_active'] and $generator->valid()) {
                // Thread is inactive and generator still has values : run something in the thread
                $item_id = $generator->current();
                $threads[$thread_id]['run'] = \parallel\Runtime::run($producer, [$item_id]);
                echo "ThreadId: $thread_id => Item: $item_id (Start)\n";
                $threads[$thread_id]['is_active'] = true;
                $generator->next();
            } elseif (!isset($threads[$thread_id]['run'])) {
                // Destroy supplementary threads in case generator closes sooner than number of threads
                echo "Destroy ThreadId: $thread_id\n";
                unset($threads[$thread_id]);
            } elseif ($threads[$thread_id]['run']->done()) {
                // Thread finished. Get results
                $item = $threads[$thread_id]['run']->value();
                echo "ThreadId: $thread_id => Item: {$item['item_id']} Sleep: {$item['sleep_seconds']}s (End)\n";

                if (!$generator->valid()) {
                    // Generator is closed then destroy thread
                    echo "Destroy ThreadId: $thread_id\n";
                    unset($threads[$thread_id]);
                } else {
                    // Thread is ready to run again
                    $threads[$thread_id]['is_active'] = false;
                }
            }
        }

        // Escape loop when all threads are destroyed
        if (empty($threads)) break;
    }
}

$concurrency = 5;
$item_count = 50;

testConcurrency($concurrency, $item_count);

//$runtime = new parallel\Runtime();


// class WorkerThread extends Thread {
//     private $start;
//     private $end;
    
//     public function __construct($start, $end) {
//         $this->start = $start;
//         $this->end = $end;
//     }
    
//     public function run() {
//         for ($i = $this->start; $i <= $this->end; $i++) {
//             echo $i . "\n";
//             usleep(100000);  // delay for 100ms
//         }
//     }
// }

// $worker1 = new WorkerThread(1, 100);
// $worker2 = new WorkerThread(200, 300);

// $worker1->start();
// $worker2->start();

// $worker1->join();
// $worker2->join();

// $bot = new IRC('irc.freenode.net', 6667, 'themhz2023', '#greece-cafe');
// $bot->connect();


// class MyThread extends Thread
// {
//     private $bot;
//     private $type;    

//     public function __construct($bot, $type)
//     {
//         $this->bot = $bot;
//         $this->type = $type;
//         $this->input = '';
//     }

//     public function run()
//     {
//         if ($this->type == "run") {                     
//             $this->bot->run();
//         }        
//         else{
//             $this->bot->checkUserInput();
//         }
//     }


//     public function setInput($input)
//     {
//         $this->input = $input;
//     }
// }


//  $thread1 = new MyThread($bot, "run");
//  $thread2 = new MyThread($bot, "test");
 
//  $thread1->start();
//  $thread2->start();