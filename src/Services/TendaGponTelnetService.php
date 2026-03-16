<?php
namespace iProtek\Device\Services;

class TendaGponTelnetService
{
    protected $host;
    protected $port;
    public $fp;

    public function __construct($host, $port = 23)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function connect($timeout=5)
    {
        $this->fp = fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        if (!$this->fp) {
            throw new \Exception("Cannot connect: $errstr ($errno)");
        }
        stream_set_blocking($this->fp, false);
    }

    
    public function login($username, $password, $wait = 0.2)
    {
        if (!$this->fp) {
            throw new \Exception("Not connected");
        }

        // send username
        fwrite($this->fp, $username . "\r\n");
        usleep($wait * 1000000);

        // send password
        fwrite($this->fp, $password . "\r\n");
        usleep($wait * 1000000);

        // optional: read any response
        $output = '';
        while ($line = fgets($this->fp, 128)) {
            $output .= $line;
            if (feof($this->fp)) break;
        }

        // simple check if login “succeeded”
        // many Tenda devices return a prompt like >
        if (strpos($output, '>') !== false || strpos($output, '#') !== false) {
            return true;
        }

        return false;
    }

    public function sendCommand($command, $wait = 0.2, $charCheck=null)
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

    
    public function sendCommandCheck($command, $charCheck=null, $wait = 0.2)
    {
        fwrite($this->fp, $command . "\r\n");
        usleep($wait * 1000000); // wait for response

        $output = '';
        while ($line = fgets($this->fp, 128)) {
            $output .= $line;
            if (feof($this->fp)) break;
        }
        if($charCheck){

            if (strpos($output, $charCheck) !== false ) {
                return true;
            }
            return false;
        }

        return $output;
    }

    public function disconnect()
    {
        fclose($this->fp);
    }
}