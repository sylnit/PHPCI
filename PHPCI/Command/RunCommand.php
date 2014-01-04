<?php
/**
* PHPCI - Continuous Integration for PHP
*
* @copyright    Copyright 2013, Block 8 Limited.
* @license      https://github.com/Block8/PHPCI/blob/master/LICENSE.md
* @link         http://www.phptesting.org/
*/

namespace PHPCI\Command;

use Monolog\Logger;
use PHPCI\Helper\BuildDBLogHandler;
use PHPCI\Helper\LoggedBuildContextTidier;
use PHPCI\Helper\OutputLogHandler;
use Psr\Log\LoggerAwareInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use b8\Store\Factory;
use PHPCI\Builder;
use PHPCI\BuildFactory;

/**
* Run console command - Runs any pending builds.
* @author       Dan Cryer <dan@block8.co.uk>
* @package      PHPCI
* @subpackage   Console
*/
class RunCommand extends Command
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param \Monolog\Logger $logger
     * @param string $name
     */
    public function __construct(Logger $logger, $name = null)
    {
        parent::__construct($name);
        $this->logger = $logger;
    }


    protected function configure()
    {
        $this
            ->setName('phpci:run-builds')
            ->setDescription('Run all pending PHPCI builds.');
    }

    /**
     * Pulls all pending builds from the database and runs them.
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        // For verbose mode we want to output all informational and above
        // messages to the symphony output interface.
        if ($input->hasOption('verbose')) {
            $this->logger->pushHandler(
                new OutputLogHandler($this->output, Logger::INFO)
            );
        }

        $this->logger->pushProcessor(new LoggedBuildContextTidier());

        $this->logger->addInfo("Finding builds to process");
        $store = Factory::getStore('Build');
        $result = $store->getByStatus(0);
        $this->logger->addInfo(sprintf("Found %d builds", count($result['items'])));

        $builds = 0;

        foreach ($result['items'] as $build) {
            $builds++;

            $build = BuildFactory::getBuild($build);

            // Logging relevant to this build should be stored
            // against the build itself.
            $buildDbLog = new BuildDBLogHandler($build, Logger::INFO);
            $this->logger->pushHandler($buildDbLog);

            $builder = new Builder($build, $this->logger);
            $builder->execute();

            // After execution we no longer want to record the information
            // back to this specific build so the handler should be removed.
            $this->logger->popHandler($buildDbLog);
        }

        $this->logger->addInfo("Finished processing builds");

        return $builds;
    }
}
