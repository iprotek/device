<?php
namespace App\Services;

class TendaTelnetService
{
    protected $host;
    protected $port;
    protected $fp;

    public function __construct($host, $port = 23)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function connect()
    {
        $this->fp = fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if (!$this->fp) {
            throw new \Exception("Cannot connect: $errstr ($errno)");
        }
        stream_set_blocking($this->fp, true);
    }

    public function sendCommand($command, $wait = 0.2)
    {
        fwrite($this->fp, $command . "\r\n");
        usleep($wait * 1000000); // wait for response

        $output = '';
        while ($line = fgets($this->fp, 128)) {
            $output .= $line;
            if (feof($this->fp)) break;
        }
        return $output;
    }

    public function disconnect()
    {
        fclose($this->fp);
    }
}