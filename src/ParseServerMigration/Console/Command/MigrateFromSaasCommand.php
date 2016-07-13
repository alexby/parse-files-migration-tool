<?php

namespace ParseServerMigration\Console\Command;

use Aws\S3\Exception\S3Exception;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Parse\ParseObject;
use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureRepository;
use Parse\ParseClient;
use Parse\ParseQuery;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @package ParseServerMigration\Console\Command
 * @author Maxence Dupressoir <m.dupressoir@meetic-corp.com>
 * @copyright 2016 Meetic
 */
class MigrateFromSaasCommand extends Command
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
            ->setName('parse:migration:migrate')
            ->setDescription('Fetch existing SAAS Parse server and insert all data into MongoDB')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $query = new ParseQuery(Config::PICTURES_TABLE_NAME);

        $io->progressStart($query->count() *2);

        //This is crap but we can't count other wise
        $query = new ParseQuery(Config::PICTURES_TABLE_NAME);

        //Todo We need to compare perf between this way of dumping all images vs PictureRepository::migrateAllPictures()
        $query->each(function (ParseObject $picture) use ($io) {
            try {
                $this->pictureRepository->migrateAllPictures();
                $io->text('Uploading picture');
                $resultUpload = $this->pictureRepository->uploadPicture($picture);
                $io->progressAdvance(1);
                $io->newLine();
                $io->text('Update picture file name');
                $resultRename = $this->pictureRepository->renamePicture($picture);

                $io->progressAdvance(1);
                $message = 'Export success for: ['.$picture->get('image')->getName().'] Uploaded to : ['.$resultUpload['ObjectURL'].']';
                $this->logger->info($message);
                $io->success($message);
            } catch (\ErrorException $exception) {
                $message = 'Upload failed for: [' .$picture->get('image')->getName().'] \nDetail error : [' .$exception->getMessage().']';

                $this->logger->error($message);
                $io->warning($message);
            }
        });
    }
}
