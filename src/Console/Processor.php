<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2017
 *
 * @see      https://www.github.com/janhuang
 * @see      http://www.fast-d.cn/
 */

namespace FastD\Console;

use FastD\Swoole\Process;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class Process.
 */
class Processor extends Command
{
    /**
     * php bin/console process {name} {args} {options}
     */
    protected function configure()
    {
        $this->setName('process');
        $this->addArgument('process', InputArgument::OPTIONAL);
        $this->addOption('path', '-p', InputOption::VALUE_OPTIONAL, 'set process pid path.');
        $this->addOption('daemon', '-d', InputOption::VALUE_NONE, 'set process daemonize.');
        $this->addOption('list', '-l', InputOption::VALUE_NONE, 'show all processes.');
        $this->setDescription('Create new processor.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->hasParameterOption(['--list', '-l'])) {
            return $this->showProcesses($input, $output);
        }

        $process = $input->getArgument('process');
        $processes = config()->get('processes', []);

        if (!isset($processes[$process])) {
            throw new \RuntimeException(sprintf('Process %s cannot found', $process));
        }

        $processor = $processes[$process];
        if (!class_exists($processor)) {
            throw new \RuntimeException(sprintf('Class "%s" is not found.', $process));
        }

        $process = new $processor($process);
        if (!($process instanceof Process)) {
            throw new \RuntimeException('Process must be instance of \FastD\Swoole\Process');
        }
        if ($input->hasParameterOption(['--daemon', '-d'])) {
            $process->daemon();
        }

        $path = $this->targetDirectory($input);
        $file = $path . '/' . $process->getName() . '.pid';

        $pid = $process->start();
        file_put_contents($file, $pid);

        $output->writeln(sprintf('Process %s is started, pid: %s', $process->getName(), $pid));
        $output->writeln(sprintf('Pid file save is %s', $file));

        $process->wait(function ($ret) use ($output) {
            $output->writeln(sprintf('Pid %s exit.', $ret['pid']));
        });

        return $pid;
    }

    /**
     * @param InputInterface $input
     * @return string
     */
    protected function targetDirectory(InputInterface $input)
    {
        $path = $input->getParameterOption(['--path', '-p']);

        if (empty($path)) {
            $path = app()->getPath() . '/runtime/process';
        }

        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function showProcesses(InputInterface $input, OutputInterface $output)
    {
        $table = new Table($output);
        $path = $input->getParameterOption(['--path', '-p']);
        $table->setHeaders(array('Process', 'Pid', 'Status', 'Start At', 'Runtime'));
        $rows = [];
        foreach (config()->get('processes', []) as $name => $processor) {
            $pidFile = $path . '/' . $name . '.pid';
            $startAt = null;
            $pid = null;
            $isRunning = false;
            $runtime = null;
            if (file_exists($pidFile)) {
                $startAt = date('Y m d H:i:s', filemtime($pidFile));
                $pid = file_get_contents($pidFile);
                $isRunning = process_kill($pid, 0) ? true : false;
                if ($isRunning) {
                    $runtime = (time() - filemtime($pidFile)) . 's';
                }
            }
            $rows[] = [
                $name,
                $pid,
                ($isRunning ? 'running' : 'stopped'),
                $startAt,
                $runtime,
            ];
        }
        $table->setRows($rows);
        $table->render();
        return 0;
    }
}
