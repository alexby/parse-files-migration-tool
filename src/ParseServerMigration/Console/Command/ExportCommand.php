<?php

namespace ParseServerMigration\Console\Command;

use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureApplicationService;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseQuery;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class ExportCommand extends Command
{
    /**
     * @var PictureRepository
     */
    private $pictureApplicationService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param PictureApplicationService $pictureApplicationService
     * @param LoggerInterface           $logger
     */
    public function __construct(PictureApplicationService $pictureApplicationService, LoggerInterface $logger)
    {
        $this->pictureApplicationService = $pictureApplicationService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('parse:migration:export')
            ->setDescription('Upload existing parse pictures to S3 bucket and rename')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Parse migration tool');
        $io->section('Export command');
        $io->text('Upload existing parse pictures to S3 bucket and rename file in mongoDB');

        $limit = $io->ask('Number of picture to export', 1, null);
        $descOrder = $io->confirm('Migrate newer pictures first ?', false);

        //Because we have 2 steps for each picture
        $io->progressStart($limit * 2);
        $io->newLine();

        $pictures = $this->pictureApplicationService->retrievePictures($limit, $descOrder);

        foreach ($pictures as $picture) {
            try {
                $io->text('Migrating picture\'s image');
                $message = $this->pictureApplicationService->migrateImage($picture);
                $io->progressAdvance(1);
                $io->newLine();
                $this->logger->info($message);
                $io->success($message);

                $io->text('Migrating picture\'s thumbnail');
                $message = $this->pictureApplicationService->migrateThumbnail($picture);
                $io->progressAdvance(1);
                $io->newLine();
                $this->logger->info($message);
                $io->success($message);
            } catch (\ErrorException $exception) {
                $message = 'Upload failed for: ['.$picture->get('image')->getName().'] \nDetail error : ['.$exception->getMessage().']';

                $this->logger->error($message);
                $io->warning($message);
            }
        }
    }
}
