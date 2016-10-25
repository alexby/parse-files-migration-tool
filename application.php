#!/usr/bin/env php
<?php
// application.php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use ParseServerMigration\Console\Command\MainCommand;
use ParseServerMigration\Console\Command\ExportCommand;
use ParseServerMigration\Console\Command\DeleteCommand;
use Parse\ParseClient;
use Aws\S3\S3Client;
use ParseServerMigration\Config;
use ParseServerMigration\Console\PictureRepository;
use ParseServerMigration\Console\PictureApplicationService;
use ParseServerMigration\Console\Command\MigrateFromSaasCommand;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use MongoDB\Client;

//Init Parse client
ParseClient::initialize(Config::PARSE_APP_ID, Config::PARSE_REST_KEY, Config::PARSE_MASTER_KEY);
ParseClient::setServerURL(Config::PARSE_URL,'1');

//Init S3 client
$s3Client = new S3Client([
    'version' => 'latest',
    'region'  => Config::S3_REGION,
    'credentials' => [
        'key'    => Config::S3_KEY,
        'secret' => Config::S3_SECRET
    ]
]);

//Clients
$mongoDbClient = new Client(Config::MONGO_DB_CONNECTION.Config::MONGO_DB_NAME);

//Services
$pictureRepository = new PictureRepository($s3Client, $mongoDbClient);
$pictureApplicationService = new PictureApplicationService($pictureRepository);

//Logger
$logger = new Logger('export');
$logger->pushHandler(new StreamHandler(__DIR__.Config::LOG_PATH, Logger::INFO));

//Init SF app
$delete = new DeleteCommand($pictureRepository, $logger);
$export = new ExportCommand($pictureApplicationService, $logger);
$migrate = new MigrateFromSaasCommand($pictureApplicationService, $logger);
$main = new MainCommand(array($delete, $export, $migrate), $logger);

$application = new Application('Parse exporter', '0.1');
$application->addCommands(array($main, $delete, $export, $migrate));
$application->setDefaultCommand($main->getName());

$application->run();
