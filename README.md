[![Build Status](https://travis-ci.org/Meetic/parse-files-migration-tool.svg?branch=master)](https://travis-ci.org/Meetic/parse-files-migration-tool)

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Meetic/parse-files-migration-tool/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/Meetic/parse-files-migration-tool/?branch=master)

#Parse server migration tool
This is a simple PHP tool to migrate your files from s.a.a.s Parse backend to self hosted Parse Server with an underlying mongoDB and S3.

You need to migrate your parse s.a.a.s DB to your own private mongoDB instance before running this tool.

Also if your files in several collection just edit the config.php file and set PARSE_FILES_CLASS_NAME

This is a W.I.P version with very roughs edges but still works

##Export command
This command will allow you to migrate and existing Parse mongoDB data into your own mongoDB database and

##Delete command
This wipe a given s3 bucket from Parse server data.

##Migrate command

This command will read from a SAS Parse server, upload pictures to a given S3 bucket and export parse data to a given mongo DB

## Getting started:


```bash
git clone git@github.com:alexby/parse-files-migration-tool.git migration-tool
```

* fill up src/ParseServerMigration/Config.php.dist constants with your credentials and rename it to Config.php

```php
    //Default Parse server file server URL
    const PARSE_FS_URL = 'http://files.parsetfss.com/';
    //All Parse keys can be found in your dashbord https://dashboard.parse.com/apps/<APP_NALE>/settings/keys
    const PARSE_APP_ID = '';
    const PARSE_REST_KEY = '';
    const PARSE_MASTER_KEY = '';
    const PARSE_FILE_KEY = '';
    //If you want to upload your pictures in a given folder of your bucket
    const S3_UPLOAD_FOLDER = '';
    const S3_BUCKET = '';
    //Mongo DB connection string to read pictures from
    const MONGO_DB_CONNECTION = '';
    const MONGO_DB_NAME = '';
    const PARSE_FILES_CLASS_NAME = '';
    const PARSE_FILES_FIELD_NAME = '';
    const PARSE_FILES_THUMBNAIL_FIELD_NAME = '';
    const PARSE_FILES_CONTENT_TYPE = 'image/jpeg';
    const LOG_PATH = '/var/logs/app.log';
```

You can use docker to install full environmnt for that tool. To do it run next commands:

- `docker build . -t parse-files-migration-tool`

- `docker run -v $(pwd):/app -t -i parse-files-migration-tool composer install`

- `docker run -v $(pwd):/app -t -i parse-files-migration-tool php application.php parse:migration:export` (See list of commands below and use it instead of `parse:migration:export`)

If you don't use prepared docker container you have to install dependencies:

```bash
composer install
```

Try your setup with a simple 1 file migration: 

```bash
php application.php parse:migration:export
```

If everything went fine you can to export you whole database with : 

```bash
php application.php parse:migration:migrate
```

run main command: 

```bash
php application.php parse:migration
```
