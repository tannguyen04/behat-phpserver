<?php

/**
 * This file is part of the IntegratedExperts Behat tests.
 *
 * @file Context PhpServer for Behat.
 */

namespace IntegratedExperts\BehatPhpServer;

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\MinkContext;
use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Class PhpServerTrait.
 */
class PhpServerContext extends MinkContext implements Context
{

    /**
     * Docroot directory.
     *
     * @var string
     */
    protected $docroot;

    /**
     * Server hostname.
     *
     * @var string
     */
    protected $host;

    /**
     * Server port.
     *
     * @var string
     */
    protected $port;

    /**
     * Server process id.
     *
     * @var string
     */
    protected $pid;


    /**
     * PhpServerTrait constructor.
     *
     * @param array $parameters Settings for server.
     */
    public function __construct($parameters = [])
    {
        $this->docroot = isset($parameters['docroot']) ? $parameters['docroot'] : __DIR__.'/fixtures';
        $this->host    = isset($parameters['host']) ? $parameters['host'] : 'localhost';
        $this->port    = isset($parameters['port']) ? $parameters['port'] : '8888';

    }//end __construct()


    /**
     * Start server before each scenario.
     *
     * @param BeforeScenarioScope $scope Scenario scope.
     *
     * @beforeScenario @phpserver
     */
    public function beforeScenarioStartPhpServer(BeforeScenarioScope $scope)
    {
        if ($scope->getScenario()->hasTag('phpserver')) {
            $this->phpServerStart();
        }

    }//end beforeScenarioStartPhpServer()


    /**
     * Stop server after each scenario.
     *
     * @param AfterScenarioScope $scope Scenario scope.
     *
     * @afterScenario @phpserver
     */
    public function afterScenarioStopPhpServer(AfterScenarioScope $scope)
    {
        if ($scope->getScenario()->hasTag('phpserver')) {
            $this->phpServerStop();
        }

    }//end afterScenarioStopPhpServer()


    /**
     * Start a server.
     *
     * @throws RuntimeException
     *   If unable to start a server.
     *
     * @return int
     *   PID as number.
     */
    protected function phpServerStart()
    {
        // If the server already running on this port, stop it.
        // This is a much simpler way of handling previously started servers than
        // having a server manager that would track each instance.
        if ($this->phpServerIsRunning(false)) {
            $pid = $this->phpServerGetPid($this->port);
            $this->phpServerTerminateProcess($pid);
        }

        $command = sprintf(
            'php -S %s:%d -t %s >/dev/null 2>&1 & echo $!',
            $this->host,
            $this->port,
            $this->docroot
        );

        $output = [];
        $code   = 0;
        exec($command, $output, $code);
        if ($code === 0) {
            $this->pid = $output[0];
        }

        if (!$this->pid || !$this->phpServerIsRunning()) {
            throw new \RuntimeException('Unable to start PHP server');
        }

        return $this->pid;

    }//end phpServerStart()


    /**
     * Stop running server.
     *
     * @return bool
     *   TRUE if server process was stopped, FALSE otherwise.
     */
    public function phpServerStop()
    {
        if (!$this->phpServerIsRunning(false)) {
            return true;
        }

        return $this->phpServerTerminateProcess($this->pid);

    }//end phpServerStop()


    /**
     * Check that a server is running.
     *
     * @param int $timeout Retry timeout in seconds.
     * @param int $delay   Delay between retries in microseconds.
     *      Default to 0.5 of the second.
     *
     * @return bool
     *   TRUE if the server is running, FALSE otherwise.
     */
    public function phpServerIsRunning($timeout = 1, $delay = 500000)
    {
        if ($timeout === false) {
            return $this->phpServerCanConnect();
        }

        $start = microtime(true);

        while ((microtime(true) - $start) <= $timeout) {
            if ($this->phpServerCanConnect()) {
                return true;
            }

            usleep($delay);
        }

        return false;

    }//end phpServerIsRunning()


    /**
     * Check if it is possible to connect to a server.
     *
     * @return bool
     *   TRUE if server is running and it is possible to connect to it via socket,
     *   FALSE otherwise.
     */
    protected function phpServerCanConnect()
    {
        set_error_handler(
            function () {
                return true;
            }
        );

        $sp = fsockopen($this->host, $this->port);

        restore_error_handler();

        if ($sp === false) {
            return false;
        }

        fclose($sp);

        return true;

    }//end phpServerCanConnect()


    /**
     * Terminate a process.
     *
     * @param int $pid Process id.
     *
     * @return int
     *   TRUE if the process was successfully terminated, FALSE otherwise.
     */
    protected function phpServerTerminateProcess($pid)
    {
        // If pid was not provided, do not allow to terminate current process.
        if (!$pid) {
            return 1;
        }

        $output = [];
        $code   = 0;
        exec('kill '.(int) $pid, $output, $code);

        return $code === 0;

    }//end phpServerTerminateProcess()


    /**
     * Get PID of the running server on the specified port.
     *
     * Note that this will retrieve a PID of the process that could have been
     * started by another process rather then current one.
     *
     * @param string $port Port number.
     *
     * @return int
     *   PID as number.
     */
    protected function phpServerGetPid($port)
    {
        $pid = 0;

        $output = [];
        // @todo: Add support to OSes other then OSX and Ubuntu.
        exec("netstat -peanut 2>/dev/null|grep ':$port'", $output);

        if (!isset($output[0])) {
            throw new RuntimeException(
                'Unable to determine if PHP server was started on current OS.'
            );
        }

        $parts = explode(' ', preg_replace('/\s+/', ' ', $output[0]));

        if (isset($parts[8]) && $parts[8] != '-') {
            list($pid, $name) = explode('/', $parts[8]);
            if ($name != 'php') {
                $pid = 0;
            }
        }

        return (int) $pid;

    }//end phpServerGetPid()


}//end class
