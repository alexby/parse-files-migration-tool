<?php

namespace ParseServerMigration\Console\Command;

use Aws\S3\Exception\S3Exception;
use Monolog\Logger;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseQuery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package ParseServerMigration\Console\Command
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class DeleteCommand extends Command
{
    /**
     * @var PictureRepository
     */
    private $pictureRepository;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @param PictureRepository $pictureRepository
     * @param Logger $logger
     */
    public function __construct(PictureRepository $pictureRepository, Logger $logger)
    {
        $this->pictureRepository = $pictureRepository;
        $this->logger = $logger;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('parse:migration:delete')
            ->setDescription('Delete pictures from S3')
            ->addArgument(
                'number',
                InputArgument::REQUIRED,
                'Number of picture to delete'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $number = $input->getArgument('number');
        $query = new ParseQuery("Photos");
        $query->limit($number);
        $results = $query->find();

        $io = new SymfonyStyle($input, $output);

        foreach ($results as $picture) {
            try {
                $deleteResult = $this->pictureRepository->deletePicture($picture);

                $message = 'Export success for: ['.$picture->get('image')->getName().'] Uploaded to : ['.$deleteResult['ObjectURL'].']';
                $this->logger->info($message);
                $io->success($message);
            } catch (S3Exception $exception) {
                $message = 'Delete failed for: [' .$picture->get('image')->getName().'] \nDetail error : [' .$exception->getMessage().']';

                $io->error($message);
                $this->logger->error($message);
            }
        }
    }
}
