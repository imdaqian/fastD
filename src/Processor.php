<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2017
 *
 * @see      https://www.github.com/janhuang
 * @see      http://www.fast-d.cn/
 */

namespace FastD;

use FastD\Console\Process;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Class Processor
 * @package FastD
 */
class Processor extends Console
{
    public function registerCommands()
    {
        $command = new Process();

        $this->add($command);

        $path = app()->getPath().'/config/process.php';

        if (file_exists($path)) {
            config()->set('processes', include $path);
        }
    }

    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        $argv = $_SERVER['argv'];
        $script = array_shift($argv);
        array_unshift($argv, 'process');
        array_unshift($argv, $script);

        return parent::run(new ArgvInput($argv), $output);
    }
}