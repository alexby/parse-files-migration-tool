<?php

namespace ParseServerMigration\Console\Command;

use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package ParseServerMigration\Console\Command
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class MainCommand extends Command
{
    /**
     * @var Command[]
     */
    private $commandList;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param Command[] $commandList
     * @param Logger $logger
     */
    public function __construct(array $commandList, Logger $logger)
    {
        $this->commandList = $commandList;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('parse:migration')
            ->setDescription('Parse server migration tool')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Parse server migration tool');
        $io->section('Delete command');
        $io->text('Delete files from an S3 bucker');
        $io->section('Export command');
        $io->text('Upload existing parse pictures to S3 bucket and rename file in mongoDB');
        $io->section('Migration command');
        $io->text('Dump an existing Parse server into a given mongoDB, upload files to S3 and fix files names in order to match Parse self hosted pattern');

        $choice = $io->choice('Select which command you want to execute', $this->getCommandsNames());

        $command = $this->getCommandByName($choice);

        $command->execute($input, $output);
    }

    /**
     * @return array
     */
    private function getCommandsNames()
    {
        $names = array();
        foreach ($this->commandList as $command) {
            $names[] = $command->getName();
        }

        return $names;
    }

    /**
     * @param $name
     * @return Command
     */
    private function getCommandByName($name)
    {
        foreach ($this->commandList as $command) {
            if ($name == $command->getName()) {
                return $command;
            }
        }

        throw new \InvalidArgumentException('No command found with name: ['.$name.']');
    }
}
